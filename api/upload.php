<?php
session_start();
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['file'];
$type = $_POST['type'] ?? 'image';

// Validate file type
if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPG, PNG and GIF are allowed.']);
    exit();
}

// Validate file size
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Maximum size is 5MB.']);
    exit();
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '_' . time() . '.' . $extension;

// Determine upload directory based on type
$uploadDir = UPLOAD_DIR;
if ($type === 'firm_logo') {
    $uploadDir .= 'firm_logos/';
} elseif ($type === 'user_image') {
    $uploadDir .= 'user_images/';
}

// Ensure directory exists
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filepath = $uploadDir . $filename;
$relativePath = str_replace('../', '', $filepath); // Remove '../' for database storage
$urlPath = str_replace('../', '', $filepath); // Remove '../' for URL

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit();
}

// Update database if needed
if ($type === 'firm_logo') {
    $stmt = $conn->prepare("UPDATE Firm SET Logo = ? WHERE id = ?");
    $stmt->bind_param("si", $relativePath, $_SESSION['firmID']);
    $stmt->execute();
} elseif ($type === 'user_image') {
    $stmt = $conn->prepare("UPDATE Firm_Users SET image_path = ? WHERE id = ?");
    $stmt->bind_param("si", $relativePath, $_SESSION['id']);
    $stmt->execute();
}

// Return success response
echo json_encode([
    'success' => true,
    'url' => $urlPath,
    'message' => 'File uploaded successfully'
]); 