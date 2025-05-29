<?php
$servername = "localhost";
$username = "u176143338_retailstore";
$password = "Rontik10@";
$dbname = "u176143338_retailstore";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>