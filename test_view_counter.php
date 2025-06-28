<?php
// Test page for view counter debugging
require 'config/config.php';
require_once 'config/hallmark.php';

echo "<h1>Page View Counter Debug Test</h1>";

// Test database connection
echo "<h2>Database Connection Test</h2>";
if (isset($conn2) && $conn2) {
    echo "✅ Hallmark database connection successful<br>";
} else {
    echo "❌ Hallmark database connection failed<br>";
    exit;
}

// Test table creation
echo "<h2>Table Creation Test</h2>";
$create_table = "CREATE TABLE IF NOT EXISTS page_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_name VARCHAR(255) NOT NULL,
    view_count INT DEFAULT 1,
    first_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_page (page_name)
)";

if ($conn2->query($create_table)) {
    echo "✅ Table creation/check successful<br>";
} else {
    echo "❌ Table creation failed: " . $conn2->error . "<br>";
}

// Test insert
echo "<h2>Insert Test</h2>";
$page_name = 'huid_data_page';
$insert_sql = "INSERT INTO page_views (page_name, view_count) VALUES (?, 1) ON DUPLICATE KEY UPDATE view_count = view_count + 1";
$stmt = $conn2->prepare($insert_sql);

if ($stmt) {
    $stmt->bind_param('s', $page_name);
    if ($stmt->execute()) {
        echo "✅ Insert successful for page: $page_name<br>";
    } else {
        echo "❌ Insert failed: " . $stmt->error . "<br>";
    }
    $stmt->close();
} else {
    echo "❌ Prepare failed: " . $conn2->error . "<br>";
}

// Test select
echo "<h2>Select Test</h2>";
$select_sql = "SELECT * FROM page_views WHERE page_name = ?";
$stmt = $conn2->prepare($select_sql);

if ($stmt) {
    $stmt->bind_param('s', $page_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row) {
        echo "✅ Select successful: Page: {$row['page_name']}, Views: {$row['view_count']}<br>";
    } else {
        echo "❌ No data found for page: $page_name<br>";
    }
    $stmt->close();
} else {
    echo "❌ Select prepare failed: " . $conn2->error . "<br>";
}

// Show all records
echo "<h2>All Records</h2>";
$all_records = $conn2->query("SELECT * FROM page_views ORDER BY last_viewed DESC LIMIT 10");
if ($all_records) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Page Name</th><th>View Count</th><th>First Viewed</th><th>Last Viewed</th></tr>";
    while ($row = $all_records->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['page_name']}</td>";
        echo "<td>{$row['view_count']}</td>";
        echo "<td>{$row['first_viewed']}</td>";
        echo "<td>{$row['last_viewed']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Failed to fetch records: " . $conn2->error . "<br>";
}

echo "<br><a href='huid_data.php'>Test with huid_data.php</a>";
?> 