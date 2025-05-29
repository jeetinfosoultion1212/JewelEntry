
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Jewelry Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #fff7ed; /* Consistent light orange/peach background */
            min-height: 100vh;
        }
        .gradient-gold {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }
        .gradient-purple { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .header-glass { 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .bottom-nav { 
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(0, 0, 0, 0.06);
        }
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-7px); } 
        }
        .profile-card-gradient {
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%); /* slate-50 to indigo-50 */
        }
        .staff-card-gradient {
             background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%); /* sky-50 to emerald-50 */
        }
        .form-input {
            width: 100%;
            margin-top: 0.25rem;
            padding: 0.6rem 0.8rem; /* Slightly more padding */
            font-size: 0.875rem;
            border: 1px solid #d1d5db; /* gray-300 */
            border-radius: 0.375rem; /* rounded-md */
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            background-color: rgba(255, 255, 255, 0.7);
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .form-input:focus {
            outline: none;
            border-color: #8b5cf6; /* purple-500 */
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3); /* purple-500 focus ring */
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #4b5563; /* gray-600 */
        }
        .btn-primary {
            background-color: #6d28d9; /* purple-700 */
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        .btn-primary:hover {
            background-color: #5b21b6; /* purple-800 */
        }
        .btn-secondary {
            background-color: #e5e7eb; /* gray-200 */
            color: #374151; /* gray-700 */
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        .btn-secondary:hover {
            background-color: #d1d5db; /* gray-300 */
        }
        .btn-danger {
            background-color: #dc2626; /* red-600 */
            color: white;
        }
        .btn-danger:hover {
             background-color: #b91c1c; /* red-700 */
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header-glass sticky top-0 z-50 shadow-md">
        <div class="px-3 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-9 h-9 gradient-gold rounded-xl flex items-center justify-center shadow-lg floating">
                        <i class="fas fa-gem text-white text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-gray-800">Golden Palace</h1>
                        <p class="text-xs text-gray-600 font-medium">Premium Jewelry</p>
                    </div>
                </div>
                 <div class="flex items-center space-x-2">
                    <div class="text-right">
                        <p class="text-sm font-bold text-gray-800">John Manager</p>
                        <p class="text-xs text-purple-600 font-medium">Store Manager</p>
                    </div>
                    <div class="w-9 h-9 gradient-purple rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-user-crown text-white text-sm"></i>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="px-3 pb-24 pt-4">
        <!-- Section 1: Firm Details -->
        <div class="profile-card-gradient rounded-xl p-4 shadow-lg mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Firm Profile</h2>
            
            <div class="flex flex-col items-center mb-4">
                <img id="firmLogoPreview" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md mb-2" src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIj48Y2lyY2xlIGZpbGw9IiNDQ0MiIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIvPjxwYXRoIGZpbGw9IiNGRkYiIGQ9Ik01MCA1OWMtOC4yODQgMC0xNS02LjcxNi0xNS0xNXM2LjcxNi0xNSAxNS0xNCAxNSA2LjcxNiAxNSAxNS02LjcxNiAxNS0xNSAxNXptMC0yNWMtNS41MjMgMC0xMCA0LjQ3Ny0xMCAxMHM0LjQ3NyAxMCAxMCAxMCAxMC00LjQ3NyAxMC0xMC00LjQ3Ny0xMC0xMC0xMHoiLz48cGF0aCBmaWxsPSIjRkZGIiBkPSJNNzIgNzJoLTEuNWMtMS4xNi0xLjMxLS44My0yLjY2LS41LTMuNWwuNjYtMS42N2MtLjQ5LTEuMy0xLjYxLTIuODEtMy4zNi0zLjg2YTE3LjQ0IDE3LjQ0IDAgMCAxLTIuNzQtMS42NWMtNC4zMy0yLjA1LTEwLjM2LTIuMDUtMTQuNzYgMCAxLjAxMy40NDMgMS45MjIgMS4wMzQgMi43MyAxLjY1IDIgMS4xNiAzLjE5IDIuNzkgMy4zOCA0LjA0bC42NiAxLjY2Yy4zMy44NC42NiAyLjE5LS41IDMuNWgtMS41Yy0xMSAwLTIwLjI1LTguNjMtMjAuMjUtMTkuNUMzMSA0MS4yNyA0MC4yNyAzMiA1MS41IDMyIDYyLjczIDMyIDcyIDQxLjI3IDcyIDUyLjVjMCAxMC44My05LjI5IDE5LjUtMjAgMTkuNWgtMXoiLz48L2c+PC9zdmc+" alt="Firm Logo">
                <label for="firmLogoInput" class="text-xs bg-purple-600 text-white px-4 py-1.5 rounded-md hover:bg-purple-700 cursor-pointer shadow transition-colors">
                    <i class="fas fa-camera mr-1"></i> Change Logo
                </label>
                <input type="file" id="firmLogoInput" class="hidden" accept="image/*">
            </div>

            <form id="firmDetailsForm" class="space-y-3">
                <div>
                    <label for="firmName" class="form-label">Firm Name</label>
                    <input type="text" id="firmName" class="form-input" placeholder="e.g., Golden Palace Jewellers">
                </div>
                <div>
                    <label for="firmTagline" class="form-label">Tagline (Optional)</label>
                    <input type="text" id="firmTagline" class="form-input" placeholder="e.g., Exquisite Craftsmanship Since 1980">
                </div>
                <div>
                    <label for="firmAddress1" class="form-label">Address Line 1</label>
                    <input type="text" id="firmAddress1" class="form-input" placeholder="Shop No / Building">
                </div>
                <div>
                    <label for="firmAddress2" class="form-label">Address Line 2 (Optional)</label>
                    <input type="text" id="firmAddress2" class="form-input" placeholder="Street / Landmark">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="firmCity" class="form-label">City</label>
                        <input type="text" id="firmCity" class="form-input" placeholder="e.g., Mumbai">
                    </div>
                    <div>
                        <label for="firmPincode" class="form-label">Pincode</label>
                        <input type="text" id="firmPincode" class="form-input" placeholder="e.g., 400001">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="firmPhone" class="form-label">Contact Phone</label>
                        <input type="tel" id="firmPhone" class="form-input" placeholder="e.g., +919876543210">
                    </div>
                    <div>
                        <label for="firmEmail" class="form-label">Contact Email</label>
                        <input type="email" id="firmEmail" class="form-input" placeholder="e.g., contact@goldenpalace.com">
                    </div>
                </div>
                <div>
                    <label for="firmGST" class="form-label">GST Number</label>
                    <input type="text" id="firmGST" class="form-input" placeholder="e.g., 27ABCDE1234F1Z5">
                </div>
                <button type="submit" class="btn-primary w-full mt-3 py-2.5">
                    <i class="fas fa-save mr-1"></i> Save Firm Details
                </button>
            </form>
        </div>

        <!-- Section 2: Staff Management -->
        <div class="staff-card-gradient rounded-xl p-4 shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Staff Members</h2>
                <button id="addNewStaffBtn" class="btn-primary text-sm px-3 py-1.5">
                    <i class="fas fa-plus mr-1"></i> Add Staff
                </button>
            </div>
            <div id="staffListContainer" class="space-y-2">
                <!-- Staff items will be injected here -->
                <p class="text-sm text-gray-600 text-center py-4 hidden" id="noStaffMessage">No staff members added yet.</p>
            </div>
        </div>
    </div>

    <!-- Staff Add/Edit Modal -->
    <div id="staffModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-lg p-5 shadow-2xl w-full max-w-md transform transition-all scale-95 opacity-0" id="staffModalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 id="staffModalTitle" class="text-lg font-bold text-gray-800">Add Staff Member</h3>
                <button id="closeStaffModalBtn" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="staffForm" class="space-y-3">
                <input type="hidden" id="staffEditIndex">
                <div>
                    <label for="staffName" class="form-label">Full Name</label>
                    <input type="text" id="staffName" class="form-input" required>
                </div>
                <div>
                    <label for="staffRole" class="form-label">Role</label>
                    <input type="text" id="staffRole" class="form-input" placeholder="e.g., Sales Manager, Goldsmith" required>
                </div>
                <div>
                    <label for="staffPhone" class="form-label">Phone Number (Optional)</label>
                    <input type="tel" id="staffPhone" class="form-input">
                </div>
                <div>
                    <label for="staffEmail" class="form-label">Email (Optional)</label>
                    <input type="email" id="staffEmail" class="form-input">
                </div>
                <div class="flex justify-end space-x-3 pt-3">
                    <button type="button" id="cancelStaffModalBtn" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i> Save Staff</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Bottom Navigation -->
    <nav class="bottom-nav fixed bottom-0 left-0 right-0 shadow-xl">
        <div class="px-4 py-2">
            <div class="flex justify-around">
                 <a href="home.php" data-nav-id="home" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Home</span>
                </a>
                <button data-nav-id="search" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-search text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Search</span>
                </button>
                <button data-nav-id="add" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Add</span>
                </button>
                <button data-nav-id="alerts_nav" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Alerts</span>
                </button>
                <a href="profile.php" data-nav-id="profile" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Profile</span>
                </a>
            </div>
        </div>
    </nav>

    <script type="module" src="profile.js"></script>
</body>
</html>
