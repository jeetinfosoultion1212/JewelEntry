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

// Get scheme ID from GET request
$scheme_id = $_GET['scheme_id'] ?? null;

// Validate scheme_id
if (empty($scheme_id) || !is_numeric($scheme_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid scheme ID.']);
    $conn->close();
    exit();
}

$scheme_id = (int)$scheme_id;

// Fetch scheme details
$sql_scheme = "SELECT * FROM schemes WHERE id = ?";
$stmt_scheme = $conn->prepare($sql_scheme);
$stmt_scheme->bind_param("i", $scheme_id);
$stmt_scheme->execute();
$scheme_result = $stmt_scheme->get_result();
$scheme = $scheme_result->fetch_assoc();
$stmt_scheme->close();

if (!$scheme) {
    echo json_encode(['success' => false, 'message' => 'Scheme not found.']);
    $conn->close();
    exit();
}

// Fetch rewards for the scheme
$sql_rewards = "SELECT * FROM scheme_rewards WHERE scheme_id = ? ORDER BY rank ASC";
$stmt_rewards = $conn->prepare($sql_rewards);
$stmt_rewards->bind_param("i", $scheme_id);
$stmt_rewards->execute();
$rewards_result = $stmt_rewards->get_result();
$rewards = [];
while ($row = $rewards_result->fetch_assoc()) {
    $rewards[] = $row;
}
$stmt_rewards->close();

// Return scheme details and rewards as JSON
echo json_encode(['success' => true, 'scheme' => $scheme, 'rewards' => $rewards]);

$conn->close();

?> 