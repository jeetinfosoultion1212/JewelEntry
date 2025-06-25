<?php
session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

// Get user details
$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get date range from URL parameters or use defaults
// Default to previous 7 days if no date is specified to ensure data is shown
$today = date('Y-m-d');
$oneWeekAgo = date('Y-m-d', strtotime('-7 days'));

$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : $oneWeekAgo;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : $today;

// Update the summary query to include GST counts
$summaryQuery = "SELECT 
    SUM(grand_total) as total_sales,
    SUM(total_paid_amount) as total_received,
    SUM(due_amount) as total_due,
    COUNT(*) as total_orders,
    SUM(CASE WHEN is_gst_applicable = 1 THEN 1 ELSE 0 END) as gst_orders,
    SUM(CASE WHEN is_gst_applicable = 0 THEN 1 ELSE 0 END) as non_gst_orders,
    SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(CASE 
        WHEN payment_method = 'bank' THEN total_paid_amount
        ELSE 0 
    END) as bank_payments,
    SUM(CASE 
        WHEN payment_method = 'upi' THEN total_paid_amount
        ELSE 0 
    END) as upi_payments,
    SUM(CASE 
        WHEN payment_method = 'card' THEN total_paid_amount
        ELSE 0 
    END) as card_payments,
    SUM(CASE 
        WHEN payment_method = 'cash' THEN total_paid_amount
        ELSE 0 
    END) as cash_payments
FROM jewellery_sales 
WHERE firm_id = ? 
AND DATE(sale_date) BETWEEN ? AND ?";

$stmt = $conn->prepare($summaryQuery);
$stmt->bind_param("iss", $firm_id, $startDate, $endDate);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Handle null values in summary results
$summary['total_sales'] = $summary['total_sales'] ?? 0;
$summary['total_received'] = $summary['total_received'] ?? 0;
$summary['total_due'] = $summary['total_due'] ?? 0;
$summary['total_orders'] = $summary['total_orders'] ?? 0;
$summary['gst_orders'] = $summary['gst_orders'] ?? 0;
$summary['non_gst_orders'] = $summary['non_gst_orders'] ?? 0;
$summary['completed_orders'] = $summary['completed_orders'] ?? 0;
$summary['bank_payments'] = $summary['bank_payments'] ?? 0;
$summary['upi_payments'] = $summary['upi_payments'] ?? 0;
$summary['card_payments'] = $summary['card_payments'] ?? 0;
$summary['cash_payments'] = $summary['cash_payments'] ?? 0;

// Add GST filter to sales query if specified
$gstFilter = isset($_GET['gst_filter']) ? $_GET['gst_filter'] : 'all';
$salesQuery = "SELECT js.*, 
    c.FirstName, c.LastName, c.PhoneNumber,
    COALESCE(SUM(jsi.gross_weight), 0) as total_gross_weight,
    COALESCE(SUM(jsi.net_weight), 0) as total_net_weight
FROM jewellery_sales js
LEFT JOIN Customer c ON js.customer_id = c.id
LEFT JOIN Jewellery_sales_items jsi ON js.id = jsi.sale_id
WHERE js.firm_id = ?
AND DATE(js.sale_date) BETWEEN ? AND ?
" . ($gstFilter !== 'all' ? "AND js.is_gst_applicable = " . ($gstFilter === 'gst' ? '1' : '0') : "") . "
GROUP BY js.id
ORDER BY js.sale_date DESC";

$stmt = $conn->prepare($salesQuery);
$stmt->bind_param("iss", $firm_id, $startDate, $endDate);
$stmt->execute();
$sales = $stmt->get_result();

// Determine date range display text
function getDateRangeDisplayText($startDate, $endDate) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $weekStart = date('Y-m-d', strtotime('last sunday +1 day'));
    $monthStart = date('Y-m-d', strtotime('first day of this month'));
    
    if ($startDate == $endDate) {
        if ($startDate == $today) {
            return "Today";
        } elseif ($startDate == $yesterday) {
            return "Yesterday";
        } else {
            return date('d M Y', strtotime($startDate));
        }
    } elseif ($startDate == $weekStart && $endDate == $today) {
        return "This Week";
    } elseif ($startDate == $monthStart && $endDate == $today) {
        return "This Month";
    } else {
        return date('d M', strtotime($startDate)) . " - " . date('d M', strtotime($endDate));
    }
}

$dateRangeText = getDateRangeDisplayText($startDate, $endDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Compact Customer Billing List</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              light: '#8BBEE8',
              DEFAULT: '#4A8FE7',
              dark: '#2563EB',
            },
            secondary: {
              light: '#FDE68A',
              DEFAULT: '#F59E0B',
              dark: '#D97706',
            }
          },
          fontFamily: {
            'sans': ['Inter', 'sans-serif'],
          },
          fontSize: {
            'xxs': '0.65rem',
            'xs': '0.75rem',
            'sm': '0.875rem',
          },
          spacing: {
            '1.5': '0.375rem',
          }
        }
      }
    }
  </script>
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="theme-color" content="#EBF4FF">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      letter-spacing: -0.01em;
      padding-bottom: 80px;
    }
    .font-numeric {
      font-feature-settings: "tnum";
      font-variant-numeric: tabular-nums;
    }

    /* Bottom Navigation */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to bottom, rgba(255,255,255,0.95), rgba(255,255,255,1));
      padding: 0.5rem;
      display: flex;
      justify-content: space-around;
      align-items: center;
      border-top: 1px solid rgba(59, 130, 246, 0.1);
      backdrop-filter: blur(8px);
      box-shadow: 0 -4px 20px rgba(59, 130, 246, 0.15);
      z-index: 40;
    }

    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 0.5rem;
      border-radius: 0.75rem;
      transition: all 0.2s;
      min-width: 64px;
      color: #64748b;
      position: relative;
      cursor: pointer;
      text-decoration: none;
    }

    .nav-item.active {
      color: #3b82f6;
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1));
    }

    .nav-item:hover {
      color: #3b82f6;
      transform: translateY(-2px);
    }

    .nav-icon {
      font-size: 1.25rem;
      margin-bottom: 0.25rem;
    }

    .nav-text {
      font-size: 0.75rem;
      font-weight: 500;
    }

    /* Top Navigation */
    .top-nav {
      position: sticky;
      top: 0;
      z-index: 50;
      background: linear-gradient(to bottom, rgba(255,255,255,0.95), rgba(255,255,255,0.9));
      backdrop-filter: blur(8px);
      border-bottom: 1px solid rgba(59, 130, 246, 0.1);
      padding: 0.5rem 1rem;
    }

    .top-stats {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }

    .top-stats::-webkit-scrollbar {
      display: none;
    }
    
    /* Date Range Dropdown */
    .date-dropdown {
      position: absolute;
      right: 20;
      top: 100%;
      margin-top: 4px;
      width: 280px;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
      border: 1px solid rgba(59, 130, 246, 0.2);
      z-index: 60;
      display: none;
    }
    
    .date-dropdown.show {
      display: block;
    }
  </style>
</head>
<body class="bg-blue-50">
  <div class="max-w-md mx-auto p-1.5">
    <div class="top-nav">
      <div class="flex items-center justify-between mb-3">
        <h1 class="text-sm font-semibold text-primary-dark tracking-tight flex items-center">
          <i class="fas fa-receipt mr-2"></i>Billing
        </h1>
        <div class="flex items-center gap-2">
          <!-- Date Filter Button -->
          <div class="relative inline-block">
            <button id="dateFilterBtn" class="flex items-center gap-2 px-3  bg-white border border-gray-200 rounded-lg text-sm hover:bg-gray-50">
              <i class="far fa-calendar-alt text-primary"></i>
              <span id="dateRangeText" class="text-gray-600"><?php echo htmlspecialchars($dateRangeText); ?></span>
              <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
            </button>

            <!-- Date Range Dropdown -->
            <div id="dateRangeDropdown" class="date-dropdown">
              <div class="p-3">
                <!-- Quick Select Options -->
                <div class="grid grid-cols-2 gap-2 mb-3">
                  <button type="button" data-range="today" class="date-option text-center px-3 py-2 rounded border border-gray-100 hover:bg-blue-50 hover:border-blue-200 text-sm">Today</button>
                  <button type="button" data-range="yesterday" class="date-option text-center px-3 py-2 rounded border border-gray-100 hover:bg-blue-50 hover:border-blue-200 text-sm">Yesterday</button>
                  <button type="button" data-range="week" class="date-option text-center px-3 py-2 rounded border border-gray-100 hover:bg-blue-50 hover:border-blue-200 text-sm">This Week</button>
                  <button type="button" data-range="month" class="date-option text-center px-3 py-2 rounded border border-gray-100 hover:bg-blue-50 hover:border-blue-200 text-sm">This Month</button>
                </div>
                
                <!-- Custom Date Range -->
                <div class="border-t border-gray-100 pt-3">
                  <div class="text-xs text-gray-500 mb-2">Custom Range</div>
                  <form id="dateRangeForm" class="space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                      <div>
                        <label class="text-xs text-gray-500 mb-1 block">Start Date</label>
                        <input type="date" id="startDate" name="startDate" 
                               value="<?php echo htmlspecialchars($startDate); ?>"
                               class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded">
                      </div>
                      <div>
                        <label class="text-xs text-gray-500 mb-1 block">End Date</label>
                        <input type="date" id="endDate" name="endDate" 
                               value="<?php echo htmlspecialchars($endDate); ?>"
                               class="w-full px-2 py-1.5 text-sm border border-gray-200 rounded">
                      </div>
                    </div>
                    <button type="submit" class="w-full bg-primary text-white py-2 rounded text-sm hover:bg-primary-dark">
                      Apply Range
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
          
          <!-- GST Filter Buttons -->
          <div class="flex bg-white border border-gray-200 rounded-lg">
            <button id="gstFilterAll" class="px-1 py-1 text-xs font-medium rounded-l-lg transition-colors <?php echo ($gstFilter === 'all') ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50'; ?>">
              All
              <span class="ml-1 text-xxs">(<?php echo $summary['total_orders']; ?>)</span>
            </button>
            <button id="gstFilterGst" class="px-1 py-1 text-xs font-medium border-x border-gray-200 transition-colors <?php echo ($gstFilter === 'gst') ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50'; ?>">
              <i class="fas fa-money-bill-transfer mr-1"></i>
              <span class="ml-1 text-xxs">(<?php echo $summary['gst_orders']; ?>)</span>
            </button>
            <button id="gstFilterNonGst" class="px-1 py-1 text-xs font-medium rounded-r-lg transition-colors <?php echo ($gstFilter === 'non-gst') ? 'bg-primary text-white' : 'text-gray-500 hover:bg-gray-50'; ?>">
              <i class="fas fa-money-bill-trend-up mr-1"></i>
              <span class="ml-1 text-xxs">(<?php echo $summary['non_gst_orders']; ?>)</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="top-stats">
      <div class="flex space-x-3 min-w-max pb-2">
        <!-- Sales Stats -->
        <div class="bg-blue-50 rounded-lg p-2 w-44">
          <div class="flex items-center justify-between mb-1">
            <span class="text-gray-600 text-xs">Sales Amount</span>
            <i class="fas fa-chart-line text-blue-500"></i>
          </div>
          <div class="font-semibold text-blue-700 text-sm font-numeric">
            ₹<?php echo number_format($summary['total_sales'], 2); ?>
          </div>
        </div>

        <!-- Amount Received -->
        <div class="bg-green-50 rounded-lg p-2 w-44">
          <div class="flex items-center justify-between mb-1">
            <span class="text-gray-600 text-xs">Amount Received</span>
            <i class="fas fa-hand-holding-dollar text-green-500"></i>
          </div>
          <div class="font-semibold text-green-700 text-sm font-numeric">
            ₹<?php echo number_format($summary['total_received'], 2); ?>
          </div>
          <div class="text-xxs text-gray-500">
            <span class="text-green-600">
              <?php echo $summary['total_sales'] > 0 ? round(($summary['total_received']/$summary['total_sales'])*100) : 0; ?>% collected
            </span>
          </div>
        </div>

        <!-- Due Amount -->
        <div class="bg-red-50 rounded-lg p-2 w-44">
          <div class="flex items-center justify-between mb-1">
            <span class="text-gray-600 text-xs">Due Amount</span>
            <i class="fas fa-clock text-red-500"></i>
          </div>
          <div class="font-semibold text-red-700 text-sm font-numeric">
            ₹<?php echo number_format($summary['total_due'], 2); ?>
          </div>
          <div class="text-xxs text-gray-500">
            <?php echo $summary['total_orders'] - $summary['completed_orders']; ?> orders pending
          </div>
        </div>

        <!-- Payment Methods -->
        <div class="bg-gray-50 rounded-lg p-2 w-44">
          <div class="flex items-center justify-between mb-1">
            <span class="text-gray-600 text-xs">Payments</span>
            <i class="fas fa-wallet text-gray-500"></i>
          </div>
          <div class="grid grid-cols-2 gap-1 text-xxs">
            <div>
              <i class="fas fa-building-columns text-gray-400 mr-1"></i>
              <span class="font-numeric">₹<?php echo number_format($summary['bank_payments'], 2); ?></span>
            </div>
            <div>
              <i class="fas fa-mobile-screen text-gray-400 mr-1"></i>
              <span class="font-numeric">₹<?php echo number_format($summary['upi_payments'], 2); ?></span>
            </div>
            <div>
              <i class="fas fa-credit-card text-gray-400 mr-1"></i>
              <span class="font-numeric">₹<?php echo number_format($summary['card_payments'], 2); ?></span>
            </div>
            <div>
              <i class="fas fa-money-bill-wave text-gray-400 mr-1"></i>
              <span class="font-numeric">₹<?php echo number_format($summary['cash_payments'], 2); ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- No data message -->
    <?php if ($sales->num_rows == 0): ?>
    <div class="bg-white rounded-lg p-6 text-center mt-4">
      <i class="fas fa-search text-4xl text-gray-300 mb-2"></i>
      <h3 class="text-lg font-medium text-gray-800">No sales found</h3>
      <p class="text-gray-500 text-sm mt-1">No sales data available for the selected date range.</p>
      <p class="text-gray-500 text-sm">Try selecting a different date range.</p>
    </div>
    <?php endif; ?>

    <!-- Enhanced sales list item template -->
    <?php while($sale = $sales->fetch_assoc()): ?>
    <div class="bg-white rounded-lg shadow-sm mb-2 overflow-hidden border border-blue-100">
      <div class="bg-gradient-to-r from-primary-light to-primary text-white p-2 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="flex items-center">
            <i class="fas fa-user-circle text-white mr-1.5"></i>
            <h2 class="font-semibold tracking-tight text-sm">
              <?php echo htmlspecialchars($sale['FirstName'].' '.$sale['LastName']); ?>
            </h2>
          </div>
          <div class="flex items-center text-xs">
            <i class="fas fa-phone text-white/70 mr-1"></i>
            <span><?php echo htmlspecialchars($sale['PhoneNumber']); ?></span>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <div class="text-xxs bg-<?php echo $sale['payment_status'] == 'completed' ? 'green' : 'yellow'; ?>-500/20 text-white px-2 py-0.5 rounded-full">
            <?php echo ucfirst($sale['payment_status']); ?>
          </div>
          <div class="flex items-center text-xxs bg-white text-primary-dark px-2 py-0.5 rounded-full font-medium">
            #<?php echo htmlspecialchars($sale['invoice_no']); ?>
          </div>
        </div>
      </div>
      
      <div class="p-2 space-y-2">
        <!-- Date and Item Details -->
        <div class="flex items-center justify-between text-xs">
          <div class="flex items-center gap-3">
            <span class="flex items-center text-gray-500">
              <i class="far fa-calendar-alt text-primary mr-1"></i>
              <?php echo date('d M Y', strtotime($sale['sale_date'])); ?>
            </span>
            <span class="flex items-center text-gray-500">
              <i class="fas fa-clock text-primary mr-1"></i>
              <?php echo date('h:i A', strtotime($sale['sale_date'])); ?>
            </span>
          </div>
          <div class="flex items-center gap-2 text-gray-600">
            <span class="flex items-center">
                <i class="fas fa-weight-hanging text-purple-500 mr-1"></i>
                <?php echo number_format($sale['total_gross_weight'], 2); ?>g
            </span>
            <span class="text-gray-300">|</span>
            <span class="flex items-center">
                <i class="fas fa-gem text-pink-500 mr-1"></i>
                <?php echo number_format($sale['total_net_weight'], 2); ?>g
            </span>
          </div>
        </div>

        <!-- Amount Details -->
        <div class="grid grid-cols-2 gap-2 text-xxs bg-gray-50 rounded-lg p-2">
          <div>
            <span class="text-gray-500">Metal Amount:</span>
            <span class="float-right font-medium">₹<?php echo number_format($sale['total_metal_amount'], 0); ?></span>
          </div>
          <div>
            <span class="text-gray-500">Stone:</span>
            <span class="float-right font-medium">₹<?php echo number_format($sale['total_stone_amount'], 0); ?></span>
          </div>
          <div>
            <span class="text-gray-500">Making:</span>
            <span class="float-right font-medium">₹<?php echo number_format($sale['total_making_charges'], 0); ?></span>
          </div>
          <div>
            <span class="text-gray-500">GST:</span>
            <span class="float-right font-medium">₹<?php echo number_format($sale['gst_amount'], 0); ?></span>
          </div>
        </div>

        <!-- Payment Summary -->
        <div class="flex items-center justify-between bg-blue-50 rounded-lg p-2">
          <div class="space-y-0.5">
            <div class="text-xs font-medium text-gray-600">
              Total: ₹<?php echo number_format($sale['grand_total'], 0); ?>
            </div>
            <div class="flex items-center gap-2 text-xxs">
              <span class="flex items-center text-green-600">
                <i class="fas fa-circle-check mr-1"></i>
                Paid: ₹<?php echo number_format($sale['total_paid_amount'], 0); ?>
              </span>
              <?php if($sale['due_amount'] > 0): ?>
              <span class="flex items-center text-red-500">
                <i class="fas fa-circle-exclamation mr-1"></i>
                Due: ₹<?php echo number_format($sale['due_amount'], 0); ?>
              </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <a href="print-invoice.php?id=<?php echo $sale['id']; ?>" class="bg-blue-100 text-blue-600 p-1.5 rounded-full hover:bg-blue-200">
              <i class="fas fa-print"></i>
            </a>
            <a href="view-sale.php?id=<?php echo $sale['id']; ?>" class="bg-primary text-white px-3 py-1 rounded text-xs hover:bg-primary-dark">
              View Details
            </a>
          </div>
        </div>
      </div>
    </div>
    <?php endwhile; ?>
  </div>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav">
    <a href="home.php" class="nav-item">
      <i class="nav-icon fas fa-home"></i>
      <span class="nav-text">Home</span>
    </a>
    <a href="add.php" class="nav-item">
      <i class="nav-icon fas fa-tags"></i>
      <span class="nav-text">Add</span>
    </a>
     <a href="sale-entry.php" class="nav-item">
      <i class="nav-icon fas fa-shopping-cart"></i>
      <span class="nav-text">Sale</span>
    </a>
    <a href="sale-list.php" class="nav-item active">
      <i class="nav-icon fa-solid fa-file-invoice-dollar"></i>
      <span class="nav-text">Sales List</span>
    </a>
   
    <a href="reports.php" class="nav-item">
      <i class="nav-icon fas fa-chart-pie"></i>
      <span class="nav-text">Reports</span>
    </a>
  </nav>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const dateFilterBtn = document.getElementById('dateFilterBtn');
      const dateRangeDropdown = document.getElementById('dateRangeDropdown');
      const dateRangeForm = document.getElementById('dateRangeForm');
      
      // Check if elements exist before adding event listeners
      if (!dateFilterBtn || !dateRangeDropdown || !dateRangeForm) {
        console.error('Required DOM elements not found');
        return;
      }
      
      const startDate = document.getElementById('startDate');
      const endDate = document.getElementById('endDate');
      const dateRangeText = document.getElementById('dateRangeText');

      // Toggle dropdown
      dateFilterBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dateRangeDropdown.classList.toggle('show');
      });

      // Handle date range form submission
      dateRangeForm.addEventListener('submit', (e) => {
        e.preventDefault();
        submitDateRange();
      });

      // Handle quick select options
      document.querySelectorAll('.date-option').forEach(option => {
        option.addEventListener('click', () => {
          const range = option.getAttribute('data-range');
          const today = new Date();
          
          switch(range) {
            case 'today':
              setDateRange(today, today, 'Today');
              break;
            case 'yesterday':
              const yesterday = new Date(today);
              yesterday.setDate(today.getDate() - 1);
              setDateRange(yesterday, yesterday, 'Yesterday');
              break;
            case 'week':
              const weekStart = new Date(today);
              weekStart.setDate(today.getDate() - today.getDay());
              setDateRange(weekStart, today, 'This Week');
              break;
            case 'month':
              const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
              // ...existing code...
              setDateRange(monthStart, today, 'This Month');
              break;
          }
        });
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (!dateRangeDropdown.contains(e.target) && !dateFilterBtn.contains(e.target)) {
          dateRangeDropdown.classList.remove('show');
        }
      });

      // Function to format date as YYYY-MM-DD
      function formatDate(date) {
        return date.toISOString().split('T')[0];
      }

      // Function to set date range and update UI
      function setDateRange(start, end, displayText) {
        startDate.value = formatDate(start);
        endDate.value = formatDate(end);
        dateRangeText.textContent = displayText;
        submitDateRange();
      }

      // Function to submit date range and update page
      function submitDateRange() {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('startDate', startDate.value);
        currentUrl.searchParams.set('endDate', endDate.value);
        window.location.href = currentUrl.toString();
      }

      // Handle GST filter buttons
      ['gstFilterAll', 'gstFilterGst', 'gstFilterNonGst'].forEach(id => {
        const button = document.getElementById(id);
        if (button) {
          button.addEventListener('click', () => {
            const currentUrl = new URL(window.location.href);
            switch(id) {
              case 'gstFilterAll':
                currentUrl.searchParams.set('gst_filter', 'all');
                break;
              case 'gstFilterGst':
                currentUrl.searchParams.set('gst_filter', 'gst');
                break;
              case 'gstFilterNonGst':
                currentUrl.searchParams.set('gst_filter', 'non-gst');
                break;
            }
            window.location.href = currentUrl.toString();
          });
        }
      });
    });
  </script>
</body>
</html>