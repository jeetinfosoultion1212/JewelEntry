<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log'); // Set a specific error log file, adjust path as needed

// Set JSON response header
header('Content-Type: application/json');

require 'config/config.php';

// Function to send JSON response
function sendJsonResponse($success, $message, $redirect = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($redirect !== null) {
        $response['redirect'] = $redirect;
    }
    echo json_encode($response);
    exit;
}

// Function to validate mobile number
function validateMobile($mobile) {
    return preg_match('/^[0-9]{10}$/', $mobile);
}

// Function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to check if mobile number already exists
function isMobileExists($conn, $mobile) {
    $stmt = $conn->prepare("SELECT id FROM firm_users WHERE PhoneNumber = ?");
    if (!$stmt) {
        error_log("isMobileExists prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Function to check if email already exists
function isEmailExists($conn, $email) {
    if (empty($email)) return false;
    $stmt = $conn->prepare("SELECT id FROM firm_users WHERE Email = ?");
    if (!$stmt) {
        error_log("isEmailExists prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Function to create a new firm
function createFirm($conn, $firmName, $ownerName, $phoneNumber, $email) {
    $stmt = $conn->prepare("INSERT INTO firm (FirmName, OwnerName, PhoneNumber, Email, status, CreatedAt) VALUES (?, ?, ?, ?, 'active', NOW())");
    if (!$stmt) {
        error_log("createFirm prepare failed: " . $conn->error);
        return null;
    }
    $stmt->bind_param("ssss", $firmName, $ownerName, $phoneNumber, $email);
    $success = $stmt->execute();
    $firmId = $success ? $conn->insert_id : null;
    $stmt->close();
    return $firmId;
}

// Fixed Function to create a new user
function createUser($conn, $name, $username, $password, $firmId, $email, $phoneNumber) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO firm_users (Name, Username, Password, FirmID, Email, PhoneNumber, Role, Status, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, 'Super Admin', 'Active', NOW())");
    if (!$stmt) {
        error_log("createUser prepare failed: " . $conn->error);
        return null;
    }
    $stmt->bind_param("sssiss", $name, $username, $hashedPassword, $firmId, $email, $phoneNumber);
    $success = $stmt->execute();
    $userId = $success ? $conn->insert_id : null;
    $stmt->close();
    return $userId;
}

// Function to create a trial subscription for a firm
function createTrialSubscription($conn, $firmId) {
    $trialPlanId = 1;
    $startDate = date('Y-m-d H:i:s');
    $endDate = date('Y-m-d H:i:s', strtotime('+7 days'));

    $stmt = $conn->prepare("INSERT INTO firm_subscriptions (firm_id, plan_id, start_date, end_date, is_trial, is_active, auto_renew) VALUES (?, ?, ?, ?, 1, 1, 0)");
    if (!$stmt) {
        error_log("createTrialSubscription prepare failed: " . $conn->error);
        return null;
    }
    $stmt->bind_param("iiss", $firmId, $trialPlanId, $startDate, $endDate);
    $success = $stmt->execute();
    $subscriptionId = $success ? $conn->insert_id : null;
    $stmt->close();

    if ($subscriptionId) {
        $updateStmt = $conn->prepare("UPDATE firm SET current_subscription_id = ? WHERE id = ?");
        if (!$updateStmt) {
            error_log("update firm current_subscription_id prepare failed: " . $conn->error);
            return null;
        }
        $updateStmt->bind_param("ii", $subscriptionId, $firmId);
        $updateStmt->execute();
        $updateStmt->close();
    }

    return $subscriptionId;
}

// Handle the registration request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fullName = trim($_POST['fullName'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $firmName = trim($_POST['firmName'] ?? '');
        $userPassword = $_POST['password'] ?? '';

        // Validate required fields
        if (empty($fullName) || empty($mobile) || empty($userPassword)) {
            sendJsonResponse(false, 'Please fill in all required fields');
        }

        if (!validateMobile($mobile)) {
            sendJsonResponse(false, false, 'Please enter a valid 10-digit mobile number');
        }

        if (!empty($email) && !validateEmail($email)) {
            sendJsonResponse(false, 'Please enter a valid email address');
        }

        // Connect to DB
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            sendJsonResponse(false, 'Database connection failed');
        }

        if (isMobileExists($conn, $mobile)) {
            sendJsonResponse(false, 'Mobile number already registered');
        }

        if (!empty($email) && isEmailExists($conn, $email)) {
            sendJsonResponse(false, 'Email already registered');
        }

        // Begin transaction
        $conn->begin_transaction();

        try {
            $firmId = createFirm($conn, $firmName, $fullName, $mobile, $email);
            if (!$firmId) throw new Exception('Failed to create firm');

            $subscriptionId = createTrialSubscription($conn, $firmId);
            if (!$subscriptionId) throw new Exception('Failed to create trial subscription');

            $userId = createUser($conn, $fullName, $mobile, $userPassword, $firmId, $email, $mobile);
            if (!$userId) throw new Exception('Failed to create user');

            $configStmt = $conn->prepare("INSERT INTO firm_configurations (firm_id) VALUES (?)");
            if (!$configStmt) {
                error_log("Failed to create firm configurations prepare: " . $conn->error);
                throw new Exception('Failed to prepare firm configurations statement');
            }
            $configStmt->bind_param("i", $firmId);
            if (!$configStmt->execute()) {
                error_log("Failed to create firm configurations: " . $conn->error);
                throw new Exception('Failed to create firm configurations');
            }

            $conn->commit();

            session_start();
            $_SESSION['id'] = $userId;
            $_SESSION['firmID'] = $firmId;
            $_SESSION['username'] = $mobile;
            $_SESSION['role'] = 'Super Admin';

            sendJsonResponse(true, 'Registration successful!', 'home.php');

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Transaction failed: " . $e->getMessage());
            throw $e;
        }

    } catch (Exception $e) {
        // Catch all other exceptions and log them as well
        error_log("Registration process failed: " . $e->getMessage());
        sendJsonResponse(false, 'Registration failed: ' . $e->getMessage());
    } finally {
        if (isset($conn) && $conn->connected) {
            $conn->close();
        }
    }
} else {
    sendJsonResponse(false, 'Invalid request method');
}
?>
