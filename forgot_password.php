<?php
// Initialize the session
session_start();

// Set the default timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');
 
// Include database connection
require_once "config/config.php";

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Define variables and initialize with empty values
$email = "";
$email_err = $success_msg = "";
 
// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    // Check if email is empty
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter your email address.";
    } else{
        $email = trim($_POST["email"]);
    }
    
    // Validate credentials
    if(empty($email_err)){
        // Prepare a select statement
        $sql = "SELECT id, Username, Email FROM Firm_Users WHERE Email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            
            // Set parameters
            $param_email = $email;
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Store result
                mysqli_stmt_store_result($stmt);
                
                // Check if email exists
                if(mysqli_stmt_num_rows($stmt) == 1){                    
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $email);
                    if(mysqli_stmt_fetch($stmt)){
                        // Generate OTP
                        $otp = sprintf("%06d", mt_rand(1, 999999));
                        $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        
                        // Store OTP in database
                        $update_sql = "UPDATE Firm_Users SET reset_token = ?, token_expiry = ? WHERE id = ?";
                        if($update_stmt = mysqli_prepare($conn, $update_sql)){
                            mysqli_stmt_bind_param($update_stmt, "ssi", $otp, $otp_expiry, $id);
                            if(mysqli_stmt_execute($update_stmt)){
                                // Create a new PHPMailer instance
                                $mail = new PHPMailer(true);

                                try {
                                    // Server settings
                                    $mail->isSMTP();
                                    $mail->Host = 'smtp.gmail.com';
                                    $mail->SMTPAuth = true;
                                    $mail->Username = 'jeettechnoguide@gmail.com';
                                    $mail->Password = 'nhsi kbyn vlxq fylj';
                                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                    $mail->Port = 587;

                                    // Recipients
                                    $mail->setFrom('jeettechnoguide@gmail.com', 'Jewel Entry');
                                    $mail->addAddress($email, $username);

                                    // Content
                                    $mail->isHTML(true);
                                    
                                    $mail->Subject = 'Password Reset OTP';
                                    $mail->Body = "
                                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                            <h2 style='color: #4F46E5;'>Password Reset OTP</h2>
                                            <p>Hello {$username},</p>
                                            <p>Your OTP for password reset is:</p>
                                            <div style='text-align: center; margin: 30px 0;'>
                                                <div style='background-color: #f3f4f6; padding: 20px; border-radius: 5px; 
                                                     display: inline-block; font-size: 24px; letter-spacing: 5px; 
                                                     font-weight: bold; color: #4F46E5;'>
                                                    {$otp}
                                                </div>
                                            </div>
                                            <p>This OTP will expire in 15 minutes.</p>
                                            <p>If you did not request this password reset, please ignore this email.</p>
                                            <hr style='border: 1px solid #eee; margin: 20px 0;'>
                                            <p style='color: #666; font-size: 12px;'>
                                                Best regards,<br>
                                                Jewel Entry Team
                                            </p>
                                        </div>";

                                    $mail->send();
                                    // Store email in session for OTP verification
                                    $_SESSION['reset_email'] = $email;
                                    $_SESSION['reset_username'] = $username;
                                    header("location: verify_otp.php");
                                    exit();
                                } catch (Exception $e) {
                                    $email_err = "Failed to send OTP. Please try again. Mailer Error: {$mail->ErrorInfo}";
                                }
                            } else {
                                $email_err = "Something went wrong. Please try again later.";
                            }
                            mysqli_stmt_close($update_stmt);
                        }
                    }
                } else {
                    // Email doesn't exist
                    $email_err = "No account found with that email address.";
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
    <title>Forgot Password - Jewel Entry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .forgot-card {
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
        <!-- Forgot Password Card -->
        <div class="forgot-card rounded-2xl shadow-2xl p-6">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 mb-3">
                    <i class="fas fa-key text-indigo-600 text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-1">Forgot Password</h1>
                <p class="text-gray-500 text-sm">Enter your email to reset your password</p>
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
            
            <!-- Error Alert -->
            <?php if(!empty($email_err)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-sm mr-2"></i>
                    <p class="text-sm text-red-700"><?php echo $email_err; ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Forgot Password Form -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="email" name="email" value="<?php echo $email; ?>"
                            class="input-field w-full pl-10 pr-4 py-2.5 rounded-lg focus:outline-none text-sm"
                            placeholder="Enter your email address">
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="submit-btn w-full py-2.5 rounded-lg text-white font-medium text-sm">
                    <span>Send Reset Link</span>
                    <i class="fas fa-paper-plane ml-2"></i>
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