<?php
// Test script to verify timezone settings
require 'config/config.php';

echo "<h2>Timezone Test Results</h2>";

// Test PHP timezone
echo "<h3>PHP Timezone:</h3>";
echo "Current PHP timezone: " . date_default_timezone_get() . "<br>";
echo "Current PHP time: " . date('Y-m-d H:i:s') . "<br>";
echo "Current PHP timestamp: " . time() . "<br>";

// Test MySQL timezone
echo "<h3>MySQL Timezone:</h3>";
$result = $conn->query("SELECT NOW() as current_time, CURDATE() as current_date, @@time_zone as timezone");
if ($result) {
    $row = $result->fetch_assoc();
    echo "MySQL timezone setting: " . $row['timezone'] . "<br>";
    echo "MySQL current time: " . $row['current_time'] . "<br>";
    echo "MySQL current date: " . $row['current_date'] . "<br>";
} else {
    echo "Error querying MySQL timezone: " . $conn->error . "<br>";
}

// Test date functions
echo "<h3>Date Function Tests:</h3>";
echo "PHP date('Y-m-d'): " . date('Y-m-d') . "<br>";
echo "PHP date('H:i:s'): " . date('H:i:s') . "<br>";
echo "PHP date('Y-m-d H:i:s'): " . date('Y-m-d H:i:s') . "<br>";

$conn->close();
?> 