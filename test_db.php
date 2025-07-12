<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$conn = mysqli_connect('localhost', 'root', '', 'jewelentrypro');
if($conn === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
echo "Database connection successful!";
?> 