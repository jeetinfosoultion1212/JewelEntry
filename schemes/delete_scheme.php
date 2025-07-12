<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config/config.php'; // Include your database configuration

date_default_timezone_set('Asia/Kolkata');

// Enable CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get scheme ID and firm ID from POST request
    $scheme_id = $_POST['scheme_id'] ?? null;
    $firm_id = $_POST['firm_id'] ?? null;

    // Validate input
    if (empty($scheme_id) || !is_numeric($scheme_id) || empty($firm_id) || !is_numeric($firm_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
        $conn->close();
        exit();
    }

    $scheme_id = (int)$scheme_id;
    $firm_id = (int)$firm_id;

    // Start transaction
    $conn->begin_transaction();

    try {
        // Prepare and execute the delete statement
        // Add firm_id to the WHERE clause to ensure only schemes belonging to the firm can be deleted
        $sql = "DELETE FROM schemes WHERE id = ? AND firm_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $scheme_id, $firm_id);

        if (!$stmt->execute()) {
            throw new Exception('Error deleting scheme: ' . $stmt->error);
        }

        // Check if any rows were affected (means a scheme was actually deleted)
        if ($stmt->affected_rows > 0) {
            // Commit transaction
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Scheme deleted successfully!']);
        } else {
            // No rows affected, scheme not found or didn't belong to the firm
            $conn->rollback(); // Should not be necessary if no changes were made, but good practice
            echo json_encode(['success' => false, 'message' => 'Scheme not found or does not belong to your firm.']);
        }

        $stmt->close();

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error deleting scheme: ' . $e->getMessage()]);
    }

} else {
    // Not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();

?> 