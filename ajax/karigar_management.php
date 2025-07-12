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

function addKarigar($conn, $firm_id, $data) {
    $name = trim($data['name']);
    $phone_number = trim($data['phone_number']) ?: null;
    $alternate_phone = trim($data['alternate_phone']) ?: null;
    $email = trim($data['email']) ?: null;
    $address_line1 = trim($data['address_line1']) ?: null;
    $address_line2 = trim($data['address_line2']) ?: null;
    $city = trim($data['city']) ?: null;
    $state = trim($data['state']) ?: null;
    $postal_code = trim($data['postal_code']) ?: null;
    $country = trim($data['country']) ?: 'India';
    $karigar_type = isset($data['karigar_type']) && in_array($data['karigar_type'], ['in_house', 'outsource']) ? $data['karigar_type'] : 'in_house';
    $default_making_charge = floatval($data['default_making_charge']) ?: 0.00;
    $charge_type = $data['charge_type'] ?: 'PerGram';
    $gst_number = trim($data['gst_number']) ?: null;
    $pan_number = trim($data['pan_number']) ?: null;
    $status = isset($data['status']) ? $data['status'] : 'Active';

    if (empty($name)) {
        return ['success' => false, 'message' => 'Name is required'];
    }

    $query = "INSERT INTO karigars (
        firm_id, name, phone_number, alternate_phone, email, address_line1, address_line2, city, state, postal_code, country, karigar_type,
        default_making_charge, charge_type, gst_number, pan_number, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "isssssssssssdssss",
        $firm_id, $name, $phone_number, $alternate_phone, $email, $address_line1, $address_line2, $city, $state, $postal_code, $country, $karigar_type,
        $default_making_charge, $charge_type, $gst_number, $pan_number, $status
    );

    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Karigar added successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to add karigar'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_karigar') {
    $result = addKarigar($conn, $firm_id, $_POST);
    echo json_encode($result);
    $conn->close();
    exit();
}

$conn->close();
?>
