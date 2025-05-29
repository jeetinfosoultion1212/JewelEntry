
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Jewelry Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #fff7ed; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .gradient-gold {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }
        .input-group {
            position: relative;
        }
        .form-input {
            width: 100%; 
            margin-top: 0.25rem; 
            padding: 0.75rem 1rem 0.75rem 2.5rem; /* Adjusted padding for icon */
            font-size: 0.875rem; 
            border: 1px solid #d1d5db; 
            border-radius: 0.5rem; /* More rounded */
            background-color: white;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            color: #374151;
        }
        .form-input:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3); }
        .input-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(calc(-50% + 0.125rem)); /* Adjust for label and input margin */
            color: #9ca3af; /* gray-400 */
            pointer-events: none; /* Make sure icon doesn't interfere with input clicks */
        }
        .form-label { font-size: 0.8rem; font-weight: 500; color: #4b5563; }
        .btn-primary {
            background-color: #6d28d9; color: white; padding: 0.6rem 1.25rem; border-radius: 0.5rem; /* More rounded */
            font-size: 0.9rem; font-weight: 500; transition: background-color 0.2s ease;
            display: flex; align-items: center; justify-content: center;
        }
        .btn-primary:hover { background-color: #5b21b6; }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; }
        .floating-icon {
            animation: floating 3s ease-in-out infinite;
        }
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); } 
        }
         .error-message {
            background-color: #fee2e2; /* red-100 */
            color: #b91c1c; /* red-700 */
            border: 1px solid #fecaca; /* red-300 */
            padding: 0.75rem 1rem;
            border-radius: 0.375rem; /* rounded-md */
            font-size: 0.875rem; /* text-sm */
            margin-top: 1rem;
            text-align: center;
        }
    </style>
</head>
<body class="p-4">
    <div class="w-full max-w-md">
        <div class="flex flex-col items-center mb-6">
            <div class="w-16 h-16 gradient-gold rounded-2xl flex items-center justify-center shadow-lg mb-3 floating-icon">
                <i class="fas fa-gem text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Create Account</h1>
            <p class="text-sm text-gray-600">Join Golden Palace Management</p>
        </div>

        <div class="bg-white p-6 sm:p-8 rounded-xl shadow-xl">
            <form id="registerForm" class="space-y-3">
                <div>
                    <label for="fullName" class="form-label">Full Name</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" id="fullName" name="fullName" class="form-input" placeholder="John Doe" required>
                    </div>
                </div>
                <div>
                    <label for="firmName" class="form-label">Firm Name (Optional)</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-building"></i></span>
                        <input type="text" id="firmName" name="firmName" class="form-input" placeholder="e.g., Doe Jewellers">
                    </div>
                </div>
                <div>
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required>
                    </div>
                </div>
                <div>
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Minimum 6 characters" required>
                    </div>
                </div>
                <div>
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" placeholder="Re-enter your password" required>
                    </div>
                </div>
                <button type="submit" id="registerButton" class="btn-primary w-full !mt-5">
                    <i class="fas fa-user-plus mr-2"></i>Register
                </button>
            </form>
            <div id="errorMessageContainer" class="mt-4"></div>
            <p class="text-center text-sm text-gray-600 mt-5">
                Already have an account? 
                <a href="login.html" class="font-medium text-purple-600 hover:text-purple-800">Login here</a>
            </p>
        </div>
         <p class="text-center text-xs text-gray-500 mt-8">© 2024 JewelEntry Software. All rights reserved.</p>
    </div>
    <script type="module" src="register.js"></script>
</body>
</html>
