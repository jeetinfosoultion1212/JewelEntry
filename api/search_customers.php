<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['firmID'])) {
    echo json_encode(['error' => 'Firm ID not set. User not authenticated.']);
    exit();
}

$current_firm_id = $_SESSION['firmID'];
$search_term = isset($_GET['term']) ? $_GET['term'] : '';

// Database Connection from config
require_once __DIR__ . '/../config/db_connect.php';

// Search customers by name (combining FirstName and LastName)
$sql = "SELECT id, CONCAT(FirstName, ' ', LastName) as name, PhoneNumber as phone, 
               CONCAT(Address, ', ', City, ', ', State) as address 
        FROM customer 
        WHERE firm_id = ? AND (FirstName LIKE ? OR LastName LIKE ?) 
        ORDER BY FirstName, LastName 
        LIMIT 10";

$stmt = $conn->prepare($sql);
$search_pattern = "%" . $search_term . "%";
$stmt->bind_param("iss", $current_firm_id, $search_pattern, $search_pattern);
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'phone' => $row['phone'],
        'address' => $row['address']
    ];
}

$conn->close();
echo json_encode($customers);
?> 