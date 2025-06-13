<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database configs
session_start();
require 'config/config.php';
require 'config/hallmarkpro_config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$firm_id = $_SESSION['firmID'];
$user_id = $_SESSION['id'];

// Fetch hallmark centers
$centersQuery = "SELECT * FROM hallmark_centers ORDER BY name";
$centersResult = $conn->query($centersQuery);
$hallmarkCenters = $centersResult->fetch_all(MYSQLI_ASSOC);

// Fetch firm's BIS registration number
$firmQuery = "SELECT BISRegistrationNumber FROM Firm WHERE id = ?";
$firmStmt = $conn->prepare($firmQuery);
$firmStmt->bind_param("i", $firm_id);
$firmStmt->execute();
$firmResult = $firmStmt->get_result();
$firmInfo = $firmResult->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        $hallmarkpro_conn->begin_transaction();

        // Generate request number
        $request_no = date('Y') . str_pad($firm_id, 4, '0', STR_PAD_LEFT) . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert into hallmark_requests table
        $requestQuery = "INSERT INTO hallmark_requests (
            firm_id, bis_registration_number, hallmark_center_id, 
            total_items, total_weight, total_amount, remarks,
            request_no, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $requestStmt = $conn->prepare($requestQuery);
        $requestStmt->bind_param(
            "isiiidss",
            $firm_id,
            $_POST['bis_registration_number'],
            $_POST['hallmark_center_id'],
            $_POST['total_items'],
            $_POST['total_weight'],
            $_POST['total_amount'],
            $_POST['remarks'],
            $request_no
        );
        
        if (!$requestStmt->execute()) {
            throw new Exception("Error creating hallmark request: " . $requestStmt->error);
        }
        
        $requestId = $conn->insert_id;

        // Insert into hallmark_request_items table
        $itemQuery = "INSERT INTO hallmark_request_items (
            request_id, item_type, metal_type, purity, 
            weight, quantity, description
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $itemStmt = $conn->prepare($itemQuery);

        // Insert into HallmarkPro job_cards table
        $jobCardQuery = "INSERT INTO job_cards (
            firm_id, date_of_request, licence_no, request_no,
            item, pcs, purity, weight, status, financial_year
        ) VALUES (?, CURRENT_DATE(), ?, ?, ?, ?, ?, ?, 'pending', ?)";
        
        $jobCardStmt = $hallmarkpro_conn->prepare($jobCardQuery);
        
        foreach ($_POST['items'] as $item) {
            // Insert into hallmark_request_items
            $itemStmt->bind_param(
                "issddis",
                $requestId,
                $item['type'],
                $item['metal_type'],
                $item['purity'],
                $item['weight'],
                $item['quantity'],
                $item['description']
            );
            
            if (!$itemStmt->execute()) {
                throw new Exception("Error adding hallmark request item: " . $itemStmt->error);
            }

            // Insert into HallmarkPro job_cards
            $financial_year = date('Y');
            $jobCardStmt->bind_param(
                "issssds",
                $firm_id,
                $_POST['bis_registration_number'],
                $request_no,
                $item['type'],
                $item['quantity'],
                $item['purity'],
                $item['weight'],
                $financial_year
            );
            
            if (!$jobCardStmt->execute()) {
                throw new Exception("Error creating job card in HallmarkPro: " . $jobCardStmt->error);
            }
        }

        $conn->commit();
        $hallmarkpro_conn->commit();
        
        $_SESSION['success_message'] = "Hallmark request created successfully!";
        header("Location: hallmark_requests.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $hallmarkpro_conn->rollback();
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Hallmark Request - Jewel Entry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <?php include 'includes/header.php'; ?>

        <!-- Main Content -->
        <div class="container mx-auto px-4 py-8">
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-semibold text-gray-800">New Hallmark Request</h1>
                        <a href="hallmark_requests.php" class="text-yellow-600 hover:text-yellow-700">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Requests
                        </a>
                    </div>

                    <?php if (isset($error_message)): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                                <p class="text-red-700"><?php echo $error_message; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form id="hallmarkRequestForm" method="POST" class="space-y-6">
                        <!-- Basic Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    BIS Registration Number
                                </label>
                                <input type="text" name="bis_registration_number" 
                                    value="<?php echo htmlspecialchars($firmInfo['BISRegistrationNumber'] ?? ''); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-yellow-500 focus:border-yellow-500"
                                    required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Hallmark Center
                                </label>
                                <select name="hallmark_center_id" 
                                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-yellow-500 focus:border-yellow-500"
                                    required>
                                    <option value="">Select Hallmark Center</option>
                                    <?php foreach ($hallmarkCenters as $center): ?>
                                        <option value="<?php echo $center['id']; ?>">
                                            <?php echo htmlspecialchars($center['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Items Section -->
                        <div class="border-t border-gray-200 pt-6">
                            <h2 class="text-lg font-medium text-gray-800 mb-4">Items for Hallmarking</h2>
                            
                            <div id="itemsContainer" class="space-y-4">
                                <!-- Item template will be cloned here -->
                            </div>

                            <button type="button" id="addItemBtn" 
                                class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                <i class="fas fa-plus mr-2"></i> Add Item
                            </button>
                        </div>

                        <!-- Totals -->
                        <div class="border-t border-gray-200 pt-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Total Items
                                    </label>
                                    <input type="number" name="total_items" id="totalItems" readonly
                                        class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Total Weight (g)
                                    </label>
                                    <input type="number" name="total_weight" id="totalWeight" step="0.001" readonly
                                        class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Total Amount (₹)
                                    </label>
                                    <input type="number" name="total_amount" id="totalAmount" step="0.01" readonly
                                        class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-md">
                                </div>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Remarks
                            </label>
                            <textarea name="remarks" rows="3" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-yellow-500 focus:border-yellow-500"
                                placeholder="Any additional notes or instructions..."></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" 
                                class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                <i class="fas fa-paper-plane mr-2"></i> Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Item Template -->
    <template id="itemTemplate">
        <div class="item-entry bg-gray-50 p-4 rounded-lg">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-md font-medium text-gray-700">Item Details</h3>
                <button type="button" class="remove-item text-red-600 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Item Type
                    </label>
                    <input type="text" name="items[INDEX][type]" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-yellow-500 focus:border-yellow-500"
                        placeholder="e.g., Ring, Necklace, etc.">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Metal Type
                    </label>
                    <select name="items[INDEX][metal_type]" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-yellow-500 focus:border-yellow-500">
                        <option value="">Select Metal</option>
                        <option value="Gold">Gold</option>
                        <option value="Silver">Silver</option>
                        <option value="Platinum">Platinum</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Purity
                    </label>
                    <input type="number" name="items[INDEX][purity]" step="0.01" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-yellow-500 focus:border-yellow-500"
                        placeholder="e.g., 99.99 for Gold">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Weight (g)
                    </label>
                    <input type="number" name="items[INDEX][weight]" step="0.001" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-yellow-500 focus:border-yellow-500"
                        placeholder="Weight in grams">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Quantity
                    </label>
                    <input type="number" name="items[INDEX][quantity]" min="1" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-yellow-500 focus:border-yellow-500"
                        value="1">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <input type="text" name="items[INDEX][description]"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-yellow-500 focus:border-yellow-500"
                        placeholder="Additional details about the item">
                </div>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const itemsContainer = document.getElementById('itemsContainer');
            const addItemBtn = document.getElementById('addItemBtn');
            const itemTemplate = document.getElementById('itemTemplate');
            let itemIndex = 0;

            // Function to add new item
            function addItem() {
                const clone = itemTemplate.content.cloneNode(true);
                const itemDiv = clone.querySelector('.item-entry');
                
                // Update all input names with current index
                itemDiv.querySelectorAll('[name]').forEach(input => {
                    input.name = input.name.replace('INDEX', itemIndex);
                });
                
                // Add remove functionality
                const removeBtn = itemDiv.querySelector('.remove-item');
                removeBtn.addEventListener('click', function() {
                    itemDiv.remove();
                    updateTotals();
                });
                
                itemsContainer.appendChild(itemDiv);
                itemIndex++;
                updateTotals();
            }

            // Function to update totals
            function updateTotals() {
                let totalItems = 0;
                let totalWeight = 0;
                let totalAmount = 0;

                document.querySelectorAll('.item-entry').forEach(item => {
                    const quantity = parseFloat(item.querySelector('[name$="[quantity]"]').value) || 0;
                    const weight = parseFloat(item.querySelector('[name$="[weight]"]').value) || 0;
                    
                    totalItems += quantity;
                    totalWeight += weight * quantity;
                });

                // Update total fields
                document.getElementById('totalItems').value = totalItems;
                document.getElementById('totalWeight').value = totalWeight.toFixed(3);
                document.getElementById('totalAmount').value = totalAmount.toFixed(2);
            }

            // Add event listeners
            addItemBtn.addEventListener('click', addItem);
            
            // Add change event listeners to all numeric inputs
            document.addEventListener('input', function(e) {
                if (e.target.matches('input[type="number"]')) {
                    updateTotals();
                }
            });

            // Add first item by default
            addItem();
        });
    </script>
</body>
</html> 