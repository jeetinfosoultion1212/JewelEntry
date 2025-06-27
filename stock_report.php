<?php
session_start();
require 'config/config.php';
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];
$firm_id = $_SESSION['firmID'];

// Fetch user and firm details
$userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Report - JewelEntry</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="css/home.css">
  <style>
    body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
    .section-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid rgba(0,0,0,0.04); margin-bottom: 1.5rem; }
    .table-responsive { overflow-x: auto; border-radius: 8px; border: 1px solid #e5e7eb; }
    .table-responsive table { min-width: 100%; font-size: 12px; }
    .table-header { background: #f8fafc; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #475569; }
    .bottom-nav { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-top: 1px solid rgba(0,0,0,0.05); }
    .nav-btn { transition: all 0.2s ease; }
    .nav-btn:hover { transform: scale(1.05); }
    /* Additional enhancements for the recent stock list */
    .recent-stock-table {
      border-radius: 12px;
      background: rgba(255,255,255,0.95);
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      overflow-x: auto;
      font-size: 13px;
    }
    .recent-stock-table th, .recent-stock-table td {
      padding: 8px 10px;
      text-align: left;
      white-space: nowrap;
    }
    .recent-stock-table th {
      background: #f8fafc;
      font-weight: 600;
      color: #475569;
      font-size: 12px;
      border-bottom: 1px solid #e5e7eb;
    }
    .recent-stock-table tr {
      transition: background 0.2s;
    }
    .recent-stock-table tr:hover {
      background: #f3f4f6;
    }
    .recent-stock-table td {
      border-bottom: 1px solid #f1f5f9;
      font-size: 13px;
      color: #374151;
    }
    .recent-stock-table tr:last-child td {
      border-bottom: none;
    }
    .recent-stock-table-container {
      max-height: 220px;
      overflow-y: auto;
      border-radius: 12px;
    }
  </style>
</head>
<body class="bg-gray-50">
<header class="header-glass sticky top-0 z-50 shadow-md">
  <div class="px-3 py-2 flex items-center justify-between">
    <div class="flex items-center space-x-2">
      <div class="w-9 h-9 gradient-gold rounded-xl flex items-center justify-center shadow-lg floating">
        <?php if (!empty($userInfo['Logo'])): ?>
          <img src="<?php echo htmlspecialchars($userInfo['Logo']); ?>" alt="Firm Logo" class="w-full h-full object-cover rounded-xl">
        <?php else: ?>
          <i class="fas fa-gem text-white text-sm"></i>
        <?php endif; ?>
      </div>
      <div>
        <h1 class="text-sm font-bold text-gray-800"><?php echo $userInfo['FirmName']; ?></h1>
        <p class="text-xs text-gray-600 font-medium">Stock Report</p>
      </div>
    </div>
    <div class="flex items-center space-x-2">
      <div class="text-right">
        <p id="headerUserName" class="text-xs font-bold text-gray-800"><?php echo $userInfo['Name']; ?></p>
        <p id="headerUserRole" class="text-xs text-purple-600 font-medium"><?php echo $userInfo['Role']; ?></p>
      </div>
      <a href="profile.php" class="w-9 h-9 gradient-purple rounded-xl flex items-center justify-center shadow-lg overflow-hidden cursor-pointer relative transition-transform duration-200">
        <?php 
        $defaultImage = 'public/uploads/user.png';
        if (!empty($userInfo['image_path']) && file_exists($userInfo['image_path'])): ?>
          <img src="<?php echo htmlspecialchars($userInfo['image_path']); ?>" alt="User Profile" class="w-full h-full object-cover">
        <?php elseif (file_exists($defaultImage)): ?>
          <img src="<?php echo htmlspecialchars($defaultImage); ?>" alt="Default User" class="w-full h-full object-cover">
        <?php else: ?>
          <i class="fas fa-user-crown text-white text-sm"></i>
        <?php endif; ?>
      </a>
    </div>
  </div>
</header>
<main class="px-4 pb-24 pt-4">
  <div class="section-card p-4">
    <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
      <i class="fas fa-layer-group mr-2 text-yellow-500"></i>Jewellery Stock by Purity & Type
    </h3>
    <div class="chart-container mb-4" style="height:220px;">
      <canvas id="jewelryStockChart"></canvas>
    </div>
    <div class="table-responsive mt-2">
      <table class="min-w-full text-xs">
        <thead><tr class="table-header"><th>Purity</th><th>Type</th><th>Count</th><th>Gross Wt. (g)</th><th>Net Wt. (g)</th></tr></thead>
        <tbody id="jewelryStockTable"></tbody>
      </table>
    </div>
  </div>
  <div class="section-card p-4">
    <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
      <i class="fas fa-cubes mr-2 text-blue-500"></i>Inventory Metal by Purity & Material
    </h3>
    <div class="chart-container mb-4" style="height:220px;">
      <canvas id="inventoryStockChart"></canvas>
    </div>
    <div class="table-responsive mt-2">
      <table class="min-w-full text-xs">
        <thead><tr class="table-header"><th>Purity</th><th>Material</th><th>Lots</th><th>Total Stock (g)</th><th>Unallocated (g)</th></tr></thead>
        <tbody id="inventoryStockTable"></tbody>
      </table>
    </div>
  </div>
  <div class="section-card p-4 mt-4">
    <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
      <i class="fas fa-history mr-2 text-indigo-500"></i>Recent Stock Transactions
    </h3>
    <div class="recent-stock-table-container">
      <table class="recent-stock-table min-w-full">
        <thead><tr><th>Date</th><th>Type</th><th>Material</th><th>Purity</th><th>Weight (g)</th><th>Action</th></tr></thead>
        <tbody id="recentStockTable"></tbody>
      </table>
    </div>
  </div>
</main>
<nav class="bottom-nav fixed bottom-0 left-0 right-0 z-40">
  <div class="px-4 py-2">
    <div class="flex justify-around">
      <a href="home.php" class="nav-item flex flex-col items-center space-y-1 py-2 px-3 rounded-lg">
        <i class="nav-icon fas fa-home text-gray-400 text-lg"></i>
        <span class="nav-text text-xs text-gray-500 font-medium">Home</span>
      </a>
      <a href="stock_report.php" class="nav-item flex flex-col items-center space-y-1 py-2 px-3 rounded-lg gradient-gold active">
        <i class="nav-icon fas fa-cubes text-yellow-600 text-lg"></i>
        <span class="nav-text text-xs text-yellow-700 font-bold">Stock</span>
      </a>
      <a href="reports.php" class="nav-item flex flex-col items-center space-y-1 py-2 px-3 rounded-lg">
        <i class="nav-icon fas fa-chart-pie text-gray-400 text-lg"></i>
        <span class="nav-text text-xs text-gray-500 font-medium">Reports</span>
      </a>
      <a href="customers.php" class="nav-item flex flex-col items-center space-y-1 py-2 px-3 rounded-lg">
        <i class="nav-icon fas fa-users text-gray-400 text-lg"></i>
        <span class="nav-text text-xs text-gray-500 font-medium">Customers</span>
      </a>
    </div>
  </div>
</nav>
<script>
document.addEventListener('DOMContentLoaded', function() {
  fetch('api/get_stock_report.php')
    .then(res => res.json())
    .then(res => {
      if (!res.success) throw new Error(res.message);
      renderJewelryStock(res.data.jewelry_stock);
      renderInventoryStock(res.data.inventory_stock);
      renderJewelryChart(res.data.jewelry_stock);
      renderInventoryChart(res.data.inventory_stock);
      fetchRecentStock();
    })
    .catch(err => {
      document.getElementById('jewelryStockTable').innerHTML = `<tr><td colspan='5' class='text-center text-red-500'>${err.message}</td></tr>`;
      document.getElementById('inventoryStockTable').innerHTML = `<tr><td colspan='5' class='text-center text-red-500'>${err.message}</td></tr>`;
    });

  function renderJewelryStock(data) {
    const tbody = document.getElementById('jewelryStockTable');
    if (!data.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-gray-400">No data</td></tr>'; return; }
    tbody.innerHTML = data.map(row => `<tr><td>${row.purity}</td><td>${row.jewelry_type}</td><td>${row.item_count}</td><td>${parseFloat(row.total_gross_weight).toFixed(2)}</td><td>${parseFloat(row.total_net_weight).toFixed(2)}</td></tr>`).join('');
  }
  function renderInventoryStock(data) {
    const tbody = document.getElementById('inventoryStockTable');
    if (!data.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-gray-400">No data</td></tr>'; return; }
    tbody.innerHTML = data.map(row => `<tr><td>${row.purity}</td><td>${row.material_type}</td><td>${row.lot_count}</td><td>${parseFloat(row.total_stock).toFixed(2)}</td><td>${parseFloat(row.remaining_stock).toFixed(2)}</td></tr>`).join('');
  }
  function purityToCarat(purity) {
    purity = parseFloat(purity);
    if (purity >= 99.5) return '24K';
    if (purity >= 91.6 && purity <= 92.0) return '22K';
    if (purity >= 83.3 && purity < 91.6) return '20K';
    if (purity >= 75.0 && purity < 83.3) return '18K';
    if (purity >= 58.3 && purity < 75.0) return '14K';
    return purity + 'K';
  }
  function getColor(idx) {
    // Pastel color palette
    const colors = [
      '#fbbf24', '#60a5fa', '#34d399', '#f472b6', '#a78bfa', '#f87171', '#facc15', '#38bdf8', '#4ade80', '#c084fc'
    ];
    return colors[idx % colors.length];
  }
  function renderInventoryChart(data) {
    if (!data.length) return;
    // Group for grouped bar: X = purity, bars = material_type
    const purities = [...new Set(data.map(r => r.purity))].sort((a,b) => b-a);
    const materials = [...new Set(data.map(r => r.material_type))];
    const datasets = materials.map((mat, mIdx) => ({
      label: mat,
      data: purities.map((p, idx) => {
        const found = data.find(r => r.purity == p && r.material_type == mat);
        return found ? parseFloat(found.remaining_stock) : 0;
      }),
      backgroundColor: purities.map((_, idx) => getColor(idx)),
      borderWidth: 1
    }));
    new Chart(document.getElementById('inventoryStockChart').getContext('2d'), {
      type: 'bar',
      data: { labels: purities.map(purityToCarat), datasets },
      options: { responsive: true, plugins: { legend: { position: 'top' } },
        scales: { x: { stacked: false }, y: { beginAtZero: true, title: { display: true, text: 'Unallocated Stock (g)' } } }
      }
    });
  }
  function renderJewelryChart(data) {
    if (!data.length) return;
    // Group for stacked bar: X = purity, stacks = jewelry_type
    const purities = [...new Set(data.map(r => r.purity))].sort((a,b) => b-a);
    const types = [...new Set(data.map(r => r.jewelry_type))];
    const datasets = types.map((type, tIdx) => ({
      label: type,
      data: purities.map((p, idx) => {
        const found = data.find(r => r.purity == p && r.jewelry_type == type);
        return found ? parseFloat(found.total_net_weight) : 0;
      }),
      backgroundColor: purities.map((_, idx) => getColor(idx)),
      borderWidth: 1
    }));
    new Chart(document.getElementById('jewelryStockChart').getContext('2d'), {
      type: 'bar',
      data: { labels: purities.map(purityToCarat), datasets },
      options: { responsive: true, plugins: { legend: { position: 'top' } },
        scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Net Weight (g)' } } }
      }
    });
  }
  function fetchRecentStock() {
    fetch('api/get_stock_report.php?action=recent')
      .then(res => res.json())
      .then(res => {
        if (!res.success || !res.data.recent_stock) throw new Error('No data');
        renderRecentStock(res.data.recent_stock);
      })
      .catch(() => {
        document.getElementById('recentStockTable').innerHTML = '<tr><td colspan="6" class="text-center text-gray-400">No data</td></tr>';
      });
  }
  function renderRecentStock(data) {
    const tbody = document.getElementById('recentStockTable');
    if (!data.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-gray-400">No data</td></tr>'; return; }
    tbody.innerHTML = data.map(row => `<tr><td>${row.date}</td><td>${row.type}</td><td>${row.material_type}</td><td>${purityToCarat(row.purity)}</td><td>${parseFloat(row.weight).toFixed(2)}</td><td>${row.action}</td></tr>`).join('');
  }
});
</script>
</body>
</html> 