<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log'); // Set a specific error log file, adjust path as needed

// Set JSON response header
header('Content-Type: application/json');

require '../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// Function to create main branch for a firm
function createMainBranch($conn, $firmId, $firmName) {
    // First check if branches table exists
    $checkTableSql = "SHOW TABLES LIKE 'branches'";
    $checkResult = $conn->query($checkTableSql);
    if ($checkResult->num_rows === 0) {
        error_log("Branches table does not exist. Creating it...");
        $createTableSql = "CREATE TABLE branches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            firm_id INT NOT NULL,
            branch_name VARCHAR(100) NOT NULL,
            address VARCHAR(255),
            city VARCHAR(100),
            state VARCHAR(100),
            pincode VARCHAR(20),
            phone VARCHAR(20),
            email VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (firm_id) REFERENCES Firm(id) ON DELETE CASCADE
        )";
        if (!$conn->query($createTableSql)) {
            error_log("Failed to create branches table: " . $conn->error);
            return null;
        }
    }
    
    $branchName = $firmName . " - Main Branch";
    $stmt = $conn->prepare("INSERT INTO branches (firm_id, branch_name, created_at) VALUES (?, ?, NOW())");
    if (!$stmt) {
        error_log("createMainBranch prepare failed: " . $conn->error);
        return null;
    }
    $stmt->bind_param("is", $firmId, $branchName);
    $success = $stmt->execute();
    $branchId = $success ? $conn->insert_id : null;
    $stmt->close();
    
    if ($branchId) {
        error_log("Successfully created main branch with ID: $branchId for firm: $firmId");
    } else {
        error_log("Failed to create main branch for firm: $firmId");
    }
    
    return $branchId;
}

// Function to ensure firm_users table has branch_id column
function ensureBranchIdColumn($conn) {
    // Check if branch_id column exists in firm_users table
    $checkColumnSql = "SHOW COLUMNS FROM firm_users LIKE 'branch_id'";
    $checkResult = $conn->query($checkColumnSql);
    if ($checkResult->num_rows === 0) {
        error_log("branch_id column does not exist in firm_users table. Adding it...");
        $addColumnSql = "ALTER TABLE firm_users ADD COLUMN branch_id INT NULL";
        if (!$conn->query($addColumnSql)) {
            error_log("Failed to add branch_id column to firm_users table: " . $conn->error);
            return false;
        }
        error_log("Successfully added branch_id column to firm_users table");
    }
    return true;
}

// Fixed Function to create a new user
function createUser($conn, $name, $username, $password, $firmId, $email, $phoneNumber, $branchId) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO firm_users (Name, Username, Password, FirmID, Email, PhoneNumber, Role, Status, branch_id, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, 'Super Admin', 'Active', ?, NOW())");
    if (!$stmt) {
        error_log("createUser prepare failed: " . $conn->error);
        return null;
    }
    $stmt->bind_param("sssissi", $name, $username, $hashedPassword, $firmId, $email, $phoneNumber, $branchId);
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

// Function to send SMS using Fast2SMS
function sendWelcomeSMS($mobile, $username, $password) {
    $apiKey = "XcAU17bOEmokyIQN65YKSG2w4Mfg0RrTe38nsqx9FLWVDutlJZ9Wwf6J8qYNuL1OGUVlhaECoDnSApZ2";
    $message = "Welcome to JewelEntry! Your account has been created successfully. Username: $username, Password: $password. Thank you for choosing us!";
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'route' => 'v3',
            'sender_id' => 'JEWELENTRY',
            'message' => $message,
            'language' => 'english',
            'flash' => 0,
            'numbers' => $mobile,
        ]),
        CURLOPT_HTTPHEADER => array(
            "authorization: " . $apiKey,
            "accept: */*",
            "content-type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("SMS sending failed: " . $err);
        return false;
    }
    
    $result = json_decode($response, true);
    return isset($result['return']) && $result['return'] === true;
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

            $branchId = createMainBranch($conn, $firmId, $firmName);
            if (!$branchId) throw new Exception('Failed to create main branch');

            // Ensure firm_users table has branch_id column
            if (!ensureBranchIdColumn($conn)) {
                throw new Exception('Failed to ensure branch_id column exists');
            }

            $subscriptionId = createTrialSubscription($conn, $firmId);
            if (!$subscriptionId) throw new Exception('Failed to create trial subscription');

            $userId = createUser($conn, $fullName, $mobile, $userPassword, $firmId, $email, $mobile, $branchId);
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

            // Send welcome SMS
            $smsSent = sendWelcomeSMS($mobile, $mobile, $userPassword);
            if (!$smsSent) {
                error_log("Failed to send welcome SMS to: " . $mobile);
            }

            // --- PHPMailer: Send Welcome Email to Customer and Notification to Admin ---
            
            // 1. Welcome Email to Customer
            if (!empty($email)) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'jeettechnoguide@gmail.com'; // Change to your sender email
                    $mail->Password = 'nhsi kbyn vlxq fylj'; // Change to your app password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('jeettechnoguide@gmail.com', 'Jewel Entry');
                    $mail->addAddress($email, $fullName);
                    $mail->isHTML(true);
                    $mail->Subject = 'Welcome to Jewel Entry!';
                    $mail->Body = "
                        <div style='font-family: Poppins, Arial, sans-serif; max-width: 480px; margin: 0 auto; background: #FFF9F3; border-radius: 18px; box-shadow: 0 4px 24px rgba(251,191,36,0.08); padding: 32px 24px;'>
                            <div style='text-align:center; margin-bottom: 24px;'>
                                <span style='display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; background:#fde68a; border-radius:50%; margin-bottom:18px;'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='36' height='36' fill='none' viewBox='0 0 24 24'><circle cx='12' cy='12' r='12' fill='#fde68a'/><path d='M6 8.5l6 4 6-4' stroke='#ea580c' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/><rect x='6' y='8.5' width='12' height='7' rx='2' stroke='#ea580c' stroke-width='1.5'/></svg>
                                </span>
                                <h2 style='color: #ea580c; font-size: 2rem; font-weight: 700; margin: 0 0 8px;'>Welcome to Jewel Entry!</h2>
                                <p style='color: #374151; font-size: 1rem; margin: 0;'>Dear <b>{$fullName}</b>,</p>
                            </div>
                            <div style='background: #fff; border-radius: 12px; padding: 24px 0; margin: 24px 0; text-align: center; box-shadow: 0 2px 8px rgba(251,191,36,0.08);'>
                                <span style='display:inline-block; font-size: 1.2rem; font-weight: 600; color: #ea580c;'>Your account has been created successfully!</span>
                            </div>
                            <p style='color: #6b7280; font-size: 1rem; margin-bottom: 18px;'>We're thrilled to have you on board. Here are your login details:</p>
                            <div style='background: #fff7ed; border-radius: 10px; padding: 16px 24px; margin-bottom: 18px; text-align:left;'>
                                <div style='color:#ea580c; font-weight:600;'>Login Username (Mobile): <span style='color:#374151;'>{$mobile}</span></div>
                                <div style='color:#ea580c; font-weight:600;'>Password: <span style='color:#374151;'>{$userPassword}</span></div>
                            </div>
                            <p style='color: #374151; font-size: 1rem;'>You can now log in and start exploring all the features we offer. If you have any questions or need help, our support team is just a message away!</p>
                            <hr style='border: none; border-top: 1px solid #fbbf24; margin: 32px 0 16px;'>
                            <div style='text-align:center; color:#ea580c; font-size:1.1rem; font-weight:600; margin-bottom: 4px;'>Jewel Entry</div>
                            <div style='text-align:center; color:#374151; font-size:0.95rem;'>Help Line: <a href='https://wa.me/919810359334' style='color:#22c55e; text-decoration:none; font-weight:600;'>9810359334 (WhatsApp)</a></div>
                            <div style='text-align:center; color:#9ca3af; font-size:0.85rem; margin-top: 8px;'>© " . date('Y') . " Jewel Entry. All rights reserved.</div>
                        </div>";
                    $mail->send();
                } catch (Exception $e) {
                    error_log("Customer welcome email failed: " . $mail->ErrorInfo);
                }
            }

            // 2. Admin Notification Email
            try {
                $adminMail = new PHPMailer(true);
                $adminMail->isSMTP();
                $adminMail->Host = 'smtp.gmail.com';
                $adminMail->SMTPAuth = true;
                $adminMail->Username = 'jeettechnoguide@gmail.com'; // Change to your sender email
                $adminMail->Password = 'nhsi kbyn vlxq fylj'; // Change to your app password
                $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $adminMail->Port = 587;

                $adminMail->setFrom('jeettechnoguide@gmail.com', 'Jewel Entry');
                $adminMail->addAddress('prosenjittechhub@gmail.com', 'Jewel Entry Admin');
                $adminMail->isHTML(true);
                $adminMail->Subject = 'New Customer Registration - Jewel Entry';
                $adminMail->Body = "
                    <div style='font-family: Poppins, Arial, sans-serif; max-width: 520px; margin: 0 auto; background: #FFF9F3; border-radius: 18px; box-shadow: 0 4px 24px rgba(251,191,36,0.08); padding: 32px 24px;'>
                        <h2 style='color: #ea580c; font-size: 1.5rem; font-weight: 700; margin-bottom: 18px;'>New Customer Registered</h2>
                        <div style='background: #fff7ed; border-radius: 10px; padding: 16px 24px; margin-bottom: 18px;'>
                            <div><b>Name:</b> {$fullName}</div>
                            <div><b>Mobile:</b> {$mobile}</div>
                            <div><b>Email:</b> {$email}</div>
                            <div><b>Firm Name:</b> {$firmName}</div>
                            <div><b>Registration Time:</b> " . date('Y-m-d H:i:s') . "</div>
                        </div>
                        <p style='color: #374151; font-size: 1rem;'>A new customer has joined Jewel Entry. Please review their details and ensure their onboarding experience is smooth.</p>
                        <hr style='border: none; border-top: 1px solid #fbbf24; margin: 32px 0 16px;'>
                        <div style='text-align:center; color:#ea580c; font-size:1.1rem; font-weight:600; margin-bottom: 4px;'>Jewel Entry Admin Notification</div>
                        <div style='text-align:center; color:#9ca3af; font-size:0.85rem; margin-top: 8px;'>© " . date('Y') . " Jewel Entry. All rights reserved.</div>
                    </div>";
                $adminMail->send();
            } catch (Exception $e) {
                error_log("Admin notification email failed: " . $adminMail->ErrorInfo);
            }

            session_start();
            $_SESSION['id'] = $userId;
            $_SESSION['firmID'] = $firmId;
            $_SESSION['branchID'] = $branchId; // Store branch ID in session
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