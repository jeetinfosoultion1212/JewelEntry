<?php
session_start();
require '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['firmID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

if ($_POST['action'] === 'add_karigar') {
    try {
        $name = trim($_POST['name']);
        $phone_number = trim($_POST['phone_number']) ?: null;
        $email = trim($_POST['email']) ?: null;
        $address_line1 = trim($_POST['address_line1']) ?: null;
        $default_making_charge = floatval($_POST['default_making_charge']) ?: 0.00;
        $charge_type = $_POST['charge_type'] ?: 'PerGram';

        if (empty($name)) {
            throw new Exception('Name is required');
        }

        $query = "INSERT INTO karigars (firm_id, name, phone_number, email, address_line1, default_making_charge, charge_type, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW())";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issssds", $firm_id, $name, $phone_number, $email, $address_line1, $default_making_charge, $charge_type);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Karigar added successfully']);
        } else {
            throw new Exception('Failed to add karigar');
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

$conn->close();
?>
