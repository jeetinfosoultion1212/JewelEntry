<?php
// Include database connection
$servername = "localhost";
$username = "root";
$password = ""; // XAMPP default has no password
$dbname = "jewelentry"; // change this to your actual local DB

$conn = mysqli_connect($servername, $username, $password, $dbname);

if($conn === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

require_once 'config/db_connect.php';

try {
    // Create jewellery_price_config table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS jewellery_price_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_type VARCHAR(50) NOT NULL,
        purity DECIMAL(5,2) NOT NULL,
        unit VARCHAR(20) NOT NULL,
        rate DECIMAL(10,2) NOT NULL,
        effective_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        updated_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_material_purity (material_type, purity)
    )";

    if ($conn->query($sql)) {
        echo "Table jewellery_price_config created successfully or already exists\n";
    } else {
        throw new Exception("Error creating table: " . $conn->error);
    }

    // Add some sample data if the table is empty
    $check = $conn->query("SELECT COUNT(*) as count FROM jewellery_price_config");
    $row = $check->fetch_assoc();
    
    if ($row['count'] == 0) {
        $sample_data = [
            ['Gold', 24, 'gram', 6500.00],
            ['Gold', 22, 'gram', 5958.33],
            ['Gold', 18, 'gram', 4875.00],
            ['Silver', 99.9, 'gram', 75.00],
            ['Platinum', 95, 'gram', 3500.00]
        ];

        $stmt = $conn->prepare("INSERT INTO jewellery_price_config (material_type, purity, unit, rate) VALUES (?, ?, ?, ?)");
        
        foreach ($sample_data as $data) {
            $stmt->bind_param("sdsd", $data[0], $data[1], $data[2], $data[3]);
            if (!$stmt->execute()) {
                throw new Exception("Error inserting sample data: " . $stmt->error);
            }
        }
        
        echo "Sample data inserted successfully\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}
?> 