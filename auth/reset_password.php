<?php
// Initialize the session
session_start();

// Check if user is verified
if(!isset($_SESSION['reset_user_id'])) {
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
        $sql = "UPDATE Firm_Users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "si", $param_password, $param_id);
            
            // Set parameters
            $param_password = password_hash($new_password, PASSWORD_DEFAULT);
            $param_id = $_SESSION['reset_user_id'];
            
            error_log("Resetting password for user ID: " . $param_id);
            error_log("New password (plain): " . $new_password);
            error_log("New password (hashed): " . $param_password);

            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Password updated successfully. Destroy the session, and redirect to login page
                session_destroy();
                $success_msg = "Password has been reset successfully. You can now login with your new password.";
                // Redirect after 2 seconds
                header("refresh:2;url=login.php");
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fef7cd;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            width: 100%;
            max-width: 380px;
            position: relative;
            z-index: 10;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.25rem;
        }
        
        .input-field {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            font-size: 0.95rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background-color: #f9fafb;
            transition: all 0.2s ease;
            color: #374151;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);
        }
        
        .input-field:focus {
            outline: none;
            border-color: #fbbf24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1rem;
        }

        .reset-btn {
            background: linear-gradient(135deg, #ffc107 0%, #ffa000 100%);
            color: white;
            padding: 0.875rem 1.5rem;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 165, 0, 0.3);
            border: none;
            cursor: pointer;
            width: 100%;
        }
        
        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 165, 0, 0.4);
        }

        .reset-btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 10px rgba(255, 165, 0, 0.2);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: #6b7280;
        }

        @media (max-width: 640px) {
            .reset-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="w-full max-w-sm">
        <div class="reset-card">
            <!-- Header -->
            <div class="text-center mb-6 mt-4">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 mb-3">
                    <i class="fas fa-key text-yellow-600 text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-yellow-600 mb-2">Reset Password</h1>
                <p class="text-gray-500 text-sm">Enter your new password below</p>
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
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <!-- New Password -->
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="new_password" id="new_password"
                        class="input-field"
                        placeholder="New Password"
                        value="<?php echo $new_password; ?>">
                    <span class="password-toggle" onclick="togglePassword('new_password')">
                        <i class="fas fa-eye"></i>
                    </span>
                    <?php if(!empty($new_password_err)): ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo $new_password_err; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Confirm Password -->
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="confirm_password" id="confirm_password"
                        class="input-field"
                        placeholder="Confirm Password"
                        value="<?php echo $confirm_password; ?>">
                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </span>
                    <?php if(!empty($confirm_password_err)): ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo $confirm_password_err; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Reset Button -->
                <button type="submit" class="reset-btn mb-5">
                    Reset Password
                </button>
            </form>
            
            <!-- Back to Login -->
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Remember your password? 
                    <a href="login.php" class="text-yellow-600 hover:text-yellow-700 font-semibold">Back to Login</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html> 