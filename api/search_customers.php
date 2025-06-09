<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['firmID'])) {
    echo json_encode(['error' => 'Firm ID not set. User not authenticated.']);
    exit();
}

$current_firm_id = $_SESSION['firmID'];
$search_term = isset($_GET['term']) ? $_GET['term'] : '';

// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jewelentryapp";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

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