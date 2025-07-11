<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Karigar Management System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .tab-button.active { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
    .floating-animation { animation: float 3s ease-in-out infinite; }
    @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-10px); } }
    .pulse-ring { animation: pulse-ring 1.5s cubic-bezier(0.215, 0.61, 0.355, 1) infinite; }
    @keyframes pulse-ring { 0% { transform: scale(0.33); } 80%, 100% { opacity: 0; } }
    .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .card-hover { transition: all 0.3s ease; }
    .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.15); }
  </style>
</head>
<body class="bg-gradient-to-br from-orange-50 to-yellow-50 min-h-screen">

  <!-- Header -->
  <header class="gradient-bg text-white p-6 shadow-xl">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
      <div class="flex items-center space-x-4">
        <div class="w-12 h-12 bg-yellow-400 rounded-full flex items-center justify-center floating-animation">
          <span class="text-2xl">💎</span>
        </div>
        <div>
          <h1 class="text-3xl font-bold">Karigar Management System</h1>
          <p class="text-blue-100">Complete Jewelry Workshop Management</p>
        </div>
      </div>
      <div class="text-right">
        <p class="text-sm text-blue-100">Today's Date</p>
        <p class="text-lg font-semibold" id="currentDate"></p>
      </div>
    </div>
  </header>

  <!-- Navigation Tabs -->
  <div class="max-w-7xl mx-auto px-4 mt-6">
    <nav class="flex space-x-2 mb-8">
      <button onclick="showTab('dashboard')" class="tab-button active px-6 py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg">
        📊 Dashboard
      </button>
      <button onclick="showTab('orders')" class="tab-button px-6 py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg bg-white">
        📋 Orders
      </button>
      <button onclick="showTab('karigars')" class="tab-button px-6 py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg bg-white">
        👥 Karigars
      </button>
      <button onclick="showTab('stock')" class="tab-button px-6 py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg bg-white">
        📦 Stock
      </button>
      <button onclick="showTab('calculator')" class="tab-button px-6 py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg bg-white">
        🧮 Calculator
      </button>
      <button onclick="showTab('returns')" class="tab-button px-6 py-3 rounded-xl font-semibold transition-all duration-300 shadow-lg bg-white">
        📦 Returns
      </button>
    </nav>

    <!-- Dashboard Tab -->
    <div id="dashboard" class="tab-content active">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Stats Cards -->
        <div class="bg-white rounded-2xl shadow-xl p-6 card-hover">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600">Active Orders</p>
              <p class="text-3xl font-bold text-blue-600" id="activeOrdersCount">0</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <span class="text-2xl">📋</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-6 card-hover">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600">Total Karigars</p>
              <p class="text-3xl font-bold text-green-600" id="totalKarigars">0</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
              <span class="text-2xl">👥</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-6 card-hover">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600">Fine Gold Stock</p>
              <p class="text-3xl font-bold text-yellow-600" id="fineGoldStock">1000g</p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
              <span class="text-2xl">🥇</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-6 card-hover">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600">Pending Tasks</p>
              <p class="text-3xl font-bold text-red-600" id="pendingTasks">0</p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
              <span class="text-2xl">⏰</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-xl p-6 card-hover">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600">Completed Items</p>
              <p class="text-3xl font-bold text-purple-600" id="completedItemsCount">0</p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
              <span class="text-2xl">✅</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activities -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Recent Activities</h2>
        <div id="recentActivities" class="space-y-3">
          <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
              <span class="text-sm">📋</span>
            </div>
            <p class="text-gray-700">System initialized - Welcome to Karigar Management!</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Orders Tab -->
    <div id="orders" class="tab-content">
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Create New Order</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block mb-2 font-medium text-gray-700">Customer Name</label>
            <input type="text" id="customerName" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Enter customer name">
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Item Type</label>
            <select id="itemType" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
              <option value="">Select Item</option>
              <option value="Ring">Ring</option>
              <option value="Necklace">Necklace</option>
              <option value="Bracelet">Bracelet</option>
              <option value="Earrings">Earrings</option>
              <option value="Pendant">Pendant</option>
              <option value="Chain">Chain</option>
            </select>
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Weight (grams)</label>
            <input type="number" id="orderWeight" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Enter weight">
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Purity</label>
            <select id="orderPurity" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
              <option value="">Select Purity</option>
              <option value="22">22KT</option>
              <option value="18">18KT</option>
              <option value="14">14KT</option>
            </select>
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Delivery Date</label>
            <input type="date" id="deliveryDate" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Priority</label>
            <select id="orderPriority" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
              <option value="Normal">Normal</option>
              <option value="High">High</option>
              <option value="Urgent">Urgent</option>
            </select>
          </div>
        </div>
        <button onclick="createOrder()" class="mt-4 bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-3 rounded-xl hover:from-blue-600 hover:to-purple-700 transition-all duration-300 shadow-lg">
          Create Order
        </button>
      </div>

      <!-- Orders List -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Active Orders</h2>
        <div id="ordersList" class="space-y-4">
          <p class="text-gray-500 text-center py-8">No orders yet. Create your first order above!</p>
        </div>
      </div>
    </div>

    <!-- Karigars Tab -->
    <div id="karigars" class="tab-content">
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Add New Karigar</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block mb-2 font-medium text-gray-700">Karigar Name</label>
            <input type="text" id="karigarName" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Enter karigar name">
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Specialization</label>
            <select id="karigarSpecialization" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
              <option value="All">All Types</option>
              <option value="Rings">Rings</option>
              <option value="Necklaces">Necklaces</option>
              <option value="Bracelets">Bracelets</option>
              <option value="Earrings">Earrings</option>
            </select>
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Experience (years)</label>
            <input type="number" id="karigarExperience" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500" placeholder="Years of experience">
          </div>
        </div>
        <button onclick="addKarigar()" class="mt-4 bg-gradient-to-r from-green-500 to-teal-600 text-white px-6 py-3 rounded-xl hover:from-green-600 hover:to-teal-700 transition-all duration-300 shadow-lg">
          Add Karigar
        </button>
      </div>

      <!-- Karigars List -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Karigar Directory</h2>
        <div id="karigarsList" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <p class="text-gray-500 text-center py-8 col-span-full">No karigars added yet. Add your first karigar above!</p>
        </div>
      </div>
    </div>

    <!-- Stock Tab -->
    <div id="stock" class="tab-content">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Stock Cards -->
        <div class="bg-gradient-to-br from-yellow-400 to-yellow-600 text-white rounded-2xl shadow-xl p-6 card-hover">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-xl font-bold">Fine Gold (24KT)</h3>
              <p class="text-3xl font-bold" id="stockFineGold">1000.000g</p>
            </div>
            <div class="text-4xl opacity-80">🥇</div>
          </div>
          <div class="mt-4 flex space-x-2">
            <button onclick="showStockModal('fineGold')" class="bg-white bg-opacity-20 px-4 py-2 rounded-lg text-sm font-medium hover:bg-opacity-30 transition-all">
              Add Stock
            </button>
          </div>
        </div>

        <div class="bg-gradient-to-br from-red-400 to-red-600 text-white rounded-2xl shadow-xl p-6 card-hover">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-xl font-bold">Copper</h3>
              <p class="text-3xl font-bold" id="stockCopper">500.000g</p>
            </div>
            <div class="text-4xl opacity-80">🔴</div>
          </div>
          <div class="mt-4 flex space-x-2">
            <button onclick="showStockModal('copper')" class="bg-white bg-opacity-20 px-4 py-2 rounded-lg text-sm font-medium hover:bg-opacity-30 transition-all">
              Add Stock
            </button>
          </div>
        </div>

        <div class="bg-gradient-to-br from-gray-400 to-gray-600 text-white rounded-2xl shadow-xl p-6 card-hover">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-xl font-bold">Silver</h3>
              <p class="text-3xl font-bold" id="stockSilver">400.000g</p>
            </div>
            <div class="text-4xl opacity-80">⚪</div>
          </div>
          <div class="mt-4 flex space-x-2">
            <button onclick="showStockModal('silver')" class="bg-white bg-opacity-20 px-4 py-2 rounded-lg text-sm font-medium hover:bg-opacity-30 transition-all">
              Add Stock
            </button>
          </div>
        </div>
      </div>

      <!-- Stock Transactions -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Stock Transaction History</h2>
        <div id="stockTransactions" class="space-y-3">
          <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
              <span class="text-sm">📦</span>
            </div>
            <p class="text-gray-700">Initial stock loaded - Fine Gold: 1000g, Copper: 500g, Silver: 400g</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Calculator Tab -->
    <div id="calculator" class="tab-content">
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-2xl font-bold mb-6 text-gray-800">Gold Alloy Calculator</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-4">
            <div>
              <label class="block mb-2 font-medium text-gray-700">Purity (KT)</label>
              <select id="purity" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                <option value="">-- Select --</option>
                <option value="22">22KT</option>
                <option value="18">18KT</option>
                <option value="14">14KT</option>
              </select>
            </div>

            <div>
              <label class="block mb-2 font-medium text-gray-700">Color Tone</label>
              <select id="tone" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                <option value="">-- Select --</option>
                <option value="standard">Standard Yellow</option>
                <option value="light">Light Yellow</option>
                <option value="rose">Rose Gold</option>
                <option value="green">Green Gold</option>
              </select>
            </div>

            <div>
              <label class="block mb-2 font-medium text-gray-700">Final Weight (g)</label>
              <input type="number" id="finalWeight" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500" placeholder="e.g., 5">
            </div>

            <div>
              <label class="block mb-2 font-medium text-gray-700">Wastage (%)</label>
              <input type="number" id="wastage" value="6" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500" placeholder="e.g., 6">
            </div>

            <div>
              <label class="block mb-2 font-medium text-gray-700">Assign to Karigar</label>
              <select id="assignKarigar" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500">
                <option value="">-- Choose Karigar --</option>
              </select>
            </div>

            <button onclick="calculateAlloy()" class="w-full bg-gradient-to-r from-yellow-500 to-orange-500 text-white py-3 px-4 rounded-xl hover:from-yellow-600 hover:to-orange-600 transition-all duration-300 shadow-lg">
              Calculate Alloy
            </button>
          </div>

          <div id="calculatorResults" class="space-y-4 hidden">
            <h3 class="text-lg font-semibold text-gray-800">Calculation Results</h3>
            <div>
              <label class="block text-sm font-medium text-gray-600">Fine Gold (24KT)</label>
              <input id="fineGold" type="text" readonly class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-600">Total Alloy Required</label>
              <input id="alloy" type="text" readonly class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-600">Copper</label>
              <input id="copper" type="text" readonly class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-600">Silver</label>
              <input id="silver" type="text" readonly class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50">
            </div>
            <button onclick="issueToKarigar()" class="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white py-3 px-4 rounded-xl hover:from-green-600 hover:to-teal-600 transition-all duration-300 shadow-lg">
              Issue Materials to Karigar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Returns Tab -->
    <div id="returns" class="tab-content">
      <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Return Completed Item</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label class="block mb-2 font-medium text-gray-700">Karigar Name</label>
            <select id="returnKarigar" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
              <option value="">Select Karigar</option>
            </select>
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Order ID</label>
            <select id="returnOrderId" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
              <option value="">Select Order</option>
            </select>
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Actual Weight (g)</label>
            <input type="number" id="actualWeight" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="Enter actual weight">
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Wastage (g)</label>
            <input type="number" id="returnWastage" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="Enter wastage">
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Quality Check</label>
            <select id="qualityCheck" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
              <option value="Pass">Pass</option>
              <option value="Minor Issues">Minor Issues</option>
              <option value="Reject">Reject</option>
            </select>
          </div>
          <div>
            <label class="block mb-2 font-medium text-gray-700">Notes</label>
            <input type="text" id="returnNotes" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500" placeholder="Any additional notes">
          </div>
        </div>
        <button onclick="returnCompletedItem()" class="mt-4 bg-gradient-to-r from-purple-500 to-pink-600 text-white px-6 py-3 rounded-xl hover:from-purple-600 hover:to-pink-700 transition-all duration-300 shadow-lg">
          Return Item
        </button>
      </div>

      <!-- Completed Items List -->
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Completed Items</h2>
        <div id="completedItemsList" class="space-y-4">
          <p class="text-gray-500 text-center py-8">No completed items yet.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Stock Modal -->
  <div id="stockModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
      <h3 class="text-xl font-bold mb-4">Add Stock</h3>
      <div class="space-y-4">
        <div>
          <label class="block mb-2 font-medium text-gray-700">Material</label>
          <input type="text" id="modalMaterial" readonly class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50">
        </div>
        <div>
          <label class="block mb-2 font-medium text-gray-700">Quantity (grams)</label>
          <input type="number" id="modalQuantity" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Enter quantity">
        </div>
      </div>
      <div class="flex space-x-3 mt-6">
        <button onclick="addStock()" class="flex-1 bg-green-500 text-white py-3 rounded-lg hover:bg-green-600 transition-colors">
          Add Stock
        </button>
        <button onclick="closeStockModal()" class="flex-1 bg-gray-500 text-white py-3 rounded-lg hover:bg-gray-600 transition-colors">
          Cancel
        </button>
      </div>
    </div>
  </div>

  <script>
    // Initialize data with localStorage support
    let stock = JSON.parse(localStorage.getItem('karigarStock')) || {
      fineGold: 1000,
      copper: 500,
      silver: 400
    };

    let orders = JSON.parse(localStorage.getItem('karigarOrders')) || [];
    let karigars = JSON.parse(localStorage.getItem('karigarKarigars')) || [];
    let activities = JSON.parse(localStorage.getItem('karigarActivities')) || [];
    let stockTransactions = JSON.parse(localStorage.getItem('karigarStockTransactions')) || [];
    let completedItems = JSON.parse(localStorage.getItem('karigarCompletedItems')) || [];

    // Save data to localStorage
    function saveData() {
      localStorage.setItem('karigarStock', JSON.stringify(stock));
      localStorage.setItem('karigarOrders', JSON.stringify(orders));
      localStorage.setItem('karigarKarigars', JSON.stringify(karigars));
      localStorage.setItem('karigarActivities', JSON.stringify(activities));
      localStorage.setItem('karigarStockTransactions', JSON.stringify(stockTransactions));
      localStorage.setItem('karigarCompletedItems', JSON.stringify(completedItems));
    }

    // Initialize the application
    function init() {
      document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-IN');
      updateDashboard();
      updateKarigarDropdown();
      updateReturnDropdowns();
      updateCompletedItemsDisplay();
      updateStockDisplay();
      
      // Add initial activity if none exists
      if (activities.length === 0) {
        addActivity({
          icon: '📋',
          text: 'System initialized - Welcome to Karigar Management!',
          time: new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
        });
      }
      
      // Add initial stock transaction if none exists
      if (stockTransactions.length === 0) {
        addStockTransaction('Initial stock loaded - Fine Gold: 1000g, Copper: 500g, Silver: 400g', 'IN');
      }
    }

    // Tab functionality
    function showTab(tabName) {
      // Hide all tabs
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Remove active class from all buttons
      document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.add('bg-white');
      });
      
      // Show selected tab
      document.getElementById(tabName).classList.add('active');
      
      // Add active class to clicked button
      event.target.classList.add('active');
      event.target.classList.remove('bg-white');
    }

    // Dashboard functions
    function updateDashboard() {
      document.getElementById('activeOrdersCount').textContent = orders.filter(o => o.status !== 'Completed').length;
      document.getElementById('totalKarigars').textContent = karigars.length;
      document.getElementById('fineGoldStock').textContent = stock.fineGold.toFixed(3) + 'g';
      document.getElementById('pendingTasks').textContent = orders.filter(o => o.status === 'Pending').length;
      document.getElementById('completedItemsCount').textContent = completedItems.length;
    }

    function addActivity(activity) {
      activities.unshift(activity);
      updateActivitiesDisplay();
      saveData();
    }

    function updateActivitiesDisplay() {
      const container = document.getElementById('recentActivities');
      container.innerHTML = activities.slice(0, 10).map(activity => `
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
          <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
            <span class="text-sm">${activity.icon}</span>
          </div>
          <p class="text-gray-700">${activity.text}</p>
          <span class="text-xs text-gray-500 ml-auto">${activity.time}</span>
        </div>
      `).join('');
    }

    // Order functions
    function createOrder() {
      const customerName = document.getElementById('customerName').value;
      const itemType = document.getElementById('itemType').value;
      const weight = document.getElementById('orderWeight').value;
      const purity = document.getElementById('orderPurity').value;
      const deliveryDate = document.getElementById('deliveryDate').value;
      const priority = document.getElementById('orderPriority').value;

      if (!customerName || !itemType || !weight || !purity || !deliveryDate) {
        alert('Please fill all required fields');
        return;
      }

      const order = {
        id: Date.now(),
        customerName,
        itemType,
        weight: parseFloat(weight),
        purity,
        deliveryDate,
        priority,
        status: 'Pending',
        createdAt: new Date().toLocaleString('en-IN')
      };

      orders.push(order);
      updateOrdersDisplay();
      updateDashboard();
      saveData();
      
      // Clear form
      document.getElementById('customerName').value = '';
      document.getElementById('itemType').value = '';
      document.getElementById('orderWeight').value = '';
      document.getElementById('orderPurity').value = '';
      document.getElementById('deliveryDate').value = '';
      document.getElementById('orderPriority').value = 'Normal';

      addActivity({
        icon: '📋',
        text: `New order created for ${customerName} - ${itemType} (${weight}g)`,
        time: new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
      });
    }

    function updateOrdersDisplay() {
      const container = document.getElementById('ordersList');
      if (orders.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">No orders yet. Create your first order above!</p>';
        return;
      }

      container.innerHTML = orders.map(order => `
        <div class="border border-gray-200 p-4 rounded-lg">
          <div class="flex justify-between items-start mb-3">
            <div>
              <h3 class="font-semibold text-lg">${order.customerName}</h3>
              <p class="text-gray-600">${order.itemType} - ${order.weight}g (${order.purity}KT)</p>
            </div>
            <div class="text-right">
              <span class="inline-block px-3 py-1 rounded-full text-sm font-medium ${
                order.status === 'Pending' ? 'bg-yellow-100 text-yellow-800' :
                order.status === 'In Progress' ? 'bg-blue-100 text-blue-800' :
                'bg-green-100 text-green-800'
              }">
                ${order.status}
              </span>
              <p class="text-sm text-gray-500 mt-1">Due: ${order.deliveryDate}</p>
            </div>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm font-medium px-2 py-1 rounded ${
              order.priority === 'Urgent' ? 'bg-red-100 text-red-800' :
              order.priority === 'High' ? 'bg-orange-100 text-orange-800' :
              'bg-gray-100 text-gray-800'
            }">
              ${order.priority} Priority
            </span>
            <div class="space-x-2">
              <button onclick="assignOrder(${order.id})" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                Assign
              </button>
              <button onclick="updateOrderStatus(${order.id})" class="px-3 py-1 bg-green-500 text-white rounded text-sm hover:bg-green-600">
                Update
              </button>
            </div>
          </div>
        </div>
      `).join('');
    }

    function assignOrder(orderId) {
      if (karigars.length === 0) {
        alert('Please add karigars first');
        return;
      }
      
      const karigarOptions = karigars.map(k => k.name).join(', ');
      const karigarName = prompt(`Assign to which karigar?\nAvailable: ${karigarOptions}`);
      
      if (karigarName && karigars.find(k => k.name === karigarName)) {
        const order = orders.find(o => o.id === orderId);
        order.assignedTo = karigarName;
        order.status = 'In Progress';
        updateOrdersDisplay();
        updateDashboard();
        
        addActivity({
          icon: '👤',
          text: `Order #${orderId} assigned to ${karigarName}`,
          time: new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
        });
      }
    }

    function updateOrderStatus(orderId) {
      const order = orders.find(o => o.id === orderId);
      const newStatus = prompt(`Current status: ${order.status}\nEnter new status (Pending/In Progress/Completed):`);
      
      if (newStatus && ['Pending', 'In Progress', 'Completed'].includes(newStatus)) {
        order.status = newStatus;
        updateOrdersDisplay();
        updateDashboard();
        
        addActivity({
          icon: '📋',
          text: `Order #${orderId} status updated to ${newStatus}`,
          time: new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
        });
      }
    }

    // Karigar functions
    function addKarigar() {
      const name = document.getElementById('karigarName').value;
      const specialization = document.getElementById('karigarSpecialization').value;
      const experience = document.getElementById('karigarExperience').value;

      if (!name || !specialization || !experience) {
        alert('Please fill all fields');
        return;
      }

      const karigar = {
        id: Date.now(),
        name,
        specialization,
        experience: parseInt(experience),
        status: 'Available',
        tasksCompleted: 0,
        joinedDate: new Date().toLocaleDateString('en-IN')
      };

      karigars.push(karigar);
      updateKarigarsDisplay();
      updateKarigarDropdown();
      updateReturnDropdowns();
      updateDashboard();
      saveData();

      // Clear form
      document.getElementById('karigarName').value = '';
      document.getElementById('karigarSpecialization').value = 'All';
      document.getElementById('karigarExperience').value = '';

      addActivity({
        icon: '👥',
        text: `New karigar added: ${name} (${specialization}, ${experience} years experience)`,
        time: new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
      });
    }

    function updateKarigarsDisplay() {
      const container = document.getElementById('karigarsList');
      if (karigars.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8 col-span-full">No karigars added yet. Add your first karigar above!</p>';
        return;
      }

      container.innerHTML = karigars.map(karigar => `
        <div class="bg-gradient-to-br from-green-50 to-teal-50 border border-green-200 p-6 rounded-xl card-hover">
          <div class="flex items-start justify-between mb-4">
            <div>
              <h3 class="font-bold text-lg text-gray-800">${karigar.name}</h3>
              <p class="text-gray-600">${karigar.specialization}</p>
            </div>
            <span class="px-3 py-1 rounded-full text-sm font-medium ${
              karigar.status === 'Available' ? 'bg-green-100 text-green-800' :
              karigar.status === 'Busy' ? 'bg-yellow-100 text-yellow-800' :
              'bg-red-100 text-red-800'
            }">
              ${karigar.status}
            </span>
          </div>
          <div class="space-y-2 text-sm text-gray-600">
            <p><span class="font-medium">Experience:</span> ${karigar.experience} years</p>
            <p><span class="font-medium">Tasks Completed:</span> ${karigar.tasksCompleted}</p>
            <p><span class="font-medium">Joined:</span> ${karigar.joinedDate}</p>
          </div>
          <div class="mt-4 flex space-x-2">
            <button onclick="updateKarigarStatus(${karigar.id})" class="flex-1 bg-blue-500 text-white py-2 px-3 rounded-lg text-sm hover:bg-blue-600 transition-colors">
              Update Status
            </button>
            <button onclick="viewKarigarTasks(${karigar.id})" class="flex-1 bg-gray-500 text-white py-2 px-3 rounded-lg text-sm hover:bg-gray-600 transition-colors">
              View Tasks
            </button>
          </div>
        </div>
      `).join('');
    }

    function updateKarigarDropdown() {
      const dropdown = document.getElementById('assignKarigar');
      dropdown.innerHTML = '<option value="">-- Choose Karigar --</option>' + 
        karigars.map(k => `<option value="${k.name}">${k.name} (${k.specialization})</option>`).join('');
    }

    function updateKarigarStatus(karigarId) {
      const karigar = karigars.find(k => k.id === karigarId);
      const newStatus = prompt(`Current status: ${karigar.status}\nEnter new status (Available/Busy/On Leave):`);
      
      if (newStatus && ['Available', 'Busy', 'On Leave'].includes(newStatus)) {
        karigar.status = newStatus;
        updateKarigarsDisplay();
        
        addActivity({
          icon: '👤',
          text: `${karigar.name} status updated to ${newStatus}`,
          time: new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
        });
      }
    }

    function viewKarigarTasks(karigarId) {
      const karigar = karigars.find(k => k.id === karigarId);
      const assignedOrders = orders.filter(o => o.assignedTo === karigar.name);
      
      if (assignedOrders.length === 0) {
        alert(`${karigar.name} has no assigned tasks currently.`);
        return;
      }
      
      const tasksList = assignedOrders.map(o => 
        `• ${o.customerName} - ${o.itemType} (${o.weight}g) - Status: ${o.status}`
      ).join('\n');
      
      alert(`Tasks assigned to ${karigar.name}:\n\n${tasksList}`);
    }

    // Stock functions
    function showStockModal(material) {
      document.getElementById('stockModal').classList.remove('hidden');
      document.getElementById('stockModal').classList.add('flex');
      document.getElementById('modalMaterial').value = material.charAt(0).toUpperCase() + material.slice(1);
      document.getElementById('modalQuantity').value = '';
      document.getElementById('modalQuantity').focus();
    }

    function closeStockModal() {
      document.getElementById('stockModal').classList.add('hidden');
      document.getElementById('stockModal').classList.remove('flex');
    }

    function addStock() {
      const material = document.getElementById('modalMaterial').value.toLowerCase();
      const quantity = parseFloat(document.getElementById('modalQuantity').value);
      
      if (!quantity || quantity <= 0) {
        alert('Please enter a valid quantity');
        return;
      }
      
      if (material === 'finegold') {
        stock.fineGold += quantity;
        document.getElementById('stockFineGold').textContent = stock.fineGold.toFixed(3) + 'g';
        document.getElementById('fineGoldStock').textContent = stock.fineGold.toFixed(3) + 'g';
      } else if (material === 'copper') {
        stock.copper += quantity;
        document.getElementById('stockCopper').textContent = stock.copper.toFixed(3) + 'g';
      } else if (material === 'silver') {
        stock.silver += quantity;
        document.getElementById('stockSilver').textContent = stock.silver.toFixed(3) + 'g';
      }
      
      addStockTransaction(`Added ${quantity}g of ${material}`, 'IN');
      closeStockModal();
      saveData();
      
      addActivity({
        icon: '📦',
        text: `Stock added: ${quantity}g of ${material}`,
        time: new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
      });
    }

    function addStockTransaction(description, type) {
      const transaction = {
        id: Date.now(),
        description,
        type,
        timestamp: new Date().toLocaleString('en-IN')
      };
      
      stockTransactions.unshift(transaction);
      updateStockTransactionsDisplay();
      saveData();
    }

    function updateStockTransactionsDisplay() {
      const container = document.getElementById('stockTransactions');
      container.innerHTML = stockTransactions.slice(0, 10).map(transaction => `
        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
          <div class="w-8 h-8 ${transaction.type === 'IN' ? 'bg-green-100' : 'bg-red-100'} rounded-full flex items-center justify-center">
            <span class="text-sm">${transaction.type === 'IN' ? '📦' : '📤'}</span>
          </div>
          <p class="text-gray-700 flex-1">${transaction.description}</p>
          <span class="text-xs text-gray-500">${transaction.timestamp}</span>
        </div>
      `).join('');
    }

    // Calculator functions
    function calculateAlloy() {
      const purity = document.getElementById('purity').value;
      const tone = document.getElementById('tone').value;
      const finalWeight = parseFloat(document.getElementById('finalWeight').value);
      const wastagePercent = parseFloat(document.getElementById('wastage').value) || 0;

      if (!purity || !tone || isNaN(finalWeight) || finalWeight <= 0) {
        alert("Please select purity, tone, and enter valid weight.");
        return;
      }

      const purityMap = {
        '22': 0.916,
        '18': 0.75,
        '14': 0.585
      };

      const toneRatio = {
        'standard': { copper: 0.75, silver: 0.25 },
        'light': { copper: 0.60, silver: 0.40 },
        'rose': { copper: 0.90, silver: 0.10 },
        'green': { copper: 0.50, silver: 0.50 }
      };

      const goldPurity = purityMap[purity];
      const totalWeight = finalWeight * (1 + wastagePercent / 100);
      const fineGold = (totalWeight * goldPurity).toFixed(3);
      const alloy = (totalWeight - fineGold).toFixed(3);
      const copper = (alloy * toneRatio[tone].copper).toFixed(3);
      const silver = (alloy * toneRatio[tone].silver).toFixed(3);

      document.getElementById('fineGold').value = fineGold + 'g';
      document.getElementById('alloy').value = alloy + 'g';
      document.getElementById('copper').value = copper + 'g';
      document.getElementById('silver').value = silver + 'g';

      document.getElementById('calculatorResults').classList.remove('hidden');
    }

    function issueToKarigar() {
      const karigarName = document.getElementById('assignKarigar').value;
      const wastagePercent = document.getElementById('wastage').value || 0;
      
      if (!karigarName) {
        alert("Please select a karigar.");
        return;
      }

      const fineGold = parseFloat(document.getElementById('fineGold').value.replace('g', '')) || 0;
      const copper = parseFloat(document.getElementById('copper').value.replace('g', '')) || 0;
      const silver = parseFloat(document.getElementById('silver').value.replace('g', '')) || 0;

      if (stock.fineGold < fineGold || stock.copper < copper || stock.silver < silver) {
        alert("Insufficient stock. Please check available quantities.");
        return;
      }

      // Deduct from stock
      stock.fineGold -= fineGold;
      stock.copper -= copper;
      stock.silver -= silver;

      // Update stock display
      updateStockDisplay();

      // Update karigar status
      const karigar = karigars.find(k => k.name === karigarName);
      if (karigar) {
        karigar.status = 'Busy';
        updateKarigarsDisplay();
      }

      // Add transaction record
      addStockTransaction(`Issued to ${karigarName}: Fine Gold: ${fineGold}g, Copper: ${copper}g, Silver: ${silver}g (Wastage: ${wastagePercent}%)`, 'OUT');

      addActivity({
        icon: '📤',
        text: `Materials issued to ${karigarName}: ${fineGold}g gold, ${copper}g copper, ${silver}g silver`,
        time: new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
      });

      alert(`Materials successfully issued to ${karigarName}!`);
      
      // Clear calculator form
      document.getElementById('purity').value = '';
      document.getElementById('tone').value = '';
      document.getElementById('finalWeight').value = '';
      document.getElementById('wastage').value = '6';
      document.getElementById('assignKarigar').value = '';
      document.getElementById('calculatorResults').classList.add('hidden');
      
      saveData();
    }

    // Return functionality
    function updateReturnDropdowns() {
      const karigarDropdown = document.getElementById('returnKarigar');
      const orderDropdown = document.getElementById('returnOrderId');
      
      // Update karigar dropdown
      karigarDropdown.innerHTML = '<option value="">Select Karigar</option>' + 
        karigars.map(k => `<option value="${k.name}">${k.name}</option>`).join('');
      
      // Update order dropdown based on selected karigar
      karigarDropdown.onchange = function() {
        const selectedKarigar = this.value;
        const assignedOrders = orders.filter(o => o.assignedTo === selectedKarigar && o.status === 'In Progress');
        
        orderDropdown.innerHTML = '<option value="">Select Order</option>' + 
          assignedOrders.map(o => `<option value="${o.id}">${o.customerName} - ${o.itemType} (${o.weight}g)</option>`).join('');
      };
    }

    function returnCompletedItem() {
      const karigarName = document.getElementById('returnKarigar').value;
      const orderId = parseInt(document.getElementById('returnOrderId').value);
      const actualWeight = parseFloat(document.getElementById('actualWeight').value);
      const wastage = parseFloat(document.getElementById('returnWastage').value);
      const qualityCheck = document.getElementById('qualityCheck').value;
      const notes = document.getElementById('returnNotes').value;

      if (!karigarName || !orderId || isNaN(actualWeight) || isNaN(wastage)) {
        alert('Please fill all required fields');
        return;
      }

      const order = orders.find(o => o.id === orderId);
      if (!order) {
        alert('Order not found');
        return;
      }

      // Calculate returned materials
      const totalReturned = actualWeight + wastage;
      const returnedFineGold = (totalReturned * (parseInt(order.purity) / 24)).toFixed(3);
      const returnedAlloy = (totalReturned - returnedFineGold).toFixed(3);

      // Add returned materials to stock
      stock.fineGold += parseFloat(returnedFineGold);
      stock.copper += parseFloat(returnedAlloy) * 0.75; // Assuming standard ratio
      stock.silver += parseFloat(returnedAlloy) * 0.25;

      // Create completed item record
      const completedItem = {
        id: Date.now(),
        orderId: orderId,
        karigarName: karigarName,
        customerName: order.customerName,
        itemType: order.itemType,
        originalWeight: order.weight,
        actualWeight: actualWeight,
        wastage: wastage,
        qualityCheck: qualityCheck,
        notes: notes,
        returnedFineGold: returnedFineGold,
        returnedAlloy: returnedAlloy,
        completedDate: new Date().toLocaleString('en-IN')
      };

      completedItems.push(completedItem);

      // Update order status
      order.status = 'Completed';
      order.completedDate = new Date().toLocaleString('en-IN');

      // Update karigar status and tasks completed
      const karigar = karigars.find(k => k.name === karigarName);
      if (karigar) {
        karigar.status = 'Available';
        karigar.tasksCompleted += 1;
      }

      // Update displays
      updateDashboard();
      updateOrdersDisplay();
      updateKarigarsDisplay();
      updateCompletedItemsDisplay();
      updateStockDisplay();
      updateReturnDropdowns();

      // Add activities
      addActivity({
        icon: '✅',
        text: `${karigarName} completed item for ${order.customerName} - ${order.itemType}`,
        time: new Date().toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })
      });

      addStockTransaction(`Returned from ${karigarName}: Fine Gold: ${returnedFineGold}g, Alloy: ${returnedAlloy}g`, 'IN');

      // Clear form
      document.getElementById('returnKarigar').value = '';
      document.getElementById('returnOrderId').innerHTML = '<option value="">Select Order</option>';
      document.getElementById('actualWeight').value = '';
      document.getElementById('returnWastage').value = '';
      document.getElementById('qualityCheck').value = 'Pass';
      document.getElementById('returnNotes').value = '';

      alert('Item returned successfully!');
      saveData();
    }

    function updateCompletedItemsDisplay() {
      const container = document.getElementById('completedItemsList');
      if (completedItems.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-8">No completed items yet.</p>';
        return;
      }

      container.innerHTML = completedItems.map(item => `
        <div class="border border-gray-200 p-4 rounded-lg">
          <div class="flex justify-between items-start mb-3">
            <div>
              <h3 class="font-semibold text-lg">${item.customerName}</h3>
              <p class="text-gray-600">${item.itemType} - Original: ${item.originalWeight}g, Actual: ${item.actualWeight}g</p>
              <p class="text-sm text-gray-500">Karigar: ${item.karigarName}</p>
            </div>
            <div class="text-right">
              <span class="inline-block px-3 py-1 rounded-full text-sm font-medium ${
                item.qualityCheck === 'Pass' ? 'bg-green-100 text-green-800' :
                item.qualityCheck === 'Minor Issues' ? 'bg-yellow-100 text-yellow-800' :
                'bg-red-100 text-red-800'
              }">
                ${item.qualityCheck}
              </span>
              <p class="text-sm text-gray-500 mt-1">${item.completedDate}</p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p><span class="font-medium">Wastage:</span> ${item.wastage}g</p>
              <p><span class="font-medium">Returned Gold:</span> ${item.returnedFineGold}g</p>
            </div>
            <div>
              <p><span class="font-medium">Returned Alloy:</span> ${item.returnedAlloy}g</p>
              ${item.notes ? `<p><span class="font-medium">Notes:</span> ${item.notes}</p>` : ''}
            </div>
          </div>
        </div>
      `).join('');
    }

    // Update stock display function
    function updateStockDisplay() {
      document.getElementById('stockFineGold').textContent = stock.fineGold.toFixed(3) + 'g';
      document.getElementById('stockCopper').textContent = stock.copper.toFixed(3) + 'g';
      document.getElementById('stockSilver').textContent = stock.silver.toFixed(3) + 'g';
      document.getElementById('fineGoldStock').textContent = stock.fineGold.toFixed(3) + 'g';
    }

    // Initialize the application when page loads
    window.onload = init;
  </script>

</body>
</html>