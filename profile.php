<?php
session_start();
require 'config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user and firm details
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.Address, f.City, f.State, 
             f.PostalCode, f.PhoneNumber, f.Email, f.GSTNumber, f.Logo, f.Tagline, f.OwnerName,
             f.PANNumber, f.BISRegistrationNumber, f.BankAccountNumber, f.BankName, f.BankBranch,
             f.IFSCCode, f.AccountType, f.IsGSTRegistered
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();

// Check if user is Super Admin
$isSuperAdmin = ($userInfo['Role'] === 'Super Admin');

// Fetch all staff members if Super Admin
$staffMembers = [];
if ($isSuperAdmin) {
    $staffQuery = "SELECT id, Name, Username, Email, PhoneNumber, Role, Status, CreatedAt, image_path 
                   FROM Firm_Users WHERE FirmID = ? ORDER BY CreatedAt DESC";
    $staffStmt = $conn->prepare($staffQuery);
    $staffStmt->bind_param("i", $firm_id);
    $staffStmt->execute();
    $staffResult = $staffStmt->get_result();
    while ($row = $staffResult->fetch_assoc()) {
        $staffMembers[] = $row;
    }
}

// Split address into two lines if it contains a comma
$addressLines = explode(',', $userInfo['Address'] ?? '', 2);
$addressLine1 = trim($addressLines[0] ?? '');
$addressLine2 = trim($addressLines[1] ?? '');

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($userInfo['FirmName'] ?? 'Jewelry Store'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #fff7ed;
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
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
        }
        .staff-card-gradient {
             background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
        }
        .form-input {
            width: 100%;
            margin-top: 0.25rem;
            padding: 0.6rem 0.8rem;
            font-size: 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
            background-color: rgba(255, 255, 255, 0.7);
            transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .form-input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.3);
        }
        .form-input:disabled {
            background-color: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            color: #4b5563;
        }
        .btn-primary {
            background-color: #6d28d9;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.2s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #5b21b6;
        }
        .btn-primary:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }
        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.2s ease;
            border: none;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background-color: #d1d5db;
        }
        .btn-danger {
            background-color: #dc2626;
            color: white;
        }
        .btn-danger:hover {
             background-color: #b91c1c;
        }
        .btn-success {
            background-color: #059669;
            color: white;
        }
        .btn-success:hover {
            background-color: #047857;
        }
        .btn-warning {
            background-color: #d97706;
            color: white;
        }
        .btn-warning:hover {
            background-color: #b45309;
        }
        .staff-item {
            transition: all 0.3s ease;
        }
        .staff-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-inactive {
            background-color: #fef2f2;
            color: #991b1b;
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        .toast.show {
            transform: translateX(0);
        }
        .toast-success {
            background-color: #059669;
        }
        .toast-error {
            background-color: #dc2626;
        }
        .modal-overlay {
            backdrop-filter: blur(4px);
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header-glass sticky top-0 z-40 shadow-md">
        <div class="px-3 py-2">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-9 h-9 gradient-gold rounded-xl flex items-center justify-center shadow-lg floating">
                        <i class="fas fa-gem text-white text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-gray-800"><?php echo htmlspecialchars($userInfo['FirmName'] ?? 'Golden Palace'); ?></h1>
                        <p class="text-xs text-gray-600 font-medium">Premium Jewelry</p>
                    </div>
                </div>
                 <div class="flex items-center space-x-2">
                    <!-- User Info & Dropdown Toggle -->
                    <div class="relative inline-block text-left">
                        <button id="userDropdownToggle" type="button" class="inline-flex items-center rounded-md text-gray-800 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            <div class="text-right mr-2">
                                <p class="text-sm font-bold"><?php echo htmlspecialchars($userInfo['Name'] ?? 'John Manager'); ?></p>
                                <p class="text-xs text-purple-600 font-medium"><?php echo htmlspecialchars($userInfo['Role'] ?? 'Store Manager'); ?></p>
                            </div>
                            <div class="w-9 h-9 gradient-purple rounded-xl flex items-center justify-center shadow-lg">
                                <?php if (!empty($userInfo['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="Profile" class="w-full h-full rounded-xl object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user-crown text-white text-sm"></i>
                                <?php endif; ?>
                            </div>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="userDropdownMenu" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none hidden" role="menu" aria-orientation="vertical" aria-labelledby="userDropdownToggle">
                            <div class="py-1" role="none">
                                <!-- Add more profile options here if needed -->
                                <a href="logout.php" class="text-gray-700 block px-4 py-2 text-sm hover:bg-gray-100" role="menuitem">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="px-3 pb-24 pt-4">
        <?php if ($isSuperAdmin): ?>
        <!-- Section 1: Firm Details (Super Admin Only) -->
        <div class="profile-card-gradient rounded-xl p-4 shadow-lg mb-6">
            <h2 class="text-sm font-bold text-gray-800 mb-4">
                <i class="fas fa-building mr-2"></i>Firm Profile
            </h2>
            
            <div class="flex flex-col items-center mb-4">
                <img id="firmLogoPreview" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md mb-2" 
                     src="<?php echo !empty($userInfo['Logo']) ? htmlspecialchars($userInfo['Logo']) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIj48Y2lyY2xlIGZpbGw9IiNDQ0MiIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIvPjxwYXRoIGZpbGw9IiNGRkYiIGQ9Ik01MCA1OWMtOC4yODQgMC0xNS02LjcxNi0xNS0xNXM2LjcxNi0xNSAxNS0xNCAxNSA2LjcxNiAxNSAxNS02LjcxNiAxNS0xNSAxNXptMC0yNWMtNS41MjMgMC0xMCA0LjQ3Ny0xMCAxMHM0LjQ3NyAxMCAxMCAxMCAxMC00LjQ3NyAxMC0xMC00LjQ3Ny0xMC0xMC0xMHoiLz48cGF0aCBmaWxsPSIjRkZGIiBkPSJNNzIgNzJoLTEuNWMtMS4xNi0xLjMxLS44My0yLjY2LS41LTMuNWwuNjYtMS42N2MtLjQ5LTEuMy0xLjYxLTIuODEtMy4zNi0zLjg2YTE3LjQ0IDE3LjQ0IDAgMCAxLTIuNzQtMS42NWMtNC4zMy0yLjA1LTEwLjM2LTIuMDUtMTQuNzYgMCAxLjAxMy40NDMgMS45MjIgMS4wMzQgMi43MyAxLjY1IDIgMS4xNiAzLjE5IDIuNzkgMy4zOCA0LjA0bC42NiAxLjY2Yy4zMy44NC42NiAyLjE5LS41IDMuNWgtMS41Yy0xMSAwLTIwLjI1LTguNjMtMjAuMjUtMTkuNUMzMSA0MS4yNyA0MC4yNyAzMiA1MS41IDMyIDYyLjczIDMyIDcyIDQxLjI3IDcyIDUyLjVjMCAxMC44My05LjI5IDE5LjUtMjAgMTkuNWgtMXoiLz48L2c+PC9zdmc+'; ?>" 
                     alt="Firm Logo">
                <label for="firmLogoInput" class="text-xs bg-purple-600 text-white px-4 py-1.5 rounded-md hover:bg-purple-700 cursor-pointer shadow transition-colors">
                    <i class="fas fa-camera mr-1"></i> Change Logo
                </label>
                <input type="file" id="firmLogoInput" class="hidden" accept="image/*">
            </div>

            <form id="firmDetailsForm" class="space-y-3">
                <div>
                    <label for="firmName" class="form-label">Firm Name *</label>
                    <input type="text" id="firmName" class="form-input" placeholder="e.g., Golden Palace Jewellers" 
                           value="<?php echo htmlspecialchars($userInfo['FirmName'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="ownerName" class="form-label">Owner Name</label>
                    <input type="text" id="ownerName" class="form-input" placeholder="e.g., Rajesh Kumar" 
                           value="<?php echo htmlspecialchars($userInfo['OwnerName'] ?? ''); ?>">
                </div>
                <div>
                    <label for="firmTagline" class="form-label">Tagline (Optional)</label>
                    <input type="text" id="firmTagline" class="form-input" placeholder="e.g., Exquisite Craftsmanship Since 1980" 
                           value="<?php echo htmlspecialchars($userInfo['Tagline'] ?? ''); ?>">
                </div>
                <div>
                    <label for="firmAddress1" class="form-label">Address Line 1 *</label>
                    <input type="text" id="firmAddress1" class="form-input" placeholder="Shop No / Building" 
                           value="<?php echo htmlspecialchars($addressLine1); ?>" required>
                </div>
                <div>
                    <label for="firmAddress2" class="form-label">Address Line 2 (Optional)</label>
                    <input type="text" id="firmAddress2" class="form-input" placeholder="Street / Landmark" 
                           value="<?php echo htmlspecialchars($addressLine2); ?>">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label for="firmCity" class="form-label">City *</label>
                        <input type="text" id="firmCity" class="form-input" placeholder="e.g., Mumbai" 
                               value="<?php echo htmlspecialchars($userInfo['City'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="firmState" class="form-label">State</label>
                        <select id="firmState" class="form-input">
                            <option value="">Select State</option>
                            <option value="Andhra Pradesh" <?php echo ($userInfo['State'] === 'Andhra Pradesh') ? 'selected' : ''; ?>>Andhra Pradesh</option>
                            <option value="Arunachal Pradesh" <?php echo ($userInfo['State'] === 'Arunachal Pradesh') ? 'selected' : ''; ?>>Arunachal Pradesh</option>
                            <option value="Assam" <?php echo ($userInfo['State'] === 'Assam') ? 'selected' : ''; ?>>Assam</option>
                            <option value="Bihar" <?php echo ($userInfo['State'] === 'Bihar') ? 'selected' : ''; ?>>Bihar</option>
                            <option value="Chhattisgarh" <?php echo ($userInfo['State'] === 'Chhattisgarh') ? 'selected' : ''; ?>>Chhattisgarh</option>
                            <option value="Goa" <?php echo ($userInfo['State'] === 'Goa') ? 'selected' : ''; ?>>Goa</option>
                            <option value="Gujarat" <?php echo ($userInfo['State'] === 'Gujarat') ? 'selected' : ''; ?>>Gujarat</option>
                            <option value="Haryana" <?php echo ($userInfo['State'] === 'Haryana') ? 'selected' : ''; ?>>Haryana</option>
                            <option value="Himachal Pradesh" <?php echo ($userInfo['State'] === 'Himachal Pradesh') ? 'selected' : ''; ?>>Himachal Pradesh</option>
                            <option value="Jharkhand" <?php echo ($userInfo['State'] === 'Jharkhand') ? 'selected' : ''; ?>>Jharkhand</option>
                            <option value="Karnataka" <?php echo ($userInfo['State'] === 'Karnataka') ? 'selected' : ''; ?>>Karnataka</option>
                            <option value="Kerala" <?php echo ($userInfo['State'] === 'Kerala') ? 'selected' : ''; ?>>Kerala</option>
                            <option value="Madhya Pradesh" <?php echo ($userInfo['State'] === 'Madhya Pradesh') ? 'selected' : ''; ?>>Madhya Pradesh</option>
                            <option value="Maharashtra" <?php echo ($userInfo['State'] === 'Maharashtra') ? 'selected' : ''; ?>>Maharashtra</option>
                            <option value="Manipur" <?php echo ($userInfo['State'] === 'Manipur') ? 'selected' : ''; ?>>Manipur</option>
                            <option value="Meghalaya" <?php echo ($userInfo['State'] === 'Meghalaya') ? 'selected' : ''; ?>>Meghalaya</option>
                            <option value="Mizoram" <?php echo ($userInfo['State'] === 'Mizoram') ? 'selected' : ''; ?>>Mizoram</option>
                            <option value="Nagaland" <?php echo ($userInfo['State'] === 'Nagaland') ? 'selected' : ''; ?>>Nagaland</option>
                            <option value="Odisha" <?php echo ($userInfo['State'] === 'Odisha') ? 'selected' : ''; ?>>Odisha</option>
                            <option value="Punjab" <?php echo ($userInfo['State'] === 'Punjab') ? 'selected' : ''; ?>>Punjab</option>
                            <option value="Rajasthan" <?php echo ($userInfo['State'] === 'Rajasthan') ? 'selected' : ''; ?>>Rajasthan</option>
                            <option value="Sikkim" <?php echo ($userInfo['State'] === 'Sikkim') ? 'selected' : ''; ?>>Sikkim</option>
                            <option value="Tamil Nadu" <?php echo ($userInfo['State'] === 'Tamil Nadu') ? 'selected' : ''; ?>>Tamil Nadu</option>
                            <option value="Telangana" <?php echo ($userInfo['State'] === 'Telangana') ? 'selected' : ''; ?>>Telangana</option>
                            <option value="Tripura" <?php echo ($userInfo['State'] === 'Tripura') ? 'selected' : ''; ?>>Tripura</option>
                            <option value="Uttar Pradesh" <?php echo ($userInfo['State'] === 'Uttar Pradesh') ? 'selected' : ''; ?>>Uttar Pradesh</option>
                            <option value="Uttarakhand" <?php echo ($userInfo['State'] === 'Uttarakhand') ? 'selected' : ''; ?>>Uttarakhand</option>
                            <option value="West Bengal" <?php echo ($userInfo['State'] === 'West Bengal') ? 'selected' : ''; ?>>West Bengal</option>
                            <option value="Andaman and Nicobar Islands" <?php echo ($userInfo['State'] === 'Andaman and Nicobar Islands') ? 'selected' : ''; ?>>Andaman and Nicobar Islands</option>
                            <option value="Chandigarh" <?php echo ($userInfo['State'] === 'Chandigarh') ? 'selected' : ''; ?>>Chandigarh</option>
                            <option value="Dadra and Nagar Haveli and Daman and Diu" <?php echo ($userInfo['State'] === 'Dadra and Nagar Haveli and Daman and Diu') ? 'selected' : ''; ?>>Dadra and Nagar Haveli and Daman and Diu</option>
                            <option value="Delhi" <?php echo ($userInfo['State'] === 'Delhi') ? 'selected' : ''; ?>>Delhi</option>
                            <option value="Jammu and Kashmir" <?php echo ($userInfo['State'] === 'Jammu and Kashmir') ? 'selected' : ''; ?>>Jammu and Kashmir</option>
                            <option value="Ladakh" <?php echo ($userInfo['State'] === 'Ladakh') ? 'selected' : ''; ?>>Ladakh</option>
                            <option value="Lakshadweep" <?php echo ($userInfo['State'] === 'Lakshadweep') ? 'selected' : ''; ?>>Lakshadweep</option>
                            <option value="Puducherry" <?php echo ($userInfo['State'] === 'Puducherry') ? 'selected' : ''; ?>>Puducherry</option>
                        </select>
                    </div>
                    <div>
                        <label for="firmPincode" class="form-label">Pincode *</label>
                        <input type="text" id="firmPincode" class="form-input" placeholder="e.g., 400001" 
                               value="<?php echo htmlspecialchars($userInfo['PostalCode'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="firmPhone" class="form-label">Contact Phone *</label>
                        <input type="tel" id="firmPhone" class="form-input" placeholder="e.g., +919876543210" 
                               value="<?php echo htmlspecialchars($userInfo['PhoneNumber'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="firmEmail" class="form-label">Contact Email</label>
                        <input type="email" id="firmEmail" class="form-input" placeholder="e.g., contact@goldenpalace.com" 
                               value="<?php echo htmlspecialchars($userInfo['Email'] ?? ''); ?>">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="firmPAN" class="form-label">PAN Number</label>
                        <input type="text" id="firmPAN" class="form-input" placeholder="e.g., ABCDE1234F" 
                               value="<?php echo htmlspecialchars($userInfo['PANNumber'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="firmGST" class="form-label">GST Number</label>
                        <input type="text" id="firmGST" class="form-input" placeholder="e.g., 27ABCDE1234F1Z5" 
                               value="<?php echo htmlspecialchars($userInfo['GSTNumber'] ?? ''); ?>">
                    </div>
                </div>
                <div>
                    <label for="bisRegistration" class="form-label">BIS Registration Number</label>
                    <input type="text" id="bisRegistration" class="form-input" placeholder="e.g., BIS123456789" 
                           value="<?php echo htmlspecialchars($userInfo['BISRegistrationNumber'] ?? ''); ?>">
                </div>
                
                <!-- Bank Details Section -->
                <div class="border-t pt-4 mt-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Bank Details</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="bankName" class="form-label">Bank Name</label>
                            <input type="text" id="bankName" class="form-input" placeholder="e.g., State Bank of India" 
                                   value="<?php echo htmlspecialchars($userInfo['BankName'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="bankBranch" class="form-label">Branch</label>
                            <input type="text" id="bankBranch" class="form-input" placeholder="e.g., Mumbai Main Branch" 
                                   value="<?php echo htmlspecialchars($userInfo['BankBranch'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-3">
                        <div>
                            <label for="accountNumber" class="form-label">Account Number</label>
                            <input type="text" id="accountNumber" class="form-input" placeholder="e.g., 1234567890123456" 
                                   value="<?php echo htmlspecialchars($userInfo['BankAccountNumber'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="ifscCode" class="form-label">IFSC Code</label>
                            <input type="text" id="ifscCode" class="form-input" placeholder="e.g., SBIN0000001" 
                                   value="<?php echo htmlspecialchars($userInfo['IFSCCode'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="accountType" class="form-label">Account Type</label>
                        <select id="accountType" class="form-input">
                            <option value="">Select Account Type</option>
                            <option value="Savings" <?php echo ($userInfo['AccountType'] === 'Savings') ? 'selected' : ''; ?>>Savings</option>
                            <option value="Current" <?php echo ($userInfo['AccountType'] === 'Current') ? 'selected' : ''; ?>>Current</option>
                            <option value="Business" <?php echo ($userInfo['AccountType'] === 'Business') ? 'selected' : ''; ?>>Business</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full mt-4 py-2.5">
                    <i class="fas fa-save mr-1"></i> Save Firm Details
                </button>
            </form>
        </div>

        <!-- Section 2: Staff Management (Super Admin Only) -->
        <div class="staff-card-gradient rounded-xl p-4 shadow-lg mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-sm font-bold text-gray-800">
                    <i class="fas fa-users mr-2"></i>Staff Management
                </h2>
                <button id="addNewStaffBtn" class="btn-primary text-sm px-3 py-1.5">
                    <i class="fas fa-plus mr-1"></i> Add Staff
                </button>
            </div>
            <div id="staffListContainer" class="space-y-3">
                <!-- Staff items will be loaded here -->
            </div>
        </div>
        <?php else: ?>
        <!-- Personal Profile for Non-Admin Users -->
        <div class="profile-card-gradient rounded-xl p-4 shadow-lg mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-user mr-2"></i>My Profile
            </h2>
            
            <div class="flex flex-col items-center mb-4">
                <img id="userImagePreview" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md mb-2" 
                     src="<?php echo !empty($userInfo['image_path']) ? htmlspecialchars($userInfo['image_path']) : 'https://via.placeholder.com/150'; ?>" 
                     alt="Profile Picture">
                <label for="userImageInput" class="text-xs bg-purple-600 text-white px-4 py-1.5 rounded-md hover:bg-purple-700 cursor-pointer shadow transition-colors">
                    <i class="fas fa-camera mr-1"></i> Change Photo
                </label>
                <input type="file" id="userImageInput" class="hidden" accept="image/*">
            </div>

            <form id="userProfileForm" class="space-y-3">
                <div>
                    <label for="userName" class="form-label">Full Name *</label>
                    <input type="text" id="userName" class="form-input" placeholder="Enter your full name" 
                           value="<?php echo htmlspecialchars($userInfo['Name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="userEmail" class="form-label">Email</label>
                    <input type="email" id="userEmail" class="form-input" placeholder="Enter your email" 
                           value="<?php echo htmlspecialchars($userInfo['Email'] ?? ''); ?>">
                </div>
                <div>
                    <label for="userPhone" class="form-label">Phone Number</label>
                    <input type="tel" id="userPhone" class="form-input" placeholder="Enter your phone number" 
                           value="<?php echo htmlspecialchars($userInfo['PhoneNumber'] ?? ''); ?>">
                </div>
                <div>
                    <label for="userRole" class="form-label">Role</label>
                    <input type="text" id="userRole" class="form-input" 
                           value="<?php echo htmlspecialchars($userInfo['Role'] ?? ''); ?>" disabled>
                </div>
                
                <button type="submit" class="btn-primary w-full mt-4 py-2.5">
                    <i class="fas fa-save mr-1"></i> Update Profile
                </button>
            </form>
        </div>

        <!-- Change Password Section -->
        <div class="profile-card-gradient rounded-xl p-4 shadow-lg">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-lock mr-2"></i>Change Password
            </h3>
            <form id="changePasswordForm" class="space-y-3">
                <div>
                    <label for="currentPassword" class="form-label">Current Password *</label>
                    <input type="password" id="currentPassword" class="form-input" placeholder="Enter current password" required>
                </div>
                <div>
                    <label for="newPassword" class="form-label">New Password *</label>
                    <input type="password" id="newPassword" class="form-input" placeholder="Enter new password" required>
                </div>
                <div>
                    <label for="confirmPassword" class="form-label">Confirm New Password *</label>
                    <input type="password" id="confirmPassword" class="form-input" placeholder="Confirm new password" required>
                </div>
                
                <button type="submit" class="btn-primary w-full mt-4 py-2.5">
                    <i class="fas fa-key mr-1"></i> Change Password
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Staff Add/Edit Modal -->
    <div id="staffModal" class="fixed inset-0 bg-black bg-opacity-60 modal-overlay flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-lg p-5 shadow-2xl w-full max-w-md transform transition-all scale-95 opacity-0" id="staffModalContent">
            <div class="flex justify-between items-center mb-4">
                <h3 id="staffModalTitle" class="text-lg font-bold text-gray-800">Add Staff Member</h3>
                <button id="closeStaffModalBtn" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="staffForm" class="space-y-3">
                <input type="hidden" id="staffEditId">
                <input type="hidden" id="staffImageData">
                
                <!-- Staff Image Section -->
                <div class="flex flex-col items-center mb-4">
                    <div class="relative w-24 h-24 mb-2">
                        <img id="staffImagePreview" class="w-full h-full rounded-full object-cover border-4 border-white shadow-md" 
                             src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIj48Y2lyY2xlIGZpbGw9IiNDQ0MiIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIvPjxwYXRoIGZpbGw9IiNGRkYiIGQ9Ik01MCA1OWMtOC4yODQgMC0xNS02LjcxNi0xNS0xNXM2LjcxNi0xNSAxNS0xNCAxNSA2LjcxNiAxNSAxNS02LjcxNiAxNS0xNSAxNXptMC0yNWMtNS41MjMgMC0xMCA0LjQ3Ny0xMCAxMHM0LjQ3NyAxMCAxMCAxMCAxMC00LjQ3NyAxMC0xMC00LjQ3Ny0xMC0xMC0xMHoiLz48cGF0aCBmaWxsPSIjRkZGIiBkPSJNNzIgNzJoLTEuNWMtMS4xNi0xLjMxLS44My0yLjY2LS41LTMuNWwuNjYtMS42N2MtLjQ5LTEuMy0xLjYxLTIuODEtMy4zNi0zLjg2YTE3LjQ0IDE3LjQ0IDAgMCAxLTIuNzQtMS42NWMtNC4zMy0yLjA1LTEwLjM2LTIuMDUtMTQuNzYgMCAxLjAxMy40NDMgMS45MjIgMS4wMzQgMi43MyAxLjY1IDIgMS4xNiAzLjE5IDIuNzkgMy4zOCA0LjA0bC42NiAxLjY2Yy4zMy44NC42NiAyLjE5LS41IDMuNWgtMS41Yy0xMSAwLTIwLjI1LTguNjMtMjAuMjUtMTkuNUMzMSA0MS4yNyA0MC4yNyAzMiA1MS41IDMyIDYyLjczIDMyIDcyIDQxLjI3IDcyIDUyLjVjMCAxMC44My05LjI5IDE5LjUtMjAgMTkuNWgtMXoiLz48L2c+PC9zdmc+"; 
                             alt="Staff Image">
                        <div class="absolute bottom-0 right-0">
                            <button type="button" id="staffImageBtn" class="bg-purple-600 text-white p-1.5 rounded-full shadow-lg hover:bg-purple-700">
                                <i class="fas fa-camera text-sm"></i>
                            </button>
                        </div>
                    </div>
                    <div id="staffImageOptions" class="hidden space-x-2">
                        <button type="button" id="staffCameraBtn" class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-md hover:bg-blue-700">
                            <i class="fas fa-camera mr-1"></i> Take Photo
                        </button>
                        <label for="staffImageInput" class="text-xs bg-purple-600 text-white px-3 py-1.5 rounded-md hover:bg-purple-700 cursor-pointer">
                            <i class="fas fa-upload mr-1"></i> Upload
                        </button>
                    </div>
                    <input type="file" id="staffImageInput" class="hidden" accept="image/*">
                    <video id="staffCamera" class="hidden w-full max-w-xs rounded-lg mb-2"></video>
                    <button type="button" id="staffCaptureBtn" class="hidden text-xs bg-green-600 text-white px-3 py-1.5 rounded-md hover:bg-green-700 mb-2">
                        <i class="fas fa-camera mr-1"></i> Capture
                    </button>
                </div>

                <div>
                    <label for="staffName" class="form-label">Full Name *</label>
                    <input type="text" id="staffName" class="form-input" placeholder="Enter full name" required>
                </div>
                <div>
                    <label for="staffPhone" class="form-label">Phone Number *</label>
                    <input type="tel" id="staffPhone" class="form-input" placeholder="Enter 10-digit phone number" pattern="[0-9]{10}" required>
                </div>
                <div>
                    <label for="staffRole" class="form-label">Role *</label>
                    <select id="staffRole" class="form-input" required>
                        <option value="">Select Role</option>
                        <option value="Super Admin">Super Admin</option>
                        <option value="Admin">Admin</option>
                        <option value="Manager">Manager</option>
                        <option value="Sales Executive">Sales Executive</option>
                        <option value="Cashier">Cashier</option>
                        <option value="Goldsmith">Goldsmith</option>
                        <option value="Designer">Designer</option>
                        <option value="Accountant">Accountant</option>
                        <option value="Security">Security</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label for="staffEmail" class="form-label">Email</label>
                    <input type="email" id="staffEmail" class="form-input" placeholder="Enter email address">
                </div>
                <div id="passwordSection">
                    <label for="staffPassword" class="form-label">Password *</label>
                    <input type="password" id="staffPassword" class="form-input" placeholder="Enter password" required>
                </div>
                <div class="flex justify-end space-x-3 pt-3">
                    <button type="button" id="cancelStaffModalBtn" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save mr-1"></i> Save Staff
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="fixed inset-0 bg-black bg-opacity-60 modal-overlay flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white rounded-lg p-5 shadow-2xl w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Reset Password</h3>
                <button id="closeResetPasswordModalBtn" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="resetPasswordForm" class="space-y-3">
                <input type="hidden" id="resetPasswordUserId">
                <p class="text-sm text-gray-600 mb-3">
                    Reset password for: <span id="resetPasswordUserName" class="font-semibold"></span>
                </p>
                <div>
                    <label for="newStaffPassword" class="form-label">New Password *</label>
                    <input type="password" id="newStaffPassword" class="form-input" placeholder="Enter new password" required>
                </div>
                <div>
                    <label for="confirmStaffPassword" class="form-label">Confirm Password *</label>
                    <input type="password" id="confirmStaffPassword" class="form-input" placeholder="Confirm new password" required>
                </div>
                <div class="flex justify-end space-x-3 pt-3">
                    <button type="button" id="cancelResetPasswordBtn" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-key mr-1"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav fixed bottom-0 left-0 right-0 shadow-xl z-30">
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
                    <div class="w-8 h-8 gradient-purple rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-circle text-white text-sm"></i>
                    </div>
                    <span class="text-xs text-purple-600 font-medium">Profile</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Hidden data for JavaScript -->
    <script>
        const userData = {
            isSuperAdmin: <?php echo json_encode($isSuperAdmin); ?>,
            firmId: <?php echo json_encode($firm_id); ?>,
            userId: <?php echo json_encode($user_id); ?>,
            staffMembers: <?php echo json_encode($staffMembers); ?>
        };
    </script>

    <script>
        // Global variables
        let isLoading = false;
        
        // Utility functions
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 3000);
        }

        function setLoading(element, loading) {
            if (loading) {
                element.classList.add('loading');
                element.disabled = true;
            } else {
                element.classList.remove('loading');
                element.disabled = false;
            }
        }

        // API functions
        async function apiCall(url, data = null, method = 'POST') {
            try {
                const options = {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin' // Include cookies for session
                };
                
                if (data && method !== 'GET') {
                    options.body = JSON.stringify(data);
                }
                
                console.log('Making API call to:', url, 'with options:', options); // Debug log
                
                const response = await fetch(url, options);
                console.log('Raw response:', response); // Debug log
                
                // Check if response is empty
                const text = await response.text();
                console.log('Response text:', text); // Debug log
                
                if (!text) {
                    throw new Error('Empty response from server');
                }
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response from server');
                }
                
                if (!response.ok) {
                    throw new Error(result.message || 'Server error');
                }
                
                return result;
            } catch (error) {
                console.error('API Error:', error);
                throw error;
            }
        }

        // File upload function
        async function uploadFile(file, type = 'image') {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', type);
            
            try {
                const response = await fetch('api/upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || 'Upload failed');
                }
                
                return result;
            } catch (error) {
                console.error('Upload Error:', error);
                throw error;
            }
        }

        // Staff management functions
        function loadStaffMembers() {
            const container = document.getElementById('staffListContainer');
            if (!container) return;

            if (userData.staffMembers.length === 0) {
                container.innerHTML = '<p class="text-sm text-gray-600 text-center py-4">No staff members added yet.</p>';
                return;
            }

            const staffHTML = userData.staffMembers.map(staff => `
                <div class="staff-item bg-white rounded-lg p-3 shadow-sm border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-full overflow-hidden bg-gray-200 flex items-center justify-center">
                                ${staff.image_path ? 
                                    `<img src="${staff.image_path}" alt="${staff.Name}" class="w-full h-full object-cover">` : 
                                    `<i class="fas fa-user text-gray-500"></i>`
                                }
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800 text-sm">${staff.Name}</h4>
                                <p class="text-xs text-gray-600">${staff.Role}</p>
                                <p class="text-xs text-gray-500">@${staff.Username}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-1 rounded-full text-xs font-medium ${staff.Status === 'Active' ? 'status-active' : 'status-inactive'}">
                                ${staff.Status}
                            </span>
                            <div class="relative">
                                <button class="text-gray-400 hover:text-gray-600 p-1" onclick="toggleStaffActions(${staff.id})">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div id="staffActions-${staff.id}" class="absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg border border-gray-200 hidden z-10">
                                    <div class="py-1">
                                        <button onclick="editStaff(${staff.id})" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-edit mr-2"></i>Edit Details
                                        </button>
                                        <button onclick="resetStaffPassword(${staff.id}, '${staff.Name}')" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-key mr-2"></i>Reset Password
                                        </button>
                                        <button onclick="toggleStaffStatus(${staff.id}, '${staff.Status}')" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-${staff.Status === 'Active' ? 'pause' : 'play'} mr-2"></i>
                                            ${staff.Status === 'Active' ? 'Deactivate' : 'Activate'}
                                        </button>
                                        ${staff.Role !== 'Super Admin' ? `
                                        <button onclick="deleteStaff(${staff.id}, '${staff.Name}')" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fas fa-trash mr-2"></i>Delete
                                        </button>` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ${staff.Email || staff.PhoneNumber ? `
                    <div class="mt-2 pt-2 border-t border-gray-100">
                        <div class="flex justify-between text-xs text-gray-500">
                            ${staff.Email ? `<span><i class="fas fa-envelope mr-1"></i>${staff.Email}</span>` : '<span></span>'}
                            ${staff.PhoneNumber ? `<span><i class="fas fa-phone mr-1"></i>${staff.PhoneNumber}</span>` : '<span></span>'}
                        </div>
                    </div>` : ''}
                </div>
            `).join('');

            container.innerHTML = staffHTML;
        }

        function toggleStaffActions(staffId) {
            // Close all other dropdowns
            document.querySelectorAll('[id^="staffActions-"]').forEach(dropdown => {
                if (dropdown.id !== `staffActions-${staffId}`) {
                    dropdown.classList.add('hidden');
                }
            });
            
            // Toggle current dropdown
            const dropdown = document.getElementById(`staffActions-${staffId}`);
            dropdown.classList.toggle('hidden');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[onclick^="toggleStaffActions"]')) {
                document.querySelectorAll('[id^="staffActions-"]').forEach(dropdown => {
                    dropdown.classList.add('hidden');
                });
            }
        });

        // Staff modal functions
        function openStaffModal(isEdit = false, staffId = null) {
            const modal = document.getElementById('staffModal');
            const modalContent = document.getElementById('staffModalContent');
            const title = document.getElementById('staffModalTitle');
            const form = document.getElementById('staffForm');
            const passwordSection = document.getElementById('passwordSection');
            const imagePreview = document.getElementById('staffImagePreview');
            const imageData = document.getElementById('staffImageData');

            if (isEdit && staffId) {
                const staff = userData.staffMembers.find(s => s.id === staffId);
                if (!staff) return;

                title.textContent = 'Edit Staff Member';
                document.getElementById('staffEditId').value = staffId;
                document.getElementById('staffName').value = staff.Name;
                document.getElementById('staffPhone').value = staff.PhoneNumber || '';
                document.getElementById('staffRole').value = staff.Role;
                document.getElementById('staffEmail').value = staff.Email || '';
                
                // Set image if exists
                if (staff.image_path) {
                    imagePreview.src = staff.image_path;
                    imageData.value = ''; // Clear any previous image data
                }
                
                // Hide password field for edit
                passwordSection.style.display = 'none';
                document.getElementById('staffPassword').required = false;
            } else {
                title.textContent = 'Add Staff Member';
                form.reset();
                document.getElementById('staffEditId').value = '';
                imagePreview.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIj48Y2lyY2xlIGZpbGw9IiNDQ0MiIGN4PSI1MCIgY3k9IjUwIiByPSI1MCIvPjxwYXRoIGZpbGw9IiNGRkYiIGQ9Ik01MCA1OWMtOC4yODQgMC0xNS02LjcxNi0xNS0xNXM2LjcxNi0xNSAxNS0xNCAxNSA2LjcxNiAxNSAxNS02LjcxNiAxNS0xNSAxNXptMC0yNWMtNS41MjMgMC0xMCA0LjQ3Ny0xMCAxMHM0LjQ3NyAxMCAxMCAxMCAxMC00LjQ3NyAxMC0xMC00LjQ3Ny0xMC0xMC0xMHoiLz48cGF0aCBmaWxsPSIjRkZGIiBkPSJNNzIgNzJoLTEuNWMtMS4xNi0xLjMxLS44My0yLjY2LS41LTMuNWwuNjYtMS42N2MtLjQ5LTEuMy0xLjYxLTIuODEtMy4zNi0zLjg2YTE3LjQ0IDE3LjQ0IDAgMCAxLTIuNzQtMS42NWMtNC4zMy0yLjA1LTEwLjM2LTIuMDUtMTQuNzYgMCAxLjAxMy40NDMgMS45MjIgMS4wMzQgMi43MyAxLjY1IDIgMS4xNiAzLjE5IDIuNzkgMy4zOCA0LjA0bC42NiAxLjY2Yy4zMy44NC42NiAyLjE5LS41IDMuNWgtMS41Yy0xMSAwLTIwLjI1LTguNjMtMjAuMjUtMTkuNUMzMSA0MS4yNyA0MC4yNyAzMiA1MS41IDMyIDYyLjczIDMyIDcyIDQxLjI3IDcyIDUyLjVjMCAxMC44My05LjI5IDE5LjUtMjAgMTkuNWgtMXoiLz48L2c+PC9zdmc+';
                imageData.value = '';
                
                // Show password field for new staff
                passwordSection.style.display = 'block';
                document.getElementById('staffPassword').required = true;
            }

            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeStaffModal() {
            // Stop camera if active
            if (staffStream) {
                staffStream.getTracks().forEach(track => track.stop());
                staffStream = null;
            }
            
            const modal = document.getElementById('staffModal');
            const modalContent = document.getElementById('staffModalContent');
            
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }

        function editStaff(staffId) {
            openStaffModal(true, staffId);
        }

        function resetStaffPassword(staffId, staffName) {
            const modal = document.getElementById('resetPasswordModal');
            document.getElementById('resetPasswordUserId').value = staffId;
            document.getElementById('resetPasswordUserName').textContent = staffName;
            document.getElementById('resetPasswordForm').reset();
            modal.classList.remove('hidden');
        }

        function closeResetPasswordModal() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
        }

        async function toggleStaffStatus(staffId, currentStatus) {
            const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
            const action = newStatus === 'Active' ? 'activate' : 'deactivate';
            
            if (!confirm(`Are you sure you want to ${action} this staff member?`)) {
                return;
            }

            try {
                await apiCall('api/staff_management.php', {
                    action: 'toggle_status',
                    staff_id: staffId,
                    status: newStatus
                });

                // Update local data
                const staffIndex = userData.staffMembers.findIndex(s => s.id === staffId);
                if (staffIndex !== -1) {
                    userData.staffMembers[staffIndex].Status = newStatus;
                    loadStaffMembers();
                }

                showToast(`Staff member ${action}d successfully`);
            } catch (error) {
                showToast(error.message, 'error');
            }
        }

        async function deleteStaff(staffId, staffName) {
            if (!confirm(`Are you sure you want to delete ${staffName}? This action cannot be undone.`)) {
                return;
            }

            try {
                await apiCall('api/staff_management.php', {
                    action: 'delete',
                    staff_id: staffId
                });

                // Remove from local data
                userData.staffMembers = userData.staffMembers.filter(s => s.id !== staffId);
                loadStaffMembers();

                showToast('Staff member deleted successfully');
            } catch (error) {
                showToast(error.message, 'error');
            }
        }

        // Form submission handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Load staff members if Super Admin
            if (userData.isSuperAdmin) {
                loadStaffMembers();
            }

            // Firm logo upload
            const firmLogoInput = document.getElementById('firmLogoInput');
            if (firmLogoInput) {
                firmLogoInput.addEventListener('change', async function(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    try {
                        const result = await uploadFile(file, 'firm_logo');
                        document.getElementById('firmLogoPreview').src = result.url;
                        showToast('Logo uploaded successfully');
                    } catch (error) {
                        showToast(error.message, 'error');
                    }
                });
            }

            // User image upload
            const userImageInput = document.getElementById('userImageInput');
            if (userImageInput) {
                userImageInput.addEventListener('change', async function(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    try {
                        const result = await uploadFile(file, 'user_image');
                        document.getElementById('userImagePreview').src = result.url;
                        showToast('Profile picture updated successfully');
                    } catch (error) {
                        showToast(error.message, 'error');
                    }
                });
            }

            // Firm details form
            const firmDetailsForm = document.getElementById('firmDetailsForm');
            if (firmDetailsForm) {
                firmDetailsForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const submitBtn = e.target.querySelector('button[type="submit"]');
                    setLoading(submitBtn, true);

                    try {
                        // Collect form data
                        const formData = {
                            action: 'update_firm',
                            firmName: document.getElementById('firmName').value,
                            ownerName: document.getElementById('ownerName').value,
                            firmTagline: document.getElementById('firmTagline').value,
                            address: `${document.getElementById('firmAddress1').value}${document.getElementById('firmAddress2').value ? ', ' + document.getElementById('firmAddress2').value : ''}`,
                            firmCity: document.getElementById('firmCity').value,
                            firmState: document.getElementById('firmState').value,
                            firmPincode: document.getElementById('firmPincode').value,
                            firmPhone: document.getElementById('firmPhone').value,
                            firmEmail: document.getElementById('firmEmail').value,
                            firmPAN: document.getElementById('firmPAN').value,
                            firmGST: document.getElementById('firmGST').value,
                            bisRegistration: document.getElementById('bisRegistration').value,
                            bankName: document.getElementById('bankName').value,
                            bankBranch: document.getElementById('bankBranch').value,
                            accountNumber: document.getElementById('accountNumber').value,
                            ifscCode: document.getElementById('ifscCode').value,
                            accountType: document.getElementById('accountType').value
                        };

                        const response = await apiCall('api/firm_management.php', formData);
                        showToast('Firm details updated successfully');
                    } catch (error) {
                        showToast(error.message, 'error');
                    } finally {
                        setLoading(submitBtn, false);
                    }
                });
            }

            // User profile form
            const userProfileForm = document.getElementById('userProfileForm');
            if (userProfileForm) {
                userProfileForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const submitBtn = e.target.querySelector('button[type="submit"]');
                    setLoading(submitBtn, true);

                    try {
                        const formData = new FormData(e.target);
                        const data = Object.fromEntries(formData.entries());

                        await apiCall('api/user_management.php', {
                            action: 'update_profile',
                            ...data
                        });

                        showToast('Profile updated successfully');
                    } catch (error) {
                        showToast(error.message, 'error');
                    } finally {
                        setLoading(submitBtn, false);
                    }
                });
            }

            // Change password form
            const changePasswordForm = document.getElementById('changePasswordForm');
            if (changePasswordForm) {
                changePasswordForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;
                    
                    if (newPassword !== confirmPassword) {
                        showToast('Passwords do not match', 'error');
                        return;
                    }

                    const submitBtn = e.target.querySelector('button[type="submit"]');
                    setLoading(submitBtn, true);

                    try {
                        await apiCall('api/user_management.php', {
                            action: 'change_password',
                            current_password: document.getElementById('currentPassword').value,
                            new_password: newPassword
                        });

                        showToast('Password changed successfully');
                        e.target.reset();
                    } catch (error) {
                        showToast(error.message, 'error');
                    } finally {
                        setLoading(submitBtn, false);
                    }
                });
            }

            // Staff form
            const staffForm = document.getElementById('staffForm');
            if (staffForm) {
                staffForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const submitBtn = e.target.querySelector('button[type="submit"]');
                    setLoading(submitBtn, true);

                    try {
                        // Ensure all required fields are present
                        const name = document.getElementById('staffName').value.trim();
                        const phone = document.getElementById('staffPhone').value.trim();
                        const role = document.getElementById('staffRole').value.trim();
                        const password = document.getElementById('staffPassword').value;
                        const email = document.getElementById('staffEmail').value.trim();
                        const staffId = document.getElementById('staffEditId').value.trim();
                        const imageData = document.getElementById('staffImageData').value;

                        // Validate required fields
                        if (!name) throw new Error('Name is required');
                        if (!phone) throw new Error('Phone number is required');
                        if (!role) throw new Error('Role is required');
                        if (!staffId && !password) throw new Error('Password is required for new staff');

                        // Validate phone number format
                        if (!/^[0-9]{10}$/.test(phone)) {
                            throw new Error('Phone number must be 10 digits');
                        }

                        // Validate email format if provided
                        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                            throw new Error('Invalid email format');
                        }

                        const formData = {
                            action: staffId ? 'update' : 'create',
                            staff_id: staffId || undefined,
                            name: name,
                            phone: phone,
                            role: role,
                            email: email || '',
                            password: password,
                            image: imageData || undefined
                        };

                        console.log('Sending staff data:', formData);

                        const response = await apiCall('api/staff_management.php', formData);
                        console.log('Received response:', response);

                        if (!formData.staff_id && response.username) {
                            showToast(`Staff member created successfully. Username: ${response.username}`);
                        } else {
                            showToast(`Staff member ${formData.staff_id ? 'updated' : 'added'} successfully`);
                        }

                        // Refresh staff list
                        const listResponse = await apiCall('api/staff_management.php?action=list', null, 'GET');
                        userData.staffMembers = listResponse.staff;
                        loadStaffMembers();

                        closeStaffModal();
                    } catch (error) {
                        console.error('Staff form error:', error);
                        showToast(error.message, 'error');
                    } finally {
                        setLoading(submitBtn, false);
                    }
                });
            }

            // Reset password form
            const resetPasswordForm = document.getElementById('resetPasswordForm');
            if (resetPasswordForm) {
                resetPasswordForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const newPassword = document.getElementById('newStaffPassword').value;
                    const confirmPassword = document.getElementById('confirmStaffPassword').value;
                    
                    if (newPassword !== confirmPassword) {
                        showToast('Passwords do not match', 'error');
                        return;
                    }

                    const submitBtn = e.target.querySelector('button[type="submit"]');
                    setLoading(submitBtn, true);

                    try {
                        await apiCall('api/staff_management.php', {
                            action: 'reset_password',
                            staff_id: document.getElementById('resetPasswordUserId').value,
                            new_password: newPassword
                        });

                        closeResetPasswordModal();
                        showToast('Password reset successfully');
                    } catch (error) {
                        showToast(error.message, 'error');
                    } finally {
                        setLoading(submitBtn, false);
                    }
                });
            }

            // Modal event listeners
            document.getElementById('addNewStaffBtn')?.addEventListener('click', () => openStaffModal());
            document.getElementById('closeStaffModalBtn')?.addEventListener('click', closeStaffModal);
            document.getElementById('cancelStaffModalBtn')?.addEventListener('click', closeStaffModal);
            document.getElementById('closeResetPasswordModalBtn')?.addEventListener('click', closeResetPasswordModal);
            document.getElementById('cancelResetPasswordBtn')?.addEventListener('click', closeResetPasswordModal);

            // Close modals on outside click
            document.getElementById('staffModal')?.addEventListener('click', function(e) {
                if (e.target === this) closeStaffModal();
            });
            document.getElementById('resetPasswordModal')?.addEventListener('click', function(e) {
                if (e.target === this) closeResetPasswordModal();
            });
        });

        // Staff image handling
        let staffStream = null;

        // User dropdown toggle
        const userDropdownToggle = document.getElementById('userDropdownToggle');
        const userDropdownMenu = document.getElementById('userDropdownMenu');

        if (userDropdownToggle && userDropdownMenu) {
            userDropdownToggle.addEventListener('click', function() {
                userDropdownMenu.classList.toggle('hidden');
            });

            // Close the dropdown if the user clicks outside of it
            document.addEventListener('click', function(event) {
                if (!userDropdownToggle.contains(event.target) && !userDropdownMenu.contains(event.target)) {
                    userDropdownMenu.classList.add('hidden');
                }
            });
        }

        document.getElementById('staffImageBtn')?.addEventListener('click', function() {
            document.getElementById('staffImageOptions').classList.toggle('hidden');
        });

        document.getElementById('staffCameraBtn')?.addEventListener('click', async function() {
            try {
                staffStream = await navigator.mediaDevices.getUserMedia({ video: true });
                const video = document.getElementById('staffCamera');
                video.srcObject = staffStream;
                video.play();
                
                document.getElementById('staffCamera').classList.remove('hidden');
                document.getElementById('staffCaptureBtn').classList.remove('hidden');
                document.getElementById('staffImageOptions').classList.add('hidden');
            } catch (error) {
                showToast('Failed to access camera: ' + error.message, 'error');
            }
        });

        document.getElementById('staffCaptureBtn')?.addEventListener('click', function() {
            const video = document.getElementById('staffCamera');
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            
            // Convert to base64
            const imageData = canvas.toDataURL('image/jpeg');
            document.getElementById('staffImageData').value = imageData;
            document.getElementById('staffImagePreview').src = imageData;
            
            // Stop camera
            if (staffStream) {
                staffStream.getTracks().forEach(track => track.stop());
                staffStream = null;
            }
            
            document.getElementById('staffCamera').classList.add('hidden');
            document.getElementById('staffCaptureBtn').classList.add('hidden');
        });

        document.getElementById('staffImageInput')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageData = e.target.result;
                    document.getElementById('staffImageData').value = imageData;
                    document.getElementById('staffImagePreview').src = imageData;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>