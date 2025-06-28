<?php
// Start session but don't require login for viewing
session_start();
require 'config/config.php'; // Your main DB
require_once 'config/hallmark.php'; // For huid_data table
date_default_timezone_set('Asia/Kolkata');

// Test database connections
if (!isset($conn2) || !$conn2) {
    die('Error: Hallmark database connection failed');
}

// Database table required for view tracking:
// CREATE TABLE IF NOT EXISTS page_views (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     page_name VARCHAR(255) NOT NULL,
//     view_count INT DEFAULT 1,
//     first_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//     last_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//     UNIQUE KEY unique_page (page_name)
// );

// 🔄 Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => 'Invalid input.'];

    if (isset($_POST['action'])) {
        // Increment view counter
        if ($_POST['action'] === 'increment_view') {
            try {
                // First, check if the table exists
                $table_check = $conn2->query("SHOW TABLES LIKE 'page_views'");
                if ($table_check->num_rows === 0) {
                    // Create the table if it doesn't exist
                    $create_table = "CREATE TABLE IF NOT EXISTS page_views (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        page_name VARCHAR(255) NOT NULL,
                        view_count INT DEFAULT 1,
                        first_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        last_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_page (page_name)
                    )";
                    $conn2->query($create_table);
                }
                
                $page_name = 'huid_data_page';
                
                // Check if page view record exists
                $check_stmt = $conn2->prepare("SELECT id, view_count FROM page_views WHERE page_name = ?");
                if (!$check_stmt) {
                    $response = ['status' => 'error', 'message' => 'Prepare failed: ' . $conn2->error];
                } else {
                    $check_stmt->bind_param('s', $page_name);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    $view_record = $result->fetch_assoc();
                    $check_stmt->close();
                    
                    if ($view_record) {
                        // Update existing record
                        $new_count = $view_record['view_count'] + 1;
                        $update_stmt = $conn2->prepare("UPDATE page_views SET view_count = ?, last_viewed = NOW() WHERE page_name = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param('is', $new_count, $page_name);
                            if ($update_stmt->execute()) {
                                $response = ['status' => 'success', 'view_count' => $new_count];
                            } else {
                                $response = ['status' => 'error', 'message' => 'Update failed: ' . $update_stmt->error];
                            }
                            $update_stmt->close();
                        } else {
                            $response = ['status' => 'error', 'message' => 'Update prepare failed: ' . $conn2->error];
                        }
                    } else {
                        // Create new record
                        $insert_stmt = $conn2->prepare("INSERT INTO page_views (page_name, view_count, first_viewed, last_viewed) VALUES (?, 1, NOW(), NOW())");
                        if ($insert_stmt) {
                            $insert_stmt->bind_param('s', $page_name);
                            if ($insert_stmt->execute()) {
                                $response = ['status' => 'success', 'view_count' => 1];
                            } else {
                                $response = ['status' => 'error', 'message' => 'Insert failed: ' . $insert_stmt->error];
                            }
                            $insert_stmt->close();
                        } else {
                            $response = ['status' => 'error', 'message' => 'Insert prepare failed: ' . $conn2->error];
                        }
                    }
                }
            } catch (Exception $e) {
                $response = ['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()];
            }
        }

        // Save Pair ID action
        if ($_POST['action'] === 'save_pair_id') {
            $ids = $_POST['ids'] ?? [];
            $pair_id = $_POST['pair_id'] ?? '';
            if (!empty($ids)) {
                if ($pair_id === '') {
                    $in = implode(',', array_fill(0, 1, '?'));
                    $stmt = $conn2->prepare("SELECT huid_code FROM huid_data WHERE id IN ($in) LIMIT 1");
                    $stmt->bind_param('i', $ids[0]);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $stmt->close();
                    $pair_id = $row ? $row['huid_code'] : '';
                }
                if ($pair_id !== '') {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $types = str_repeat('i', count($ids));
                    $stmt = $conn2->prepare("UPDATE huid_data SET pair_id = ? WHERE id IN ($in)");
                    $params = array_merge([$pair_id], $ids);
                    $stmt->bind_param('s' . $types, ...$params);
                    if ($stmt->execute()) {
                        $response = ['status' => 'success', 'message' => 'Pair ID updated successfully.', 'pair_id' => $pair_id];
                    } else {
                        $response['message'] = $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }

        // Reset all pair_ids for the request_no
        elseif ($_POST['action'] === 'reset_pair_ids') {
            $request_no = $_POST['request_no'] ?? '';
            if (!empty($request_no)) {
                $stmt = $conn2->prepare("UPDATE huid_data SET pair_id = NULL WHERE request_no = ?");
                $stmt->bind_param('s', $request_no);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'All Pair IDs reset.'];
                } else {
                    $response['message'] = $stmt->error;
                }
                $stmt->close();
            }
        }

        // Generate all single IDs
        elseif ($_POST['action'] === 'generate_all_ids') {
            $request_no = $_POST['request_no'] ?? '';
            if (!empty($request_no)) {
                // Get all items without pair_id
                $stmt = $conn2->prepare("SELECT id, huid_code FROM huid_data WHERE request_no = ? AND (pair_id IS NULL OR pair_id = '')");
                $stmt->bind_param('s', $request_no);
                $stmt->execute();
                $result = $stmt->get_result();
                $items = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                $success = 0;
                $errors = 0;
                
                foreach ($items as $item) {
                    // Set pair_id as the item's own HUID code
                    $pairId = $item['huid_code'];
                    $update_stmt = $conn2->prepare("UPDATE huid_data SET pair_id = ? WHERE id = ?");
                    $update_stmt->bind_param('si', $pairId, $item['id']);
                    if ($update_stmt->execute()) {
                        $success++;
                    } else {
                        $errors++;
                    }
                    $update_stmt->close();
                }
                
                if ($success > 0) {
                    $response = [
                        'status' => 'success', 
                        'message' => "Generated $success IDs successfully" . ($errors > 0 ? " with $errors errors" : "")
                    ];
                } else {
                    $response['message'] = 'No IDs were generated. All items may already have IDs.';
                }
            }
        }

        // Update weight action
        elseif ($_POST['action'] === 'update_weight') {
            $id = $_POST['id'] ?? 0;
            $weight = $_POST['weight'] ?? 0;
            
            if (!empty($id) && is_numeric($weight)) {
                $stmt = $conn2->prepare("UPDATE huid_data SET weight = ? WHERE id = ?");
                $stmt->bind_param('di', $weight, $id);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Weight updated successfully.', 'weight' => $weight];
                } else {
                    $response['message'] = $stmt->error;
                }
                $stmt->close();
            }
        }

        // Update item name action
        elseif ($_POST['action'] === 'update_item') {
            $id = $_POST['id'] ?? 0;
            $item_name = $_POST['item_name'] ?? '';
            
            if (!empty($id) && !empty($item_name)) {
                $stmt = $conn2->prepare("UPDATE huid_data SET item = ? WHERE id = ?");
                $stmt->bind_param('si', $item_name, $id);
                
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Item name updated successfully.', 'item_name' => $item_name];
                } else {
                    $response['message'] = $stmt->error;
                }
                $stmt->close();
            }
        }
        
        // Save selected IDs for printing
        elseif ($_POST['action'] === 'save_selected_ids') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids)) {
                $_SESSION['print_ids'] = $ids;
                $response = ['status' => 'success', 'message' => 'Selected IDs saved for printing.', 'count' => count($ids)];
            } else {
                $response['message'] = 'No IDs selected.';
            }
        }
        
        // Submit to Jewel Entry
        elseif ($_POST['action'] === 'submit_to_jewel_entry') {
            if (!isset($_SESSION['firm_user_id'])) {
                $response = ['status' => 'login_required', 'message' => 'Please login to submit to Jewel Entry.'];
            } else {
                $request_no = $_POST['request_no'] ?? '';
                
                if (!empty($request_no)) {
                    $firm_user_id = $_SESSION['firm_user_id'];
                    $firm_stmt = $conn->prepare("SELECT f.id as firm_id, f.FirmName FROM Firm_Users u JOIN Firm f ON u.FirmID = f.id WHERE u.id = ?");
                    $firm_stmt->bind_param('i', $firm_user_id);
                    $firm_stmt->execute();
                    $firm_result = $firm_stmt->get_result();
                    $firm_data = $firm_result->fetch_assoc();
                    $firm_stmt->close();
                    
                    if (!$firm_data) {
                        $response = ['status' => 'error', 'message' => 'Could not retrieve firm details.'];
                    } else {
                        $firm_id = $firm_data['firm_id'];
                        
                        $stmt = $conn2->prepare("SELECT * FROM huid_data WHERE request_no = ? AND pair_id IS NOT NULL");
                        $stmt->bind_param('s', $request_no);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $items = $result->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                        
                        $grouped_items = [];
                        foreach ($items as $item) {
                            $pair_id = $item['pair_id'];
                            if (!isset($grouped_items[$pair_id])) {
                                $grouped_items[$pair_id] = [];
                            }
                            $grouped_items[$pair_id][] = $item;
                        }
                        
                        $success = 0;
                        $errors = 0;
                        
                        foreach ($grouped_items as $pair_id => $group) {
                            // Check for duplicate product_id (pair_id)
                            $check_stmt = $conn->prepare("SELECT id FROM jewellery_items WHERE product_id = ?");
                            $check_stmt->bind_param('s', $pair_id);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();
                            if ($check_result->num_rows > 0) {
                                // Already exists, skip insert
                                $check_stmt->close();
                                continue;
                            }
                            $check_stmt->close();
                            
                            $gross_weight = 0;
                            $huid_codes = [];
                            foreach ($group as $item) {
                                $gross_weight += floatval($item['weight']);
                                if (!empty($item['huid_code']) && $item['huid_code'] !== '0') {
                                    $huid_codes[] = $item['huid_code'];
                                }
                            }
                            $huid_code_combined = implode(',', $huid_codes);
                            if (empty($huid_code_combined)) {
                                $huid_code_combined = '';
                            }
                            // Debug log for verification
                            $debug_log = "PAIR_ID: $pair_id | HUID_CODES: $huid_code_combined | WEIGHT: $gross_weight\n";
                            file_put_contents('debug_huid_submit.log', $debug_log, FILE_APPEND);
                            
                            $job_no = $group[0]['job_no'];
                            $item_stmt = $conn2->prepare("SELECT item FROM job_cards WHERE job_no = ? LIMIT 1");
                            $item_stmt->bind_param("s", $job_no);
                            $item_stmt->execute();
                            $item_result = $item_stmt->get_result();
                            $item_row = $item_result->fetch_assoc();
                            $item_stmt->close();
                            
                            $item_name = $item_row ? $item_row['item'] : 'Unknown';
                            
                            $purity_percentage = '0.00';
                            $purity = $group[0]['purity'] ?? '';
                            if (strpos($purity, '22K916') !== false) {
                                $purity_percentage = '92.00';
                            } elseif (strpos($purity, '18K750') !== false) {
                                $purity_percentage = '76.00';
                            } elseif (strpos($purity, '20K833') !== false) {
                                $purity_percentage = '84.00';
                            } elseif (strpos($purity, '14K585') !== false) {
                                $purity_percentage = '59.00';
                            }
                            
                            $insert_stmt = $conn->prepare("
                                INSERT INTO jewellery_items 
                                (firm_id, product_id, jewelry_type, product_name, material_type, purity, 
                                huid_code, gross_weight, net_weight, status, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, 'Gold', ?, ?, ?, ?, 'Available', NOW(), NOW())
                            ");
                            
                            $net_weight = $gross_weight;
                            
                            $insert_stmt->bind_param(
                                'issssddd',
                                $firm_id,
                                $pair_id,
                                $item_name,
                                $item_name,
                                $purity_percentage,
                                $huid_code_combined,
                                $gross_weight,
                                $net_weight
                            );
                            
                            if ($insert_stmt->execute()) {
                                $success++;
                            } else {
                                $errors++;
                            }
                            $insert_stmt->close();
                        }
                        
                        if ($success > 0) {
                            $response = [
                                'status' => 'success', 
                                'message' => "Submitted $success items to Jewel Entry successfully" . ($errors > 0 ? " with $errors errors" : "")
                            ];
                        } else {
                            $response['message'] = 'No items were submitted. Please check if items have pair IDs.';
                        }
                    }
                }
            }
        }
        
        // Export to Excel
        elseif ($_POST['action'] === 'export_excel') {
            $request_no = $_POST['request_no'] ?? '';
            
            if (!empty($request_no)) {
                $_SESSION['export_excel'] = $request_no;
                $response = ['status' => 'success', 'message' => 'Preparing Excel export...'];
            } else {
                $response['message'] = 'Request number is required for export.';
            }
        }
        
        // Upload image for product
        elseif ($_POST['action'] === 'upload_image') {
            $pair_id = $_POST['pair_id'] ?? '';
            
            if (!empty($pair_id) && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
                $upload_dir = 'uploads/products/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = $pair_id . '_' . time() . '_' . basename($_FILES['product_image']['name']);
                $target_file = $upload_dir . $file_name;
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($_FILES['product_image']['type'], $allowed_types)) {
                    $response['message'] = 'Only JPG, PNG, GIF, and WEBP files are allowed.';
                } else {
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                        $stmt = $conn->prepare("UPDATE jewellery_items SET image_path = ? WHERE product_id = ?");
                        $stmt->bind_param('ss', $target_file, $pair_id);
                        
                        if ($stmt->execute()) {
                            $response = [
                                'status' => 'success', 
                                'message' => 'Image uploaded successfully.',
                                'image_path' => $target_file
                            ];
                        } else {
                            $response['message'] = 'Failed to update database with image path.';
                        }
                        $stmt->close();
                    } else {
                        $response['message'] = 'Failed to upload image.';
                    }
                }
            } else {
                $response['message'] = 'Invalid image or pair ID.';
            }
        }
        
        // Check login status
        elseif ($_POST['action'] === 'check_login') {
            if (isset($_SESSION['firm_user_id'])) {
                $user_id = $_SESSION['firm_user_id'];
                $stmt = $conn->prepare("SELECT u.*, f.FirmName FROM Firm_Users u JOIN Firm f ON u.FirmID = f.id WHERE u.id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
                
                if ($user) {
                    $response = [
                        'status' => 'success',
                        'logged_in' => true,
                        'user' => [
                            'id' => $user['id'],
                            'name' => $user['Name'],
                            'firm_id' => $user['FirmID'],
                            'firm_name' => $user['FirmName'],
                            'role' => $user['Role']
                        ]
                    ];
                } else {
                    $response = ['status' => 'error', 'logged_in' => false, 'message' => 'User not found.'];
                }
            } else {
                $response = ['status' => 'success', 'logged_in' => false];
            }
        }
        
        // Login action
        elseif ($_POST['action'] === 'login') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (!empty($username) && !empty($password)) {
                $stmt = $conn->prepare("SELECT * FROM Firm_Users WHERE Username = ? AND Status = 'Active'");
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
                
                if ($user && password_verify($password, $user['Password'])) {
                    $_SESSION['firm_user_id'] = $user['id'];
                    $_SESSION['firm_id'] = $user['FirmID'];
                    $_SESSION['username'] = $user['Username'];
                    $_SESSION['user_name'] = $user['Name'];
                    
                    $response = [
                        'status' => 'success',
                        'message' => 'Login successful.',
                        'user' => [
                            'id' => $user['id'],
                            'name' => $user['Name'],
                            'firm_id' => $user['FirmID']
                        ]
                    ];
                } else {
                    $response = ['status' => 'error', 'message' => 'Invalid username or password.'];
                }
            } else {
                $response['message'] = 'Username and password are required.';
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle Excel export if requested
if (isset($_SESSION['export_excel'])) {
    $request_no = $_SESSION['export_excel'];
    unset($_SESSION['export_excel']);
    
    $stmt = $conn2->prepare("SELECT h.*, j.item FROM huid_data h 
                            LEFT JOIN job_cards j ON h.job_no = j.job_no 
                            WHERE h.request_no = ? ORDER BY h.id DESC");
    $stmt->bind_param("s", $request_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $export_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (!empty($export_data)) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="huid_data_' . $request_no . '_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Request No</th>';
        echo '<th>Job No</th>';
        echo '<th>Item Name</th>';
        echo '<th>HUID Code</th>';
        echo '<th>Weight (g)</th>';
        echo '<th>Purity</th>';
        echo '<th>Pair ID</th>';
        echo '</tr>';
        
        foreach ($export_data as $row) {
            echo '<tr>';
            echo '<td>' . $row['id'] . '</td>';
            echo '<td>' . $row['request_no'] . '</td>';
            echo '<td>' . $row['job_no'] . '</td>';
            echo '<td>' . ($row['item'] ?? 'N/A') . '</td>';
            echo '<td>' . $row['huid_code'] . '</td>';
            echo '<td>' . $row['weight'] . '</td>';
            echo '<td>' . $row['purity'] . '</td>';
            echo '<td>' . ($row['pair_id'] ?? '') . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        exit;
    }
}

$request_no = $_GET['request_no'] ?? '';
$data = [];
$total_records = 0;
$total_weight = 0;
$purity = '';

if ($request_no !== '') {
    $stmt = $conn2->prepare("SELECT * FROM huid_data WHERE request_no = ? ORDER BY id DESC");
    $stmt->bind_param("s", $request_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $rawData = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $total_records = count($rawData);

    foreach ($rawData as $row) {
        $job_no = $row['job_no'];
        $item_stmt = $conn2->prepare("SELECT item FROM job_cards WHERE job_no = ? LIMIT 1");
        $item_stmt->bind_param("s", $job_no);
        $item_stmt->execute();
        $item_result = $item_stmt->get_result();
        $item_row = $item_result->fetch_assoc();
        $item_stmt->close();

        $row['item_name'] = $item_row ? $item_row['item'] : 'N/A';
        $data[] = $row;
        
        if (is_numeric($row['weight'])) {
            $total_weight += floatval($row['weight']);
        }
        
        if (empty($purity) && !empty($row['purity'])) {
            $purity = $row['purity'];
        }
    }
}

// Get unique item names for filtering
$unique_item_names = array_unique(array_column($data, 'item_name'));
sort($unique_item_names);

// Check if user is logged in
$is_logged_in = isset($_SESSION['firm_user_id']);
$user_name = $_SESSION['user_name'] ?? 'Guest';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced HUID Manager - Compact</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#6366f1',
                        'secondary': '#f59e0b',
                        'success': '#10b981',
                        'danger': '#ef4444',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        .compact-table { font-size: 0.8rem; }
        .compact-table td, .compact-table th { padding: 0.5rem 0.25rem; }
        .filter-highlight { background-color: #fef3c7 !important; border-left: 3px solid #f59e0b; }
        .spinner { border: 2px solid #f3f3f3; border-top: 2px solid #6366f1; border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .sticky-header { position: sticky; top: 0; z-index: 20; }
        .table-container { max-height: calc(100vh - 200px); overflow-y: auto; }
        .compact-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.5rem; overflow-x: auto; white-space: nowrap; }
        @media (max-width: 640px) {
            .compact-stats { display: flex; flex-wrap: nowrap; overflow-x: auto; gap: 0.5rem; }
            .stat-card { min-width: 120px; flex: 0 0 auto; }
        }
        .stat-card { padding: 0.75rem; border-radius: 0.5rem; text-align: center; }
        .inline-filter { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.25rem; }
        .filter-input { border: none; background: transparent; outline: none; font-size: 0.75rem; width: 100%; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; border-radius: 0.25rem; }
        .match-group { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Compact Header -->
    <div class="bg-primary text-white p-3 sticky-header shadow-md">
        <div class="flex justify-between items-center mb-2">
            <h1 class="text-lg font-bold flex items-center gap-2">
                <i class="fas fa-gem text-yellow-300"></i>
                HUID Manager
            </h1>
            <div class="text-xs">
                <?php if ($is_logged_in): ?>
                    <span class="bg-green-500 px-2 py-1 rounded text-xs">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($user_name) ?>
                    </span>
                <?php else: ?>
                    <button id="loginBtn" class="bg-gradient-to-r from-yellow-400 to-orange-400 text-gray-800 px-3 py-1 rounded text-xs hover:from-yellow-300 hover:to-orange-300 transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105 font-medium">
                        <i class="fas fa-sign-in-alt mr-1"></i> Login
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Compact Search -->
        <form method="get" class="flex gap-1">
            <input type="text" name="request_no" 
                class="flex-1 px-2 py-1 rounded text-gray-800 text-sm" 
                placeholder="Request No" 
                value="<?= htmlspecialchars($request_no) ?>" required>
            <button type="submit" class="bg-yellow-400 text-gray-800 px-3 py-1 rounded hover:bg-yellow-300">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
    
    <?php if (!$is_logged_in && $request_no !== ''): ?>
        <!-- Promotional Banner for Non-logged Users -->
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-3 text-center">
            <div class="flex items-center justify-center gap-2 text-sm">
                <i class="fas fa-gem text-yellow-300"></i>
                <span>Unlock full features with a <strong>FREE Jewel Entry account</strong></span>
                <button onclick="openLoginModal()" class="bg-white text-blue-600 px-3 py-1 rounded text-xs font-medium hover:bg-gray-100 transition-colors ml-2">
                    Get Started
                </button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($request_no !== ''): ?>
        <div class="p-2 space-y-3">
            <!-- Compact Stats -->
            <div class="compact-stats">
                <div class="stat-card bg-blue-50 border border-blue-200">
                    <div class="text-blue-600 text-xs font-medium">Request</div>
                    <div class="text-blue-800 font-bold text-sm"><?= htmlspecialchars($request_no) ?></div>
                </div>
                <div class="stat-card bg-purple-50 border border-purple-200">
                    <div class="text-purple-600 text-xs font-medium">Purity</div>
                    <div class="text-purple-800 font-bold text-sm"><?= htmlspecialchars($purity) ?></div>
                </div>
                <div class="stat-card bg-amber-50 border border-amber-200">
                    <div class="text-amber-600 text-xs font-medium">Items</div>
                    <div class="text-amber-800 font-bold text-sm"><?= $total_records ?></div>
                </div>
                <div class="stat-card bg-green-50 border border-green-200">
                    <div class="text-green-600 text-xs font-medium">Weight</div>
                    <div class="text-green-800 font-bold text-sm"><?= number_format($total_weight, 2) ?>g</div>
                </div>
                <div class="stat-card bg-indigo-50 border border-indigo-200">
                    <div class="text-indigo-600 text-xs font-medium">Paired</div>
                    <div class="text-indigo-800 font-bold text-sm" id="pairedCount">
                        <?php 
                        $paired = 0;
                        foreach ($data as $item) {
                            if (!empty($item['pair_id'])) $paired++;
                        }
                        echo $paired;
                        ?>
                    </div>
                </div>
                <div class="stat-card bg-red-50 border border-red-200">
                    <div class="text-red-600 text-xs font-medium">Views</div>
                    <div class="text-red-800 font-bold text-sm" id="viewCount">
                        <i class="fas fa-eye text-xs"></i> <span id="viewCountValue">-</span>
                    </div>
                </div>
            </div>
            
            <!-- Compact Action Buttons -->
            <div class="flex flex-wrap gap-1">
                <button id="generateAllIdsBtn" class="btn-sm bg-secondary text-white hover:bg-amber-600">
                    <i class="fas fa-tags"></i> Gen IDs
                </button>
                <button id="pairButton" class="btn-sm bg-primary text-white opacity-50 cursor-not-allowed" disabled>
                    <i class="fas fa-link"></i> Pair
                </button>
                <button id="resetIdsBtn" class="btn-sm bg-danger text-white hover:bg-red-600">
                    <i class="fas fa-sync"></i> Reset
                </button>
                <button id="printTagsBtn" class="btn-sm bg-cyan-600 text-white hover:bg-cyan-700">
                    <i class="fas fa-print"></i> Print
                </button>
                <button id="submitJewelBtn" class="btn-sm bg-success text-white hover:bg-green-600">
                    <i class="fas fa-check"></i> Submit
                </button>
                <button id="exportExcelBtn" class="btn-sm bg-green-600 text-white hover:bg-green-700">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button id="testViewCounterBtn" class="btn-sm bg-purple-600 text-white hover:bg-purple-700">
                    <i class="fas fa-eye"></i> Test View
                </button>
                <?php if (!$is_logged_in): ?>
                <button id="manualLoginBtn" class="btn-sm bg-orange-500 text-white hover:bg-orange-600">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Selection Info -->
            <div id="selectionInfo" class="hidden bg-blue-50 p-2 rounded text-xs border border-blue-200">
                <span id="selectedCount">0</span> selected | 
                <button id="selectAllBtn" class="text-blue-600 hover:underline">All</button> | 
                <button id="clearSelectionBtn" class="text-red-600 hover:underline">Clear</button>
            </div>
            
            <!-- Enhanced Weight Finder -->
            <div class="bg-white p-3 rounded border border-gray-200">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-search text-primary"></i>
                    <h3 class="font-semibold text-sm">Smart Weight Finder</h3>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
                    <select id="filterItemType" class="px-2 py-1 border rounded text-xs">
                        <option value="">All Items</option>
                        <?php foreach ($unique_item_names as $name): ?>
                            <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" id="targetWeight" step="0.001" placeholder="Target Weight" 
                        class="px-2 py-1 border rounded text-xs">
                    <select id="combinationCount" class="px-2 py-1 border rounded text-xs">
                        <option value="1">Single</option>
                        <option value="2">Pair (2)</option>
                        <option value="3">Triple (3)</option>
                        <option value="4">Quad (4)</option>
                    </select>
                    <button id="findCombinationsBtn" class="btn-sm bg-primary text-white">
                        <i class="fas fa-search"></i> Find
                    </button>
                    <button id="clearFilterBtn" class="btn-sm bg-gray-500 text-white">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
                <div id="filterResults" class="hidden mt-2 p-2 bg-yellow-50 rounded border border-yellow-200 text-xs">
                    <span id="filterResultText"></span>
                </div>
            </div>
            
            <!-- Compact Table -->
            <div class="bg-white rounded border border-gray-200 overflow-hidden">
                <div class="table-container">
                    <table class="w-full compact-table">
                        <thead class="bg-primary text-white sticky-header">
                            <tr>
                                <th class="w-8">
                                    <input type="checkbox" id="selectAll" class="w-3 h-3">
                                </th>
                                <th>ID</th>
                                <th>
                                    <div class="inline-filter">
                                        <input type="text" id="itemFilter" placeholder="Item..." class="filter-input">
                                    </div>
                                </th>
                                <th>HUID</th>
                                <th>
                                    <div class="inline-filter">
                                        <input type="number" id="weightFilter" step="0.001" placeholder="Weight..." class="filter-input">
                                    </div>
                                </th>
                                <th>PAIR ID</th>
                                <th>STATUS</th>
                                <th>ACT</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        <?php foreach ($data as $row): ?>
                            <tr data-id="<?= $row['id'] ?>"
                                data-item="<?= htmlspecialchars($row['item_name']) ?>" 
                                data-weight="<?= htmlspecialchars($row['weight']) ?>"
                                data-huid="<?= htmlspecialchars($row['huid_code']) ?>"
                                data-pair-id="<?= htmlspecialchars($row['pair_id'] ?? '') ?>"
                                class="hover:bg-gray-50 transition-colors">
                                <td>
                                    <input type="checkbox" class="item-checkbox w-3 h-3" value="<?= $row['id'] ?>">
                                </td>
                                <td class="font-mono text-gray-600"><?= $row['id'] ?></td>
                                <td class="group">
                                    <div class="flex items-center gap-1">
                                        <span class="item-name-value font-medium"><?= htmlspecialchars($row['item_name']) ?></span>
                                        <button class="edit-item-btn text-gray-400 hover:text-primary opacity-0 group-hover:opacity-100">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="font-mono text-xs bg-gray-50"><?= htmlspecialchars($row['huid_code']) ?></td>
                                <td class="group">
                                    <div class="flex items-center gap-1">
                                        <span class="weight-value font-semibold text-green-700"><?= htmlspecialchars($row['weight']) ?></span>
                                        <button class="edit-weight-btn text-gray-400 hover:text-primary opacity-0 group-hover:opacity-100">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($row['pair_id'])): ?>
                                        <span class="bg-green-100 text-green-800 px-1 py-0.5 rounded text-xs font-medium">
                                            <?= htmlspecialchars($row['pair_id']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-500 px-1 py-0.5 rounded text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['pair_id'])): ?>
                                        <span class="bg-green-100 text-green-800 px-1 py-0.5 rounded text-xs">
                                            <i class="fas fa-check"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-yellow-100 text-yellow-800 px-1 py-0.5 rounded text-xs">
                                            <i class="fas fa-clock"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['pair_id'])): ?>
                                    <button class="upload-image-btn text-blue-500 hover:text-blue-700" 
                                        data-pair-id="<?= htmlspecialchars($row['pair_id']) ?>" title="Upload">
                                        <i class="fas fa-camera text-xs"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="p-8 text-center">
            <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-search text-2xl text-primary"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Enhanced HUID Manager</h2>
            <p class="text-gray-600 mb-4">Enter a Request Number to start managing your jewelry inventory</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 max-w-lg mx-auto text-sm">
                <div class="bg-blue-50 p-3 rounded">
                    <i class="fas fa-search text-blue-500 text-lg mb-1"></i>
                    <h3 class="font-semibold">Smart Search</h3>
                    <p class="text-xs text-gray-600">Find weight combinations</p>
                </div>
                <div class="bg-green-50 p-3 rounded">
                    <i class="fas fa-link text-green-500 text-lg mb-1"></i>
                    <h3 class="font-semibold">Quick Pairing</h3>
                    <p class="text-xs text-gray-600">Instant item pairing</p>
                </div>
                <div class="bg-purple-50 p-3 rounded">
                    <i class="fas fa-mobile text-purple-500 text-lg mb-1"></i>
                    <h3 class="font-semibold">Mobile First</h3>
                    <p class="text-xs text-gray-600">Optimized for mobile</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Toast Notification -->
    <div id="toast" class="fixed top-4 right-4 p-3 rounded shadow-lg transition-all duration-300 transform translate-x-full opacity-0 z-50 text-sm max-w-sm">
        <div class="flex items-center gap-2">
            <span class="toast-icon"></span>
            <span class="toast-message"></span>
            <button onclick="hideToast()" class="ml-auto text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <!-- Modals (Compact versions) -->
    <!-- Edit Weight Modal -->
    <div id="editWeightModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-lg shadow-xl p-4 max-w-sm w-full">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold">Edit Weight</h3>
                <button class="close-modal text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editWeightForm">
                <input type="hidden" id="weightItemId">
                <div class="mb-3">
                    <label class="block text-sm font-medium mb-1">Weight (g)</label>
                    <input type="number" id="weightValue" step="0.001" 
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-primary">
                </div>
                <div class="flex gap-2">
                    <button type="button" class="close-modal flex-1 px-3 py-2 border rounded hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-primary text-white px-3 py-2 rounded hover:bg-indigo-700">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Item Modal -->
    <div id="editItemModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-lg shadow-xl p-4 max-w-sm w-full">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold">Edit Item</h3>
                <button class="close-modal text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editItemForm">
                <input type="hidden" id="itemId">
                <div class="mb-3">
                    <label class="block text-sm font-medium mb-1">Item Name</label>
                    <input type="text" id="itemName" 
                        class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-primary">
                </div>
                <div class="flex gap-2">
                    <button type="button" class="close-modal flex-1 px-3 py-2 border rounded hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-primary text-white px-3 py-2 rounded hover:bg-indigo-700">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Login Modal -->
   <div id="loginModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="relative bg-gradient-to-br from-orange-500 via-orange-600 to-red-500 p-6 rounded-t-2xl text-white text-center">
            <div class="absolute top-4 right-4">
                <button onclick="closeAllModals()" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors close-modal">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
            
            <div class="absolute top-4 left-4">
                <button onclick="openRegistrationPage()" class="bg-white text-orange-600 px-3 py-1.5 rounded-lg text-sm font-medium hover:bg-gray-50 transition-all duration-200 shadow-md">
                    <i class="fas fa-user-plus mr-1"></i>Create Account
                </button>
            </div>
            
            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mx-auto mb-4 backdrop-blur-sm">
                <i class="fas fa-gem text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-bold mb-2">Welcome Back</h2>
            <p class="text-orange-100 text-sm">Access your jewelry business dashboard</p>
        </div>

        <!-- Login Form -->
        <div class="p-6">
            <form id="loginForm" class="space-y-5">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" id="username" 
                                class="block w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200 bg-gray-50 focus:bg-white"
                                placeholder="Enter your username">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password" 
                                class="block w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all duration-200 bg-gray-50 focus:bg-white"
                                placeholder="Enter your password">
                        </div>
                    </div>
                </div>
                
                <div id="loginError" class="hidden text-red-600 text-sm p-3 bg-red-50 rounded-xl border border-red-200">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span>Invalid credentials. Please try again.</span>
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white py-3 px-6 rounded-xl font-semibold transition-all duration-200 transform hover:scale-[1.02] shadow-lg hover:shadow-xl">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login to Dashboard
                </button>
            </form>
        </div>

        <!-- Quick Features Preview -->
        <div class="px-6 pb-4">
            <div class="bg-gradient-to-r from-gray-50 to-orange-50 rounded-xl p-4 border border-gray-100">
                <h5 class="font-semibold text-gray-800 mb-3 text-center">15 Professional Modules</h5>
                <div class="grid grid-cols-3 gap-2 text-xs">
                    <div class="bg-white p-2 rounded-lg shadow-sm border text-center">
                        <i class="fas fa-warehouse text-blue-600 text-lg mb-1"></i>
                        <p class="font-medium text-gray-700">Inventory</p>
                    </div>
                    <div class="bg-white p-2 rounded-lg shadow-sm border text-center">
                        <i class="fas fa-cash-register text-green-600 text-lg mb-1"></i>
                        <p class="font-medium text-gray-700">Sales</p>
                    </div>
                    <div class="bg-white p-2 rounded-lg shadow-sm border text-center">
                        <i class="fas fa-users text-purple-600 text-lg mb-1"></i>
                        <p class="font-medium text-gray-700">CRM</p>
                    </div>
                    <div class="bg-white p-2 rounded-lg shadow-sm border text-center">
                        <i class="fas fa-coins text-yellow-600 text-lg mb-1"></i>
                        <p class="font-medium text-gray-700">Gold Loans</p>
                    </div>
                    <div class="bg-white p-2 rounded-lg shadow-sm border text-center">
                        <i class="fas fa-ring text-pink-600 text-lg mb-1"></i>
                        <p class="font-medium text-gray-700">Products</p>
                    </div>
                    <div class="bg-white p-2 rounded-lg shadow-sm border text-center">
                        <i class="fas fa-chart-line text-orange-600 text-lg mb-1"></i>
                        <p class="font-medium text-gray-700">Reports</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 pb-6 text-center border-t border-gray-100 pt-4">
            <p class="text-sm text-gray-600 mb-3">Need help? Contact our support team</p>
            <div class="flex justify-center gap-4">
                <a href="mailto:support@jewelentry.com" class="flex items-center gap-2 text-orange-600 hover:text-orange-700 transition-colors font-medium text-sm">
                    <i class="fas fa-envelope"></i>
                    <span>Email</span>
                </a>
                <a href="tel:+919876543210" class="flex items-center gap-2 text-orange-600 hover:text-orange-700 transition-colors font-medium text-sm">
                    <i class="fas fa-phone"></i>
                    <span>Call</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    setupEventListeners();
    setupInlineFilters();
});

function initializeApp() {
    console.log('Enhanced HUID Manager - Compact Version Loaded');
    updateSelectionCount();
    updatePairButtonState();
    
    // Check login status and show welcome message
    checkLoginStatus();
    
    // Initialize modal dismissal tracking
    window.loginModalDismissed = false;
    
    // Increment page view counter (always)
    incrementViewCounter();
}

function incrementViewCounter() {
    console.log('Incrementing page view counter');
    
    const formData = new FormData();
    formData.append('action', 'increment_view');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('View counter response:', data);
        if (data.status === 'success') {
            const viewCountElement = document.getElementById('viewCountValue');
            if (viewCountElement) {
                viewCountElement.textContent = data.view_count;
                console.log('Page view count updated to:', data.view_count);
            } else {
                console.error('View count element not found');
            }
        } else {
            console.error('View counter error:', data.message);
            // Show error in toast
            showToast('View counter error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error incrementing view counter:', error);
        showToast('View counter failed: ' + error.message, 'error');
    });
}

function checkLoginStatus() {
    const formData = new FormData();
    formData.append('action', 'check_login');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success' && data.logged_in) {
            // Show welcome message for logged-in users
            setTimeout(() => {
                showToast(`👋 Welcome back, ${data.user.name}!`, 'success', 3000);
            }, 1000);
        }
    })
    .catch(error => {
        console.error('Error checking login status:', error);
    });
}

function setupEventListeners() {
    // Checkbox handlers
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox:not([style*="display: none"])');
            checkboxes.forEach(checkbox => {
                if (checkbox.closest('tr').style.display !== 'none') {
                    checkbox.checked = selectAllCheckbox.checked;
                }
            });
            updateSelectionCount();
            updatePairButtonState();
        });
    }
    
    // Individual checkboxes
    document.querySelectorAll('.item-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectionCount();
            updatePairButtonState();
        });
    });
    
    // Button handlers
    const buttons = {
        'generateAllIdsBtn': generateAllIds,
        'pairButton': generatePairId,
        'resetIdsBtn': () => resetPairIDs('<?= $request_no ?>'),
        'printTagsBtn': printSelectedTags,
        'submitJewelBtn': submitToJewelEntry,
        'exportExcelBtn': exportToExcel,
        'testViewCounterBtn': () => incrementViewCounter(),
        'loginBtn': openLoginModal,
        'manualLoginBtn': openLoginModal,
        'findCombinationsBtn': findWeightCombinations,
        'clearFilterBtn': clearAllFilters,
        'selectAllBtn': selectAllVisible,
        'clearSelectionBtn': clearSelection
    };
    
    Object.entries(buttons).forEach(([id, handler]) => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('click', handler);
        }
    });
    
    // Modal close handlers
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', closeAllModals);
    });
    
    // Click outside modal to close
    document.getElementById('loginModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAllModals();
        }
    });
    
    // ESC key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
    
    // Form handlers
    const forms = {
        'editWeightForm': handleWeightUpdate,
        'editItemForm': handleItemUpdate,
        'loginForm': handleLogin
    };
    
    Object.entries(forms).forEach(([id, handler]) => {
        const form = document.getElementById(id);
        if (form) {
            form.addEventListener('submit', handler);
        }
    });
    
    // Edit button handlers
    setupEditButtons();
    
    // Auto-filter on weight input
    const targetWeight = document.getElementById('targetWeight');
    if (targetWeight) {
        let timeout;
        targetWeight.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                if (this.value) {
                    findWeightCombinations();
                }
            }, 500);
        });
    }
}

function setupInlineFilters() {
    const itemFilter = document.getElementById('itemFilter');
    const weightFilter = document.getElementById('weightFilter');
    const filterItemType = document.getElementById('filterItemType');
    const combinationCount = document.getElementById('combinationCount');

    if (itemFilter) {
        itemFilter.addEventListener('input', function() {
            filterTable();
        });
    }

    if (weightFilter) {
        let timeout;
        weightFilter.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                filterTable();
            }, 300);
        });
    }

    // Auto-select combination count based on item type
    if (filterItemType) {
        filterItemType.addEventListener('change', function() {
            const value = this.value.toUpperCase();
            if (["EARRING", "TOPS", "JHUMKI"].some(type => value.includes(type))) {
                combinationCount.value = '2'; // Pair
            } else if (["SET", "NECKLACE SET", "PENDENT SET", "NECKLACE", "PENDANT SET"].some(type => value.includes(type))) {
                combinationCount.value = '3'; // Triple
            } else if (["CHURI", "BANGLES", "KARA"].some(type => value.includes(type))) {
                combinationCount.value = '4'; // Quad
            } else {
                combinationCount.value = '1'; // Single
            }
        });
    }
}

function filterTable() {
    const itemFilter = document.getElementById('itemFilter').value.toLowerCase();
    const weightFilter = parseFloat(document.getElementById('weightFilter').value);
    const rows = document.querySelectorAll('tbody tr');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        const item = row.dataset.item.toLowerCase();
        const weight = parseFloat(row.dataset.weight);
        
        let showRow = true;
        
        if (itemFilter && !item.includes(itemFilter)) {
            showRow = false;
        }
        
        if (!isNaN(weightFilter) && Math.abs(weight - weightFilter) > 0.005) {
            showRow = false;
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    showToast(`Showing ${visibleCount} items`, 'info', 2000);
}

// Enhanced weight combination finder
function findWeightCombinations() {
    const itemType = document.getElementById('filterItemType').value;
    const targetWeight = parseFloat(document.getElementById('targetWeight').value);
    const combinationCount = parseInt(document.getElementById('combinationCount').value);
    
    if (!targetWeight) {
        showToast('Please enter target weight', 'warning');
        return;
    }
    
    // Clear previous highlights
    document.querySelectorAll('tr').forEach(row => {
        row.classList.remove('filter-highlight', 'match-group');
    });
    
    // Get candidates
    const candidates = [];
    // Build a map of pair_id counts
    const pairIdCount = {};
    document.querySelectorAll('tbody tr').forEach(row => {
        const pairId = row.dataset.pairId;
        if (pairId && pairId !== '') {
            pairIdCount[pairId] = (pairIdCount[pairId] || 0) + 1;
        }
    });
    document.querySelectorAll('tbody tr').forEach(row => {
        const isPaired = row.dataset.pairId && row.dataset.pairId !== '';
        const matchesItem = !itemType || row.dataset.item === itemType;
        const weight = parseFloat(row.dataset.weight);
        const pairId = row.dataset.pairId;
        // Only include if:
        // - Not paired
        // - Or paired but pair_id is unique (not duplicated)
        let include = false;
        if (!isPaired) {
            include = true;
        } else if (pairId && pairIdCount[pairId] === 1) {
            include = true;
        }
        if (include && matchesItem && !isNaN(weight)) {
            candidates.push({
                row: row,
                weight: weight,
                id: row.dataset.id
            });
        }
    });
    
    if (candidates.length === 0) {
        showToast('No unpaired items found', 'info');
        return;
    }
    
    const tolerance = 0.005;
    const matchingCombinations = [];
    
    // Find combinations based on count
    if (combinationCount === 1) {
        // Single items
        candidates.forEach(candidate => {
            if (Math.abs(candidate.weight - targetWeight) <= tolerance) {
                matchingCombinations.push([candidate]);
            }
        });
    } else if (combinationCount === 2) {
        // Pairs
        for (let i = 0; i < candidates.length; i++) {
            for (let j = i + 1; j < candidates.length; j++) {
                const sum = candidates[i].weight + candidates[j].weight;
                if (Math.abs(sum - targetWeight) <= tolerance) {
                    matchingCombinations.push([candidates[i], candidates[j]]);
                }
            }
        }
    } else if (combinationCount === 3) {
        // Triples
        for (let i = 0; i < candidates.length; i++) {
            for (let j = i + 1; j < candidates.length; j++) {
                for (let k = j + 1; k < candidates.length; k++) {
                    const sum = candidates[i].weight + candidates[j].weight + candidates[k].weight;
                    if (Math.abs(sum - targetWeight) <= tolerance) {
                        matchingCombinations.push([candidates[i], candidates[j], candidates[k]]);
                    }
                }
            }
        }
    } else if (combinationCount === 4) {
        // Quads
        for (let i = 0; i < candidates.length; i++) {
            for (let j = i + 1; j < candidates.length; j++) {
                for (let k = j + 1; k < candidates.length; k++) {
                    for (let l = k + 1; l < candidates.length; l++) {
                        const sum = candidates[i].weight + candidates[j].weight + candidates[k].weight + candidates[l].weight;
                        if (Math.abs(sum - targetWeight) <= tolerance) {
                            matchingCombinations.push([candidates[i], candidates[j], candidates[k], candidates[l]]);
                        }
                    }
                }
            }
        }
    }
    
    // Hide all rows first
    document.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = 'none';
    });
    
    // Show and highlight matches
    let totalMatches = 0;
    const usedIds = new Set();
    
    matchingCombinations.forEach((combination, index) => {
        const isUsed = combination.some(item => usedIds.has(item.id));
        
        if (!isUsed) {
            combination.forEach(item => {
                item.row.style.display = '';
                item.row.classList.add('filter-highlight', 'match-group');
                usedIds.add(item.id);
                totalMatches++;
            });
        }
    });
    
    // Update results
    const filterResults = document.getElementById('filterResults');
    const filterResultText = document.getElementById('filterResultText');
    
    if (totalMatches > 0) {
        filterResults.classList.remove('hidden');
        filterResultText.innerHTML = `
            <i class="fas fa-check-circle text-green-600"></i>
            Found ${matchingCombinations.length} combination(s) with ${totalMatches} items matching ${targetWeight}g
        `;
        showToast(`Found ${matchingCombinations.length} combinations`, 'success');
    } else {
        filterResults.classList.remove('hidden');
        filterResultText.innerHTML = `
            <i class="fas fa-exclamation-circle text-yellow-600"></i>
            No combinations found for ${targetWeight}g with ${combinationCount} item(s)
        `;
        showToast('No matching combinations found', 'info');
        
        // Show all items if no matches
        document.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = '';
        });
    }
}

function clearAllFilters() {
    // Clear all filter inputs
    document.getElementById('filterItemType').value = '';
    document.getElementById('targetWeight').value = '';
    document.getElementById('combinationCount').value = '1';
    document.getElementById('itemFilter').value = '';
    document.getElementById('weightFilter').value = '';
    
    // Show all rows and remove highlights
    document.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = '';
        row.classList.remove('filter-highlight', 'match-group');
    });
    
    // Hide results
    document.getElementById('filterResults').classList.add('hidden');
    
    showToast('All filters cleared', 'info');
}

function updateSelectionCount() {
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    const selectionInfo = document.getElementById('selectionInfo');
    const selectedCount = document.getElementById('selectedCount');
    
    if (selectedCount) {
        selectedCount.textContent = checkedBoxes.length;
    }
    
    if (checkedBoxes.length > 0) {
        selectionInfo.classList.remove('hidden');
    } else {
        selectionInfo.classList.add('hidden');
    }
}

function updatePairButtonState() {
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    const pairButton = document.getElementById('pairButton');
    
    if (checkedBoxes.length >= 2) {
        pairButton.classList.remove('opacity-50', 'cursor-not-allowed');
        pairButton.disabled = false;
    } else {
        pairButton.classList.add('opacity-50', 'cursor-not-allowed');
        pairButton.disabled = true;
    }
}

function selectAllVisible() {
    document.querySelectorAll('.item-checkbox').forEach(checkbox => {
        if (checkbox.closest('tr').style.display !== 'none') {
            checkbox.checked = true;
        }
    });
    updateSelectionCount();
    updatePairButtonState();
}

function clearSelection() {
    document.querySelectorAll('.item-checkbox').forEach(cb => cb.checked = false);
    updateSelectionCount();
    updatePairButtonState();
}

// Core functionality
function generateAllIds() {
    const requestNo = '<?= $request_no ?>';
    if (!requestNo) {
        showToast('Request number required', 'error');
        return;
    }
    
    const btn = document.getElementById('generateAllIdsBtn');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="spinner mr-1"></div>Gen...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'generate_all_ids');
    formData.append('request_no', requestNo);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalContent;
        btn.disabled = false;
        
        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to generate IDs', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = originalContent;
        btn.disabled = false;
        showToast('Error occurred', 'error');
    });
}

function generatePairId() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length < 2) {
        showToast('Select at least 2 items', 'error');
        return;
    }

    const ids = Array.from(checkboxes).map(cb => cb.value);
    const firstRow = checkboxes[0].closest('tr');
    const huidCode = firstRow.getAttribute('data-huid');
    const pairId = huidCode;

    const btn = document.getElementById('pairButton');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="spinner mr-1"></div>Pair...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'save_pair_id');
    formData.append('pair_id', pairId);
    ids.forEach(id => formData.append('ids[]', id));

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalContent;
        btn.disabled = false;
        
        if (data.status === 'success') {
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                row.setAttribute('data-pair-id', pairId);
                
                // Update pair ID cell
                const pairIdCell = row.querySelector('td:nth-child(6)');
                if (pairIdCell) {
                    pairIdCell.innerHTML = `<span class="bg-green-100 text-green-800 px-1 py-0.5 rounded text-xs font-medium">${pairId}</span>`;
                }
                
                // Update status cell
                const statusCell = row.querySelector('td:nth-child(7)');
                if (statusCell) {
                    statusCell.innerHTML = '<span class="bg-green-100 text-green-800 px-1 py-0.5 rounded text-xs"><i class="fas fa-check"></i></span>';
                }
                
                // Add upload button
                const actionsCell = row.querySelector('td:nth-child(8)');
                if (actionsCell && !actionsCell.querySelector('.upload-image-btn')) {
                    const uploadBtn = document.createElement('button');
                    uploadBtn.className = 'upload-image-btn text-blue-500 hover:text-blue-700';
                    uploadBtn.setAttribute('data-pair-id', pairId);
                    uploadBtn.title = 'Upload';
                    uploadBtn.innerHTML = '<i class="fas fa-camera text-xs"></i>';
                    actionsCell.appendChild(uploadBtn);
                }
                
                checkbox.checked = false;
            });
            
            showToast(`Paired ${checkboxes.length} items: ${pairId}`, 'success');
            updatePairButtonState();
            updateSelectionCount();
            
            // Update paired count
            const pairedCount = document.getElementById('pairedCount');
            if (pairedCount) {
                const currentCount = parseInt(pairedCount.textContent);
                pairedCount.textContent = currentCount + checkboxes.length;
            }
        } else {
            showToast(data.message || 'Failed to pair items', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = originalContent;
        btn.disabled = false;
        showToast('Error occurred', 'error');
    });
}

function resetPairIDs(requestNo) {
    if (!requestNo) {
        showToast('Request number required', 'error');
        return;
    }
    
    if (!confirm('Reset all pair IDs? This cannot be undone.')) {
        return;
    }
    
    const btn = document.getElementById('resetIdsBtn');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="spinner mr-1"></div>Reset...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'reset_pair_ids');
    formData.append('request_no', requestNo);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalContent;
        btn.disabled = false;
        
        if (data.status === 'success') {
            showToast('All pair IDs reset', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showToast(data.message || 'Failed to reset', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = originalContent;
        btn.disabled = false;
        showToast('Error occurred', 'error');
    });
}

function printSelectedTags() {
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    
    if (checkedBoxes.length === 0) {
        showToast('Select items to print', 'warning');
        return;
    }
    
    const ids = Array.from(checkedBoxes).map(cb => cb.value);
    
    const formData = new FormData();
    formData.append('action', 'save_selected_ids');
    ids.forEach(id => formData.append('ids[]', id));
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            window.open('print_tag.php?request_no=<?= $request_no ?>&use_pair_id=1', '_blank');
            showToast(`Printing ${data.count} tags`, 'success');
        } else {
            showToast(data.message || 'Failed to prepare tags', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error occurred', 'error');
    });
}

function submitToJewelEntry() {
    const requestNo = '<?= $request_no ?>';
    if (!requestNo) {
        showToast('Request number required', 'error');
        return;
    }
    
    const rows = document.querySelectorAll('tr[data-pair-id]');
    let hasPairIds = false;
    
    rows.forEach(row => {
        if (row.getAttribute('data-pair-id') !== '') {
            hasPairIds = true;
        }
    });
    
    if (!hasPairIds) {
        showToast('Generate IDs first', 'warning');
        return;
    }
    
    const btn = document.getElementById('submitJewelBtn');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="spinner mr-1"></div>Submit...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'submit_to_jewel_entry');
    formData.append('request_no', requestNo);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalContent;
        btn.disabled = false;
        
        if (data.status === 'success') {
            showToast('Submitted to Jewel Entry successfully', 'success');
        } else if (data.status === 'login_required') {
            showToast('🔐 Login required to submit to Jewel Entry', 'warning');
            // Only auto-open modal if user hasn't dismissed it before
            if (!window.loginModalDismissed) {
                setTimeout(() => {
                    openLoginModal();
                }, 1000);
            }
        } else {
            showToast(data.message || 'Failed to submit', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = originalContent;
        btn.disabled = false;
        showToast('Error occurred', 'error');
    });
}

function exportToExcel() {
    const requestNo = '<?= $request_no ?>';
    if (!requestNo) {
        showToast('Request number required', 'error');
        return;
    }
    
    const btn = document.getElementById('exportExcelBtn');
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<div class="spinner mr-1"></div>Export...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'export_excel');
    formData.append('request_no', requestNo);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalContent;
        btn.disabled = false;
        
        if (data.status === 'success') {
            showToast('Excel export prepared', 'success');
            window.location.href = window.location.pathname + '?request_no=' + requestNo + '&export=1';
        } else {
            showToast(data.message || 'Failed to export', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.innerHTML = originalContent;
        btn.disabled = false;
        showToast('Error occurred', 'error');
    });
}

function openLoginModal() {
    const loginModal = document.getElementById('loginModal');
    loginModal.classList.remove('hidden');
    
    // Clear form
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('loginError').classList.add('hidden');
}

function openRegistrationPage() {
    // Close login modal
    closeAllModals();
    
    // Show toast with registration info
    showToast('Redirecting to registration page...', 'info');
    
    // You can replace this with your actual registration page URL
    setTimeout(() => {
        window.open('register.php', '_blank');
    }, 1000);
}

function closeAllModals() {
    const modals = ['editWeightModal', 'editItemModal', 'loginModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            // Track if login modal was dismissed
            if (modalId === 'loginModal') {
                window.loginModalDismissed = true;
            }
        }
    });
}

function setupEditButtons() {
    const editWeightBtns = document.querySelectorAll('.edit-weight-btn');
    const editItemBtns = document.querySelectorAll('.edit-item-btn');
    
    editWeightBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            const currentWeight = row.querySelector('.weight-value').textContent;
            
            document.getElementById('weightItemId').value = id;
            document.getElementById('weightValue').value = currentWeight;
            document.getElementById('editWeightModal').classList.remove('hidden');
        });
    });
    
    editItemBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            const currentItemName = row.querySelector('.item-name-value').textContent;
            
            document.getElementById('itemId').value = id;
            document.getElementById('itemName').value = currentItemName;
            document.getElementById('editItemModal').classList.remove('hidden');
        });
    });
}

function handleWeightUpdate(e) {
    e.preventDefault();
    
    const id = document.getElementById('weightItemId').value;
    const weight = document.getElementById('weightValue').value;
    
    if (!id || !weight) {
        showToast('ID and weight required', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_weight');
    formData.append('id', id);
    formData.append('weight', weight);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                row.querySelector('.weight-value').textContent = data.weight;
                row.dataset.weight = data.weight;
            }
            
            showToast('Weight updated', 'success');
            document.getElementById('editWeightModal').classList.add('hidden');
        } else {
            showToast(data.message || 'Failed to update', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error occurred', 'error');
    });
}

function handleItemUpdate(e) {
    e.preventDefault();
    
    const id = document.getElementById('itemId').value;
    const name = document.getElementById('itemName').value;
    
    if (!id || !name) {
        showToast('ID and name required', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_item');
    formData.append('id', id);
    formData.append('item_name', name);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                row.querySelector('.item-name-value').textContent = data.item_name;
                row.setAttribute('data-item', data.item_name);
            }
            
            showToast('Item updated', 'success');
            document.getElementById('editItemModal').classList.add('hidden');
        } else {
            showToast(data.message || 'Failed to update', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error occurred', 'error');
    });
}

function handleLogin(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const loginError = document.getElementById('loginError');
    
    if (!username || !password) {
        loginError.textContent = 'Username and password required';
        loginError.classList.remove('hidden');
        return;
    }
    
    loginError.classList.add('hidden');
    
    const formData = new FormData();
    formData.append('action', 'login');
    formData.append('username', username);
    formData.append('password', password);
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<div class="spinner mr-1"></div>Login...';
    submitBtn.disabled = true;
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        
        if (data.status === 'success') {
            showToast('Login successful', 'success');
            document.getElementById('loginModal').classList.add('hidden');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            loginError.textContent = data.message || 'Login failed';
            loginError.classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
        loginError.textContent = 'Login error occurred';
        loginError.classList.remove('hidden');
    });
}

function showToast(message, type = 'info', duration = 3000) {
    const toast = document.getElementById('toast');
    const toastMessage = document.querySelector('.toast-message');
    const toastIcon = document.querySelector('.toast-icon');
    
    toastMessage.textContent = message;
    
    // Clear previous classes
    toast.className = 'fixed top-4 right-4 p-3 rounded shadow-lg transition-all duration-300 transform translate-x-full opacity-0 z-50 text-sm max-w-sm';
    
    // Set type-specific styling
    const styles = {
        success: {
            bg: 'bg-green-100 border border-green-200 text-green-800',
            icon: 'fas fa-check-circle text-green-600'
        },
        error: {
            bg: 'bg-red-100 border border-red-200 text-red-800',
            icon: 'fas fa-exclamation-circle text-red-600'
        },
        warning: {
            bg: 'bg-yellow-100 border border-yellow-200 text-yellow-800',
            icon: 'fas fa-exclamation-triangle text-yellow-600'
        },
        info: {
            bg: 'bg-blue-100 border border-blue-200 text-blue-800',
            icon: 'fas fa-info-circle text-blue-600'
        }
    };
    
    const style = styles[type] || styles.info;
    toast.classList.add(...style.bg.split(' '));
    toastIcon.className = `toast-icon ${style.icon}`;
    
    // Show toast
    setTimeout(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
    }, 100);
    
    // Hide toast after duration
    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
    }, duration);
}

function hideToast() {
    const toast = document.getElementById('toast');
    toast.classList.add('translate-x-full', 'opacity-0');
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'a' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        selectAllVisible();
    }
});

console.log('Enhanced HUID Manager - Compact Version Ready!');
</script>
</body>
</html>