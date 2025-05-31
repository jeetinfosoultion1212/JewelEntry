<?php
// Initialize the session
session_start();

// Check if OTP is verified
if(!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header("location: forgot_password.php");
    exit;
}

// Include database connection
require_once "config/config.php";

// Define variables and initialize with empty values
$new_password = $confirm_password = "";
$new_password_err = $confirm_password_err = $success_msg = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    // Validate new password
    if(empty(trim($_POST["new_password"]))){
        $new_password_err = "Please enter the new password.";     
    } elseif(strlen(trim($_POST["new_password"])) < 6){
        $new_password_err = "Password must have at least 6 characters.";
    } else{
        $new_password = trim($_POST["new_password"]);
    }
    
    // Validate confirm password
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm the password.";     
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($new_password_err) && ($new_password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before updating the database
    if(empty($new_password_err) && empty($confirm_password_err)){
        // Prepare an update statement
        $sql = "UPDATE Firm_Users SET Password = ?, reset_token = NULL, token_expiry = NULL WHERE Email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ss", $param_password, $param_email);
            
            // Set parameters
            $param_password = password_hash($new_password, PASSWORD_DEFAULT);
            $param_email = $_SESSION['reset_email'];
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                $success_msg = "Your password has been reset successfully. You can now login with your new password.";
                // Clear session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_username']);
                unset($_SESSION['otp_verified']);
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Jewel Entry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .reset-card {
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
        <!-- Reset Password Card -->
        <div class="reset-card rounded-2xl shadow-2xl p-6">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 mb-3">
                    <i class="fas fa-lock text-indigo-600 text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-1">Reset Password</h1>
                <p class="text-gray-500 text-sm">Enter your new password</p>
            </div>
            
            <!-- Success Message -->
            <?php if(!empty($success_msg)): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-sm mr-2"></i>
                    <p class="text-sm text-green-700"><?php echo $success_msg; ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Reset Password Form -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
                <!-- New Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="password" name="new_password" 
                            class="input-field w-full pl-10 pr-4 py-2.5 rounded-lg focus:outline-none text-sm"
                            placeholder="Enter new password">
                    </div>
                    <?php if(!empty($new_password_err)): ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo $new_password_err; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Confirm Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="password" name="confirm_password" 
                            class="input-field w-full pl-10 pr-4 py-2.5 rounded-lg focus:outline-none text-sm"
                            placeholder="Confirm new password">
                    </div>
                    <?php if(!empty($confirm_password_err)): ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo $confirm_password_err; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="submit-btn w-full py-2.5 rounded-lg text-white font-medium text-sm">
                    <span>Reset Password</span>
                    <i class="fas fa-key ml-2"></i>
                </button>
            </form>
            
            <!-- Back to Login -->
            <div class="text-center mt-4">
                <p class="text-xs text-gray-600">
                    Remember your password? 
                    <a href="login.php" class="text-indigo-600 hover:text-indigo-500 font-medium">Back to Login</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html> 