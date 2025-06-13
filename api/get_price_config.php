<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['firmID'])) {
    echo json_encode(['error' => 'Firm ID not set. User not authenticated.']);
    exit();
}

$current_firm_id = $_SESSION['firmID'];

// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jewelentrypro"; // Updated database name

// Prevent HTML errors from being displayed
error_reporting(0);
ini_set('display_errors', 0);

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$priceConfig = array();

$sql_price = "SELECT p.material_type, p.purity, p.unit, p.rate, DATE_FORMAT(p.effective_date, '%d %b %Y') as formatted_date
              FROM jewellery_price_config p
              WHERE p.firm_id = ?
              ORDER BY p.effective_date DESC"; // Order by effective_date to get the latest

$stmt_price = $conn->prepare($sql_price);
if (!$stmt_price) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    $conn->close();
    exit();
}

$stmt_price->bind_param("i", $current_firm_id);
$stmt_price->execute();
$result_price = $stmt_price->get_result();

if ($result_price->num_rows > 0) {
    $tempPriceConfig = [];
    while($row = $result_price->fetch_assoc()) {
        $materialType = $row['material_type'];
        $purity = (string)$row['purity']; // Ensure purity is string for key consistency

        // Only add if we haven't seen a newer entry for this material/purity combination
        if (!isset($tempPriceConfig[$materialType][$purity])) {
            $tempPriceConfig[$materialType][$purity] = [
                'rate' => $row['rate'],
                'date' => $row['formatted_date'],
                'unit' => $row['unit']
            ];
        }
    }
    $priceConfig = $tempPriceConfig;
}

$conn->close();

echo json_encode($priceConfig);
?> 