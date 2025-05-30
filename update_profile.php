<?php
session_start();
require '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];
$user_role = $_SESSION['role'];

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Handle different actions
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'update_firm_details':
        handleFirmDetailsUpdate($conn, $firm_id);
        break;
    
    case 'upload_logo':
        handleLogoUpload($conn, $firm_id);
        break;
    
    case 'get_staff':
        getStaffList($conn, $firm_id);
        break;
    
    case 'add_staff':
        if ($user_role === 'super admin') {
            addStaffMember($conn, $firm_id);
        } else {
            echo json_encode(['success' => false, 'message' => 'Only super admin can add staff members']);
        }
        break;
    
    case 'update_staff':
        if ($user_role === 'super admin') {
            updateStaffMember($conn, $firm_id);
        } else {
            echo json_encode(['success' => false, 'message' => 'Only super admin can update staff members']);
        }
        break;
    
    case 'delete_staff':
        if ($user_role === 'super admin') {
            deleteStaffMember($conn, $firm_id);
        } else {
            echo json_encode(['success' => false, 'message' => 'Only super admin can delete staff members']);
        }
        break;
    
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
}

function handleFirmDetailsUpdate($conn, $firm_id) {
    // Validate and sanitize input
    $firmName = trim($_POST['firmName'] ?? '');
    $tagline = trim($_POST['tagline'] ?? '');
    $address1 = trim($_POST['address1'] ?? '');
    $address2 = trim($_POST['address2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gst = trim($_POST['gst'] ?? '');

    // Validate required fields
    if (empty($firmName)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Firm name is required']);
        return;
    }

    // Combine address lines
    $address = $address1;
    if (!empty($address2)) {
        $address .= ', ' . $address2;
    }

    try {
        // Update firm details
        $stmt = $conn->prepare("UPDATE Firm SET 
            FirmName = ?, 
            Tagline = ?, 
            Address = ?, 
            City = ?, 
            PostalCode = ?, 
            PhoneNumber = ?, 
            Email = ?, 
            GSTNumber = ? 
            WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("ssssssssi", 
            $firmName, 
            $tagline, 
            $address, 
            $city, 
            $pincode, 
            $phone, 
            $email, 
            $gst, 
            $firm_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Firm details updated successfully',
            'data' => [
                'name' => $firmName,
                'tagline' => $tagline,
                'address' => $address,
                'city' => $city,
                'pincode' => $pincode,
                'phone' => $phone,
                'email' => $email,
                'gst' => $gst
            ]
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update firm details: ' . $e->getMessage()
        ]);
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function handleLogoUpload($conn, $firm_id) {
    // Check if file was uploaded
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        return;
    }

    $file = $_FILES['logo'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG and GIF are allowed']);
        return;
    }

    // Validate file size (2MB max)
    if ($file['size'] > 2 * 1024 * 1024) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File size too large. Maximum size is 2MB']);
        return;
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/firm_logos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'firm_' . $firm_id . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database with new logo path
        $relativePath = 'uploads/firm_logos/' . $filename;
        $stmt = $conn->prepare("UPDATE Firm SET Logo = ? WHERE id = ?");
        $stmt->bind_param("si", $relativePath, $firm_id);

        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Logo uploaded successfully',
                'logo_url' => $relativePath
            ]);
        } else {
            // Delete uploaded file if database update fails
            unlink($filepath);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update logo in database']);
        }

        $stmt->close();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    }
}

function getStaffList($conn, $firm_id) {
    $query = "SELECT id, Name, Role, PhoneNumber, Email, image_path, CreatedAt 
              FROM Firm_Users 
              WHERE FirmID = ? 
              ORDER BY CreatedAt DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $firm_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = [
            'id' => $row['id'],
            'name' => $row['Name'],
            'role' => $row['Role'],
            'phone' => $row['PhoneNumber'],
            'email' => $row['Email'],
            'image' => $row['image_path'],
            'created_at' => $row['CreatedAt']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'staff' => $staff]);
    
    $stmt->close();
}

function addStaffMember($conn, $firm_id) {
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($name) || empty($role) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Name, role and password are required']);
        return;
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO Firm_Users (FirmID, Name, Role, PhoneNumber, Email, Password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $firm_id, $name, $role, $phone, $email, $hashedPassword);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Staff member added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add staff member']);
    }

    $stmt->close();
}

function updateStaffMember($conn, $firm_id) {
    $staff_id = $_POST['staff_id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($name) || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'Name and role are required']);
        return;
    }

    $stmt = $conn->prepare("UPDATE Firm_Users SET Name = ?, Role = ?, PhoneNumber = ?, Email = ? WHERE id = ? AND FirmID = ?");
    $stmt->bind_param("ssssii", $name, $role, $phone, $email, $staff_id, $firm_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Staff member updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update staff member']);
    }

    $stmt->close();
}

function deleteStaffMember($conn, $firm_id) {
    $staff_id = $_POST['staff_id'] ?? 0;

    $stmt = $conn->prepare("DELETE FROM Firm_Users WHERE id = ? AND FirmID = ?");
    $stmt->bind_param("ii", $staff_id, $firm_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Staff member deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete staff member']);
    }

    $stmt->close();
}

$conn->close(); 