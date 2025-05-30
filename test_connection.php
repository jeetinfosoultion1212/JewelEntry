<?php
// Include database configuration
require 'config/config.php';

// Test connection
if($conn) {
    echo "Database connection successful!";
    
    // Test if we can query the database
    $result = $conn->query("SHOW TABLES");
    if($result) {
        echo "<br><br>Tables in database:<br>";
        while($row = $result->fetch_array()) {
            echo "- " . $row[0] . "<br>";
        }
    }
} else {
    echo "Connection failed: " . mysqli_connect_error();
}

// Close connection
$conn->close();
?> 