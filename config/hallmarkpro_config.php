<?php
// HallmarkPro Database Configuration
$hallmarkpro_servername = "localhost";
$hallmarkpro_username = "root";
$hallmarkpro_password = "";
$hallmarkpro_dbname = "hallmarkpro";

// Create connection to HallmarkPro database
$hallmarkpro_conn = new mysqli($hallmarkpro_servername, $hallmarkpro_username, $hallmarkpro_password, $hallmarkpro_dbname);

// Check connection
if ($hallmarkpro_conn->connect_error) {
    die("Connection to HallmarkPro database failed: " . $hallmarkpro_conn->connect_error);
}
?> 