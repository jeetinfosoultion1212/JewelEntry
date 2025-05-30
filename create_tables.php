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
require_once 'config/config.php';

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

    // Create Firm table
    $firmTable = "CREATE TABLE IF NOT EXISTS Firm (
        id INT PRIMARY KEY AUTO_INCREMENT,
        FirmName VARCHAR(255) NOT NULL,
        OwnerName VARCHAR(255),
        Tagline TEXT,
        Address TEXT,
        City VARCHAR(100),
        State VARCHAR(100),
        PostalCode VARCHAR(20),
        PhoneNumber VARCHAR(20),
        Email VARCHAR(255),
        GSTNumber VARCHAR(50),
        Logo VARCHAR(255),
        PANNumber VARCHAR(20),
        BISRegistrationNumber VARCHAR(50),
        BankName VARCHAR(255),
        BankBranch VARCHAR(255),
        BankAccountNumber VARCHAR(50),
        IFSCCode VARCHAR(20),
        AccountType VARCHAR(50),
        IsGSTRegistered BOOLEAN DEFAULT FALSE,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    // Create Firm_Users table
    $usersTable = "CREATE TABLE IF NOT EXISTS Firm_Users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        FirmID INT NOT NULL,
        Name VARCHAR(255) NOT NULL,
        Username VARCHAR(50) NOT NULL UNIQUE,
        Password VARCHAR(255) NOT NULL,
        Email VARCHAR(255),
        PhoneNumber VARCHAR(20),
        Role ENUM('Super Admin', 'Manager', 'Sales Executive', 'Cashier', 'Goldsmith', 'Designer', 'Accountant', 'Security', 'Other') NOT NULL,
        Status ENUM('Active', 'Inactive') DEFAULT 'Active',
        image_path VARCHAR(255),
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (FirmID) REFERENCES Firm(id) ON DELETE CASCADE
    )";

    // Execute the queries
    if ($conn->query($firmTable) === TRUE) {
        echo "Firm table created successfully<br>";
    } else {
        echo "Error creating Firm table: " . $conn->error . "<br>";
    }

    if ($conn->query($usersTable) === TRUE) {
        echo "Firm_Users table created successfully<br>";
    } else {
        echo "Error creating Firm_Users table: " . $conn->error . "<br>";
    }

    // Create default Super Admin if not exists
    $checkAdmin = "SELECT id FROM Firm_Users WHERE Role = 'Super Admin' LIMIT 1";
    $result = $conn->query($checkAdmin);

    if ($result->num_rows == 0) {
        // Create default firm
        $defaultFirm = "INSERT INTO Firm (FirmName, OwnerName) VALUES ('Default Firm', 'Admin')";
        if ($conn->query($defaultFirm) === TRUE) {
            $firmId = $conn->insert_id;
            
            // Create default admin user
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $defaultAdmin = "INSERT INTO Firm_Users (FirmID, Name, Username, Password, Role) 
                           VALUES (?, 'Super Admin', 'admin', ?, 'Super Admin')";
            
            $stmt = $conn->prepare($defaultAdmin);
            $stmt->bind_param("is", $firmId, $defaultPassword);
            
            if ($stmt->execute()) {
                echo "Default Super Admin created successfully<br>";
                echo "Username: admin<br>";
                echo "Password: admin123<br>";
            } else {
                echo "Error creating default admin: " . $stmt->error . "<br>";
            }
        } else {
            echo "Error creating default firm: " . $conn->error . "<br>";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}
?> 