<?php
header('Content-Type: application/json');
require_once '../config/config.php';

// Get parameters from the request
$firm_id = isset($_GET['firm_id']) ? (int)$_GET['firm_id'] : 0;
$material_type = isset($_GET['material_type']) ? $_GET['material_type'] : '';
$purity = isset($_GET['purity']) ? $_GET['purity'] : '';

// Validate required fields
if ($firm_id <= 0 || empty($material_type) || empty($purity)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Prepare the SQL statement to get the rate for the specific firm, material, and purity
// Order by effective_date DESC to get the latest rate if multiple exist for the same purity
$sql = "SELECT rate FROM jewellery_price_config WHERE firm_id = ? AND material_type = ? AND purity = ? ORDER BY effective_date DESC LIMIT 1";

$stmt = $conn->prepare($sql);

// Convert purity to a number for binding if it's not 'custom'
$bind_purity = ($purity === 'custom') ? 0 : (float)$purity;

$stmt->bind_param("iss", $firm_id, $material_type, $bind_purity);

// Execute the statement
if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'rate' => (float)$row['rate']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Rate not found for this material and purity.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching rate: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?> 