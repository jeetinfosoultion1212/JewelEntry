<?php
// Initialize the session
session_start();

// Set the default timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

// Check if email is set in session
if(!isset($_SESSION['reset_email'])) {
    header("location: forgot_password.php");
    exit;
}

// Include database connection
require __DIR__ . '/../config/db_connect.php';

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
        $sql = "SELECT id, reset_token, token_expiry FROM Firm_Users WHERE Email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            $param_email = $_SESSION['reset_email'];
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    mysqli_stmt_bind_result($stmt, $id, $stored_otp, $expiry);
                    if(mysqli_stmt_fetch($stmt)){
                        // Check if OTP matches and hasn't expired
                        if($otp === $stored_otp && strtotime($expiry) > time()){
                            // OTP is valid, redirect to reset password page
                            $_SESSION['reset_user_id'] = $id;
                            header("location: reset_password.php");
                            exit();
                        } else {
                            $otp_err = "Invalid or expired OTP.";
                        }
                    }
                }
            } else {
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #FFF9F3;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .verify-card {
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
            padding: 0.875rem 1rem;
            font-size: 0.95rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background-color: #f9fafb;
            transition: all 0.2s ease;
            color: #374151;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);
            text-align: center;
            letter-spacing: 0.5em;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #fbbf24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2);
        }

        /* Remove old .verify-btn styles, use Tailwind classes below */

        @media (max-width: 640px) {
            .verify-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="w-full max-w-sm">
        <div class="verify-card">
            <!-- Header -->
            <div class="text-center mb-6 mt-4">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-orange-100 mb-3">
                    <i class="fas fa-shield-alt text-orange-500 text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-orange-600 mb-2">Verify OTP</h1>
                <p class="text-gray-500 text-sm">Enter the 6-digit code sent to your email</p>
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
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="input-group">
                    <input type="text" name="otp" maxlength="6" pattern="\d{6}" required
                        class="input-field"
                        placeholder="000000"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
                
                <button type="submit" class="w-full py-2 mb-5 bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-semibold rounded-lg shadow-lg text-base focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 transition duration-150 ease-in-out transform hover:scale-105 flex items-center justify-center gap-2">
                    <span>Verify OTP</span>
                    <i class="fas fa-shield-alt text-orange-100 bg-orange-500 rounded-full p-1"></i>
                </button>
            </form>
            
            <!-- Resend OTP -->
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Didn't receive the code? 
                    <a href="forgot_password.php" class="text-orange-500 hover:text-orange-700 font-semibold hover:underline">Resend OTP</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus the OTP input
        document.querySelector('input[name="otp"]').focus();
        
        // Auto-advance to next input (if we had multiple inputs)
        document.querySelector('input[name="otp"]').addEventListener('input', function(e) {
            // if (this.value.length === 6) {
            //     this.form.submit();
            // }
        });
    </script>
</body>
</html> 