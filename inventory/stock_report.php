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
    .table-responsive { overflow-x: auto; border-radius: 12px; border: 1px solid #e5e7eb; }
    .table-responsive table { min-width: 100%; font-size: 13px; border-collapse: separate; border-spacing: 0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
    .table-header th {
      position: sticky;
      top: 0;
      background: #f8fafc;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      color: #475569;
      border-bottom: 2px solid #e5e7eb;
      z-index: 2;
      padding: 10px 8px;
    }
    .enhanced-table-row {
      transition: background 0.2s;
    }
    .enhanced-table-row:nth-child(even) {
      background: #f3f4f6;
    }
    .enhanced-table-row:nth-child(odd) {
      background: #fff;
    }
    .enhanced-table-row:hover {
      background: #e0e7ef;
    }
    .enhanced-table-cell {
      padding: 10px 8px;
      border-bottom: 1px solid #f1f5f9;
      font-size: 13px;
      color: #374151;
      white-space: nowrap;
    }
    .enhanced-table-row:last-child .enhanced-table-cell {
      border-bottom: none;
    }
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
    /* Colorful badge styles */
    .badge {
      display: inline-block;
      padding: 2px 10px;
      border-radius: 9999px;
      font-size: 12px;
      font-weight: 600;
      color: #374151;
      margin-right: 2px;
      margin-bottom: 1px;
      border: 1px solid #e5e7eb;
    }
    .badge-purity-24 { background: #fef9c3; color: #b45309; }
    .badge-purity-22 { background: #fdf6b2; color: #b45309; }
    .badge-purity-20 { background: #fce7f3; color: #a21caf; }
    .badge-purity-18 { background: #dbeafe; color: #1e40af; }
    .badge-purity-14 { background: #d1fae5; color: #065f46; }
    .badge-purity-other { background: #ede9fe; color: #6d28d9; }
    .badge-type { background: #e0e7ff; color: #3730a3; }
    .badge-material { background: #fee2e2; color: #991b1b; }
    .badge-action { background: #cffafe; color: #155e75; }
    .badge-ring { background: #fef9c3; color: #b45309; }
    .badge-earring { background: #dbeafe; color: #1e40af; }
    .badge-chain { background: #d1fae5; color: #065f46; }
    .badge-bracelet { background: #fce7f3; color: #a21caf; }
    .badge-default { background: #f3f4f6; color: #374151; }
    .chart-container {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 220px;
      min-height: 180px;
      background: none;
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
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div class="chart-container">
        <h4 class="text-xs font-semibold text-gray-600 mb-1">Distribution by Purity</h4>
        <canvas id="jewelryPurityChart"></canvas>
      </div>
      <div class="chart-container">
        <h4 class="text-xs font-semibold text-gray-600 mb-1">Distribution by Type</h4>
        <canvas id="jewelryTypeChart"></canvas>
      </div>
    </div>
    <div class="table-responsive mt-2">
      <table class="min-w-full text-xs">
        <thead><tr class="table-header">
          <th title="Purity of the item">Purity</th>
          <th title="Jewellery type">Type</th>
          <th title="Number of items">Count</th>
          <th title="Gross Weight in grams">Gross Wt. (g)</th>
          <th title="Net Weight in grams">Net Wt. (g)</th>
        </tr></thead>
        <tbody id="jewelryStockTable"></tbody>
      </table>
    </div>
  </div>
  <div class="section-card p-4">
    <h3 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
      <i class="fas fa-cubes mr-2 text-blue-500"></i>Inventory Metal by Purity & Material
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <div class="chart-container">
        <h4 class="text-xs font-semibold text-gray-600 mb-1">Distribution by Purity</h4>
        <canvas id="inventoryPurityChart"></canvas>
      </div>
      <div class="chart-container">
        <h4 class="text-xs font-semibold text-gray-600 mb-1">Distribution by Material</h4>
        <canvas id="inventoryMaterialChart"></canvas>
      </div>
    </div>
    <div class="table-responsive mt-2">
      <table class="min-w-full text-xs">
        <thead><tr class="table-header">
          <th title="Purity">Purity</th>
          <th title="Material">Material</th>
          <th title="Number of lots">Lots</th>
          <th title="Total Stock in grams">Total Stock (g)</th>
          <th title="Unallocated in grams">Unallocated (g)</th>
        </tr></thead>
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
        <thead><tr class="table-header">
          <th>Date</th><th>Type</th><th>Material</th><th>Purity</th><th>Weight (g)</th><th>Action</th>
        </tr></thead>
        <tbody id="recentStockTable"></tbody>
      </table>
    </div>
  </div>
</main>
<?php include 'includes/bottom_nav.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  fetch('api/get_stock_report.php')
    .then(res => res.json())
    .then(res => {
      if (!res.success) throw new Error(res.message);
      renderJewelryStock(res.data.jewelry_stock);
      renderInventoryStock(res.data.inventory_stock);
      renderJewelryPurityChart(res.data.jewelry_stock);
      renderJewelryTypeChart(res.data.jewelry_stock);
      renderInventoryPurityChart(res.data.inventory_stock);
      renderInventoryMaterialChart(res.data.inventory_stock);
      fetchRecentStock();
    })
    .catch(err => {
      document.getElementById('jewelryStockTable').innerHTML = `<tr><td colspan='5' class='text-center text-red-500'>${err.message}</td></tr>`;
      document.getElementById('inventoryStockTable').innerHTML = `<tr><td colspan='5' class='text-center text-red-500'>${err.message}</td></tr>`;
    });

  function getPurityBadge(purity) {
    purity = parseFloat(purity);
    if (purity >= 99.5) return `<span class='badge badge-purity-24'>24K</span>`;
    if (purity >= 91.6 && purity <= 92.0) return `<span class='badge badge-purity-22'>22K</span>`;
    if (purity >= 83.3 && purity < 91.6) return `<span class='badge badge-purity-20'>20K</span>`;
    if (purity >= 75.0 && purity < 83.3) return `<span class='badge badge-purity-18'>18K</span>`;
    if (purity >= 58.3 && purity < 75.0) return `<span class='badge badge-purity-14'>14K</span>`;
    return `<span class='badge badge-purity-other'>${purity}K</span>`;
  }
  function getTypeBadge(type) {
    const t = type.toLowerCase();
    if (t.includes('ring')) return `<span class='badge badge-ring'>${type}</span>`;
    if (t.includes('earring')) return `<span class='badge badge-earring'>${type}</span>`;
    if (t.includes('chain')) return `<span class='badge badge-chain'>${type}</span>`;
    if (t.includes('bracelet')) return `<span class='badge badge-bracelet'>${type}</span>`;
    return `<span class='badge badge-type'>${type}</span>`;
  }
  function getMaterialBadge(mat) {
    return `<span class='badge badge-material'>${mat}</span>`;
  }
  function getActionBadge(action) {
    return `<span class='badge badge-action'>${action}</span>`;
  }
  function renderJewelryStock(data) {
    const tbody = document.getElementById('jewelryStockTable');
    if (!data.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-gray-400">No data</td></tr>'; return; }
    tbody.innerHTML = data.map(row => `
      <tr class="enhanced-table-row">
        <td class="enhanced-table-cell">${getPurityBadge(row.purity)}</td>
        <td class="enhanced-table-cell">${getTypeBadge(row.jewelry_type)}</td>
        <td class="enhanced-table-cell">${row.item_count}</td>
        <td class="enhanced-table-cell">${parseFloat(row.total_gross_weight).toFixed(2)}</td>
        <td class="enhanced-table-cell">${parseFloat(row.total_net_weight).toFixed(2)}</td>
      </tr>
    `).join('');
  }
  function renderInventoryStock(data) {
    const tbody = document.getElementById('inventoryStockTable');
    if (!data.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-gray-400">No data</td></tr>'; return; }
    tbody.innerHTML = data.map(row => `
      <tr class="enhanced-table-row">
        <td class="enhanced-table-cell">${getPurityBadge(row.purity)}</td>
        <td class="enhanced-table-cell">${getMaterialBadge(row.material_type)}</td>
        <td class="enhanced-table-cell">${row.lot_count}</td>
        <td class="enhanced-table-cell">${parseFloat(row.total_stock).toFixed(2)}</td>
        <td class="enhanced-table-cell">${parseFloat(row.remaining_stock).toFixed(2)}</td>
      </tr>
    `).join('');
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
    // Soft pastel color palette
    const colors = [
      '#fef9c3', // soft yellow
      '#dbeafe', // soft blue
      '#d1fae5', // soft green
      '#fce7f3', // soft pink
      '#ede9fe', // soft purple
      '#fee2e2', // soft red
      '#fdf6b2', // light gold
      '#e0e7ff', // light indigo
      '#cffafe', // light teal
      '#f3f4f6', // light gray
      '#f1f5f9', // extra light gray
      '#f9fafb', // almost white
      '#e0f2fe', // light sky
      '#fef3c7', // light amber
      '#e7e5e4', // stone
      '#f5d0fe', // light fuchsia
      '#bbf7d0', // mint
      '#fde68a', // light yellow
      '#a7f3d0', // light emerald
      '#fbcfe8'  // light rose
    ];
    return colors[idx % colors.length];
  }
  function renderJewelryPurityChart(data) {
    if (!data.length) return;
    const purityMap = {};
    data.forEach(row => {
      const purity = purityToCarat(row.purity);
      purityMap[purity] = (purityMap[purity] || 0) + parseFloat(row.total_net_weight);
    });
    const labels = Object.keys(purityMap);
    const values = Object.values(purityMap);
    new Chart(document.getElementById('jewelryPurityChart').getContext('2d'), {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: labels.map((_, idx) => getColor(idx)),
        }]
      },
      options: {
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.parsed.toFixed(2)}g` } }
        }
      }
    });
  }
  function renderJewelryTypeChart(data) {
    if (!data.length) return;
    const typeMap = {};
    data.forEach(row => {
      const type = row.jewelry_type;
      typeMap[type] = (typeMap[type] || 0) + parseFloat(row.total_net_weight);
    });
    const labels = Object.keys(typeMap);
    const values = Object.values(typeMap);
    new Chart(document.getElementById('jewelryTypeChart').getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Net Weight (g)',
          data: values,
          backgroundColor: labels.map((_, idx) => getColor(idx)),
        }]
      },
      options: {
        indexAxis: 'y',
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.parsed.x.toFixed(2)}g` } }
        },
        scales: { x: { beginAtZero: true } }
      }
    });
  }
  function renderInventoryPurityChart(data) {
    if (!data.length) return;
    const purityMap = {};
    data.forEach(row => {
      const purity = purityToCarat(row.purity);
      purityMap[purity] = (purityMap[purity] || 0) + parseFloat(row.remaining_stock);
    });
    const labels = Object.keys(purityMap);
    const values = Object.values(purityMap);
    new Chart(document.getElementById('inventoryPurityChart').getContext('2d'), {
      type: 'pie',
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: labels.map((_, idx) => getColor(idx)),
        }]
      },
      options: {
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.parsed.toFixed(2)}g` } }
        }
      }
    });
  }
  function renderInventoryMaterialChart(data) {
    if (!data.length) return;
    const matMap = {};
    data.forEach(row => {
      const mat = row.material_type;
      matMap[mat] = (matMap[mat] || 0) + parseFloat(row.remaining_stock);
    });
    const labels = Object.keys(matMap);
    const values = Object.values(matMap);
    new Chart(document.getElementById('inventoryMaterialChart').getContext('2d'), {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Unallocated Stock (g)',
          data: values,
          backgroundColor: labels.map((_, idx) => getColor(idx)),
        }]
      },
      options: {
        indexAxis: 'y',
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.parsed.x.toFixed(2)}g` } }
        },
        scales: { x: { beginAtZero: true } }
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
    tbody.innerHTML = data.map(row => `
      <tr class="enhanced-table-row">
        <td class="enhanced-table-cell">${row.date}</td>
        <td class="enhanced-table-cell">${getTypeBadge(row.type)}</td>
        <td class="enhanced-table-cell">${getMaterialBadge(row.material_type)}</td>
        <td class="enhanced-table-cell">${getPurityBadge(row.purity)}</td>
        <td class="enhanced-table-cell">${parseFloat(row.weight).toFixed(2)}</td>
        <td class="enhanced-table-cell">${getActionBadge(row.action)}</td>
      </tr>
    `).join('');
  }
});
</script>
</body>
</html> 