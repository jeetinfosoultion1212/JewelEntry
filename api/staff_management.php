<?php
// api/staff_management.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Function to log debug information
function debugLog($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log .= " - Data: " . print_r($data, true);
    }
    error_log($log);
}

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null) {
    debugLog("Sending JSON response", ['success' => $success, 'message' => $message, 'data' => $data]);
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit();
}

// Function to handle errors
function handleError($message, $code = 500) {
    debugLog("Error occurred", ['message' => $message, 'code' => $code]);
    http_response_code($code);
    sendJsonResponse(false, $message);
}

// Function to validate database table
function validateTable($conn) {
    $table_name = 'Firm_Users';
    $required_columns = [
        'id' => 'INT',
        'FirmID' => 'INT',
        'Name' => 'VARCHAR',
        'Username' => 'VARCHAR',
        'Email' => 'VARCHAR',
        'PhoneNumber' => 'VARCHAR',
        'Role' => 'VARCHAR',
        'Password' => 'VARCHAR',
        'Status' => 'VARCHAR',
        'CreatedAt' => 'DATETIME',
        'image_path' => 'VARCHAR'
    ];

    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE '$table_name'");
    if ($result->num_rows === 0) {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT PRIMARY KEY,
            FirmID INT NOT NULL,
            Name VARCHAR(100) NOT NULL,
            Username VARCHAR(50) NOT NULL,
            Email VARCHAR(100),
            PhoneNumber VARCHAR(20),
            Role VARCHAR(50) NOT NULL,
            Password VARCHAR(255) NOT NULL,
            Status VARCHAR(20) NOT NULL DEFAULT 'Active',
            CreatedAt DATETIME NOT NULL,
            image_path VARCHAR(255),
            UNIQUE KEY unique_username_firm (Username, FirmID),
            UNIQUE KEY unique_phone_firm (PhoneNumber, FirmID)
        )";
        
        if (!$conn->query($create_table)) {
            handleError('Failed to create Firm_Users table: ' . $conn->error);
        }
        debugLog("Created Firm_Users table");
    }

    // Check columns
    $result = $conn->query("SHOW COLUMNS FROM $table_name");
    $existing_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_columns[$row['Field']] = $row['Type'];
    }

    // Add missing columns
    foreach ($required_columns as $column => $type) {
        if (!isset($existing_columns[$column])) {
            $alter_table = "ALTER TABLE $table_name ADD COLUMN $column $type";
            if (!$conn->query($alter_table)) {
                handleError("Failed to add column $column: " . $conn->error);
            }
            debugLog("Added column $column to Firm_Users table");
        }
    }
}

try {
    debugLog("Starting API request");
    session_start();
    debugLog("Session data", $_SESSION);
    
    require '../config/config.php';
    debugLog("Config loaded");

    // Check authentication
    if (!isset($_SESSION['id'])) {
        handleError('Not authenticated', 403);
    }

    // Check if firm ID is set
    if (!isset($_SESSION['firmID'])) {
        handleError('Firm ID not found in session', 403);
    }

    // Check if role is set
    if (!isset($_SESSION['role'])) {
        handleError('User role not found in session', 403);
    }

    // Database connection
    debugLog("Attempting database connection");
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        handleError('Database connection failed: ' . $conn->connect_error);
    }
    debugLog("Database connected successfully");

    // Validate database table
    validateTable($conn);

    // Handle GET requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        debugLog("Processing GET request");
        $action = $_GET['action'] ?? '';
        if ($action === 'list') {
            getStaffList($conn);
        } else {
            handleError('Invalid action', 400);
        }
    } 
    // Handle POST requests
    else {
        debugLog("Processing POST request");
        $raw_input = file_get_contents('php://input');
        debugLog("Raw input received", $raw_input);
        
        $input = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            handleError('Invalid JSON data received: ' . json_last_error_msg(), 400);
        }
        debugLog("JSON decoded successfully", $input);

        $action = $input['action'] ?? '';
        debugLog("Action requested", $action);
        
        // Check Super Admin permission for modifications
        if ($_SESSION['role'] !== 'Super Admin') {
            handleError('Access denied - Not Super Admin', 403);
        }
        
        switch ($action) {
            case 'create':
                debugLog("Creating new staff member");
                createStaff($conn, $input);
                break;
            case 'update':
                debugLog("Updating staff member");
                updateStaff($conn, $input);
                break;
            case 'delete':
                debugLog("Deleting staff member");
                deleteStaff($conn, $input);
                break;
            case 'toggle_status':
                debugLog("Toggling staff status");
                toggleStaffStatus($conn, $input);
                break;
            case 'reset_password':
                debugLog("Resetting staff password");
                resetStaffPassword($conn, $input);
                break;
            default:
                handleError('Invalid action: ' . $action, 400);
        }
    }
} catch (Exception $e) {
    debugLog("Exception caught", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    handleError('Server error: ' . $e->getMessage());
} finally {
    if (isset($conn)) {
        debugLog("Closing database connection");
        $conn->close();
    }
    debugLog("API request completed");
}

function generateUsername($name, $phone) {
    // Get first name (before space)
    $firstName = strtolower(explode(' ', $name)[0]);
    
    // Get last 4 digits of phone
    $phoneLast4 = substr(preg_replace('/[^0-9]/', '', $phone), -4);
    
    // Combine and limit length
    $username = substr($firstName . $phoneLast4, 0, 12);
    
    return $username;
}

function getStaffList($conn) {
    $firm_id = $_SESSION['firmID'];
    
    $query = "SELECT id, Name, Username, Email, PhoneNumber, Role, Status, CreatedAt, image_path 
              FROM Firm_Users 
              WHERE FirmID = ? 
              ORDER BY CreatedAt DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $firm_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    
    echo json_encode(['success' => true, 'staff' => $staff]);
}

function createStaff($conn, $data) {
    $firm_id = $_SESSION['firmID'];
    
    // Validate required fields
    if (empty($data['name']) || empty($data['phone']) || empty($data['role']) || empty($data['password'])) {
        handleError('Required fields missing', 400);
    }
    
    // Validate phone number format
    $phone = preg_replace('/[^0-9]/', '', $data['phone']);
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        handleError('Invalid phone number format', 400);
    }
    
    // Check if phone number already exists
    $check_query = "SELECT id, Name FROM Firm_Users WHERE PhoneNumber = ? AND FirmID = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("si", $phone, $firm_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $existing_staff = $check_result->fetch_assoc();
        handleError("Phone number already registered with staff member: " . $existing_staff['Name'], 400);
    }
    
    // Generate username
    $username = generateUsername($data['name'], $phone);
    
    // Check if username already exists
    $check_query = "SELECT id FROM Firm_Users WHERE Username = ? AND FirmID = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("si", $username, $firm_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    // If username exists, add a number suffix
    $counter = 1;
    $originalUsername = $username;
    while ($check_result->num_rows > 0) {
        $username = $originalUsername . $counter;
        $check_stmt->bind_param("si", $username, $firm_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $counter++;
    }
    
    // Handle image upload if present
    $image_path = '';
    if (!empty($data['image'])) {
        $upload_dir = '../uploads/staff_images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Decode base64 image
        $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['image']));
        if ($image_data === false) {
            handleError('Invalid image data', 400);
        }
        
        // Generate unique filename
        $filename = uniqid('staff_') . '.jpg';
        $filepath = $upload_dir . $filename;
        
        // Save image
        if (file_put_contents($filepath, $image_data)) {
            $image_path = 'uploads/staff_images/' . $filename;
        } else {
            handleError('Failed to save image', 500);
        }
    }
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Prepare variables for binding
    $name = $data['name'];
    $email = $data['email'] ?? '';
    $role = $data['role'];
    
    $query = "INSERT INTO Firm_Users (FirmID, Name, Username, Email, PhoneNumber, Role, Password, Status, CreatedAt, image_path) 
              VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW(), ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssssss", 
        $firm_id,
        $name,
        $username,
        $email,
        $phone,
        $role,
        $hashed_password,
        $image_path
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Staff member created successfully',
            'username' => $username,
            'image_path' => $image_path
        ]);
    } else {
        // If insert fails and we uploaded an image, delete it
        if (!empty($image_path) && file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        handleError('Failed to create staff member: ' . $stmt->error, 500);
    }
}

function updateStaff($conn, $data) {
    $firm_id = $_SESSION['firmID'];
    
    if (empty($data['staff_id'])) {
        handleError('Staff ID required', 400);
    }
    
    // Check if staff belongs to current firm
    $check_query = "SELECT id, image_path FROM Firm_Users WHERE id = ? AND FirmID = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $data['staff_id'], $firm_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        handleError('Staff member not found', 404);
    }
    
    $existing_staff = $check_result->fetch_assoc();
    
    // If phone number is being updated, check if it's already in use
    if (!empty($data['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        if (!preg_match('/^[0-9]{10}$/', $phone)) {
            handleError('Invalid phone number format', 400);
        }
        
        $check_query = "SELECT id, Name FROM Firm_Users WHERE PhoneNumber = ? AND FirmID = ? AND id != ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("sii", $phone, $firm_id, $data['staff_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $existing_staff = $check_result->fetch_assoc();
            handleError("Phone number already registered with staff member: " . $existing_staff['Name'], 400);
        }
    }
    
    // Handle image upload if present
    $image_path = $existing_staff['image_path'];
    if (!empty($data['image'])) {
        $upload_dir = '../uploads/staff_images/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Decode base64 image
        $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['image']));
        if ($image_data === false) {
            handleError('Invalid image data', 400);
        }
        
        // Generate unique filename
        $filename = uniqid('staff_') . '.jpg';
        $filepath = $upload_dir . $filename;
        
        // Save image
        if (file_put_contents($filepath, $image_data)) {
            // Delete old image if exists
            if (!empty($existing_staff['image_path']) && file_exists('../' . $existing_staff['image_path'])) {
                unlink('../' . $existing_staff['image_path']);
            }
            $image_path = 'uploads/staff_images/' . $filename;
        } else {
            handleError('Failed to save image', 500);
        }
    }
    
    // Prepare variables for binding
    $name = $data['name'];
    $email = $data['email'] ?? '';
    $role = $data['role'];
    
    $query = "UPDATE Firm_Users SET 
                Name = ?, 
                Email = ?, 
                PhoneNumber = ?, 
                Role = ?,
                image_path = ?
              WHERE id = ? AND FirmID = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssii", 
        $name,
        $email,
        $phone,
        $role,
        $image_path,
        $data['staff_id'],
        $firm_id
    );
    
    if ($stmt->execute()) {
        sendJsonResponse(true, 'Staff member updated successfully', [
            'image_path' => $image_path
        ]);
    } else {
        // If update fails and we uploaded a new image, delete it
        if (!empty($data['image']) && file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        handleError('Failed to update staff member: ' . $stmt->error, 500);
    }
}

function deleteStaff($conn, $data) {
    $firm_id = $_SESSION['firmID'];
    
    if (empty($data['staff_id'])) {
        handleError('Staff ID required', 400);
    }
    
    // Check if staff belongs to current firm
    $check_query = "SELECT id FROM Firm_Users WHERE id = ? AND FirmID = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $data['staff_id'], $firm_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        handleError('Staff member not found', 404);
    }
    
    $query = "DELETE FROM Firm_Users WHERE id = ? AND FirmID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $data['staff_id'], $firm_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Staff member deleted successfully']);
    } else {
        handleError('Failed to delete staff member: ' . $stmt->error, 500);
    }
}

function toggleStaffStatus($conn, $data) {
    $firm_id = $_SESSION['firmID'];
    
    if (empty($data['staff_id'])) {
        handleError('Staff ID required', 400);
    }
    
    // Get current status
    $check_query = "SELECT Status FROM Firm_Users WHERE id = ? AND FirmID = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $data['staff_id'], $firm_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        handleError('Staff member not found', 404);
    }
    
    $current_status = $check_result->fetch_assoc()['Status'];
    $new_status = ($current_status === 'Active') ? 'Inactive' : 'Active';
    
    $query = "UPDATE Firm_Users SET Status = ? WHERE id = ? AND FirmID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $new_status, $data['staff_id'], $firm_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Staff status updated successfully',
            'new_status' => $new_status
        ]);
    } else {
        handleError('Failed to update staff status: ' . $stmt->error, 500);
    }
}

function resetStaffPassword($conn, $data) {
    $firm_id = $_SESSION['firmID'];
    
    if (empty($data['staff_id']) || empty($data['new_password'])) {
        handleError('Staff ID and new password required', 400);
    }
    
    // Check if staff belongs to current firm
    $check_query = "SELECT id FROM Firm_Users WHERE id = ? AND FirmID = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ii", $data['staff_id'], $firm_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        handleError('Staff member not found', 404);
    }
    
    $hashed_password = password_hash($data['new_password'], PASSWORD_DEFAULT);
    
    $query = "UPDATE Firm_Users SET Password = ? WHERE id = ? AND FirmID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $hashed_password, $data['staff_id'], $firm_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
    } else {
        handleError('Failed to reset password: ' . $stmt->error, 500);
    }
}
?>