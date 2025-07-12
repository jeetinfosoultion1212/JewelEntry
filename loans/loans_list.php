<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['id'])) {
   header("Location: login.php");
   exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
   error_log("Database connection failed: " . $conn->connect_error);
   die("Connection failed: " . $conn->connect_error);
}

$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.City
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans Management - JewelEntry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .tab-btn {
            transition: all 0.3s ease;
        }
        .tab-btn.active {
            color: #2563eb;
            border-bottom: 2px solid #2563eb;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .loan-card {
            transition: all 0.3s ease;
        }
        .loan-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .toast.success {
            background-color: #10B981;
        }
        .toast.error {
            background-color: #EF4444;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <div class="header-gradient p-1 text-white shadow-lg sticky top-0 z-50">
        <div class="enhanced-header">
            <div class="flex items-center">
                <i class="fas fa-gem mr-2 text-xl"></i>
                <div>
                    <div class="font-bold text-sm"><?php echo htmlspecialchars($userInfo['FirmName']); ?></div>
                    <div class="text-xs opacity-80">JewelEntry 0.02</div>
                </div>
            </div>
            <div class="user-info">
                <div class="text-right">
                    <div class="font-medium text-xs"><?php echo htmlspecialchars($userInfo['Name']); ?></div>
                    <div class="text-xs opacity-80"><?php echo htmlspecialchars($userInfo['Role']); ?></div>
                </div>
                <img src="<?php echo !empty($userInfo['image_path']) ? htmlspecialchars($userInfo['image_path']) : 'assets/default-avatar.png'; ?>"
                    alt="User" class="user-avatar">
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <div class="container mx-auto px-4 py-8">
        <!-- Tab Navigation -->
        <div class="flex border-b border-gray-200 mb-6">
            <button class="tab-btn active px-4 py-2 text-sm font-medium text-blue-600 border-b-2 border-blue-600" data-tab="loans-list">
                <i class="fas fa-list-ul mr-2"></i>Loans List
            </button>
            <button class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700" data-tab="new-loan">
                <i class="fas fa-plus-circle mr-2"></i>New Loan
            </button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Loans List Tab -->
            <div id="loans-list" class="tab-pane active">
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <!-- Search and Filter -->
                    <div class="flex flex-wrap gap-4 mb-4">
                        <div class="flex-1 min-w-[200px]">
                            <input type="text" id="loanSearch" placeholder="Search by customer name, phone or loan ID..." 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex gap-2">
                            <select id="statusFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Status</option>
                                <option value="ACTIVE">Active</option>
                                <option value="COMPLETED">Completed</option>
                                <option value="OVERDUE">Overdue</option>
                            </select>
                            <button id="refreshLoans" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Loans Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loan Details</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EMI Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="loansTableBody">
                                <!-- Loans will be loaded here dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- New Loan Tab -->
            <div id="new-loan" class="tab-pane hidden">
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <iframe src="new_loan_assignment.php" class="w-full h-[800px] border-0"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab-btn').forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons and panes
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                
                // Add active class to clicked button and corresponding pane
                button.classList.add('active');
                document.getElementById(button.dataset.tab).classList.add('active');
            });
        });

        // Function to load loans
        function loadLoans() {
            const searchTerm = document.getElementById('loanSearch').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            fetch(`api/get_loans.php?search=${searchTerm}&status=${statusFilter}`)
                .then(response => response.json())
                .then(data => {
                    const tableBody = document.getElementById('loansTableBody');
                    tableBody.innerHTML = '';
                    
                    data.forEach(loan => {
                        const row = document.createElement('tr');
                        row.className = 'loan-card hover:bg-gray-50';
                        
                        // Calculate EMI status
                        const today = new Date();
                        const nextDueDate = new Date(loan.next_emi_due_date);
                        const isOverdue = nextDueDate < today && loan.status === 'ACTIVE';
                        
                        row.innerHTML = `
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">${loan.customer_name}</div>
                                        <div class="text-sm text-gray-500">${loan.customer_phone}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-900">Loan #${loan.id}</div>
                                <div class="text-sm text-gray-500">Amount: ₹${loan.principal_amount}</div>
                                <div class="text-sm text-gray-500">EMI: ₹${loan.emi_amount}/month</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        ${isOverdue ? 'bg-red-100 text-red-800' : 
                                        loan.status === 'COMPLETED' ? 'bg-green-100 text-green-800' : 
                                        'bg-blue-100 text-blue-800'}">
                                        ${isOverdue ? 'Overdue' : loan.status}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500">
                                    Next EMI: ${loan.next_emi_due_date}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <button onclick="viewLoanDetails(${loan.id})" class="text-blue-600 hover:text-blue-900 mr-3">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button onclick="recordPayment(${loan.id})" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-money-bill-wave"></i> Pay
                                </button>
                            </td>
                        `;
                        
                        tableBody.appendChild(row);
                    });
                })
                .catch(error => {
                    console.error('Error loading loans:', error);
                    showToast('error', 'Failed to load loans');
                });
        }

        // Load loans on page load
        document.addEventListener('DOMContentLoaded', loadLoans);

        // Search and filter functionality
        document.getElementById('loanSearch').addEventListener('input', loadLoans);
        document.getElementById('statusFilter').addEventListener('change', loadLoans);
        document.getElementById('refreshLoans').addEventListener('click', loadLoans);

        // View loan details
        function viewLoanDetails(loanId) {
            window.location.href = `loan_details.php?id=${loanId}`;
        }

        // Record payment
        function recordPayment(loanId) {
            window.location.href = `record_payment.php?loan_id=${loanId}`;
        }

        // Toast notification function
        function showToast(type, message) {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}"></i><span>${message}</span>`;
            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 10);

            setTimeout(() => {
                toast.classList.remove('show');
                toast.addEventListener('transitionend', () => toast.remove(), { once: true });
            }, 3000);
        }
    </script>
</body>
</html>
<?php $conn->close(); ?> 