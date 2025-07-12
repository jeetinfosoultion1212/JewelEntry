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
        
        .forgot-card {
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

        .submit-btn {
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 165, 0, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 10px rgba(255, 165, 0, 0.2);
        }

        @media (max-width: 640px) {
            .forgot-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="w-full max-w-sm">
        <div class="forgot-card">
            <!-- Header -->
            <div class="text-center mb-6 mt-4">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 mb-3">
                    <i class="fas fa-envelope text-yellow-600 text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-yellow-600 mb-2">Forgot Password</h1>
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
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="input-group">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" value="<?php echo $email; ?>"
                        class="input-field"
                        placeholder="Enter your email address"
                        required>
                </div>
                
                <button type="submit" class="submit-btn mb-5">
                    <span>Send Reset Link</span>
                    <i class="fas fa-paper-plane"></i>
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
</body>
</html> 