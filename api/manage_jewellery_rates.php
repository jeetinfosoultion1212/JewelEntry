<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require '../config/config.php';

// Function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    sendJsonResponse(['error' => 'Unauthorized access'], 401);
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    sendJsonResponse(['error' => 'Database connection failed: ' . $conn->connect_error], 500);
}

// Check user role
$allowed_roles = ['Super Admin', 'Admin', 'Store Manager'];
$userQuery = "SELECT Role FROM Firm_Users WHERE id = ? AND firm_id = ?";
$userStmt = $conn->prepare($userQuery);
if (!$userStmt) {
    sendJsonResponse(['error' => 'Failed to prepare user query: ' . $conn->error], 500);
}

$userStmt->bind_param("ii", $user_id, $firm_id);
if (!$userStmt->execute()) {
    sendJsonResponse(['error' => 'Failed to execute user query: ' . $userStmt->error], 500);
}

$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();

if (!$userData) {
    sendJsonResponse(['error' => 'User not found'], 404);
}

$userRole = $userData['Role'];

if (!in_array($userRole, $allowed_roles)) {
    sendJsonResponse(['error' => 'You do not have permission to manage rates'], 403);
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            // Get current rates
            $ratesQuery = "SELECT * FROM jewellery_price_config WHERE firm_id = ? ORDER BY effective_date DESC";
            $ratesStmt = $conn->prepare($ratesQuery);
            if (!$ratesStmt) {
                throw new Exception('Failed to prepare rates query: ' . $conn->error);
            }

            $ratesStmt->bind_param("i", $firm_id);
            if (!$ratesStmt->execute()) {
                throw new Exception('Failed to execute rates query: ' . $ratesStmt->error);
            }

            $ratesResult = $ratesStmt->get_result();
            $rates = [];
            while ($row = $ratesResult->fetch_assoc()) {
                $rates[] = $row;
            }

            sendJsonResponse(['rates' => $rates]);
        } catch (Exception $e) {
            sendJsonResponse(['error' => 'Error loading rates: ' . $e->getMessage()], 500);
        }
        break;

    case 'POST':
        try {
            // Get POST data
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $requiredFields = ['material_type', 'purity', 'unit', 'rate'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            // Validate data types
            if (!is_numeric($data['purity']) || !is_numeric($data['rate'])) {
                throw new Exception('Purity and rate must be numeric values');
            }

            // Check if rate already exists
            $checkQuery = "SELECT id FROM jewellery_price_config 
                          WHERE firm_id = ? AND material_type = ? AND purity = ? AND unit = ?";
            $checkStmt = $conn->prepare($checkQuery);
            if (!$checkStmt) {
                throw new Exception('Failed to prepare check query: ' . $conn->error);
            }

            $checkStmt->bind_param("issd", $firm_id, $data['material_type'], $data['purity'], $data['unit']);
            if (!$checkStmt->execute()) {
                throw new Exception('Failed to execute check query: ' . $checkStmt->error);
            }

            $existingRate = $checkStmt->get_result()->fetch_assoc();

            if ($existingRate) {
                // Update existing rate
                $updateQuery = "UPDATE jewellery_price_config 
                               SET rate = ?, effective_date = CURRENT_TIMESTAMP 
                               WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                if (!$updateStmt) {
                    throw new Exception('Failed to prepare update query: ' . $conn->error);
                }

                $updateStmt->bind_param("di", $data['rate'], $existingRate['id']);
                if (!$updateStmt->execute()) {
                    throw new Exception('Failed to execute update query: ' . $updateStmt->error);
                }

                sendJsonResponse(['message' => 'Rate updated successfully']);
            } else {
                // Insert new rate
                $insertQuery = "INSERT INTO jewellery_price_config 
                               (firm_id, material_type, purity, unit, rate, effective_date) 
                               VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
                $insertStmt = $conn->prepare($insertQuery);
                if (!$insertStmt) {
                    throw new Exception('Failed to prepare insert query: ' . $conn->error);
                }

                $insertStmt->bind_param("isddd", $firm_id, $data['material_type'], $data['purity'], $data['unit'], $data['rate']);
                if (!$insertStmt->execute()) {
                    throw new Exception('Failed to execute insert query: ' . $insertStmt->error);
                }

                sendJsonResponse(['message' => 'Rate set successfully']);
            }
        } catch (Exception $e) {
            sendJsonResponse(['error' => 'Error saving rate: ' . $e->getMessage()], 500);
        }
        break;

    case 'DELETE':
        try {
            // Get DELETE data
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['material_type'])) {
                throw new Exception('Material type is required');
            }

            // Delete rate
            $deleteQuery = "DELETE FROM jewellery_price_config 
                           WHERE firm_id = ? AND material_type = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            if (!$deleteStmt) {
                throw new Exception('Failed to prepare delete query: ' . $conn->error);
            }

            $deleteStmt->bind_param("is", $firm_id, $data['material_type']);
            if (!$deleteStmt->execute()) {
                throw new Exception('Failed to execute delete query: ' . $deleteStmt->error);
            }

            sendJsonResponse(['message' => 'Rate cleared successfully']);
        } catch (Exception $e) {
            sendJsonResponse(['error' => 'Error clearing rate: ' . $e->getMessage()], 500);
        }
        break;

    default:
        sendJsonResponse(['error' => 'Method not allowed'], 405);
        break;
}

$conn->close();
?> 