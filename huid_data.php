<?php
// Start session but don't require login for viewing
session_start();
require 'config.php'; // Your main DB
require_once 'hallmark.php'; // For huid_data table
date_default_timezone_set('Asia/Kolkata');

// 🔄 Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['status' => 'error', 'message' => 'Invalid input.'];

    if (isset($_POST['action'])) {
        // Save Pair ID actionup
        if ($_POST['action'] === 'save_pair_id') {
            $ids = $_POST['ids'] ?? [];
            $pair_id = $_POST['pair_id'] ?? '';
            if (!empty($ids) && $pair_id !== '') {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $types = str_repeat('i', count($ids));
                $stmt = $conn2->prepare("UPDATE huid_data SET pair_id = ? WHERE id IN ($in)");
                $params = array_merge([$pair_id], $ids);
                $stmt->bind_param('s' . $types, ...$params);
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Pair ID updated successfully.'];
                } else {
                    $response['message'] = $stmt->error;
                }
                $stmt->close();
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
                $stmt = $conn2->prepare("SELECT id, huid_code, job_no FROM huid_data WHERE request_no = ? AND (pair_id IS NULL OR pair_id = '')");
                $stmt->bind_param('s', $request_no);
                $stmt->execute();
                $result = $stmt->get_result();
                $items = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                $success = 0;
                $errors = 0;
                
                foreach ($items as $item) {
                    // Get item name
                    $item_stmt = $conn2->prepare("SELECT item FROM job_cards WHERE job_no = ? LIMIT 1");
                    $item_stmt->bind_param("s", $item['job_no']);
                    $item_stmt->execute();
                    $item_result = $item_stmt->get_result();
                    $item_row = $item_result->fetch_assoc();
                    $item_stmt->close();
                    
                    $item_name = $item_row ? $item_row['item'] : 'ITEM';
                    
                    // Create single ID format: [FIRST_LETTER]-[HUID_CODE]
                    $firstLetter = $item_name ? strtoupper(substr(trim($item_name), 0, 1)) : 'X';
                    $singleId = $firstLetter . '-' . $item['huid_code'];
                    
                    // Update pair_id
                    $update_stmt = $conn2->prepare("UPDATE huid_data SET pair_id = ? WHERE id = ?");
                    $update_stmt->bind_param('si', $singleId, $item['id']);
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

        // 🆕 Update weight action
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

        // 🆕 Update item name action - FIXED to only update specific item
        elseif ($_POST['action'] === 'update_item') {
            $id = $_POST['id'] ?? 0;
            $item_name = $_POST['item_name'] ?? '';
            
            if (!empty($id) && !empty($item_name)) {
                // First, update the specific item in huid_data
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
        
        // 🆕 Save selected IDs for printing
        elseif ($_POST['action'] === 'save_selected_ids') {
            $ids = $_POST['ids'] ?? [];
            if (!empty($ids)) {
                $_SESSION['print_ids'] = $ids;
                $response = ['status' => 'success', 'message' => 'Selected IDs saved for printing.', 'count' => count($ids)];
            } else {
                $response['message'] = 'No IDs selected.';
            }
        }
        
        // 🆕 Submit to Jewel Entry - UPDATED to include net_weight
        elseif ($_POST['action'] === 'submit_to_jewel_entry') {
            // Check if user is logged in
            if (!isset($_SESSION['firm_user_id'])) {
                $response = ['status' => 'login_required', 'message' => 'Please login to submit to Jewel Entry.'];
            } else {
                $request_no = $_POST['request_no'] ?? '';
                
                if (!empty($request_no)) {
                    // Get firm details from logged in user
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
                        
                        // Get all items with pair_id
                        $stmt = $conn2->prepare("SELECT * FROM huid_data WHERE request_no = ? AND pair_id IS NOT NULL");
                        $stmt->bind_param('s', $request_no);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $items = $result->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                        
                        // Group items by pair_id
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
                        
                        // Process each group
                        foreach ($grouped_items as $pair_id => $group) {
                            // Get item name from first item
                            $job_no = $group[0]['job_no'];
                            $item_stmt = $conn2->prepare("SELECT item FROM job_cards WHERE job_no = ? LIMIT 1");
                            $item_stmt->bind_param("s", $job_no);
                            $item_stmt->execute();
                            $item_result = $item_stmt->get_result();
                            $item_row = $item_result->fetch_assoc();
                            $item_stmt->close();
                            
                            $item_name = $item_row ? $item_row['item'] : 'Unknown';
                            
                            // Calculate gross weight
                            $gross_weight = 0;
                            $huid_codes = [];
                            foreach ($group as $item) {
                                $gross_weight += floatval($item['weight']);
                                $huid_codes[] = $item['huid_code'];
                            }
                            
                            // Combine HUID codes
                            $huid_code_combined = implode(',', $huid_codes);
                            
                            // Get purity percentage
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
                            
                            // Insert into jewellery_items - UPDATED to include net_weight
                            $insert_stmt = $conn->prepare("
                                INSERT INTO jewellery_items 
                                (firm_id, product_id, jewelry_type, product_name, material_type, purity, 
                                huid_code, gross_weight, net_weight, status, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, 'Gold', ?, ?, ?, ?, 'Pending', NOW(), NOW())
                            ");
                            
                            $net_weight = $gross_weight; // Set net_weight same as gross_weight initially
                            
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
        
        // 🆕 Export to Excel
        elseif ($_POST['action'] === 'export_excel') {
            $request_no = $_POST['request_no'] ?? '';
            
            if (!empty($request_no)) {
                // Set session variable to trigger Excel export on page reload
                $_SESSION['export_excel'] = $request_no;
                $response = ['status' => 'success', 'message' => 'Preparing Excel export...'];
            } else {
                $response['message'] = 'Request number is required for export.';
            }
        }
        
        // 🆕 Upload image for product
        elseif ($_POST['action'] === 'upload_image') {
            $pair_id = $_POST['pair_id'] ?? '';
            
            if (!empty($pair_id) && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
                $upload_dir = 'uploads/products/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = $pair_id . '_' . time() . '_' . basename($_FILES['product_image']['name']);
                $target_file = $upload_dir . $file_name;
                
                // Check file type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($_FILES['product_image']['type'], $allowed_types)) {
                    $response['message'] = 'Only JPG, PNG, GIF, and WEBP files are allowed.';
                } else {
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
                        // Save image path to database
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
        
        // 🆕 Check login status
        elseif ($_POST['action'] === 'check_login') {
            if (isset($_SESSION['firm_user_id'])) {
                // Get user and firm details
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
        
        // 🆕 Login action
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
                    // Set session variables
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
        
        // 🆕 Auto-pair by total weight
        elseif ($_POST['action'] === 'auto_pair_by_weight') {
            $request_no = $_POST['request_no'] ?? '';
            $item_name = $_POST['item_name'] ?? '';
            $target_weight = isset($_POST['target_weight']) ? floatval($_POST['target_weight']) : 0;

            if (!empty($request_no) && !empty($item_name) && $target_weight > 0) {
                // Get all unpaired items with the specified item name
                $stmt = $conn2->prepare("
                    SELECT h.id, h.huid_code, h.weight FROM huid_data h 
                    LEFT JOIN job_cards j ON h.job_no = j.job_no 
                    WHERE h.request_no = ? AND j.item = ? AND (h.pair_id IS NULL OR h.pair_id = '') 
                    ORDER BY h.weight
                ");
                $stmt->bind_param('ss', $request_no, $item_name);
                $stmt->execute();
                $result = $stmt->get_result();
                $items = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $pairs_created = 0;
                $errors = 0;

                // Use a map to find pairs efficiently
                $weights_map = [];
                foreach ($items as $item) {
                    $weight_key = (string)round(floatval($item['weight']), 3); // Use rounded string key
                    if (!isset($weights_map[$weight_key])) {
                        $weights_map[$weight_key] = [];
                    }
                    $weights_map[$weight_key][] = $item;
                }

                $used_ids = [];

                foreach ($items as $item) {
                    $item_id = $item['id'];
                    if (in_array($item_id, $used_ids)) {
                        continue; // Skip if already paired
                    }

                    $current_weight = round(floatval($item['weight']), 3);
                    $required_weight = round($target_weight - $current_weight, 3);
                    $required_weight_key = (string)$required_weight;

                    if (isset($weights_map[$required_weight_key]) && !empty($weights_map[$required_weight_key])) {
                        // Find a partner
                        $partner = null;
                        foreach ($weights_map[$required_weight_key] as $key => $potential_partner) {
                            if ($potential_partner['id'] !== $item_id && !in_array($potential_partner['id'], $used_ids)) {
                                $partner = $potential_partner;
                                // Remove partner from map to avoid reusing
                                array_splice($weights_map[$required_weight_key], $key, 1);
                                break;
                            }
                        }

                        if ($partner) {
                            // We found a pair
                            $item1 = $item;
                            $item2 = $partner;

                            // Add IDs to used list
                            $used_ids[] = $item1['id'];
                            $used_ids[] = $item2['id'];
                            
                            // Also remove the current item from its list in the map
                            $current_weight_key = (string)$current_weight;
                            if(isset($weights_map[$current_weight_key])) {
                                foreach ($weights_map[$current_weight_key] as $key => $self) {
                                    if ($self['id'] === $item_id) {
                                        array_splice($weights_map[$current_weight_key], $key, 1);
                                        break;
                                    }
                                }
                            }

                            // Generate pair ID
                            $item_initial = strtoupper(substr(trim($item_name), 0, 2));
                            $huid_suffix = substr($item1['huid_code'], -6);
                            $pair_id = "{$item_initial}-{$huid_suffix}";

                            // Update pair_id for the two items
                            $update_stmt = $conn2->prepare("UPDATE huid_data SET pair_id = ? WHERE id IN (?, ?)");
                            $update_stmt->bind_param('sii', $pair_id, $item1['id'], $item2['id']);
                            
                            if ($update_stmt->execute()) {
                                $pairs_created++;
                            } else {
                                $errors++;
                            }
                            $update_stmt->close();
                        }
                    }
                }

                if ($pairs_created > 0) {
                    $response = [
                        'status' => 'success', 
                        'message' => "Successfully created $pairs_created pairs for '$item_name' with total weight $target_weight." . ($errors > 0 ? " with $errors errors." : "")
                    ];
                } else {
                    $response['message'] = "No new pairs were created. Not enough unpaired items found for '$item_name' to make a total weight of $target_weight.";
                }

            } else {
                $response['message'] = 'Request number, item name, and a valid target weight are required.';
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
    
    // Get data for export
    $stmt = $conn2->prepare("SELECT h.*, j.item FROM huid_data h 
                            LEFT JOIN job_cards j ON h.job_no = j.job_no 
                            WHERE h.request_no = ? ORDER BY h.id DESC");
    $stmt->bind_param("s", $request_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $export_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (!empty($export_data)) {
        // Set headers for Excel download
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="huid_data_' . $request_no . '_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Create Excel content
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
        
        // Add weight to total (if numeric)
        if (is_numeric($row['weight'])) {
            $total_weight += floatval($row['weight']);
        }
        
        // Get purity (assuming all items have the same purity)
        if (empty($purity) && !empty($row['purity'])) {
            $purity = $row['purity'];
        }
    }
}

// Get unique item names for auto-pairing dropdown
$unique_item_names = array_unique(array_column($data, 'item_name'));
sort($unique_item_names);


// Check if user is logged in - but don't require it for viewing
$is_logged_in = isset($_SESSION['firm_user_id']);
$user_name = $_SESSION['user_name'] ?? 'Guest';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HUID Pair Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif'],
                    },
                    colors: {
                        'primary': '#7c3aed', // Purple
                        'primary-light': '#f3f0ff',
                        'secondary': '#f59e0b', // Amber
                        'success': '#10b981', // Emerald
                        'warning': '#f97316', // Orange
                        'danger': '#ef4444', // Red
                        'info': '#06b6d4', // Cyan
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f9fafb;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #cdcdcd;
            border-radius: 10px;
        }
        
        /* Row hover effect */
        .row-hover:hover {
            background-color: #f3f0ff;
        }
        
        /* Edit icon hover effect */
        .edit-icon {
            transition: all 0.2s ease;
        }
        .edit-icon:hover {
            color: #7c3aed;
            transform: scale(1.2);
        }
        
        /* Modal animation */
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-animation {
            animation: modalFadeIn 0.3s ease forwards;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                font-size: 12pt;
            }
            table {
                border-collapse: collapse;
                width: 100%;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
        }
        
        /* Fix for mobile table */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Horizontal scroll for stats and buttons */
        .scroll-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        .scroll-container::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        
        /* Sticky header */
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        /* Pulse animation for notification */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Image preview */
        .image-preview {
            transition: transform 0.3s ease;
        }
        .image-preview:hover {
            transform: scale(1.05);
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.7rem;
        }
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* QR Code */
        .qr-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            margin: 20px 0;
        }
        .qr-code {
            padding: 10px;
            background: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="font-poppins">
    <div class="max-w-full">
        <!-- Header - more compact -->
        <div class="bg-primary px-4 py-3 text-white sticky-header">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <i class="fas fa-tags text-yellow-300 text-lg"></i>
                    <h1 class="text-xl font-bold">HUID Pair Manager</h1>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($is_logged_in): ?>
                    <div class="bg-white/20 px-3 py-1 rounded-full text-xs">
                        <i class="fas fa-user-circle"></i> <?= htmlspecialchars($user_name) ?>
                    </div>
                    <?php else: ?>
                    <button id="loginBtn" class="bg-white/20 px-3 py-1 rounded-full text-xs hover:bg-white/30">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Search Form -->
            <div class="mt-3">
                <form method="get" class="flex gap-1">
                    <input type="text" name="request_no" 
                        class="flex-grow px-3 py-2 border-0 rounded-l-md focus:outline-none focus:ring-2 focus:ring-yellow-300 text-gray-800 text-sm" 
                        placeholder="Enter Request No" 
                        value="<?= htmlspecialchars($request_no) ?>" 
                        required>
                    <button type="submit" class="bg-yellow-400 text-gray-800 font-bold px-3 py-2 rounded-r-md flex items-center">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <?php if ($request_no !== ''): ?>
            <!-- Main Content -->
            <div class="px-3 py-3">
                <!-- Help Text with Tooltip -->
                <div class="relative group mb-3">
                    <div class="bg-blue-50 border-l-4 border-primary p-3 rounded-md">
                        <div class="flex justify-between items-center">
                            <h4 class="text-primary font-medium flex items-center gap-2 text-sm">
                                <i class="fas fa-info-circle text-primary"></i> How to Pair HUID Codes
                            </h4>
                            <button type="button" class="text-primary" id="helpToggle">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                        <div id="helpContent" class="hidden">
                            <ol class="ml-4 list-decimal text-primary-dark text-xs mt-2">
                                <li class="mb-1">First, generate all IDs using the "Generate All IDs" button</li>
                                <li class="mb-1">To pair items, select them and click "Update Pair ID"</li>
                                <li class="mb-1">Use the same checkboxes to select items for printing tags</li>
                                <li class="mb-1">You can upload images for each product after pairing</li>
                                <li>Submit to Jewel Entry when you're done with all pairing</li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <!-- Info Cards - Horizontal Scrollable -->
                <div class="scroll-container mb-3">
                    <div class="flex gap-3 min-w-max">
                        <!-- Request No Card -->
                        <div class="bg-primary-light p-3 rounded-md border border-primary/20 w-40">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="rounded-full bg-primary p-1.5 flex items-center justify-center">
                                    <i class="fas fa-hashtag text-white text-xs"></i>
                                </div>
                                <h3 class="text-gray-600 text-xs font-medium">Request No</h3>
                            </div>
                            <p class="font-bold text-gray-800 truncate"><?= htmlspecialchars($request_no) ?></p>
                        </div>
                        
                        <!-- Purity Card -->
                        <div class="bg-purple-100 p-3 rounded-md border border-purple-200 w-40">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="rounded-full bg-purple-500 p-1.5 flex items-center justify-center">
                                    <i class="fas fa-gem text-white text-xs"></i>
                                </div>
                                <h3 class="text-gray-600 text-xs font-medium">Purity</h3>
                            </div>
                            <p class="font-bold text-gray-800"><?= htmlspecialchars($purity) ?></p>
                        </div>
                        
                        <!-- Total Records Card -->
                        <div class="bg-amber-50 p-3 rounded-md border border-amber-200 w-40">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="rounded-full bg-secondary p-1.5 flex items-center justify-center">
                                    <i class="fas fa-list-ol text-white text-xs"></i>
                                </div>
                                <h3 class="text-gray-600 text-xs font-medium">Total Records</h3>
                            </div>
                            <p class="font-bold text-gray-800"><?= $total_records ?></p>
                        </div>
                        
                        <!-- Total Weight Card -->
                        <div class="bg-green-50 p-3 rounded-md border border-green-200 w-40">
                            <div class="flex items-center gap-2 mb-1">
                                <div class="rounded-full bg-success p-1.5 flex items-center justify-center">
                                    <i class="fas fa-weight-hanging text-white text-xs"></i>
                                </div>
                                <h3 class="text-gray-600 text-xs font-medium">Total Weight</h3>
                            </div>
                            <p class="font-bold text-gray-800"><?= number_format($total_weight, 3) ?> <span class="text-xs font-normal text-gray-500">g</span></p>
                        </div>
                    </div>
                </div>
                
                 <!-- Filter and Auto Pair Bar -->
                <div class="bg-white p-2 rounded-md border border-gray-200 mb-3 flex flex-wrap gap-2 items-center text-xs">
                    <div class="flex-grow min-w-[150px]">
                        <label for="pairFinderItemName" class="sr-only">Select Item Name</label>
                        <select id="pairFinderItemName" class="w-full px-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary text-xs">
                            <option value="">-- Select an item to find pairs --</option>
                            <?php foreach ($unique_item_names as $name): ?>
                                <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="flex-grow min-w-[150px]">
                        <label for="targetPairWeight" class="sr-only">Target Pair Weight</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <i class="fas fa-balance-scale text-gray-400"></i>
                            </div>
                            <input type="text" id="targetPairWeight" class="w-full pl-8 pr-2 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary text-xs" placeholder="Target pair weight (e.g., 8.0)">
                        </div>
                    </div>
                    <button type="button" id="autoPairByWeightBtn" class="bg-purple-600 text-white px-3 py-1.5 rounded-md flex items-center gap-1 text-xs hover:bg-purple-700">
                        <i class="fas fa-magic"></i> Auto Pair by Weight
                    </button>
                </div>
                
                <!-- Action Bar - Horizontal Scrollable -->
                <div class="scroll-container mb-3">
                    <div class="flex gap-2 min-w-max">
                        <button type="button" id="generateAllIdsBtn" class="bg-secondary text-white px-3 py-2 rounded-md flex items-center gap-1 text-xs">
                            <i class="fas fa-tags"></i> Generate All IDs
                        </button>
                        <button type="button" id="pairButton" class="bg-primary text-white px-3 py-2 rounded-md flex items-center gap-1 opacity-50 cursor-not-allowed text-xs" disabled>
                            <i class="fas fa-link"></i> Update Pair ID
                        </button>
                        <button type="button" id="resetIdsBtn" class="bg-danger text-white px-3 py-2 rounded-md flex items-center gap-1 text-xs">
                            <i class="fas fa-sync-alt"></i> Reset
                        </button>
                        <button type="button" id="printTagsBtn" class="bg-info text-white px-3 py-2 rounded-md flex items-center gap-1 text-xs">
                            <i class="fas fa-print"></i> Print Tags
                        </button>
                        <button type="button" id="submitJewelBtn" class="bg-success text-white px-3 py-2 rounded-md flex items-center gap-1 text-xs">
                            <i class="fas fa-check-circle"></i> Submit to Jewel Entry
                        </button>
                        <button type="button" id="exportExcelBtn" class="bg-green-600 text-white px-3 py-2 rounded-md flex items-center gap-1 text-xs">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>
                
                <!-- Selection Counter -->
                <div id="selectionCounter" class="hidden mb-2 bg-blue-50 p-2 rounded-md text-xs">
                    <span class="font-medium">0</span> items selected
                </div>
                
                <!-- Table - More Compact with Sticky Header -->
                <div class="table-container rounded-md overflow-hidden border border-gray-200 bg-white">
                    <div class="max-h-[calc(100vh-240px)] overflow-y-auto">
                        <table class="w-full text-left">
                            <thead class="bg-primary text-white text-xs sticky top-0">
                                <tr>
                                    <th class="w-8 p-2">
                                        <input type="checkbox" id="selectAll" class="w-3 h-3 rounded border-gray-300 text-primary">
                                    </th>
                                    <th class="p-2">ID</th>
                                    <th class="p-2">ITEM NAME</th>
                                    <th class="p-2">HUID</th>
                                    <th class="p-2">WEIGHT (g)</th>
                                    <th class="p-2">PAIR ID</th>
                                    <th class="p-2">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-xs">
                            <?php foreach ($data as $row): ?>
                                <tr data-huid="<?= htmlspecialchars($row['huid_code']) ?>" 
                                    data-item="<?= htmlspecialchars($row['item_name']) ?>" 
                                    data-id="<?= $row['id'] ?>"
                                    data-job-no="<?= htmlspecialchars($row['job_no']) ?>"
                                    data-pair-id="<?= htmlspecialchars($row['pair_id'] ?? '') ?>"
                                    class="row-hover">
                                    <td class="p-2">
                                        <input type="checkbox" 
                                            class="item-checkbox w-3 h-3 rounded border-gray-300 text-primary cursor-pointer" 
                                            value="<?= $row['id'] ?>">
                                    </td>
                                    <td class="p-2"><?= $row['id'] ?></td>
                                    <td class="p-2 group">
                                        <div class="flex items-center gap-1">
                                            <span class="item-name-value"><?= htmlspecialchars($row['item_name']) ?></span>
                                            <button class="edit-icon edit-item-btn text-gray-400 hover:text-primary focus:outline-none opacity-0 group-hover:opacity-100">
                                                <i class="fas fa-pencil-alt text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-2 font-mono"><?= htmlspecialchars($row['huid_code']) ?></td>
                                    <td class="p-2 group">
                                        <div class="flex items-center gap-1">
                                            <span class="weight-value"><?= htmlspecialchars($row['weight']) ?></span>
                                            <button class="edit-icon edit-weight-btn text-gray-400 hover:text-primary focus:outline-none opacity-0 group-hover:opacity-100">
                                                <i class="fas fa-pencil-alt text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="p-2 font-mono">
                                        <?= htmlspecialchars($row['pair_id'] ?? '') ?>
                                    </td>
                                    <td class="p-2">
                                        <?php if (!empty($row['pair_id'])): ?>
                                        <button class="upload-image-btn text-blue-500 hover:text-blue-700 focus:outline-none" data-pair-id="<?= htmlspecialchars($row['pair_id']) ?>">
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
                
                <!-- Pair ID Status -->
                <div class="mt-3 text-xs text-gray-500">
                    <div id="pairStatus" class="hidden bg-green-50 p-2 rounded-md border border-green-200">
                        <i class="fas fa-info-circle text-success"></i> 
                        <span id="pairStatusText">Some items have pair IDs assigned.</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="p-6 text-center">
                <div class="mx-auto w-16 h-16 bg-primary-light rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-search text-2xl text-primary"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-800 mb-2">No Data to Display</h2>
                <p class="text-gray-600 mb-4 text-sm">Enter a Request Number above to view HUID data</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="fixed top-4 right-4 flex items-center p-3 space-x-3 w-full max-w-xs rounded-lg shadow-lg transition duration-300 transform translate-x-full opacity-0 z-50 text-xs">
        <div class="inline-flex flex-shrink-0 justify-center items-center w-6 h-6 rounded-lg">
            <span class="toast-icon text-lg"></span>
        </div>
        <div class="ml-2 font-normal toast-message"></div>
    </div>
    
    <!-- Edit Weight Modal -->
    <div id="editWeightModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl p-4 max-w-xs w-full modal-animation mx-3">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold text-gray-800">Edit Weight</h3>
                <button type="button" class="close-modal text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editWeightForm" class="space-y-3">
                <input type="hidden" id="weightItemId">
                <div>
                    <label for="weightValue" class="block text-sm font-medium text-gray-700 mb-1">Weight (grams)</label>
                    <input type="number" id="weightValue" step="0.001" min="0" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-sm" 
                           placeholder="Enter weight in grams">
                </div>
                <div class="flex justify-end pt-2">
                    <button type="button" class="close-modal mr-2 px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="bg-primary text-white px-3 py-1.5 rounded-md hover:bg-primary-dark text-xs">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Item Name Modal -->
    <div id="editItemModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl p-4 max-w-xs w-full modal-animation mx-3">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold text-gray-800">Edit Item Name</h3>
                <button type="button" class="close-modal text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editItemForm" class="space-y-3">
                <input type="hidden" id="itemId">
                <div>
                    <label for="itemName" class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                    <input type="text" id="itemName" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-sm" 
                           placeholder="Enter item name">
                </div>
                <div class="flex justify-end pt-2">
                    <button type="button" class="close-modal mr-2 px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="bg-primary text-white px-3 py-1.5 rounded-md hover:bg-primary-dark text-xs">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Print Tags Modal -->
    <div id="printTagsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl p-4 max-w-xs w-full modal-animation mx-3">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold text-gray-800">Print Tags</h3>
                <button type="button" class="close-modal text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">You have selected <span id="printCount" class="font-semibold text-primary">0</span> items for printing.</p>
                <div class="bg-blue-50 p-2 rounded-md text-xs">
                    <i class="fas fa-info-circle text-info mr-1"></i> 
                    Tags will be printed using the PAIR ID instead of individual HUID codes.
                </div>
            </div>
            <div class="flex justify-end pt-2">
                <button type="button" class="close-modal mr-2 px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 text-xs">
                    Cancel
                </button>
                <button type="button" id="confirmPrintBtn" class="bg-info text-white px-3 py-1.5 rounded-md hover:bg-cyan-600 text-xs flex items-center gap-1">
                    <i class="fas fa-print"></i> Print Tags
                </button>
            </div>
        </div>
    </div>
    
    <!-- Submit to Jewel Entry Guidance Modal -->
   <div id="jewelGuidanceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-xl shadow-2xl p-5 max-w-md w-full modal-animation mx-3 border border-gray-200">
    
    <!-- Header -->
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-xl font-bold text-indigo-700 flex items-center gap-2">
        <i class="fas fa-gem text-pink-500"></i> What is Jewel Entry?
      </h3>
      <button type="button" class="close-modal text-gray-400 hover:text-gray-600">
        <i class="fas fa-times text-lg"></i>
      </button>
    </div>

    <!-- Intro Text -->
    <p class="text-sm text-gray-600 mb-4">
      Jewel Entry is your all-in-one cloud platform to manage every aspect of your jewelry business — from inventory to billing and beyond.
    </p>

    <!-- Features Grid -->
    <div class="grid grid-cols-2 gap-3 text-xs text-gray-700">
      <div class="flex items-start gap-2 bg-indigo-50 p-3 rounded-lg">
        <i class="fas fa-box-open text-indigo-600 text-lg"></i>
        <span><strong>Inventory</strong><br>Track items, HUID stock & None Huid stock in real-time</span>
      </div>
      <div class="flex items-start gap-2 bg-green-50 p-3 rounded-lg">
        <i class="fas fa-tags text-green-600 text-lg"></i>
        <span><strong>Smart Catalog</strong><br>Upload images, tags & item details share to whatspp</span>
      </div>
      <div class="flex items-start gap-2 bg-yellow-50 p-3 rounded-lg">
        <i class="fas fa-cash-register text-yellow-600 text-lg"></i>
        <span><strong>Sales</strong><br>Instant billing/Quations with QR/barcode scan </span>
      </div>
      <div class="flex items-start gap-2 bg-pink-50 p-3 rounded-lg">
        <i class="fas fa-user-friends text-pink-500 text-lg"></i>
        <span><strong>CRM</strong><br>Loyalty, offers & customer tracking</span>
      </div>
    </div>

    <!-- Login Warning -->
    <div id="loginRequiredMessage" class="bg-yellow-100 mt-4 p-3 rounded-md border border-yellow-300 hidden">
      <p class="text-xs text-yellow-800 flex items-start">
        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-0.5"></i>
        You must be logged in to use Jewel Entry.
      </p>
    </div>

    <!-- QR Code -->
    <div class="qr-container mt-4 text-center">
      <div class="qr-code" id="qrCodeContainer"></div>
      <p class="text-xs text-gray-500 mt-2">Scan to view HUID details</p>
    </div>

    <!-- Demo CTA -->
    <div class="bg-blue-50 border border-blue-200 p-4 mt-4 rounded-md text-center">
      <p class="text-sm text-blue-800 font-medium mb-2">
        🎉 Want to try it yourself?
      </p>
      <p class="text-xs text-gray-600 mb-2">
        Visit <a href="https://www.jewelentry.com" target="_blank" class="text-blue-600 underline">jewelentry.com</a> to register and take a <strong>free demo trial</strong> now!
      </p>
      <p class="text-xs text-gray-600">
        📞 Contact us: <a href="tel:+919876543210" class="text-blue-600">+91 98765 43210</a>
      </p>
    </div>

    <!-- Buttons -->
    <div class="flex justify-end pt-4 gap-2">
      <button type="button" class="close-modal px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100 text-xs">
        Cancel
      </button>
      <button type="button" id="confirmJewelSubmitBtn" class="bg-indigo-600 text-white px-4 py-1.5 rounded-md hover:bg-indigo-700 text-xs flex items-center gap-1">
        <i class="fas fa-check-circle"></i> Submit Now
      </button>
    </div>
  </div>
</div>

    
    <!-- Submit to Jewel Entry Success Modal -->
    <div id="jewelSuccessModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl p-4 max-w-xs w-full modal-animation mx-3">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold text-gray-800">Success</h3>
                <button type="button" class="close-modal text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-4 text-center">
                <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-check-circle text-2xl text-success"></i>
                </div>
                <p class="text-sm text-gray-600">Your items have been successfully submitted to Jewel Entry Product.</p>
            </div>
            <div class="flex justify-center pt-2">
                <button type="button" class="close-modal px-4 py-2 bg-success text-white rounded-md hover:bg-green-600 text-xs">
                    OK
                </button>
            </div>
        </div>
    </div>
    
    <!-- Upload Image Modal -->
    <div id="uploadImageModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl p-4 max-w-xs w-full modal-animation mx-3">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold text-gray-800">Upload Product Image</h3>
                <button type="button" class="close-modal text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="uploadImageForm" class="space-y-3">
                <input type="hidden" id="imagePairId">
                <div>
                    <label for="productImage" class="block text-sm font-medium text-gray-700 mb-1">Select Image</label>
                    <input type="file" id="productImage" name="product_image" accept="image/*"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-sm">
                </div>
                <div id="imagePreviewContainer" class="hidden">
                    <p class="text-xs text-gray-500 mb-1">Preview:</p>
                    <img id="imagePreview" class="w-full h-auto rounded-md image-preview" src="/placeholder.svg" alt="Preview">
                </div>
                <div class="flex justify-end pt-2">
                    <button type="button" class="close-modal mr-2 px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="bg-primary text-white px-3 py-1.5 rounded-md hover:bg-primary-dark text-xs">
                        Upload Image
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-xl p-4 max-w-xs w-full modal-animation mx-3">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-bold text-gray-800">Login</h3>
                <button type="button" class="close-modal text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="loginForm" class="space-y-3">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" id="username" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-sm" 
                           placeholder="Enter your username">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="password" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent text-sm" 
                           placeholder="Enter your password">
                </div>
                <div id="loginError" class="text-danger text-xs hidden"></div>
                <div class="flex justify-end pt-2">
                    <button type="button" class="close-modal mr-2 px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 text-xs">
                        Cancel
                    </button>
                    <button type="submit" class="bg-primary text-white px-3 py-1.5 rounded-md hover:bg-primary-dark text-xs">
                        Login
                    </button>
                </div>
            </form>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.0/build/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check login status
    checkLoginStatus();
    
    // Toggle help text
    const helpToggle = document.getElementById('helpToggle');
    const helpContent = document.getElementById('helpContent');
    
    if (helpToggle) {
        helpToggle.addEventListener('click', function() {
            helpContent.classList.toggle('hidden');
            const icon = helpToggle.querySelector('i');
            if (helpContent.classList.contains('hidden')) {
                icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
            } else {
                icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
            }
        });
    }
    
    // Select All checkbox functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateSelectionCount();
            updatePairButtonState();
        });
    }
    
    // Individual checkbox change handlers
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectionCount();
            updatePairButtonState();
        });
    });
    
    // Initial button state and pair status
    updatePairButtonState();
    updatePairStatus();
    
    // Set up button events
    const generateAllIdsBtn = document.getElementById('generateAllIdsBtn');
    const pairButton = document.getElementById('pairButton');
    const resetIdsBtn = document.getElementById('resetIdsBtn');
    const printTagsBtn = document.getElementById('printTagsBtn');
    const submitJewelBtn = document.getElementById('submitJewelBtn');
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    const loginBtn = document.getElementById('loginBtn');
    
    if (generateAllIdsBtn) {
        generateAllIdsBtn.addEventListener('click', generateAllIds);
    }
    
    if (pairButton) {
        pairButton.addEventListener('click', generatePairId);
    }
    
    if (resetIdsBtn) {
        resetIdsBtn.addEventListener('click', function() {
            const requestNo = '<?= $request_no ?>';
            resetPairIDs(requestNo);
        });
    }
    
    if (printTagsBtn) {
        printTagsBtn.addEventListener('click', openPrintTagsModal);
    }
    
    if (submitJewelBtn) {
        submitJewelBtn.addEventListener('click', openJewelGuidanceModal);
    }
    
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', exportToExcel);
    }
    
    if (loginBtn) {
        loginBtn.addEventListener('click', openLoginModal);
    }
    
    // Set up pair finder functionality
    const pairFinderItemName = document.getElementById('pairFinderItemName');
    const targetPairWeight = document.getElementById('targetPairWeight');
    const autoPairByWeightBtn = document.getElementById('autoPairByWeightBtn');

    if(pairFinderItemName) {
        pairFinderItemName.addEventListener('change', updatePairFilter);
    }
    if(targetPairWeight) {
        targetPairWeight.addEventListener('keyup', updatePairFilter);
    }
    if(autoPairByWeightBtn) {
        autoPairByWeightBtn.addEventListener('click', autoPairByWeight);
    }
            
    // Confirm print button
    const confirmPrintBtn = document.getElementById('confirmPrintBtn');
    if (confirmPrintBtn) {
        confirmPrintBtn.addEventListener('click', saveAndPrintTags);
    }
    
    // Confirm jewel submit button
    const confirmJewelSubmitBtn = document.getElementById('confirmJewelSubmitBtn');
    if (confirmJewelSubmitBtn) {
        confirmJewelSubmitBtn.addEventListener('click', submitToJewelEntry);
    }
    
    // Set up edit buttons
    setupEditButtons();
    
    // Set up upload image buttons
    setupUploadImageButtons();
    
    // Image preview functionality
    const productImage = document.getElementById('productImage');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    
    if (productImage) {
        productImage.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreviewContainer.classList.remove('hidden');
                }
                reader.readAsDataURL(this.files[0]);
            } else {
                imagePreviewContainer.classList.add('hidden');
            }
        });
    }
    
    // Login form submission
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const loginError = document.getElementById('loginError');
            
            if (!username || !password) {
                loginError.textContent = 'Username and password are required';
                loginError.classList.remove('hidden');
                return;
            }
            
            loginError.classList.add('hidden');
            
            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('username', username);
            formData.append('password', password);
            
            // Show loading state
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Logging in...';
            submitBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Restore button
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                if (data.status === 'success') {
                    showToast('Login successful', 'success');
                    document.getElementById('loginModal').classList.add('hidden');
                    
                    // Reload page to update UI
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    loginError.textContent = data.message || 'Login failed';
                    loginError.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                loginError.textContent = 'An error occurred during login';
                loginError.classList.remove('hidden');
            });
        });
    }
});

function updatePairFilter() {
    const itemName = document.getElementById('pairFinderItemName').value;
    const targetWeight = parseFloat(document.getElementById('targetPairWeight').value);
    const allRows = document.querySelectorAll('tbody tr');

    // If no filter criteria, show all rows and exit
    if (!itemName && isNaN(targetWeight)) {
        allRows.forEach(row => { row.style.display = ''; });
        return;
    }

    // First, hide all rows
    allRows.forEach(row => { row.style.display = 'none'; });

    // Get candidate items: correct name, not paired yet
    const candidates = Array.from(allRows).filter(row => 
        (itemName ? row.dataset.item === itemName : true) && !row.dataset.pairId
    );

    if (isNaN(targetWeight)) {
        // If only item name is selected, show all items of that name
        candidates.forEach(row => { row.style.display = ''; });
        return;
    }
    
    // Logic to find pairs that sum to targetWeight
    const weightsMap = new Map();
    candidates.forEach(row => {
        const weightStr = parseFloat(row.querySelector('.weight-value').textContent).toFixed(3);
        if (!weightsMap.has(weightStr)) {
            weightsMap.set(weightStr, []);
        }
        weightsMap.get(weightStr).push(row);
    });

    const foundPairRows = new Set();
    const processedRows = new Set();

    for (const row of candidates) {
        if (processedRows.has(row)) continue;

        const currentWeight = parseFloat(row.querySelector('.weight-value').textContent);
        const requiredWeightStr = (targetWeight - currentWeight).toFixed(3);
        
        // Find a partner
        let partner = null;
        if (weightsMap.has(requiredWeightStr)) {
            const potentialPartners = weightsMap.get(requiredWeightStr);
            for (const p of potentialPartners) {
                // Ensure we don't pair an item with itself and that the partner is not already used
                if (p !== row && !processedRows.has(p)) {
                    partner = p;
                    break;
                }
            }
        }

        if (partner) {
            foundPairRows.add(row);
            foundPairRows.add(partner);
            processedRows.add(row);
            processedRows.add(partner);
        }
    }
    
    // Show only the rows that are part of a found pair
    foundPairRows.forEach(row => {
        row.style.display = '';
    });
}

function autoPairByWeight() {
    const itemName = document.getElementById('pairFinderItemName').value;
    const targetWeight = parseFloat(document.getElementById('targetPairWeight').value);
    const requestNo = '<?= $request_no ?>';

    if (!itemName || isNaN(targetWeight) || targetWeight <= 0) {
        showToast('Please select an item and enter a valid target pair weight.', 'warning');
        return;
    }

    // Show loader on button
    const autoPairBtn = document.getElementById('autoPairByWeightBtn');
    const originalButtonContent = autoPairBtn.innerHTML;
    autoPairBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Pairing...';
    autoPairBtn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'auto_pair_by_weight');
    formData.append('request_no', requestNo);
    formData.append('item_name', itemName);
    formData.append('target_weight', targetWeight);

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        autoPairBtn.innerHTML = originalButtonContent;
        autoPairBtn.disabled = false;
        
        if (data.status === 'success') {
            showToast(data.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to auto-pair items.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        autoPairBtn.innerHTML = originalButtonContent;
        autoPairBtn.disabled = false;
        showToast('An error occurred while auto-pairing.', 'error');
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
        if (data.status === 'success') {
            // Update UI based on login status
            if (data.logged_in) {
                // User is logged in
                console.log('User is logged in:', data.user);
            } else {
                // User is not logged in
                console.log('User is not logged in');
            }
        }
    })
    .catch(error => {
        console.error('Error checking login status:', error);
    });
}

function updateSelectionCount() {
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    const selectionCounter = document.getElementById('selectionCounter');
    const countSpan = selectionCounter.querySelector('span');
    
    countSpan.textContent = checkedBoxes.length;
    
    if (checkedBoxes.length > 0) {
        selectionCounter.classList.remove('hidden');
    } else {
        selectionCounter.classList.add('hidden');
    }
}

function updatePairButtonState() {
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    const pairButton = document.getElementById('pairButton');
    
    if (checkedBoxes.length >= 2) {
        pairButton.classList.remove('opacity-50', 'cursor-not-allowed');
        pairButton.classList.add('hover:bg-primary-dark');
        pairButton.disabled = false;
    } else {
        pairButton.classList.add('opacity-50', 'cursor-not-allowed');
        pairButton.classList.remove('hover:bg-primary-dark');
        pairButton.disabled = true;
    }
}

function updatePairStatus() {
    // Check if any items have pair IDs
    const rows = document.querySelectorAll('tr[data-pair-id]');
    const pairStatus = document.getElementById('pairStatus');
    const pairStatusText = document.getElementById('pairStatusText');
    
    let hasPairIds = false;
    let pairCount = 0;
    
    rows.forEach(row => {
        if (row.getAttribute('data-pair-id') !== '') {
            hasPairIds = true;
            pairCount++;
        }
    });
    
    if (hasPairIds) {
        pairStatus.classList.remove('hidden');
        pairStatusText.textContent = `${pairCount} items have pair IDs assigned.`;
    } else {
        pairStatus.classList.add('hidden');
    }
}

function openPrintTagsModal() {
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    const printCount = document.getElementById('printCount');
    const printTagsModal = document.getElementById('printTagsModal');
    
    if (checkedBoxes.length === 0) {
        showToast('Please select at least one item to print', 'warning');
        return;
    }
    
    printCount.textContent = checkedBoxes.length;
    printTagsModal.classList.remove('hidden');
}

function openJewelGuidanceModal() {
    const jewelGuidanceModal = document.getElementById('jewelGuidanceModal');
    const loginRequiredMessage = document.getElementById('loginRequiredMessage');
    
    // Check if user is logged in
    const formData = new FormData();
    formData.append('action', 'check_login');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            if (!data.logged_in) {
                // User is not logged in, show login required message
                loginRequiredMessage.classList.remove('hidden');
                
                // Generate QR code for current page
                const currentUrl = window.location.href;
                const qrCodeContainer = document.getElementById('qrCodeContainer');
                
                if (qrCodeContainer) {
                    qrCodeContainer.innerHTML = '';
                    QRCode.toCanvas(qrCodeContainer, currentUrl, {
                        width: 150,
                        margin: 1,
                        color: {
                            dark: '#7c3aed',
                            light: '#ffffff'
                        }
                    }, function(error) {
                        if (error) console.error(error);
                    });
                }
            } else {
                // User is logged in, hide login required message
                loginRequiredMessage.classList.add('hidden');
            }
            
            jewelGuidanceModal.classList.remove('hidden');
        }
    })
    .catch(error => {
        console.error('Error checking login status:', error);
        showToast('An error occurred while checking login status', 'error');
    });
}

function openLoginModal() {
    const loginModal = document.getElementById('loginModal');
    loginModal.classList.remove('hidden');
    
    // Clear previous form data and errors
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('loginError').classList.add('hidden');
}

function saveAndPrintTags() {
    const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
    
    if (checkedBoxes.length === 0) {
        showToast('Please select at least one item to print', 'warning');
        return;
    }
    
    const ids = Array.from(checkedBoxes).map(cb => cb.value);
    
    // Save selected IDs to session
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
            // Redirect to print page - now using pair_id instead of huid_data id
            window.open('print_tag.php?request_no=<?= $request_no ?>&use_pair_id=1', '_blank');
            
            // Close modal
            document.getElementById('printTagsModal').classList.add('hidden');
            
            showToast(`Preparing ${data.count} tags for printing`, 'success');
        } else {
            showToast(data.message || 'Failed to prepare tags', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred while processing your request', 'error');
    });
}

function exportToExcel() {
    const requestNo = '<?= $request_no ?>';
    if (!requestNo) {
        showToast('Request number is required', 'error');
        return;
    }
    
    // Show loader
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    const originalButtonContent = exportExcelBtn.innerHTML;
    exportExcelBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Exporting...';
    exportExcelBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'export_excel');
    formData.append('request_no', requestNo);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Restore button
        exportExcelBtn.innerHTML = originalButtonContent;
        exportExcelBtn.disabled = false;
        
        if (data.status === 'success') {
            showToast('Excel export is being prepared. Download will start automatically.', 'success');
            // Trigger download by reloading the page with export parameter
            window.location.href = window.location.pathname + '?request_no=' + requestNo + '&export=1';
        } else {
            showToast(data.message || 'Failed to export data', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        exportExcelBtn.innerHTML = originalButtonContent;
        exportExcelBtn.disabled = false;
        showToast('An error occurred while processing your request', 'error');
    });
}

function generateAllIds() {
    const requestNo = '<?= $request_no ?>';
    if (!requestNo) {
        showToast('Request number is required', 'error');
        return;
    }
    
    // Show loader
    const generateAllIdsBtn = document.getElementById('generateAllIdsBtn');
    const originalButtonContent = generateAllIdsBtn.innerHTML;
    generateAllIdsBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
    generateAllIdsBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'generate_all_ids');
    formData.append('request_no', requestNo);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Restore button
        generateAllIdsBtn.innerHTML = originalButtonContent;
        generateAllIdsBtn.disabled = false;
        
        if (data.status === 'success') {
            showToast(data.message, 'success');
            // Reload the page to reflect changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to generate IDs', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        generateAllIdsBtn.innerHTML = originalButtonContent;
        generateAllIdsBtn.disabled = false;
        showToast('An error occurred while processing your request', 'error');
    });
}

function generatePairId() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    if (checkboxes.length < 2) {
        showToast('Please select at least two items to pair', 'error');
        return;
    }

    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    // Get the first selected row's details for generating the ID
    const firstRow = checkboxes[0].closest('tr');
    const itemName = firstRow.getAttribute('data-item');
    const huidCode = firstRow.getAttribute('data-huid');
    
    // Extract first two letters from item name (or use first letter if only one char)
    const itemInitial = itemName.trim().substring(0, 2).toUpperCase();
    
    // Extract last 6 characters from HUID code
    const huidSuffix = huidCode.slice(-6);
    
    // Create pair ID format: [ITEM_INITIAL]-[HUID_SUFFIX]
    const pairId = `${itemInitial}-${huidSuffix}`;

    // Show loader
    const pairButton = document.getElementById('pairButton');
    const originalButtonContent = pairButton.innerHTML;
    pairButton.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...';
    pairButton.disabled = true;

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
        // Restore button state
        pairButton.innerHTML = originalButtonContent;
        pairButton.disabled = false;
        
        if (data.status === 'success') {
            // Update UI to reflect changes
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                row.setAttribute('data-pair-id', pairId);
                // Update the pair ID cell
                const pairIdCell = row.querySelector('td:nth-child(6)');
                if (pairIdCell) {
                    pairIdCell.textContent = pairId;
                }
                
                // Add upload image button if not exists
                const actionsCell = row.querySelector('td:nth-child(7)');
                if (actionsCell && !actionsCell.querySelector('.upload-image-btn')) {
                    const uploadBtn = document.createElement('button');
                    uploadBtn.className = 'upload-image-btn text-blue-500 hover:text-blue-700 focus:outline-none';
                    uploadBtn.setAttribute('data-pair-id', pairId);
                    uploadBtn.innerHTML = '<i class="fas fa-camera text-xs"></i>';
                    uploadBtn.addEventListener('click', function() {
                        openUploadImageModal(pairId);
                    });
                    actionsCell.appendChild(uploadBtn);
                }
                
                checkbox.checked = false;
            });
            
            showToast(`Successfully paired ${checkboxes.length} items with ID: ${pairId}`, 'success');
            updatePairButtonState();
            updatePairStatus();
            updateSelectionCount();
        } else {
            showToast(data.message || 'Failed to save pair ID', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        pairButton.innerHTML = originalButtonContent;
        pairButton.disabled = false;
        showToast('An error occurred while processing your request', 'error');
    });
}

function resetPairIDs(requestNo) {
    if (!requestNo) {
        showToast('Request number is required', 'error');
        return;
    }
    
    if (!confirm('Are you sure you want to reset all pair IDs for this request? This action cannot be undone.')) {
        return;
    }
    
    // Disable button and show loader
    const resetIdsBtn = document.getElementById('resetIdsBtn');
    const originalButtonContent = resetIdsBtn.innerHTML;
    resetIdsBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Resetting...';
    resetIdsBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'reset_pair_ids');
    formData.append('request_no', requestNo);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        resetIdsBtn.innerHTML = originalButtonContent;
        resetIdsBtn.disabled = false;
        
        if (data.status === 'success') {
            showToast('All pair IDs have been reset successfully', 'success');
            // Reload the page to reflect changes
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast(data.message || 'Failed to reset pair IDs', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        resetIdsBtn.innerHTML = originalButtonContent;
        resetIdsBtn.disabled = false;
        showToast('An error occurred while processing your request', 'error');
    });
}

function submitToJewelEntry() {
    const requestNo = '<?= $request_no ?>';
    if (!requestNo) {
        showToast('Request number is required', 'error');
        return;
    }
    
    // Check if there are items with pair IDs
    const rows = document.querySelectorAll('tr[data-pair-id]');
    let hasPairIds = false;
    
    rows.forEach(row => {
        if (row.getAttribute('data-pair-id') !== '') {
            hasPairIds = true;
        }
    });
    
    if (!hasPairIds) {
        showToast('Please generate IDs for items before submitting to Jewel Entry', 'warning');
        return;
    }
    
    // Close guidance modal
    document.getElementById('jewelGuidanceModal').classList.add('hidden');
    
    // Disable button and show loader
    const submitJewelBtn = document.getElementById('submitJewelBtn');
    const originalButtonContent = submitJewelBtn.innerHTML;
    submitJewelBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Submitting...';
    submitJewelBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'submit_to_jewel_entry');
    formData.append('request_no', requestNo);
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitJewelBtn.innerHTML = originalButtonContent;
        submitJewelBtn.disabled = false;
        
        if (data.status === 'success') {
            // Show success modal
            document.getElementById('jewelSuccessModal').classList.remove('hidden');
        } else if (data.status === 'login_required') {
            // User needs to login
            showToast('Please login to submit to Jewel Entry', 'warning');
            openLoginModal();
        } else {
            showToast(data.message || 'Failed to submit to Jewel Entry', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        submitJewelBtn.innerHTML = originalButtonContent;
        submitJewelBtn.disabled = false;
        showToast('An error occurred while processing your request', 'error');
    });
}

// Edit functionality for weights and item names
function setupEditButtons() {
    // Edit Weight buttons
    const editWeightBtns = document.querySelectorAll('.edit-weight-btn');
    const editWeightModal = document.getElementById('editWeightModal');
    const weightValue = document.getElementById('weightValue');
    const weightItemId = document.getElementById('weightItemId');
    const editWeightForm = document.getElementById('editWeightForm');
    
    editWeightBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            const currentWeight = row.querySelector('.weight-value').textContent;
            
            weightItemId.value = id;
            weightValue.value = currentWeight;
            editWeightModal.classList.remove('hidden');
        });
    });
    
    // Edit Item Name buttons
    const editItemBtns = document.querySelectorAll('.edit-item-btn');
    const editItemModal = document.getElementById('editItemModal');
    const itemName = document.getElementById('itemName');
    const itemId = document.getElementById('itemId');
    const editItemForm = document.getElementById('editItemForm');
    
    editItemBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const id = row.getAttribute('data-id');
            const currentItemName = row.querySelector('.item-name-value').textContent;
            
            itemId.value = id;
            itemName.value = currentItemName;
            editItemModal.classList.remove('hidden');
        });
    });
    
    // Close modals
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            editWeightModal.classList.add('hidden');
            editItemModal.classList.add('hidden');
            document.getElementById('printTagsModal').classList.add('hidden');
            document.getElementById('jewelGuidanceModal').classList.add('hidden');
            document.getElementById('jewelSuccessModal').classList.add('hidden');
            document.getElementById('uploadImageModal').classList.add('hidden');
            document.getElementById('loginModal').classList.add('hidden');
        });
    });
    
    // Submit edit weight form
    if (editWeightForm) {
        editWeightForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const id = weightItemId.value;
            const weight = weightValue.value;
            
            if (!id || !weight) {
                showToast('ID and weight are required', 'error');
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
                    // Update UI
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        row.querySelector('.weight-value').textContent = data.weight;
                    }
                    
                    showToast('Weight updated successfully', 'success');
                    editWeightModal.classList.add('hidden');
                    
                    // Recalculate total weight
                    updateTotalWeight();
                } else {
                    showToast(data.message || 'Failed to update weight', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while updating weight', 'error');
            });
        });
    }
    
    // Submit edit item form - FIXED to only update specific item
    if (editItemForm) {
        editItemForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const id = itemId.value;
            const name = itemName.value;
            
            if (!id || !name) {
                showToast('ID and item name are required', 'error');
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
                    // Update only the specific row
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) {
                        row.querySelector('.item-name-value').textContent = data.item_name;
                        row.setAttribute('data-item', data.item_name);
                    }
                    
                    showToast('Item name updated successfully', 'success');
                    editItemModal.classList.add('hidden');
                } else {
                    showToast(data.message || 'Failed to update item name', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while updating item name', 'error');
            });
        });
    }
}

// Setup upload image functionality
function setupUploadImageButtons() {
    const uploadImageBtns = document.querySelectorAll('.upload-image-btn');
    const uploadImageModal = document.getElementById('uploadImageModal');
    const imagePairId = document.getElementById('imagePairId');
    const uploadImageForm = document.getElementById('uploadImageForm');
    
    uploadImageBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const pairId = this.getAttribute('data-pair-id');
            openUploadImageModal(pairId);
        });
    });
    
    // Submit upload image form
    if (uploadImageForm) {
        uploadImageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const pairId = imagePairId.value;
            const fileInput = document.getElementById('productImage');
            
            if (!pairId || !fileInput.files.length) {
                showToast('Pair ID and image are required', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_image');
            formData.append('pair_id', pairId);
            formData.append('product_image', fileInput.files[0]);
            
            // Show loading state
            const submitBtn = uploadImageForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Uploading...';
            submitBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Restore button
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                
                if (data.status === 'success') {
                    showToast('Image uploaded successfully', 'success');
                    document.getElementById('uploadImageModal').classList.add('hidden');
                    
                    // Reset form
                    fileInput.value = '';
                    document.getElementById('imagePreviewContainer').classList.add('hidden');
                } else {
                    showToast(data.message || 'Failed to upload image', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                showToast('An error occurred while uploading image', 'error');
            });
        });
    }
}

function openUploadImageModal(pairId) {
    const uploadImageModal = document.getElementById('uploadImageModal');
    const imagePairId = document.getElementById('imagePairId');
    
    imagePairId.value = pairId;
    uploadImageModal.classList.remove('hidden');
    
    // Reset form
    document.getElementById('productImage').value = '';
    document.getElementById('imagePreviewContainer').classList.add('hidden');
}

// Function to update total weight on the UI
function updateTotalWeight() {
    // Sum all weights
    let totalWeight = 0;
    document.querySelectorAll('.weight-value').forEach(elem => {
        const weight = parseFloat(elem.textContent);
        if (!isNaN(weight)) {
            totalWeight += weight;
        }
    });
    
    // Find total weight display element and update it
    const totalWeightElement = document.querySelector('.bg-green-50 p');
    if (totalWeightElement) {
        totalWeightElement.innerHTML = `${totalWeight.toFixed(3)} <span class="text-xs font-normal text-gray-500">g</span>`;
    }
}

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.querySelector('.toast-message');
    const toastIcon = document.querySelector('.toast-icon');
    
    // Set message
    toastMessage.textContent = message;
    
    // Clear previous classes
    toast.className = 'fixed top-4 right-4 flex items-center p-3 space-x-3 w-full max-w-xs rounded-lg shadow-lg transition duration-300 transform translate-x-full opacity-0 z-50 text-xs';
    
    // Set type-specific styling
    switch(type) {
        case 'success':
            toast.classList.add('bg-green-100', 'text-green-800');
            toastIcon.className = 'toast-icon text-lg fas fa-check-circle text-success';
            break;
        case 'error':
            toast.classList.add('bg-red-100', 'text-red-800');
            toastIcon.className = 'toast-icon text-lg fas fa-exclamation-circle text-danger';
            break;
        case 'warning':
            toast.classList.add('bg-yellow-100', 'text-yellow-800');
            toastIcon.className = 'toast-icon text-lg fas fa-exclamation-triangle text-warning';
            break;
        default: // info
            toast.classList.add('bg-blue-100', 'text-blue-800');
            toastIcon.className = 'toast-icon text-lg fas fa-info-circle text-info';
    }
    
    // Show toast
    setTimeout(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
    }, 100);
    
    // Hide toast after 3 seconds
    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
    }, 3000);
}
</script>
</body>
</html>
