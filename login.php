<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include database config
session_start();
require 'config/config.php';

// Check if the user is already logged in, if yes then redirect to home page
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: home.php");
    exit;
}

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

    // Check if username is empty
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
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
        $sql = "SELECT id, username, password, role, firmID FROM Firm_Users WHERE username = ?";

        if($stmt = mysqli_prepare($conn, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);

            // Set parameters
            $param_username = $username;

            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)){
                // Store result
                mysqli_stmt_store_result($stmt);

                // Check if username exists, if yes then verify password
                if(mysqli_stmt_num_rows($stmt) == 1){
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $role, $firmID);
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

                            // Redirect user to appropriate dashboard based on role
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
    <title>Login - Jewel Entry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fef7cd; /* Light peach/orange background */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-card {
            background: white;
            border-radius: 20px; /* More rounded corners */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); /* Larger shadow */
            padding: 2rem;
            width: 100%;
            max-width: 380px;
            position: relative;
            z-index: 10;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.25rem; /* Increased margin */
        }
        
        .input-field {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem; /* Increased padding and space for icon */
            font-size: 0.95rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem; /* More rounded input fields */
            background-color: #f9fafb; /* Light gray background for inputs */
            transition: all 0.2s ease;
            color: #374151;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.03); /* Subtle inner shadow */
        }
        
        .input-field::placeholder {
            color: #9ca3af;
        }

        .input-field:focus {
            outline: none;
            border-color: #fbbf24; /* Orange focus border */
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.2), inset 0 1px 2px rgba(0,0,0,0.03); /* Orange glow */
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
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
                padding: 1.5rem;
            }
            .text-2xl { font-size: 1.75rem; } /* Slightly smaller for mobile */
            .mb-6 { margin-bottom: 1.25rem; }
            .mb-4 { margin-bottom: 1rem; }
        }
    </style>
</head>
<body>
    <!-- Main Login Container -->
    <div class="w-full max-w-sm">
        <!-- Login Card -->
        <div class="login-card">
            <!-- Language Dropdown -->
            <div class="language-dropdown">
                <span>English</span>
                <i class="fas fa-chevron-down"></i>
            </div>

            <!-- Header -->
            <div class="text-center mb-6 mt-10">
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
                <!-- Username/Mobile -->
                <div class="input-group">
                    <i class="fas fa-phone input-icon"></i>
                    <input type="text" name="username" id="usernameInput" value="<?php echo $username; ?>"
                        class="input-field"
                        placeholder="9898562314">
                    <?php if(!empty($username_err)): ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo $username_err; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Password -->
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="password"
                        class="input-field pr-10"
                        placeholder="••••••••••">
                    <button type="button" id="togglePassword" 
                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if(!empty($password_err)): ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo $password_err; ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Forgot Password -->
                <div class="text-right mb-5">
                    <a href="forgot_password.php" class="text-sm text-gray-500 hover:text-gray-700 font-medium">Forgot Password?</a>
                </div>
                
                <!-- Login Button -->
                <button type="submit" class="login-btn w-full mb-5">
                    Login
                </button>
            </form>
            
            <!-- Divider -->
            <div class="flex items-center my-4">
                <div class="flex-1 border-t border-gray-200"></div>
                <span class="px-3 text-xs text-gray-500"></span>
                <div class="flex-1 border-t border-gray-200"></div>
            </div>
            
            <!-- Register Link -->
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="text-yellow-600 hover:text-yellow-700 font-semibold">Sign up</a>
                </p>
            </div>
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

    </script>
</body>
</html>