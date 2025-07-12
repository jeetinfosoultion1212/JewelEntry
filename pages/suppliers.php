<?php
session_start();
require '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$firm_id = $_SESSION['firmID'];

// Fetch user info for header
$user_id = $_SESSION['id'];
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch user and firm details
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.City, f.Logo
             FROM Firm_Users u
             JOIN Firm f ON f.id = u.FirmID
             WHERE u.id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userInfo = $userResult->fetch_assoc();

// Fetch all suppliers for this firm
$suppliers = [];
$supplierQuery = "SELECT * FROM suppliers WHERE firm_id = ? ORDER BY name";
$supplierStmt = $conn->prepare($supplierQuery);
$supplierStmt->bind_param("i", $firm_id);
$supplierStmt->execute();
$supplierResult = $supplierStmt->get_result();
while ($row = $supplierResult->fetch_assoc()) {
    $suppliers[$row['id']] = $row;
}

// Stats
$totalSuppliers = count($suppliers);
$totalActive = 0;
$totalInactive = 0;
$recentSuppliers = [];
foreach ($suppliers as $s) {
    if (empty($s['notes']) || stripos($s['notes'], 'inactive') === false) {
        $totalActive++;
    } else {
        $totalInactive++;
    }
}
// Get 5 most recent suppliers
$recentSuppliers = array_slice(array_reverse($suppliers), 0, 5);

// 1. Fetch purchase stats for each supplier
$supplierStats = [];
$sql = "SELECT source_id, COUNT(*) as purchase_count, COALESCE(SUM(weight),0) as total_weight, COALESCE(SUM(total_amount - paid_amount),0) as due_amount
        FROM metal_purchases
        WHERE source_type = 'supplier' AND firm_id = ?
        GROUP BY source_id";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $firm_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $supplierStats[$row['source_id']] = [
        'purchase_count' => $row['purchase_count'],
        'total_weight' => $row['total_weight'],
        'due_amount' => $row['due_amount']
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <title>Supplier Management - JewelEntry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/home.css">
    <style>
        * { font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .glass-effect { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.2); }
        .stats-card { background: #fff; border-radius: 1rem; box-shadow: 0 2px 12px 0 rgba(80,80,180,0.07); border: 1px solid #f3f4f6; }
        .floating-action { position: fixed; bottom: 90px; right: 20px; z-index: 40; animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%,100%{transform:translateY(0px);} 50%{transform:translateY(-3px);} }
        .input-focus { transition: all 0.2s ease; }
        .input-focus:focus { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,0.15); }
        .avatar-gradient { background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); }
        .modal-backdrop { background: rgba(30, 41, 59, 0.18); backdrop-filter: blur(2px); }
        @keyframes scaleIn { 0% { transform: scale(0.95); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        .animate-scaleIn { animation: scaleIn 0.2s cubic-bezier(0.4,0,0.2,1); }
        .modal-shadow { box-shadow: 0 8px 32px 0 rgba(80,80,180,0.18); }
        .modal-header-gradient { background: linear-gradient(90deg, #a78bfa 0%, #6366f1 100%); }
        .modal-close-btn { transition: color 0.15s; }
        .modal-close-btn:hover { color: #a78bfa; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
    <!-- Header -->
    <header class="rounded-b-2xl shadow-md bg-white/90 px-4 pt-4 pb-2 flex flex-col gap-2">
        <div class="flex items-center justify-between">
            <button onclick="window.location.href='home.php'" class="w-10 h-10 bg-gradient-to-br from-yellow-400 to-yellow-500 rounded-full flex items-center justify-center shadow hover:scale-105 transition">
                <i class="fas fa-arrow-left text-white text-lg"></i>
            </button>
            <div class="flex-1 flex flex-col ml-3">
                <span class="text-base font-bold text-gray-800 leading-tight"><?php echo $userInfo['FirmName']; ?></span>
                <span class="text-xs text-gray-500 font-medium">Suppliers Management</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="text-right mr-2">
                    <span class="block text-xs font-bold text-gray-800"><?php echo $userInfo['Name']; ?></span>
                    <span class="block text-xs text-purple-600 font-medium"><?php echo $userInfo['Role']; ?></span>
                </div>
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-400 to-indigo-400 flex items-center justify-center overflow-hidden border-2 border-white shadow">
                    <?php 
                    $defaultImage = 'public/uploads/user.png';
                    if (!empty($userInfo['image_path']) && file_exists($userInfo['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="User Profile" class="w-full h-full object-cover">
                    <?php elseif (file_exists($defaultImage)): ?>
                        <img src="<?php echo htmlspecialchars($defaultImage); ?>" alt="Default User" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-user-crown text-white text-lg"></i>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <div class="px-4 pt-4 pb-24 max-w-md mx-auto">
        <!-- Notification Area -->
        <div id="supplierNotification" class="hidden mb-4"></div>
        <!-- Compact Horizontal Stats Row -->
<div class="py-2">
  <div class="flex items-center justify-between mb-2">
    <h2 class="text-sm font-bold text-gray-800">Supplier Stats</h2>
  </div>
  <div class="flex space-x-2 overflow-x-auto pb-1 hide-scrollbar">
    <!-- Total Suppliers -->
    <div class="min-w-[90px] bg-gradient-to-r from-orange-100 to-orange-50 rounded-lg px-2 py-1 flex flex-col items-center shadow-sm">
      <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center mb-1">
        <i class="fas fa-truck text-orange-500 text-xs"></i>
      </div>
      <span class="text-xs font-bold text-gray-800"><?php echo $totalSuppliers; ?></span>
      <span class="text-[10px] text-gray-500">Total</span>
    </div>
    <!-- Active Suppliers -->
    <div class="min-w-[90px] bg-gradient-to-r from-green-100 to-green-50 rounded-lg px-2 py-1 flex flex-col items-center shadow-sm">
      <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center mb-1">
        <i class="fas fa-check-circle text-green-500 text-xs"></i>
      </div>
      <span class="text-xs font-bold text-gray-800"><?php echo $totalActive; ?></span>
      <span class="text-[10px] text-gray-500">Active</span>
    </div>
    <!-- Inactive Suppliers -->
    <div class="min-w-[90px] bg-gradient-to-r from-gray-100 to-gray-50 rounded-lg px-2 py-1 flex flex-col items-center shadow-sm">
      <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center mb-1">
        <i class="fas fa-user-slash text-gray-400 text-xs"></i>
      </div>
      <span class="text-xs font-bold text-gray-800"><?php echo $totalInactive; ?></span>
      <span class="text-[10px] text-gray-500">Inactive</span>
    </div>
    <!-- Recently Added -->
    <div class="min-w-[90px] bg-gradient-to-r from-blue-100 to-blue-50 rounded-lg px-2 py-1 flex flex-col items-center shadow-sm">
      <div class="w-6 h-6 bg-white rounded-md flex items-center justify-center mb-1">
        <i class="fas fa-plus text-blue-500 text-xs"></i>
      </div>
      <span class="text-xs font-bold text-gray-800"><?php echo count($recentSuppliers); ?></span>
      <span class="text-[10px] text-gray-500">Recent</span>
    </div>
  </div>
</div>
        <!-- Search Bar -->
        <div class="mb-6">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400 text-lg"></i>
                </div>
                <input type="text" id="supplierSearch"
                    class="w-full pl-12 pr-4 py-3 bg-white rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-200 focus:border-purple-400 text-base placeholder-gray-500 shadow input-focus"
                    placeholder="Search suppliers by name, phone, GST...">
            </div>
        </div>
        <!-- Supplier List -->
        <div id="supplierList" class="space-y-3">
            <?php if (count($suppliers) === 0): ?>
                <div class="text-center py-12">
                    <div class="w-20 h-20 mx-auto bg-gradient-to-br from-indigo-100 to-purple-100 rounded-2xl flex items-center justify-center mb-4">
                        <i class="fas fa-truck text-4xl text-indigo-300"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">No suppliers yet</h3>
                    <p class="text-gray-500 text-sm mb-5">Start building your supplier network</p>
                    <button id="addSupplierBtnEmpty" class="bg-gradient-to-br from-purple-500 to-indigo-500 text-white px-6 py-3 rounded-xl text-base font-semibold shadow-lg flex items-center gap-2 hover:from-purple-600 hover:to-indigo-600 transition">
                        <i class="fas fa-user-plus"></i>
                        Add Your First Supplier
                    </button>
                </div>
            <?php else: ?>
                <?php $serial = 1; ?>
                <?php foreach ($suppliers as $id => $s): ?>
                <div class="supplier-item flex items-center gap-3 p-3 bg-white rounded-xl shadow border border-gray-100 hover:shadow-md transition" data-supplier-id="<?= $id ?>" data-name="<?= strtolower($s['name']) ?>" data-phone="<?= $s['phone'] ?>" data-gst="<?= strtolower($s['gstin']) ?>">
                    <!-- Avatar -->
                    <div class="relative">
                        <div class="w-12 h-12 avatar-gradient rounded-xl flex items-center justify-center shadow-sm">
                            <span class="text-indigo-600 font-bold text-lg">
                                <?= strtoupper(substr($s['name'], 0, 2)) ?>
                            </span>
                        </div>
                        <div class="absolute -top-1 -right-1 w-5 h-5 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-xs font-bold"><?= $serial++ ?></span>
                        </div>
                    </div>
                    <!-- Supplier Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 text-base leading-tight truncate">
                                    <?= htmlspecialchars($s['name']) ?>
                                </h3>
                                <div class="flex items-center space-x-2 mt-0.5">
                                    <?php if (!empty($s['phone'])): ?>
                                        <span class="inline-flex items-center text-sm text-gray-500">
                                            <?= htmlspecialchars($s['phone']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($s['gstin'])): ?>
                                        <span class="inline-flex items-center text-sm text-gray-400 ml-2">
                                            GSTIN: <?= htmlspecialchars($s['gstin']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <?php if (!empty($s['address'])): ?>
                                        <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-semibold"><?= htmlspecialchars($s['address']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($s['state'])): ?>
                                        <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-semibold"><?= htmlspecialchars($s['state']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $sid = $s['id'];
                                $stats = $supplierStats[$sid] ?? ['purchase_count'=>0,'total_weight'=>0,'due_amount'=>0];
                                ?>
                                <div class="flex flex-wrap gap-2 mt-1 text-xs">
                                  <span class="bg-green-50 text-green-700 px-2 py-0.5 rounded">Weight: <?php echo number_format($stats['total_weight'],2); ?>g</span>
                                  <span class="bg-red-50 text-red-700 px-2 py-0.5 rounded">Due: ₹<?php echo number_format($stats['due_amount'],2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- WhatsApp Button (optional) -->
                    <?php if (!empty($s['phone'])): ?>
                        <a href="https://wa.me/91<?= $s['phone'] ?>?text=<?= urlencode('Greetings from ' . ($userInfo['FirmName'] ?? 'JewelEntry') . '!') ?>"
                           target="_blank"
                           onclick="event.stopPropagation();"
                           class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center text-white shadow-sm hover:bg-green-600 transition-colors">
                            <i class="fab fa-whatsapp text-base"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- Floating Action Button -->
        <button id="addSupplierBtn" class="floating-action w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-full shadow-xl flex items-center justify-center text-white text-2xl hover:scale-110 transition">
            <i class="fas fa-user-plus"></i>
        </button>
        <!-- Add Supplier Modal -->
        <div id="addSupplierModal" class="fixed inset-0 flex items-center justify-center z-50 modal-backdrop hidden transition-opacity duration-200">
          <div class="bg-white rounded-2xl modal-shadow w-full max-w-md mx-auto p-0 overflow-hidden animate-scaleIn">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b">
              <div class="flex items-center gap-2">
                <div class="w-9 h-9 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-full flex items-center justify-center">
                  <i class="fas fa-user-plus text-white text-lg"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Add New Supplier</h3>
              </div>
              <button type="button" id="closeSupplierModalBtn" class="text-gray-400 hover:text-purple-600 text-2xl focus:outline-none modal-close-btn">&times;</button>
            </div>
            <!-- Modal Body -->
            <form id="addSupplierForm" class="px-6 py-5 space-y-3" method="POST" action="../api/add_supplier.php" autocomplete="off">
              <div class="relative">
                <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                <input type="text" name="name" required class="pl-10 pr-3 py-2 w-full rounded-xl border border-gray-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all" placeholder="Supplier Name *">
              </div>
              <div class="relative">
                <i class="fas fa-user-tie absolute left-3 top-3 text-gray-400"></i>
                <input type="text" name="contact_info" class="pl-10 pr-3 py-2 w-full rounded-xl border border-gray-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all" placeholder="Contact Person">
              </div>
              <div class="relative">
                <i class="fas fa-phone absolute left-3 top-3 text-gray-400"></i>
                <input type="tel" name="phone" required class="pl-10 pr-3 py-2 w-full rounded-xl border border-gray-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all" placeholder="Phone Number *">
              </div>
              <div class="relative">
                <i class="fas fa-envelope absolute left-3 top-3 text-gray-400"></i>
                <input type="email" name="email" class="pl-10 pr-3 py-2 w-full rounded-xl border border-gray-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all" placeholder="Email Address">
              </div>
              <div class="relative">
                <i class="fas fa-map-marker-alt absolute left-3 top-3 text-gray-400"></i>
                <input type="text" name="address" class="pl-10 pr-3 py-2 w-full rounded-xl border border-gray-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all" placeholder="Address">
              </div>
              <div class="relative">
                <i class="fas fa-flag absolute left-3 top-3 text-gray-400"></i>
                <input type="text" name="state" class="pl-10 pr-3 py-2 w-full rounded-xl border border-gray-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all" placeholder="State">
              </div>
              <div class="relative">
                <i class="fas fa-id-card absolute left-3 top-3 text-gray-400"></i>
                <input type="text" name="gstin" class="pl-10 pr-3 py-2 w-full rounded-xl border border-gray-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all" placeholder="GST Number">
              </div>
              <div class="relative">
                <i class="fas fa-file-invoice-dollar absolute left-3 top-3 text-gray-400"></i>
                <input type="text" name="payment_terms" class="pl-10 pr-3 py-2 w-full rounded-xl border border-gray-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all" placeholder="Payment Terms">
              </div>
              <div class="relative">
                <i class="fas fa-sticky-note absolute left-3 top-3 text-gray-400"></i>
                <textarea name="notes" rows="2" class="pl-10 pr-3 py-2 w-full rounded-xl border border-gray-200 focus:ring-2 focus:ring-purple-200 focus:border-purple-400 transition-all" placeholder="Notes"></textarea>
              </div>
              <div class="flex justify-end gap-2 pt-2">
                <button type="button" id="cancelAddSupplier2" class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200 transition">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-500 text-white font-semibold shadow hover:from-purple-600 hover:to-indigo-600 transition flex items-center gap-2">
                  <i class="fas fa-plus"></i> Add Supplier
                </button>
              </div>
            </form>
          </div>
        </div>
    </div>
    <!-- Bottom Navigation -->
    <nav class="bottom-nav fixed bottom-0 left-0 right-0 shadow-xl">
        <div class="px-4 py-2">
            <div class="flex justify-around">
                <a href="/JewelEntry/pages/home" data-nav-id="home" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Home</span>
                </a>
                <button data-nav-id="search" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300" onclick="window.location.href='/JewelEntry/pages/sale-entry'">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cash-register text-green-500 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-700 font-medium">Sales</span>
                </button>
                <a href="/JewelEntry/pages/add" data-nav-id="add" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Add</span>
                </a>
                <button data-nav-id="alerts_nav" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-gray-400 text-sm"></i>
                    </div>
                    <span class="text-xs text-gray-400 font-medium">Alerts</span>
                </button>
                <a href="suppliers" data-nav-id="suppliers" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                        <i class="fas fa-truck text-orange-500 text-sm"></i>
                    </div>
                    <span class="text-xs text-orange-500 font-bold">Suppliers</span>
                </a>
            </div>
        </div>
    </nav>
    <script>
        // Floating Add Supplier Button - open modal
        const addSupplierBtn = document.getElementById('addSupplierBtn');
        const addSupplierBtnEmpty = document.getElementById('addSupplierBtnEmpty');
        const addSupplierModal = document.getElementById('addSupplierModal');
        const cancelAddSupplier2 = document.getElementById('cancelAddSupplier2');
        const closeSupplierModalBtn = document.getElementById('closeSupplierModalBtn');
        const addSupplierForm = document.getElementById('addSupplierForm');
        const searchInput = document.getElementById('supplierSearch');
        const supplierItems = document.querySelectorAll('.supplier-item');
        function showModal() {
            addSupplierForm.reset();
            addSupplierModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function hideModal() {
            addSupplierModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
            addSupplierForm.reset();
        }
        if (addSupplierBtn) addSupplierBtn.addEventListener('click', showModal);
        if (addSupplierBtnEmpty) addSupplierBtnEmpty.addEventListener('click', showModal);
        if (cancelAddSupplier2) cancelAddSupplier2.addEventListener('click', hideModal);
        if (closeSupplierModalBtn) closeSupplierModalBtn.addEventListener('click', hideModal);
        if (addSupplierModal) {
            addSupplierModal.addEventListener('click', (e) => {
                if (e.target === addSupplierModal) {
                    hideModal();
                }
            });
        }
        // --- Bottom Nav Active State Logic ---
        function setActiveNavButton(activeButton) {
            const navButtons = document.querySelectorAll('.nav-btn');
            navButtons.forEach((btn) => {
                const iconDiv = btn.querySelector('div');
                const textSpan = btn.querySelector('span');
                const iconI = btn.querySelector('i');
                btn.style.transform = 'translateY(0)';
                if (iconDiv) {
                    iconDiv.className = 'w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center transition-all duration-200';
                }
                if (iconI) {
                    iconI.classList.remove('text-white');
                    ['text-blue-500', 'text-green-500', 'text-purple-500', 'text-red-500', 'text-amber-500'].forEach((cls) => iconI.classList.remove(cls));
                    iconI.classList.add('text-gray-400');
                }
                if (textSpan) {
                    textSpan.className = 'text-xs text-gray-400 font-medium transition-all duration-200';
                }
            });
            if (!activeButton) return;
            const currentIconDiv = activeButton.querySelector('div');
            const currentTextSpan = activeButton.querySelector('span');
            const currentIconI = activeButton.querySelector('i');
            const navId = activeButton.dataset.navId;
            let colorName = 'blue';
            if (navId === 'home') colorName = 'blue';
            else if (navId === 'search') colorName = 'green';
            else if (navId === 'add') colorName = 'purple';
            else if (navId === 'alerts_nav') colorName = 'red';
            else if (navId === 'profile') colorName = 'amber';
            else if (navId === 'suppliers') colorName = 'purple';
            if (currentIconDiv) {
                currentIconDiv.className = `w-8 h-8 bg-gradient-to-br from-${colorName}-500 to-${colorName}-600 rounded-lg flex items-center justify-center shadow-lg transition-all duration-200`;
            }
            if (currentIconI) {
                currentIconI.classList.remove('text-gray-400');
                currentIconI.classList.add('text-white');
            }
            if (currentTextSpan) {
                currentTextSpan.className = `text-xs text-${colorName}-600 font-bold transition-all duration-200`;
            }
            activeButton.style.transform = 'translateY(-5px)';
        }
        function initializeNavigation() {
            const navButtons = document.querySelectorAll('.nav-btn');
            navButtons.forEach((btn) => {
                btn.addEventListener('click', function (event) {
                    setActiveNavButton(this);
                });
            });
            // Set active navigation based on current page
            const currentPath = window.location.pathname.split('/').pop();
            if (currentPath === 'home.php' || currentPath === '' || currentPath === 'index.html') {
                const homeButton = document.querySelector('.nav-btn[data-nav-id="home"]');
                if (homeButton) setActiveNavButton(homeButton);
            } else if (currentPath === 'profile.php') {
                const profileButton = document.querySelector('.nav-btn[data-nav-id="profile"]');
                if (profileButton) setActiveNavButton(profileButton);
            } else if (currentPath === 'suppliers.php') {
                const suppliersButton = document.querySelector('.nav-btn[data-nav-id="suppliers"]');
                if (suppliersButton) setActiveNavButton(suppliersButton);
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            initializeNavigation();
        });
        // --- Search Functionality ---
        if (searchInput && supplierItems.length > 0) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                supplierItems.forEach(item => {
                    const supplierName = item.getAttribute('data-name') || '';
                    const supplierPhone = item.getAttribute('data-phone') || '';
                    const supplierGst = item.getAttribute('data-gst') || '';
                    const shouldShow = (supplierName.includes(searchTerm) || supplierPhone.includes(searchTerm) || supplierGst.includes(searchTerm));
                    if (shouldShow) {
                        item.style.display = 'flex';
                        item.style.animation = 'fadeIn 0.3s ease-out';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        // --- AJAX Supplier Add Functionality ---
        const supplierNotification = document.getElementById('supplierNotification');
        addSupplierForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            supplierNotification.classList.add('hidden');
            const formData = new FormData(addSupplierForm);
            const data = {};
            formData.forEach((value, key) => { data[key] = value; });
            try {
                const response = await fetch('../api/add_supplier.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    hideModal();
                    supplierNotification.innerHTML = `<div class='bg-green-100 border-l-4 border-green-500 text-green-700 p-3 rounded mb-2'>Supplier added successfully!</div>`;
                    supplierNotification.classList.remove('hidden');
                    setTimeout(() => { window.location.reload(); }, 1200);
                } else {
                    showSupplierError(result.message || 'Failed to add supplier.');
                }
            } catch (err) {
                showSupplierError('Network error. Please try again.');
            }
        });
        function showSupplierError(msg) {
            const modal = document.getElementById('addSupplierModal');
            let errorDiv = document.getElementById('supplierModalError');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = 'supplierModalError';
                errorDiv.className = 'bg-red-100 border-l-4 border-red-500 text-red-700 p-2 rounded mb-2 text-sm';
                addSupplierForm.prepend(errorDiv);
            }
            errorDiv.textContent = msg;
        }
    </script>
</body>
</html>
<?php $conn->close(); ?> 