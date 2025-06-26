<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Jewelry Store</title>
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
            overflow-x: hidden;
            padding: 0;
        }

        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            min-width: 280px;
            position: relative;
            z-index: 10;
            padding: 2rem;
            box-sizing: border-box;
        }

        @media (max-width: 640px) {
            .register-card {
                max-width: 350px;
                padding: 1.5rem;
            }
        }

        .form-group {
            position: relative;
            margin-bottom: 1rem;
        }

        .form-input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.75rem;
            font-size: 0.875rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            background-color: white;
            transition: all 0.2s ease;
            color: #374151;
        }

        .form-input:focus {
            outline: none;
            border-color: #fbbf24;
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
            transform: translateY(-1px);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.8rem;
            transition: color 0.2s ease;
        }

        .form-input:focus + .input-icon {
            color: #fbbf24;
        }

        .form-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: 0.25rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .btn-primary {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0px);
        }

        .floating-icon {
            animation: floating 3s ease-in-out infinite;
        }

        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        .error-message {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-top: 0.6rem;
            text-align: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .mobile-prefix {
            position: absolute;
            left: 2.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 0.8rem;
            font-weight: 500;
            pointer-events: none;
            z-index: 10;
        }

        .mobile-input {
            padding-left: 4.25rem !important;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            font-size: 0.8rem;
            transition: color 0.2s ease;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #6b7280;
        }

        .form-input.has-toggle {
            padding-right: 2.75rem;
        }

        .divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e5e7eb, transparent);
            margin: 1.25rem 0;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="flex items-center justify-center mb-5">
            <div class="w-9 h-9 flex items-center justify-center shadow-md floating-icon mr-2">
                <img src="uploads/logo.png" alt="Jewel Entry Logo" class="h-full w-full object-contain">
            </div>
            <div class="text-left">
                <h1 class="text-lg font-bold text-gray-800 leading-tight">Create Account</h1>
                <p class="text-xs text-gray-600 leading-tight">Jewel Entry</p>
            </div>
        </div>

        <form id="registerForm" action="process_registration.php" method="POST">
            <?php
            if (isset($_SESSION['registration_errors']) && !empty($_SESSION['registration_errors'])) {
                echo '<div class="error-message mb-4">';
                foreach ($_SESSION['registration_errors'] as $error) {
                    echo '<p>' . htmlspecialchars($error) . '</p>';
                }
                echo '</div>';
                unset($_SESSION['registration_errors']); // Clear errors after displaying
            }
            ?>
            <div class="form-group">
                <label for="fullName" class="form-label">Full Name</label>
                <div class="relative">
                    <input type="text" id="fullName" name="fullName" class="form-input" placeholder="Enter your full name" required>
                    <span class="input-icon"><i class="fas fa-user"></i></span>
                </div>
            </div>

            <div class="form-group">
                <label for="mobile" class="form-label">Mobile Number</label>
                <div class="relative">
                    <input type="tel" id="mobile" name="mobile" class="form-input mobile-input" placeholder="Enter 10-digit number" required pattern="[0-9]{10}" maxlength="10">
                    <span class="input-icon"><i class="fas fa-phone"></i></span>
                    <span class="mobile-prefix">+91</span>
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email (Optional)</label>
                <div class="relative">
                    <input type="email" id="email" name="email" class="form-input" placeholder="your@email.com">
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                </div>
            </div>

            <div class="form-group">
                <label for="firmName" class="form-label">Firm Name (Optional)</label>
                <div class="relative">
                    <input type="text" id="firmName" name="firmName" class="form-input" placeholder="Your business name">
                    <span class="input-icon"><i class="fas fa-building"></i></span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="relative">
                    <input type="password" id="password" name="password" class="form-input has-toggle" placeholder="Minimum 6 characters" required minlength="6">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <span class="password-toggle" onclick="togglePassword('password')">
                        <i class="fas fa-eye" id="password-eye"></i>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirmPassword" class="form-label">Confirm Password</label>
                <div class="relative">
                    <input type="password" id="confirmPassword" name="confirmPassword" class="form-input has-toggle" placeholder="Re-enter password" required>
                    <span class="input-icon"><i class="fas fa-shield-alt"></i></span>
                    <span class="password-toggle" onclick="togglePassword('confirmPassword')">
                        <i class="fas fa-eye" id="confirmPassword-eye"></i>
                    </span>
                </div>
            </div>

            <button type="submit" id="registerButton" class="btn-primary">
                <i class="fas fa-user-plus mr-2"></i>Create Account
            </button>
        </form>

        <div class="text-center mt-5">
            <p class="text-sm text-gray-600">
                Already have an account? 
                <a href="login.php" class="font-semibold text-yellow-600 hover:text-yellow-700 transition-colors">Sign In</a>
            </p>
        </div>

        <p class="text-center text-xs text-gray-500 mt-5">© 2024 JewelEntry Software. All rights reserved.</p>
    </div>

    <script>
        // Auto-format mobile number input
        document.getElementById('mobile').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.slice(0, 10);
            }
            e.target.value = value;
        });

        // Password toggle functionality
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(inputId + '-eye');
            
            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // Handle form submission with AJAX
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const form = e.target;
            const formData = new FormData(form);
            const errorMessageDiv = document.querySelector('.error-message');

            // Clear previous error messages
            if (errorMessageDiv) {
                errorMessageDiv.remove();
            }

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to home page on success
                    window.location.href = data.redirect;
                } else {
                    // Display error message
                    const newErrorMessageDiv = document.createElement('div');
                    newErrorMessageDiv.classList.add('error-message', 'mb-4');
                    newErrorMessageDiv.innerHTML = `<p>${data.message}</p>`;
                    form.prepend(newErrorMessageDiv);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const newErrorMessageDiv = document.createElement('div');
                newErrorMessageDiv.classList.add('error-message', 'mb-4');
                newErrorMessageDiv.innerHTML = `<p>An unexpected error occurred. Please try again.</p>`;
                form.prepend(newErrorMessageDiv);
            });
        });
    </script>
</body>
</html>