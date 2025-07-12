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
            background-color: #FFF9F3;
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

        .toast-alert {
            position: fixed;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 0.9rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            display: none;
            cursor: pointer;
            animation: slideDown 0.3s;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translate(-50%, -20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }

        .form-input.invalid {
            border-color: #f87171 !important;
            background-color: #fef2f2;
        }
        .form-input.valid {
            border-color: #4ade80 !important;
            background-color: #f0fdf4;
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
            background: linear-gradient(to right, #F97316 0%, #EA580C 100%);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.15);
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-primary:hover {
            background: linear-gradient(to right, #EA580C 0%, #C2410C 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.18);
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
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(255,249,243,0.95); z-index:9999; align-items:center; justify-content:center; flex-direction:column;">
        <div class="flex flex-col items-center justify-center h-full w-full">
            <div class="mb-6 animate-bounce">
                <img src="../uploads/logo.png" alt="Jewel Entry Logo" class="h-20 w-20 object-contain rounded-full shadow-lg">
            </div>
            <div class="mb-4">
                <svg class="animate-spin h-10 w-10 text-orange-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-orange-600 mb-2 animate-pulse">Creating your account...</h2>
            <p class="text-lg text-gray-700 mb-2">Welcome to <span class="font-semibold text-orange-500">Jewel Entry</span>!</p>
            <div class="mt-4 flex gap-1">
                <span class="inline-block w-2 h-2 bg-orange-400 rounded-full animate-bounce" style="animation-delay:0s"></span>
                <span class="inline-block w-2 h-2 bg-orange-500 rounded-full animate-bounce" style="animation-delay:0.1s"></span>
                <span class="inline-block w-2 h-2 bg-orange-600 rounded-full animate-bounce" style="animation-delay:0.2s"></span>
            </div>
        </div>
    </div>
    <div class="register-card">
        <div class="flex items-center justify-center mb-5">
            <div class="w-9 h-9 flex items-center justify-center shadow-md floating-icon mr-2">
                <img src="../uploads/logo.png" alt="Jewel Entry Logo" class="h-full w-full object-contain">
            </div>
            <div class="text-left">
                <h1 class="text-lg font-bold text-gray-800 leading-tight">Create Account</h1>
                <p class="text-xs text-gray-600 leading-tight">Jewel Entry</p>
            </div>
        </div>

        <form id="registerForm" action="../api/process_registration" method="POST">
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
                <label for="fullName" class="form-label">Full Name <span style='color:red'>*</span></label>
                <div class="relative">
                    <input type="text" id="fullName" name="fullName" class="form-input" placeholder="Enter your full name" required>
                    <span class="input-icon"><i class="fas fa-user"></i></span>
                </div>
            </div>

            <div class="form-group">
                <label for="mobile" class="form-label">Mobile Number <span style='color:red'>*</span></label>
                <div class="relative">
                    <input type="tel" id="mobile" name="mobile" class="form-input mobile-input" placeholder="Enter 10-digit number" required pattern="[0-9]{10}" maxlength="10">
                    <span class="input-icon"><i class="fas fa-phone"></i></span>
                    <span class="mobile-prefix">+91</span>
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email <span style='color:red'>*</span></label>
                <div class="relative">
                    <input type="email" id="email" name="email" class="form-input" placeholder="your@email.com" autocomplete="email" inputmode="email" required>
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                </div>
            </div>

            <div class="form-group">
                <label for="firmName" class="form-label">Firm Name <span style='color:red'>*</span></label>
                <div class="relative">
                    <input type="text" id="firmName" name="firmName" class="form-input" placeholder="Your business name" required>
                    <span class="input-icon"><i class="fas fa-building"></i></span>
                </div>
            </div>

            <div class="divider"></div>

            <div class="form-group">
                <label for="password" class="form-label">Password <span style='color:red'>*</span></label>
                <div class="relative">
                    <input type="password" id="password" name="password" class="form-input has-toggle" placeholder="Minimum 6 characters" required minlength="6">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <span class="password-toggle" onclick="togglePassword('password')">
                        <i class="fas fa-eye" id="password-eye"></i>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirmPassword" class="form-label">Confirm Password <span style='color:red'>*</span></label>
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
                <a href="/JewelEntry/auth/login" class="font-semibold text-yellow-600 hover:text-yellow-700 transition-colors">Sign In</a>
            </p>
        </div>

        <p class="text-center text-xs text-gray-500 mt-5">© 2024 JewelEntry Software. All rights reserved.</p>
    </div>

    <div id="toastAlert" class="toast-alert"></div>

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

        // Handle form submission with AJAX and show loading overlay
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const form = e.target;
            const formData = new FormData(form);
            const errorMessageDiv = document.querySelector('.error-message');

            // Clear previous error messages
            if (errorMessageDiv) {
                errorMessageDiv.remove();
            }

            // Validate all fields before AJAX, show toast for first error
            if (!validateFullName()) { showToast('Full name is required.'); return; }
            if (!validateMobile()) { showToast('Enter a valid 10-digit mobile number.'); return; }
            if (!validateEmail()) { showToast('Enter a valid email address.'); return; }
            if (!validateFirmName()) { showToast('Firm name is required.'); return; }
            if (!validatePassword()) { showToast('Password must be at least 6 characters.'); return; }
            if (!validateConfirmPassword()) { showToast('Passwords do not match.'); return; }

            // Show loading overlay and hide form
            document.getElementById('loadingOverlay').style.display = 'flex';
            form.style.display = 'none';

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingOverlay').style.display = 'none';
                form.style.display = '';
                if (data.success) {
                    // Redirect to home page on success
                    window.location.href = '/JewelEntry/pages/home';
                } else {
                    // Display error message
                    const newErrorMessageDiv = document.createElement('div');
                    newErrorMessageDiv.classList.add('error-message', 'mb-4');
                    newErrorMessageDiv.innerHTML = `<p>${data.message}</p>`;
                    form.prepend(newErrorMessageDiv);
                }
            })
            .catch(error => {
                document.getElementById('loadingOverlay').style.display = 'none';
                form.style.display = '';
                console.error('Error:', error);
                const newErrorMessageDiv = document.createElement('div');
                newErrorMessageDiv.classList.add('error-message', 'mb-4');
                newErrorMessageDiv.innerHTML = `<p>An unexpected error occurred. Please try again.</p>`;
                form.prepend(newErrorMessageDiv);
            });
        });

        // Field validation functions and event listeners
        function validateFullName() {
            const input = document.getElementById('fullName');
            if (!input.value.trim()) {
                input.classList.add('invalid');
                return false;
            } else {
                input.classList.remove('invalid');
                return true;
            }
        }
        function validateMobile() {
            const input = document.getElementById('mobile');
            const value = input.value.trim();
            if (!/^[0-9]{10}$/.test(value)) {
                input.classList.add('invalid');
                return false;
            } else {
                input.classList.remove('invalid');
                return true;
            }
        }
        function validateEmail() {
            const input = document.getElementById('email');
            const value = input.value.trim();
            // Simple email regex
            if (!/^\S+@\S+\.\S+$/.test(value)) {
                input.classList.add('invalid');
                return false;
            } else {
                input.classList.remove('invalid');
                return true;
            }
        }
        function validateFirmName() {
            const input = document.getElementById('firmName');
            if (!input.value.trim()) {
                input.classList.add('invalid');
                return false;
            } else {
                input.classList.remove('invalid');
                return true;
            }
        }
        function validatePassword() {
            const input = document.getElementById('password');
            if (input.value.length < 6) {
                input.classList.add('invalid');
                return false;
            } else {
                input.classList.remove('invalid');
                return true;
            }
        }
        function validateConfirmPassword() {
            const input = document.getElementById('confirmPassword');
            const password = document.getElementById('password').value;
            if (input.value !== password || !input.value) {
                input.classList.add('invalid');
                return false;
            } else {
                input.classList.remove('invalid');
                return true;
            }
        }

        function showToast(message) {
            const toast = document.getElementById('toastAlert');
            toast.textContent = message;
            toast.style.display = 'block';
            clearTimeout(window.toastTimeout);
            window.toastTimeout = setTimeout(() => {
                toast.style.display = 'none';
            }, 3500);
        }
        document.getElementById('toastAlert').onclick = function() {
            this.style.display = 'none';
        };
    </script>
</body>
</html>