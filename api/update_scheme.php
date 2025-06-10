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

// Check if the request method is POST (or PUT if configured)
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Using POST for simplicity with FormData
    // Get form data
    $scheme_id = $_POST['scheme_id'] ?? null;
    $firm_id = $_POST['firm_id'] ?? null; // Assuming firm_id is also sent for verification
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
    if (empty($scheme_id) || empty($firm_id) || empty($scheme_name) || empty($start_date) || empty($end_date)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
        $conn->close();
        exit();
    }

     // Validate scheme_id and firm_id match an existing scheme
     $validation_sql = "SELECT id FROM schemes WHERE id = ? AND firm_id = ?";
     $validation_stmt = $conn->prepare($validation_sql);
     $validation_stmt->bind_param("ii", $scheme_id, $firm_id);
     $validation_stmt->execute();
     $validation_result = $validation_stmt->get_result();
     if ($validation_result->num_rows === 0) {
         echo json_encode(['success' => false, 'message' => 'Scheme not found or does not belong to your firm.']);
         $validation_stmt->close();
         $conn->close();
         exit();
     }
     $validation_stmt->close();

    // Sanitize input (basic example)
    $scheme_id = (int)$scheme_id;
    $firm_id = (int)$firm_id;
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
        // Update scheme details in schemes table
        $sql_scheme = "UPDATE schemes SET scheme_type = ?, scheme_name = ?, status = ?, start_date = ?, end_date = ?, min_purchase_amount = ?, entry_fee = ?, description = ?, terms_conditions = ?, auto_entry_on_purchase = ?, auto_entry_on_registration = ? WHERE id = ? AND firm_id = ?";
        $stmt_scheme = $conn->prepare($sql_scheme);
        $scheme_type = 'lucky_draw'; // Scheme type is not editable from the form
        $stmt_scheme->bind_param("ssssddssiiii", $scheme_type, $scheme_name, $status, $start_date, $end_date, $min_purchase_amount, $entry_fee, $description, $terms_conditions, $auto_entry_on_purchase, $auto_entry_on_registration, $scheme_id, $firm_id);

        if (!$stmt_scheme->execute()) {
            throw new Exception('Error updating scheme: ' . $stmt_scheme->error);
        }
        $stmt_scheme->close();

        // --- Update Rewards ---
        // 1. Delete existing rewards for this scheme
        $sql_delete_rewards = "DELETE FROM scheme_rewards WHERE scheme_id = ?";
        $stmt_delete_rewards = $conn->prepare($sql_delete_rewards);
        $stmt_delete_rewards->bind_param("i", $scheme_id);
        if (!$stmt_delete_rewards->execute()) {
             throw new Exception('Error deleting existing rewards: ' . $stmt_delete_rewards->error);
        }
        $stmt_delete_rewards->close();

        // 2. Insert the new/updated rewards
        if (!empty($rewards)) {
            $sql_insert_reward = "INSERT INTO scheme_rewards (scheme_id, firm_id, rank, prize_name, quantity, description) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_insert_reward = $conn->prepare($sql_insert_reward);

            foreach ($rewards as $reward) {
                $reward_rank = $reward['rank'] ?? 0;
                $reward_prize_name = $reward['prize_name'] ?? '';
                $reward_quantity = $reward['quantity'] ?? 1;
                $reward_description = $reward['description'] ?? '';

                // Basic validation for reward fields
                if (empty($reward_prize_name) || $reward_rank <= 0 || $reward_quantity <= 0) {
                     // Optionally log or handle as an error, for now, skip invalid rewards
                    continue;
                }

                $stmt_insert_reward->bind_param("iiisis", $scheme_id, $firm_id, $reward_rank, $reward_prize_name, $reward_quantity, $reward_description);

                if (!$stmt_insert_reward->execute()) {
                     throw new Exception('Error inserting updated reward: ' . $stmt_insert_reward->error);
                }
            }
            $stmt_insert_reward->close();
        }
        // --- End Update Rewards ---

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Scheme and rewards updated successfully!']);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error updating scheme or rewards: ' . $e->getMessage()]);
    }

} else {
    // Not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();

?> 