<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config/config.php'; // Include your database configuration

date_default_timezone_set('Asia/Kolkata');

// Enable CORS (if your frontend is on a different origin)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firm_id = $_POST['firm_id'] ?? null;
    $scheme_type = $_POST['scheme_type'] ?? 'lucky_draw'; // Default to lucky_draw
    $scheme_name = $_POST['scheme_name'] ?? '';
    $status = $_POST['status'] ?? 'draft';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $min_purchase_amount = $_POST['min_purchase_amount'] ?? 0;
    $entry_fee = $_POST['entry_fee'] ?? 0;
    $description = $_POST['description'] ?? '';
    $terms_conditions = $_POST['terms_conditions'] ?? '';
    $auto_entry_on_purchase = isset($_POST['auto_entry_on_purchase']) ? 1 : 0;
    $auto_entry_on_registration = isset($_POST['auto_entry_on_registration']) ? 1 : 0;
    
    // Get rewards data (JSON string)
    $rewards_json = $_POST['rewards'] ?? '[]';
    $rewards = json_decode($rewards_json, true);

    // Validate required fields
    if (empty($firm_id) || empty($scheme_name) || empty($start_date) || empty($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
        $conn->close();
        exit();
    }

    // Sanitize input (basic example, enhance as needed)
    $firm_id = (int)$firm_id;
    $scheme_type = $conn->real_escape_string($scheme_type);
    $scheme_name = $conn->real_escape_string($scheme_name);
    $status = $conn->real_escape_string($status);
    $start_date = $conn->real_escape_string($start_date);
    $end_date = $conn->real_escape_string($end_date);
    $min_purchase_amount = (float)$min_purchase_amount;
    $entry_fee = (float)$entry_fee;
    $description = $conn->real_escape_string($description);
    $terms_conditions = $conn->real_escape_string($terms_conditions);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert scheme into schemes table
        $sql_scheme = "INSERT INTO schemes (firm_id, scheme_type, scheme_name, status, start_date, end_date, min_purchase_amount, entry_fee, description, terms_conditions, auto_entry_on_purchase, auto_entry_on_registration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_scheme = $conn->prepare($sql_scheme);
        $stmt_scheme->bind_param("isssssddssii", $firm_id, $scheme_type, $scheme_name, $status, $start_date, $end_date, $min_purchase_amount, $entry_fee, $description, $terms_conditions, $auto_entry_on_purchase, $auto_entry_on_registration);

        if (!$stmt_scheme->execute()) {
            throw new Exception('Error inserting scheme: ' . $stmt_scheme->error);
        }

        // Get the last inserted scheme ID
        $scheme_id = $conn->insert_id;

        // Insert rewards into scheme_rewards table
        if (!empty($rewards)) {
            $sql_reward = "INSERT INTO scheme_rewards (scheme_id, firm_id, rank, prize_name, quantity, description) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_reward = $conn->prepare($sql_reward);

            foreach ($rewards as $reward) {
                $reward_rank = $reward['rank'] ?? 0;
                $reward_prize_name = $reward['prize_name'] ?? '';
                $reward_quantity = $reward['quantity'] ?? 1;
                $reward_description = $reward['description'] ?? '';

                // Basic validation for reward fields
                if (empty($reward_prize_name) || $reward_rank <= 0 || $reward_quantity <= 0) {
                     // Optionally log this or handle as an error, but for now, skip invalid rewards
                    continue;
                }
                
                $stmt_reward->bind_param("iiisis", $scheme_id, $firm_id, $reward_rank, $reward_prize_name, $reward_quantity, $reward_description);
                
                if (!$stmt_reward->execute()) {
                     throw new Exception('Error inserting reward: ' . $stmt_reward->error);
                }
            }
             $stmt_reward->close();
        }

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Scheme and rewards created successfully!']);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error creating scheme or rewards: ' . $e->getMessage()]);
    }

    $stmt_scheme->close();

} else {
    // Not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();

?> 