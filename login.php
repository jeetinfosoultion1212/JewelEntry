<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require 'config/config.php';

// Function to generate a secure remember me token
function generateRememberMeToken() {
    return bin2hex(random_bytes(32)); // 64 character hex string
}

// Check if the user is already logged in, if yes then redirect to home page
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: home.php");
    exit;
}

// Define variables and initialize with empty values
$login_identifier = $password = "";
$login_identifier_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Check if login identifier is empty
    if(empty(trim($_POST["login_identifier"]))){
        $login_identifier_err = "Please enter your username, email or phone number.";
    } else{
        $login_identifier = trim($_POST["login_identifier"]);
    }

    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if(empty($login_identifier_err) && empty($password_err)){
        // Prepare a select statement
        $sql = "SELECT id, username, password, role, firmID, email, PhoneNumber FROM Firm_Users WHERE username = ? OR email = ? OR PhoneNumber = ?";

        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "sss", $param_identifier, $param_identifier, $param_identifier);

            // Set parameters
            $param_identifier = $login_identifier;

            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Store result
                mysqli_stmt_store_result($stmt);

                // Check if user exists, if yes then verify password
                if(mysqli_stmt_num_rows($stmt) == 1){
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $role, $firmID, $email, $PhoneNumber);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, so start a new session
                            session_start();

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["role"] = $role;
                            $_SESSION["firmID"] = $firmID;

                            // Handle "Remember Me" functionality
                            if (isset($_POST['remember_me']) && $_POST['remember_me'] == 'on') {
                                $remember_token = generateRememberMeToken();
                                $token_expiration = date('Y-m-d H:i:s', strtotime('+30 days')); // Token valid for 30 days

                                // Store token in database
                                $update_sql = "UPDATE Firm_Users SET remember_token = ?, token_expiration = ? WHERE id = ?";
                                if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
                                    mysqli_stmt_bind_param($update_stmt, "ssi", $remember_token, $token_expiration, $id);
                                    mysqli_stmt_execute($update_stmt);
                                    mysqli_stmt_close($update_stmt);
                                }

                                // Set cookie
                                setcookie("remember_me", $remember_token, [
                                    'expires' => strtotime('+30 days'),
                                    'path' => '/',
                                    'httponly' => true, // HttpOnly for security
                                    'samesite' => 'Lax' // CSRF protection
                                ]);
                            }

                            // Get the app_view from POST and detect device if not set
                            $app_view = $_POST['app_view'] ?? '';
                            
                            // If app_view is empty, try to detect device type
                            if (empty($app_view)) {
                                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                                $is_mobile = preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $user_agent);
                                $app_view = $is_mobile ? 'mobile-view' : 'pc-view';
                            }

                            // Redirect user to appropriate dashboard based on role and view
                            if ($app_view === 'pc-view') {
                                header("location: PC/dashborad.php");
                            } else {
                                header("location: home.php");
                            }
                            exit;
                        } else{
                            // Password is not valid
                            error_log("Login failed for username: " . $login_identifier);
                            error_log("Submitted password: " . $password);
                            error_log("Stored hashed password: " . $hashed_password);
                            error_log("password_verify result: " . (password_verify($password, $hashed_password) ? 'true' : 'false'));
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else{
                    // Username doesn't exist
                    $login_err = "Invalid username or password.";
                }
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
    <title>Login - Jewel Entry</title>
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
            overflow-x: hidden;
            padding: 0;
        }

        .view-selector {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            width: 90px;
            height: 30px;
            background-color: #f3f4f6;
            border-radius: 15px;
            padding: 2px;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            position: absolute;
            overflow: hidden;
            z-index: 20;
            box-sizing: border-box;
        }

        .view-selector::before {
            content: '';
            position: absolute;
            width: calc(50% - 2px);
            height: calc(100% - 4px);
            background-color: #fbbf24;
            border-radius: 13px;
            transition: transform 0.3s ease-in-out;
            z-index: 1;
            top: 2px;
            left: 2px;
        }

        .view-selector.mobile-active::before {
            transform: translateX(0);
        }

        .view-selector.pc-active::before {
            transform: translateX(100%);
        }

        .view-selector button {
            padding: 0;
            border-radius: 13px;
            border: none;
            background-color: transparent;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.3s ease;
            position: relative;
            z-index: 2;
            flex: 1;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .view-selector button i {
            font-size: 1rem;
        }

        .view-selector button.active {
            color: white;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 480px;
            min-width: 280px;
            position: relative;
            z-index: 10;
            padding: 2.5rem;
            box-sizing: border-box;
        }

        @media (max-width: 640px) {
            .login-card {
                max-width: 350px;
                padding: 2rem;
            }
        }
        
        @media (max-width: 400px) {
            .login-card {
                max-width: 300px;
                padding: 1.5rem;
            }
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
        
        .input-field::placeholder {
            color: #9ca3af;
        }

        .input-field:focus {
            outline: none;
            border-color: #fbbf24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2), inset 0 1px 2px rgba(0,0,0,0.03);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1rem;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            font-size: 1rem;
        }

        .login-btn {
            background: linear-gradient(135deg, #ffc107 0%, #ffa000 100%); /* Orange gradient */
            color: white;
            padding: 0.875rem 1.5rem; /* Slightly more padding */
            border-radius: 0.75rem; /* Rounded button */
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 165, 0, 0.3); /* Orange shadow */
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 165, 0, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 10px rgba(255, 165, 0, 0.2);
        }

        .social-btn {
            background-color: white;
            border: 1px solid #e5e7eb;
            color: #374151;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .social-btn:hover {
            background-color: #f9fafb;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .social-btn i {
            font-size: 1.1rem;
            margin-right: 0.75rem;
        }

        .tab-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            color: #6b7280;
            background-color: #f3f4f6;
        }

        .tab-btn.active {
            background-color: white;
            color: #374151;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .tab-container {
            display: flex;
            background-color: #f3f4f6;
            padding: 0.25rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .language-dropdown {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background-color: #f3f4f6;
            border-radius: 0.5rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            color: #4b5563;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .language-dropdown i {
            font-size: 0.7rem;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .login-card {
                max-width: 380px; /* Adjust for smaller screens like mobile */
                padding: 1.5rem;
            }
            .text-2xl { font-size: 1.75rem; } /* Slightly smaller for mobile */
            .mb-6 { margin-bottom: 1.25rem; }
            .mb-4 { margin-bottom: 1rem; }
        }

        /* Ensure main container also centers content without extra space */
        .w-full.flex.justify-center {
            width: 100vw; /* Ensure it takes full viewport width */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem; /* Add padding here to avoid cutting off edges */
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="w-full flex justify-center">
        <div class="login-card">
            <div class="view-selector"> <!-- Will be set by JavaScript based on device detection -->
                <button id="mobileViewBtn"><i class="fas fa-mobile-alt"></i></button>
                <button id="pcViewBtn"><i class="fas fa-desktop"></i></button>
            </div>
            <!-- Header -->
            <div class="text-center mb-6 mt-4">
                <img src="uploads/logo.png" alt="Jewel Entry Logo" class="mx-auto h-20 w-auto mb-3">
                <h1 class="text-3xl font-bold text-yellow-600 mb-2">Welcome Back</h1>
                <p class="text-gray-500 text-sm">We're happy to see you again. To use your account, you should log in first.</p>
            </div>
            
            <!-- Error Alert -->
            <?php if(!empty($login_err)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 text-sm mr-2"></i>
                    <p class="text-sm text-red-700"><?php echo $login_err; ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="app_view" id="appViewInput" value="">
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="login_identifier" class="input-field"
                        value="<?php echo $login_identifier; ?>" placeholder="Enter username, email or phone number" required>
                </div>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="passwordInput" class="input-field pr-10"
                        placeholder="Enter your password" required>
                    <span class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>

                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember_me" type="checkbox"
                            class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 rounded">
                        <label for="remember_me" class="ml-2 block text-sm text-gray-900">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="font-medium text-sm text-yellow-600 hover:text-yellow-700">Forgot password?</a>
                </div>
                
                <button type="submit" class="login-btn w-full py-3 mb-5">
                    <span>Login</span>
                    <i class="fas fa-sign-in-alt ml-2"></i>
                </button>

                <!-- Contact Support via WhatsApp (as footer note) -->
                <div class="text-center mt-6 text-sm text-gray-600">
                    Need help? Contact 
                    <a href="https://wa.me/919876543210" target="_blank" class="text-green-600 hover:text-green-700 font-semibold inline-flex items-center">
                        <i class="fab fa-whatsapp mr-1"></i> Support Team
                    </a>
                </div>

                <div class="text-center mt-2">
                    <p class="text-sm text-gray-600">
                        Don't have an account? 
                        <a href="register.php" class="text-yellow-600 hover:text-yellow-700 font-semibold">Register here</a>
                    </p>
                </div>
            </form>
            
            <!-- Divider -->
            <div class="flex items-center my-4">
                <div class="flex-1 border-t border-gray-200"></div>
                <span class="px-3 text-xs text-gray-500"></span>
                <div class="flex-1 border-t border-gray-200"></div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileViewBtn = document.getElementById('mobileViewBtn');
            const pcViewBtn = document.getElementById('pcViewBtn');
            const body = document.body;
            const viewSelector = document.querySelector('.view-selector');
            const appViewInput = document.getElementById('appViewInput');

            // Function to detect mobile device
            function isMobileDevice() {
                return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
                       window.innerWidth <= 768;
            }

            // Function to switch to mobile view
            function switchToMobileView() {
                body.classList.remove('pc-view');
                body.classList.add('mobile-view');
                viewSelector.classList.remove('pc-active');
                viewSelector.classList.add('mobile-active');
                mobileViewBtn.classList.add('active');
                pcViewBtn.classList.remove('active');
                appViewInput.value = 'mobile-view';
                localStorage.setItem('appView', 'mobile-view');
            }

            // Function to switch to PC view
            function switchToPCView() {
                body.classList.remove('mobile-view');
                body.classList.add('pc-view');
                viewSelector.classList.remove('mobile-active');
                viewSelector.classList.add('pc-active');
                pcViewBtn.classList.add('active');
                mobileViewBtn.classList.remove('active');
                appViewInput.value = 'pc-view';
                localStorage.setItem('appView', 'pc-view');
            }

            // Load preference from localStorage or detect device
            const savedView = localStorage.getItem('appView');
            if (savedView) {
                if (savedView === 'mobile-view') {
                    switchToMobileView();
                } else {
                    switchToPCView();
                }
            } else {
                // Auto-detect device and set appropriate view
                if (isMobileDevice()) {
                    switchToMobileView();
                } else {
                    switchToPCView();
                }
            }

            // Add click event listeners for toggle buttons
            mobileViewBtn.addEventListener('click', function() {
                switchToMobileView();
            });

            pcViewBtn.addEventListener('click', function() {
                switchToPCView();
            });

            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('passwordInput');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    // Toggle the type attribute
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Toggle the eye icon
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                });
            }
        });
    </script>
</body>
</html>