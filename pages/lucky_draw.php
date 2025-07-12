<?php
header('Location: gold_plan.php');
exit();
// Basic structure for Lucky Draw page

session_start();
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

require 'config/config.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch firm_id from session
$firm_id = $_SESSION['firmID'];

// Basic permission check (optional, you might want a more granular check)
$allowed_roles = ['admin', 'manager', 'super admin'];
$user_role = strtolower($_SESSION['role'] ?? '');
$hasFeatureAccess = in_array($user_role, $allowed_roles);

// Redirect if no access (adjust based on your feature access logic)
if (!$hasFeatureAccess) {
    $_SESSION['error'] = 'You do not have access to this feature.';
    header("Location: home.php");
    exit();
}

// Your Lucky Draw page logic will go here

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucky Draw Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Lucky Draw Management</h1>
        <p class="text-gray-600">This is the Lucky Draw management page. Content will be added here.</p>
        <!-- Add lucky draw listing, creation forms, etc. here -->
         <a href="home.php" class="mt-4 inline-block text-blue-500 hover:underline">&larr; Back to Dashboard</a>
    </div>
</body>
</html> 