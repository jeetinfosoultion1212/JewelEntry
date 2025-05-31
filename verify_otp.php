<?php
// Initialize the session
session_start();

// Set the default timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// Check if user came from forgot password
if(!isset($_SESSION['reset_email'])) {
    header("location: forgot_password.php");
    exit;
}

// Include database connection
require_once "config/config.php";

// Define variables and initialize with empty values
$otp = "";
$otp_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Check if OTP is empty
    if(empty(trim($_POST["otp"]))){
        $otp_err = "Please enter the OTP.";
    } else{
        $otp = trim($_POST["otp"]);
    }
    
    // Validate OTP
    if(empty($otp_err)){
        // Prepare a select statement
        $sql = "SELECT id FROM Firm_Users WHERE Email = ? AND reset_token = ? AND token_expiry > (NOW() - INTERVAL 30 SECOND)";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ss", $param_email, $param_otp);
            
            // Set parameters
            $param_email = $_SESSION['reset_email'];
            $param_otp = $otp;
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Store result
                mysqli_stmt_store_result($stmt);
                
                // Debug information
                $debug_sql = "SELECT reset_token, token_expiry FROM Firm_Users WHERE Email = '" . mysqli_real_escape_string($conn, $_SESSION['reset_email']) . "'";
                $debug_result = mysqli_query($conn, $debug_sql);
                $debug_row = mysqli_fetch_assoc($debug_result);
                
                // Check if OTP is valid
                if(mysqli_stmt_num_rows($stmt) == 1){
                    // OTP is valid, redirect to reset password page
                    $_SESSION['otp_verified'] = true;
                    header("location: reset_password.php");
                    exit();
                } else{
                    $otp_err = "Invalid or expired OTP. Please check the OTP and try again.";
                    if($debug_row) {
                        $otp_err .= " (Debug: Stored OTP: " . $debug_row['reset_token'] . ", Expiry: " . $debug_row['token_expiry'] . ")";
                    }
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Jewel Entry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .otp-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .input-field {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        
        .input-field:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            transform: translateY(-1px);
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <!-- OTP Verification Card -->
        <div class="otp-card rounded-2xl shadow-2xl p-6">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 mb-3">
                    <i class="fas fa-shield-alt text-indigo-600 text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-1">Verify OTP</h1>
                <p class="text-gray-500 text-sm">Enter the OTP sent to your email</p>
            </div>
            
            <!-- Error Alert -->
            <?php if(!empty($otp_err)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-sm mr-2"></i>
                    <p class="text-sm text-red-700"><?php echo $otp_err; ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- OTP Form -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
                <!-- OTP -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Enter OTP</label>
                    <div class="relative">
                        <i class="fas fa-key absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="otp" maxlength="6" pattern="\d{6}" 
                            class="input-field w-full pl-10 pr-4 py-2.5 rounded-lg focus:outline-none text-sm"
                            placeholder="Enter 6-digit OTP">
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="submit-btn w-full py-2.5 rounded-lg text-white font-medium text-sm">
                    <span>Verify OTP</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
            
            <!-- Back to Login -->
            <div class="text-center mt-4">
                <p class="text-xs text-gray-600">
                    Didn't receive OTP? 
                    <a href="forgot_password.php" class="text-indigo-600 hover:text-indigo-500 font-medium">Resend OTP</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html> 