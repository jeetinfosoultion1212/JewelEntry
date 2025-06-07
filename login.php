<?php
// Initialize the session
session_start();
 
// Check if the user is already logged in, if yes redirect to dashboard
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    // Role-based redirection for already logged in users
    switch($_SESSION["role"]) {
        case 'admin':
            header("location: admin_dashboard.php");
            break;
        case 'manager':
            header("location: manager_dashboard.php");
            break;
        case 'employee':
            header("location: employee_dashboard.php");
            break;
        default:
            header("location: home.php");
    }
    exit;
}
 
// Include database connection
require_once "config/config.php";
 
// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Check for remember me cookie
if(isset($_COOKIE['remember_user'])) {
    $cookie_data = json_decode($_COOKIE['remember_user'], true);
    if(isset($cookie_data['username']) && isset($cookie_data['password'])) {
        $_POST['username'] = $cookie_data['username'];
        $_POST['password'] = $cookie_data['password'];
        $_POST['remember'] = 'on';
    }
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    // Check if username/mobile is empty
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username or mobile number.";
    } else{
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err)){
        // Prepare a select statement
        $sql = "SELECT id, Username, Password, FirmID, Role, PhoneNumber FROM Firm_Users WHERE Username = ? OR PhoneNumber = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ss", $param_username, $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Store result
                mysqli_stmt_store_result($stmt);
                
                // Check if username/mobile exists, if yes then verify password
                if(mysqli_stmt_num_rows($stmt) == 1){                    
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $firmID, $role, $phoneNumber);
                    if(mysqli_stmt_fetch($stmt)){
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["firm_id"] = $firmID;
                            $_SESSION["role"] = $role;

                            // Set remember me cookie if checked
                            if(isset($_POST['remember']) && $_POST['remember'] == 'on') {
                                $cookie_data = array(
                                    'username' => $username,
                                    'password' => $password
                                );
                                setcookie('remember_user', json_encode($cookie_data), time() + (86400 * 30), "/"); // 30 days
                            } else {
                                // If remember me is not checked, delete the cookie if it exists
                                if(isset($_COOKIE['remember_user'])) {
                                    setcookie('remember_user', '', time() - 3600, '/');
                                }
                            }
                            
                            // Role-based redirection
                            switch($role) {
                                case 'admin':
                                    header("location: admin_dashboard.php");
                                    break;
                                case 'manager':
                                    header("location: manager_dashboard.php");
                                    break;
                                case 'employee':
                                    header("location: employee_dashboard.php");
                                    break;
                                case 'accountant':
                                    header("location: accountant_dashboard.php");
                                    break;
                                case 'hr':
                                    header("location: hr_dashboard.php");
                                    break;
                                default:
                                    header("location: home.php");
                            }
                            exit;
                        } else{
                            // Password is not valid
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
    <title>Login - Firm Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .login-card {
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
        
        .login-btn {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }
        
        .social-btn {
            transition: all 0.2s ease;
            border: 1px solid #e5e7eb;
        }
        
        .social-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            pointer-events: none;
        }
        
        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            top: 20%;
            right: 10%;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            bottom: 10%;
            left: 20%;
            animation-delay: 4s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(10deg); }
        }
        
        /* Compact mobile styles */
        @media (max-width: 640px) {
            .login-card {
                margin: 1rem;
                padding: 1.5rem !important;
            }
            .text-2xl { font-size: 1.5rem !important; }
            .py-3 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
            .mb-6 { margin-bottom: 1rem !important; }
            .mb-4 { margin-bottom: 0.75rem !important; }
            .space-y-4 > :not([hidden]) ~ :not([hidden]) { margin-top: 0.75rem !important; }
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <!-- Floating Background Shapes -->
    <div class="floating-shapes">
        <div class="shape w-32 h-32 bg-white rounded-full"></div>
        <div class="shape w-24 h-24 bg-yellow-300 rounded-full"></div>
        <div class="shape w-16 h-16 bg-pink-300 rounded-full"></div>
    </div>

    <!-- Main Login Container -->
    <div class="w-full max-w-sm">
        <!-- Login Card -->
        <div class="login-card rounded-2xl shadow-2xl p-6 relative z-10">
            <!-- Header -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-100 mb-3">
                    <i class="fas fa-building text-indigo-600 text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-1">Jewel Entry</h1>
                <p class="text-gray-500 text-sm">Sign in to continue</p>
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
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-4">
                <!-- Username/Mobile -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username or Mobile Number</label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="username" value="<?php echo $username; ?>"
                            class="input-field w-full pl-10 pr-4 py-2.5 rounded-lg focus:outline-none text-sm"
                            placeholder="Enter username or mobile number">
                    </div>
                    <?php if(!empty($username_err)): ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo $username_err; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Password -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-sm font-medium text-gray-700">Password</label>
                        <a href="forgot_password.php" class="text-xs text-indigo-600 hover:text-indigo-500">Forgot Password?</a>
                    </div>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="password" name="password" id="password"
                            class="input-field w-full pl-10 pr-10 py-2.5 rounded-lg focus:outline-none text-sm"
                            placeholder="Enter password">
                        <button type="button" id="togglePassword" 
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye text-sm"></i>
                        </button>
                    </div>
                    <?php if(!empty($password_err)): ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo $password_err; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center">
                        <input type="checkbox" name="remember" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-gray-600">Remember me</span>
                    </label>
                </div>
                
                <!-- Login Button -->
                <button type="submit" class="login-btn w-full py-2.5 rounded-lg text-white font-medium text-sm">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
            
            <!-- Divider -->
            <div class="flex items-center my-4">
                <div class="flex-1 border-t border-gray-200"></div>
                <span class="px-3 text-xs text-gray-500">OR</span>
                <div class="flex-1 border-t border-gray-200"></div>
            </div>
            
            <!-- Social Login -->
            <div class="grid grid-cols-1 gap-3 mb-4">
                <button class="social-btn flex items-center justify-center py-2 rounded-lg bg-white hover:bg-gray-50">
                    <i class="fab fa-whatsapp text-green-500 mr-2 text-sm"></i>
                    <span class="text-sm text-gray-700">Sales/Supports</span>
                </button>
               
            </div>
            
            <!-- Register Link -->
            <div class="text-center">
                <p class="text-xs text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="text-indigo-600 hover:text-indigo-500 font-medium">Register here</a>
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-4">
            <p class="text-xs text-white opacity-75">© 2025 Firm Management System</p>
        </div>
    </div>
    
    <script>
        // Password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Form validation enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.input-field');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });
        });
    </script>
</body>
</html>