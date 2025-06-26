<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jewelentrypro";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set MySQL timezone to Indian time (Asia/Kolkata)
$conn->query("SET time_zone = '+05:30'");

// Set charset to ensure proper encoding
$conn->set_charset("utf8mb4");

// Set PHP timezone to Indian time
date_default_timezone_set('Asia/Kolkata');
?> 