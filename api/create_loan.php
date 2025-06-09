<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['firmID']) || !isset($_SESSION['id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$current_firm_id = $_SESSION['firmID'];
$user_id = $_SESSION['id'];

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

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['error' => 'Invalid input data']);
    exit();
}

// Validate required fields
$required_fields = ['customerId', 'loanAmount', 'interestRate', 'loanDuration', 'startDate', 'collateralItems'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Calculate total collateral value
    $totalCollateralValue = 0;
    foreach ($data['collateralItems'] as $item) {
        $totalCollateralValue += floatval($item['calculatedValue']);
    }

    // Calculate maturity date
    $startDate = new DateTime($data['startDate']);
    $maturityDate = clone $startDate;
    $maturityDate->modify("+{$data['loanDuration']} months");

    // Insert loan record
    $sql = "INSERT INTO loans (
        firm_id, customer_id, loan_date, principal_amount, interest_rate, 
        loan_term_months, maturity_date, current_status, outstanding_amount,
        collateral_value, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "iisdddsddi",
        $current_firm_id,
        $data['customerId'],
        $data['startDate'],
        $data['loanAmount'],
        $data['interestRate'],
        $data['loanDuration'],
        $maturityDate->format('Y-m-d'),
        $data['loanAmount'], // Initial outstanding amount equals loan amount
        $totalCollateralValue,
        $user_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Error creating loan: " . $stmt->error);
    }

    $loan_id = $conn->insert_id;

    // Calculate and insert EMIs
    $monthlyInterestRate = ($data['interestRate'] / 100) / 12;
    $loanAmount = floatval($data['loanAmount']);
    $loanDuration = intval($data['loanDuration']);
    
    // Calculate EMI using the formula: EMI = P * r * (1 + r)^n / ((1 + r)^n - 1)
    $emi = $loanAmount * $monthlyInterestRate * pow(1 + $monthlyInterestRate, $loanDuration) / 
           (pow(1 + $monthlyInterestRate, $loanDuration) - 1);

    // Insert EMIs
    $emiSql = "INSERT INTO loan_emis (
        loan_id, customer_id, due_date, amount, status
    ) VALUES (?, ?, ?, ?, 'due')";

    $emiStmt = $conn->prepare($emiSql);
    $dueDate = clone $startDate;
    $dueDate->modify('+1 month'); // First EMI due after 1 month

    for ($i = 0; $i < $loanDuration; $i++) {
        $emiStmt->bind_param(
            "iisd",
            $loan_id,
            $data['customerId'],
            $dueDate->format('Y-m-d'),
            $emi
        );

        if (!$emiStmt->execute()) {
            throw new Exception("Error creating EMI: " . $emiStmt->error);
        }

        $dueDate->modify('+1 month');
    }

    // Insert collateral items
    $sql = "INSERT INTO loan_collateral_items (
        loan_id, material_type, purity, weight, rate_per_gram,
        calculated_value, description, image_path
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    foreach ($data['collateralItems'] as $item) {
        $stmt->bind_param(
            "isddddss",
            $loan_id,
            $item['materialType'],
            $item['purity'],
            $item['weight'],
            $item['ratePerGram'],
            $item['calculatedValue'],
            $item['description'],
            $item['imagePath']
        );

        if (!$stmt->execute()) {
            throw new Exception("Error adding collateral item: " . $stmt->error);
        }
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Loan created successfully',
        'loan_id' => $loan_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?> 