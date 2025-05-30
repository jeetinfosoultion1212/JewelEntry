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
            background: linear-gradient(135deg, #fef7cd 0%, #fff7ed 100%);
            min-height: 100vh;
            padding: 1rem 0.75rem;
        }
        .gradient-gold {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }
        .form-group {
            position: relative;
            margin-bottom: 1rem;
        }
        .form-input {
            width: 100%; 
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            font-size: 0.9rem; 
            border: 1.5px solid #e5e7eb; 
            border-radius: 0.75rem;
            background-color: white;
            transition: all 0.2s ease;
            color: #374151;
        }
        .form-input:focus { 
            outline: none; 
            border-color: #8b5cf6; 
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            transform: translateY(-1px);
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.875rem;
        }
        .form-label { 
            font-size: 0.75rem; 
            font-weight: 600; 
            color: #4b5563; 
            margin-bottom: 0.375rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white; 
            padding: 1rem 1.5rem; 
            border-radius: 0.75rem;
            font-size: 0.95rem; 
            font-weight: 600; 
            transition: all 0.3s ease;
            display: flex; 
            align-items: center; 
            justify-content: center;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover { 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
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
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            margin-top: 0.75rem;
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
            font-size: 0.875rem;
            font-weight: 500;
            pointer-events: none;
            z-index: 10;
        }
        .mobile-input {
            padding-left: 4.5rem !important;
        }
        .form-container {
            max-width: 380px;
            margin: 0 auto;
        }
        .compact-header {
            text-align: center;
            margin-bottom: 1rem;
        }
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            font-size: 0.875rem;
            transition: color 0.2s ease;
            z-index: 10;
        }
        .password-toggle:hover {
            color: #6b7280;
        }
        .form-input.has-toggle {
            padding-right: 2.75rem;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="compact-header">
            <div class="flex items-center justify-center mb-6">
                <div class="w-8 h-8 gradient-gold rounded-lg flex items-center justify-center shadow-md floating-icon mr-3">
                    <i class="fas fa-gem text-white text-sm"></i>
                </div>
                <div class="text-left">
                    <h1 class="text-lg font-bold text-gray-800 leading-tight">Create Account</h1>
                    <p class="text-xs text-gray-600 leading-tight">Golden Palace Management</p>
                </div>
            </div>
        </div>

        <div class="bg-white/80 backdrop-blur-sm premium-card p-6 rounded-xl shadow-xl">
            <form id="registerForm">
                <div class="form-group">
                    <label for="fullName" class="form-label">Full Name</label>
                    <div class="relative">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" id="fullName" name="fullName" class="form-input" placeholder="Enter your full name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="mobile" class="form-label">Mobile Number</label>
                    <div class="relative">
                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                        <span class="mobile-prefix">+91</span>
                        <input type="tel" id="mobile" name="mobile" class="form-input mobile-input" placeholder="Enter 10-digit number" required pattern="[0-9]{10}" maxlength="10">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email (Optional)</label>
                    <div class="relative">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-input" placeholder="your@email.com">
                    </div>
                </div>

                <div class="form-group">
                    <label for="firmName" class="form-label">Firm Name (Optional)</label>
                    <div class="relative">
                        <span class="input-icon"><i class="fas fa-building"></i></span>
                        <input type="text" id="firmName" name="firmName" class="form-input" placeholder="Your business name">
                    </div>
                </div>

                <div class="divider"></div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="relative">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-input has-toggle" placeholder="Minimum 6 characters" required minlength="6">
                        <span class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye" id="password-eye"></i>
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <div class="relative">
                        <span class="input-icon"><i class="fas fa-shield-alt"></i></span>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-input has-toggle" placeholder="Re-enter password" required>
                        <span class="password-toggle" onclick="togglePassword('confirmPassword')">
                            <i class="fas fa-eye" id="confirmPassword-eye"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" id="registerButton" class="btn-primary w-full mt-4">
                    <i class="fas fa-user-plus mr-2"></i>Create Account
                </button>
            </form>

            <div id="errorMessageContainer"></div>

            <div class="text-center mt-4">
                <p class="text-xs text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="font-semibold text-purple-600 hover:text-purple-800 transition-colors">Sign In</a>
                </p>
            </div>
        </div>

        <p class="text-center text-xs text-gray-500 mt-4">© 2024 JewelEntry Software. All rights reserved.</p>
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

        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fullName = document.getElementById('fullName').value.trim();
            const mobile = document.getElementById('mobile').value.trim();
            const email = document.getElementById('email').value.trim();
            const firmName = document.getElementById('firmName').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Clear previous errors
            clearError();
            
            // Validation
            if (!fullName) {
                showError('Please enter your full name');
                return;
            }
            
            if (mobile.length !== 10 || !/^\d+$/.test(mobile)) {
                showError('Please enter a valid 10-digit mobile number');
                return;
            }
            
            if (password.length < 6) {
                showError('Password must be at least 6 characters long');
                return;
            }
            
            if (password !== confirmPassword) {
                showError('Passwords do not match');
                return;
            }
            
            if (email && !isValidEmail(email)) {
                showError('Please enter a valid email address');
                return;
            }

            // Disable submit button and show loading state
            const submitButton = document.getElementById('registerButton');
            const originalContent = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Account...';
            submitButton.disabled = true;
            
            // Create FormData object
            const formData = new FormData();
            formData.append('fullName', fullName);
            formData.append('mobile', mobile);
            formData.append('email', email);
            formData.append('firmName', firmName);
            formData.append('password', password);
            
            // Send AJAX request
            fetch('process_registration.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    // Redirect after a short delay
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else {
                    showError(data.message);
                    // Reset button state
                    submitButton.innerHTML = originalContent;
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                showError('An error occurred. Please try again.');
                // Reset button state
                submitButton.innerHTML = originalContent;
                submitButton.disabled = false;
            });
        });
        
        function showError(message) {
            const errorContainer = document.getElementById('errorMessageContainer');
            errorContainer.innerHTML = `<div class="error-message"><i class="fas fa-exclamation-triangle mr-2"></i>${message}</div>`;
        }
        
        function clearError() {
            document.getElementById('errorMessageContainer').innerHTML = '';
        }
        
        function showSuccess(message) {
            const button = document.getElementById('registerButton');
            button.innerHTML = '<i class="fas fa-check mr-2"></i>Account Created!';
            button.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            button.disabled = true;
        }
        
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Password toggle functionality
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const eyeIcon = document.getElementById(fieldId + '-eye');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                eyeIcon.className = 'fas fa-eye';
            }
        }

        // Add subtle focus animations
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>