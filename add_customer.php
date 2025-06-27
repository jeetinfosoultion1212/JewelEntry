<?php
session_start();
require 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Check if firm ID is set
if (!isset($_SESSION['firmID'])) {
    header("Location: login.php?error=No firm associated with your account");
    exit();
}

// Create uploads directory if not exists
$uploadDir = 'uploads/customers/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Collect form data
$firstName = trim($_POST['FirstName'] ?? '');
$lastName = trim($_POST['LastName'] ?? '');
$phone = trim($_POST['PhoneNumber'] ?? '');
$email = trim($_POST['Email'] ?? '');
$address = trim($_POST['Address'] ?? '');
$city = trim($_POST['City'] ?? '');
$state = trim($_POST['State'] ?? '');
$postal = trim($_POST['PostalCode'] ?? '');
$country = trim($_POST['Country'] ?? 'India');
$dob = trim($_POST['DateOfBirth'] ?? null);
$gender = trim($_POST['Gender'] ?? '');
$pan = trim($_POST['PANNumber'] ?? '');
$aadhaar = trim($_POST['AadhaarNumber'] ?? '');
$firmID = $_SESSION['firmID'];

// Handle image upload
$imagePath = null;
if (isset($_FILES['CustomerImage']) && $_FILES['CustomerImage']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['CustomerImage']['name'], PATHINFO_EXTENSION);
    $fileName = 'cust_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $targetPath = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['CustomerImage']['tmp_name'], $targetPath)) {
        $imagePath = $targetPath;
    }
}

// Insert into DB
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die('DB connection failed');
}
$stmt = $conn->prepare("INSERT INTO customer (firm_id, FirstName, LastName, Email, PhoneNumber, Address, City, State, PostalCode, Country, DateOfBirth, Gender, PANNumber, AadhaarNumber, CustomerImage, CreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param('issssssssssssss', $firmID, $firstName, $lastName, $email, $phone, $address, $city, $state, $postal, $country, $dob, $gender, $pan, $aadhaar, $imagePath);
$success = $stmt->execute();
$stmt->close();
$conn->close();

if ($success) {
    header('Location: customers.php?msg=Customer+added+successfully');
    exit();
} else {
    header('Location: customers.php?msg=Failed+to+add+customer');
    exit();
} 