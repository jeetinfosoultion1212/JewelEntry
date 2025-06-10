<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config/config.php'; // Adjusted path for API directory
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['id'])) {
   // For API, return JSON error instead of redirect
   header('Content-Type: application/json');
   echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
   exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
   error_log("Database connection failed: " . $conn->connect_error);
   header('Content-Type: application/json');
   echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
   exit();
}

// Debug function to log errors without breaking JSON responses
function debug_log($message, $data = null) {
  $log_file = 'loan_debug.log'; // Changed log file name
  $timestamp = date('Y-m-d H:i:s');
  $log_message = "[$timestamp] $message";
  
  if ($data !== null) {
      $log_message .= ": " . json_encode($data);
  }
  
  file_put_contents($log_file, $log_message . PHP_EOL, FILE_APPEND);
}

// Function to save captured image
function saveBase64Image($base64Image, $loanId) {
  try {
      $uploadDir = '../uploads/loans/'; // Adjusted path for API directory
      
      if (!file_exists($uploadDir)) {
          mkdir($uploadDir, 0777, true);
      }
      
      $base64Image = preg_replace('#^data:image/\w+;base64,#i', '', $base64Image);
      $imageData = base64_decode($base64Image);
      $newFileName = 'loan_' . $loanId . '_' . time() . '_captured.jpg';
      $targetFilePath = $uploadDir . $newFileName;
      
      if (file_put_contents($targetFilePath, $imageData)) {
          return $targetFilePath;
      } else {
          return false;
      }
  } catch (Exception $e) {
      debug_log("Error saving captured image", $e->getMessage());
      return false;
  }
}

// Handle the add_loan action
ob_clean(); // Ensure no prior output before JSON
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    // Validate required fields
    $requiredFields = ['loanAmount', 'interestRate', 'loanDuration', 'startDate', 'customerId', 'collateralItems'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: " . $field);
        }
    }
    
    // Get form data
    $customerId = $_POST['customerId'];
    $loanAmount = floatval($_POST['loanAmount']);
    $interestRate = floatval($_POST['interestRate']);
    $loanDuration = intval($_POST['loanDuration']);
    $startDate = $_POST['startDate'];
    
    // Decode collateral items from JSON string
    $collateralItemsJson = $_POST['collateralItems'];
    $collateralItems = json_decode($collateralItemsJson, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON for collateral items: " . json_last_error_msg());
    }

    // Validate collateral items
    if (empty($collateralItems) || !is_array($collateralItems)) {
        throw new Exception("At least one collateral item is required or invalid data.");
    }
    
    // Calculate total collateral value
    $totalCollateralValue = 0;
    foreach ($collateralItems as $item) {
        if (!isset($item['calculatedValue'])) {
            throw new Exception("Invalid collateral item data: Missing calculatedValue");
        }
        $totalCollateralValue += floatval($item['calculatedValue']);
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Calculate maturity date based on loan term
        $startDateObj = new DateTime($startDate);
        $maturityDateObj = clone $startDateObj;
        $maturityDateObj->modify("+{$loanDuration} months");
        $maturityDate = $maturityDateObj->format('Y-m-d');

        // Calculate EMI
        $monthlyInterestRate = ($interestRate / 12) / 100; // Convert annual rate to monthly decimal
        $emi = $loanAmount * $monthlyInterestRate * pow(1 + $monthlyInterestRate, $loanDuration) / (pow(1 + $monthlyInterestRate, $loanDuration) - 1);
        $emi = round($emi, 2); // Round to 2 decimal places

        // Insert into loans table with updated columns
        $sql = "INSERT INTO loans (
            firm_id, customer_id, loan_date, principal_amount, interest_rate, 
            loan_term_months, maturity_date, current_status, total_amount_paid,
            outstanding_amount, collateral_description, collateral_value, 
            notes, created_by, created_at, emi_amount
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 0, ?, ?, ?, ?, ?, NOW(), ?
        )";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error (prepare): " . $conn->error);
        }

        // Calculate outstanding amount (same as principal amount initially)
        $outstandingAmount = $loanAmount;
        
        // Prepare collateral description
        $collateralDescription = '';
        foreach ($collateralItems as $item) {
            $collateralDescription .= sprintf(
                "%s (%.2f g, %.2f%% purity) - Rs.%.2f\\n",
                $item['materialType'] ?? '',
                $item['weight'] ?? 0,
                $item['purity'] ?? 0,
                $item['calculatedValue'] ?? 0
            );
        }

        $notes = "Loan created with " . count($collateralItems) . " collateral items";

        $stmt->bind_param(
            "iisdddsdsssid",
            $firm_id,
            $customerId,
            $startDate,
            $loanAmount,
            $interestRate,
            $loanDuration,
            $maturityDate,
            $outstandingAmount,
            $collateralDescription,
            $totalCollateralValue,
            $notes,
            $user_id,
            $emi
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create loan (execute): " . $stmt->error);
        }
        $loanId = $conn->insert_id;
        
        // Generate and insert EMI schedule
        $currentDate = clone $startDateObj;
        $remainingPrincipal = $loanAmount;
        $totalInterest = 0;

        for ($month = 1; $month <= $loanDuration; $month++) {
            // Calculate interest for this month
            $monthlyInterest = $remainingPrincipal * $monthlyInterestRate;
            $principalComponent = $emi - $monthlyInterest;
            
            // Update remaining principal
            $remainingPrincipal -= $principalComponent;
            $totalInterest += $monthlyInterest;

            // Insert EMI record
            $emiSql = "INSERT INTO loan_emi (
                loan_id, emi_number, due_date, amount, 
                principal_component, interest_component, 
                remaining_principal, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 'PENDING', NOW()
            )";

            $emiStmt = $conn->prepare($emiSql);
            if (!$emiStmt) {
                throw new Exception("Database error (prepare EMI): " . $conn->error);
            }

            $dueDate = $currentDate->format('Y-m-d');
            $emiStmt->bind_param(
                "iisdddd",
                $loanId,
                $month,
                $dueDate,
                $emi,
                $principalComponent,
                $monthlyInterest,
                $remainingPrincipal
            );

            if (!$emiStmt->execute()) {
                throw new Exception("Failed to create EMI record: " . $emiStmt->error);
            }

            // Move to next month
            $currentDate->modify('+1 month');
        }
        
        // Insert collateral items
        foreach ($collateralItems as $index => $item) {
            $materialType = $item['materialType'] ?? '';
            $purity = floatval($item['purity'] ?? 0);
            $weight = floatval($item['weight'] ?? 0);
            $ratePerGram = floatval($item['ratePerGram'] ?? 0);
            $calculatedValue = floatval($item['calculatedValue'] ?? 0);
            $description = $item['description'] ?? '';
            $imagePath = '';

            // Save image if present
            if (!empty($item['image'])) {
                $imagePath = saveBase64Image($item['image'], $loanId);
                if ($imagePath === false) {
                    debug_log("Failed to save image for collateral item", $item);
                }
            }
            
            $itemSql = "INSERT INTO loan_collateral_items (loan_id, material_type, purity, weight, rate_per_gram, calculated_value, description, image_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $itemStmt = $conn->prepare($itemSql);
            if (!$itemStmt) {
                throw new Exception("Database error (prepare collateral): " . $conn->error);
            }

            $itemStmt->bind_param(
                "isdddsds",
                $loanId, $materialType, $purity, $weight, $ratePerGram, 
                $calculatedValue, $description, $imagePath
            );
            
            if (!$itemStmt->execute()) {
                throw new Exception("Failed to add collateral item (execute): " . $itemStmt->error);
            }
        }
        
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = "Loan created successfully!";
        $response['data'] = [
            'loan_id' => $loanId,
            'maturity_date' => $maturityDate,
            'outstanding_amount' => $outstandingAmount
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e; // Re-throw to be caught by outer catch block
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = "Error: " . $e->getMessage();
    error_log("Loan creation API error: " . $e->getMessage());
} finally {
    $conn->close();
}

echo json_encode($response);
exit; 