<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jewelentry"; // Use your actual database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE loan_collateral_items (
  id INT(11) NOT NULL AUTO_INCREMENT,
  loan_id INT(11) NOT NULL,
  material_type VARCHAR(50) NOT NULL,
  purity DECIMAL(5,2) NOT NULL,
  weight DECIMAL(10,3) NOT NULL,
  rate_per_gram DECIMAL(12,2) NOT NULL,
  calculated_value DECIMAL(12,2) NOT NULL,
  description TEXT DEFAULT NULL,
  image_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table loan_collateral_items created successfully or already exists\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}

$conn->close();
?> 