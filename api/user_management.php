<?php
session_start();
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
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
    case 'update_profile':
        try {
            // Prepare the update query
            $query = "UPDATE Firm_Users SET 
                Name = ?,
                Email = ?,
                PhoneNumber = ?
                WHERE id = ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param(
                "sssi",
                $data['userName'],
                $data['userEmail'],
                $data['userPhone'],
                $_SESSION['id']
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to update profile");
            }

            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'change_password':
        try {
            // Verify current password
            $stmt = $conn->prepare("SELECT Password FROM Firm_Users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if (!password_verify($data['current_password'], $user['Password'])) {
                throw new Exception("Current password is incorrect");
            }

            // Update password
            $newPasswordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE Firm_Users SET Password = ? WHERE id = ?");
            $stmt->bind_param("si", $newPasswordHash, $_SESSION['id']);

            if (!$stmt->execute()) {
                throw new Exception("Failed to update password");
            }

            echo json_encode([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
} 