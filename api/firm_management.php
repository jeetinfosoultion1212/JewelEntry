<?php
session_start();
require_once '../config/config.php';

// Check if user is logged in and is Super Admin
if (!isset($_SESSION['id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Super Admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No action specified']);
    exit();
}

switch ($data['action']) {
    case 'update_firm':
        try {
            // Validate required fields
            $requiredFields = ['firmName', 'firmCity', 'firmPincode', 'firmPhone'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Required field missing: " . $field);
                }
            }

            // Prepare the update query
            $query = "UPDATE Firm SET 
                FirmName = ?,
                OwnerName = ?,
                Tagline = ?,
                Address = ?,
                City = ?,
                State = ?,
                PostalCode = ?,
                PhoneNumber = ?,
                Email = ?,
                PANNumber = ?,
                GSTNumber = ?,
                BISRegistrationNumber = ?,
                BankName = ?,
                BankBranch = ?,
                BankAccountNumber = ?,
                IFSCCode = ?,
                AccountType = ?
                WHERE id = ?";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }

            $stmt->bind_param(
                "sssssssssssssssssi",
                $data['firmName'],
                $data['ownerName'],
                $data['firmTagline'],
                $data['address'],
                $data['firmCity'],
                $data['firmState'],
                $data['firmPincode'],
                $data['firmPhone'],
                $data['firmEmail'],
                $data['firmPAN'],
                $data['firmGST'],
                $data['bisRegistration'],
                $data['bankName'],
                $data['bankBranch'],
                $data['accountNumber'],
                $data['ifscCode'],
                $data['accountType'],
                $_SESSION['firmID']
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to update firm details: " . $stmt->error);
            }

            if ($stmt->affected_rows === 0) {
                throw new Exception("No changes were made to the firm details");
            }

            echo json_encode([
                'success' => true,
                'message' => 'Firm details updated successfully'
            ]);
        } catch (Exception $e) {
            error_log("Firm update error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
} 