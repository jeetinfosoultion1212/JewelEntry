<?php
session_start();
require_once '../config/db_connect.php';
header('Content-Type: application/json');
if (!isset($_SESSION['firmID'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit();
}
$firm_id = $_SESSION['firmID'];
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo json_encode([]);
    exit();
}
$stmt = $conn->prepare("SELECT id, name, address, phone, gst FROM suppliers WHERE firm_id = ? AND (name LIKE ? OR phone LIKE ? OR gst LIKE ?) ORDER BY name LIMIT 10");
$like = "%$q%";
$stmt->bind_param('isss', $firm_id, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
$suppliers = [];
while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}
echo json_encode($suppliers); 