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
    $name = $_POST['name'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $alternate_phone = $_POST['alternate_phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $address_line1 = $_POST['address_line1'] ?? '';
    $address_line2 = $_POST['address_line2'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $country = $_POST['country'] ?? '';
    $default_making_charge = $_POST['default_making_charge'] ?? '';
    $charge_type = $_POST['charge_type'] ?? '';
    $gst_number = $_POST['gst_number'] ?? '';
    $pan_number = $_POST['pan_number'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $karigar_type = $_POST['karigar_type'] ?? 'in_house';

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
