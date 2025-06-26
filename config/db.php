<?php
$servername = "localhost";
$username = "u176143338_retailstore";
$password = "Rontik10@";
$dbname = "u176143338_retailstore";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set MySQL timezone to Indian time (Asia/Kolkata)
    $pdo->exec("SET time_zone = '+05:30'");
    
    // Set charset to ensure proper encoding
    $pdo->exec("SET NAMES utf8mb4");
    
    // Set PHP timezone to Indian time
    date_default_timezone_set('Asia/Kolkata');
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>