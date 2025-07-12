<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Luxury Jewelry Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
  <script src="https://unpkg.com/@tabler/icons@latest/iconfont/tabler-icons.min.js"></script>
   <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f5f7fa;
      height: 100vh;
      overflow-y: auto;
      position: relative;
    }
    .glass-card {
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 4px 12px rgba(31, 38, 135, 0.1);
    }
    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
    }
    .menu-item {
      transition: all 0.3s ease;
    }
    .menu-item:hover {
      transform: translateY(-2px);
    }
    .bottom-nav-item {
      position: relative;
      transition: all 0.25s ease;
    }
    .bottom-nav-item.active::after {
      content: "";
      position: absolute;
      bottom: 0;
      left: 25%;
      width: 50%;
      height: 3px;
      border-radius: 3px;
      background: #7c3aed;
    }
    .chart-container {
      position: relative;
      transition: all 0.2s ease;
    }
    .chart-container:hover {
      transform: scale(1.01);
    }
    ::-webkit-scrollbar {
      width: 4px;
      height: 4px;
    }
    ::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }
    ::-webkit-scrollbar-thumb {
      background: #7c3aed;
      border-radius: 10px;
    }
    #app-container {
      padding-bottom: 70px; /* Space for bottom nav */
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
    .cart-badge {
     position: absolute;
     top: -5px;
     right: -5px;
     background: #ef4444;
     color: white;
     font-size: 0.65rem;
     font-weight: 600;
     min-width: 18px;
     height: 18px;
     padding: 0 4px;
     border-radius: 999px;
     border: 2px solid white;
     display: flex;
     align-items: center;
     justify-content: center;
   }

  </style>
</head>
<body class="text-gray-800">
  <div id="app-container">
    <!-- Header - More compact -->
    <header class="px-4 py-3 flex items-center justify-between bg-white shadow-sm rounded-b-xl">
      <div class="flex items-center">
        <i class="ti ti-diamond text-xl text-violet-600 mr-2"></i>
        <div>
          <h1 class="text-base font-bold bg-clip-text text-transparent bg-gradient-to-r from-violet-700 to-purple-500">Jewel Entry</h1>
          <p class="text-xs text-gray-500">Professional Dashboard</p>
        </div>
      </div>
      <div class="flex items-center space-x-3">
        <div class="relative">
          <i class="ti ti-bell text-xl text-gray-600"></i>
          <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full flex items-center justify-center text-white text-xs"></span>
        </div>
        <div class="flex items-center bg-gray-50 p-1 pl-2 pr-2 rounded-lg">
          <img src="/api/placeholder/30/30" class="w-6 h-6 rounded-full mr-2" alt="profile"/>
          <span class="text-sm font-medium mr-1">Admin</span>
          <i class="ti ti-chevron-down text-gray-500 text-sm"></i>
        </div>
      </div>
    </header>

    <!-- Date Range Selector - More subtle -->
    <div class="px-4 mt-1 flex justify-between items-center">
      <h2 class="text-sm font-semibold text-gray-700">Performance Overview</h2>
      <div class="bg-white rounded-lg flex items-center p-1 px-3 shadow-sm text-sm border border-gray-100">
        <i class="ti ti-calendar-event text-violet-600 mr-2 text-sm"></i>
        <span class="text-xs">Last 30 Days</span>
        <i class="ti ti-chevron-down text-gray-500 ml-2 text-xs"></i>
      </div>
    </div>

    <!-- Horizontal Scrolling Stats - More compact -->
    <section class="grid grid-cols-2 gap-3 p-3 bg-gray-50">
  <!-- Sales Card -->
  <div class="bg-gradient-to-br from-white to-gray-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group">
    <div class="p-2 pb-1 border-b border-gray-100">
      <div class="flex justify-between items-center">
        <div class="flex items-center">
          <div class="w-2 h-2 rounded-full bg-violet-500 mr-1"></div>
          <p class="text-gray-600 text-xs font-medium uppercase tracking-wider">Sales</p>
        </div>
        <span class="flex items-center bg-green-50 text-green-600 text-xs font-semibold px-1 py-0.5 rounded">
          <i class="ti ti-trending-up mr-0.5"></i>12%
        </span>
      </div>
    </div>
    <div class="p-2 pt-1">
      <h3 id="dashboard-sales" class="text-lg font-bold text-gray-800">₹0</h3>
      <p class="text-xs font-medium text-green-600 ">+₹0 today</p>
    </div>
  </div>

  <!-- Orders Card -->
  <div class="bg-gradient-to-br from-white to-gray-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group">
    <div class="p-2 pb-1 border-b border-gray-100">
      <div class="flex justify-between items-center">
        <div class="flex items-center">
          <div class="w-2 h-2 rounded-full bg-purple-500 mr-1"></div>
          <p class="text-gray-600 text-xs font-medium uppercase tracking-wider">Orders</p>
        </div>
        <span class="flex items-center bg-green-50 text-green-600 text-xs font-semibold px-1 py-0.5 rounded">
          <i class="ti ti-trending-up mr-0.5"></i>8%
        </span>
      </div>
    </div>
    <div class="p-3 pt-1">
      <h3 id="dashboard-orders" class="text-lg font-bold text-gray-800">0</h3>
      <p class="text-xs font-medium text-green-600 mt-0.5">+0 today</p>
    </div>
  </div>

  <!-- Gold Stock Card -->
  <div class="bg-gradient-to-br from-white to-gray-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group">
    <div class="p-2 pb-1 border-b border-gray-100">
      <div class="flex justify-between items-center">
        <div class="flex items-center">
          <div class="w-2 h-2 rounded-full bg-blue-500 mr-1"></div>
          <p class="text-gray-600 text-xs font-medium uppercase tracking-wider">Gold Stock</p>
        </div>
        <span class="flex items-center bg-red-50 text-red-600 text-xs font-semibold px-1 py-0.5 rounded">
          <i class="ti ti-trending-down mr-0.5"></i>5%
        </span>
      </div>
    </div>
    <div class="p-3 pt-1">
      <h3 id="dashboard-gold-stock" class="text-lg font-bold text-gray-800">0g</h3>
      <p class="text-xs font-medium text-red-600 ">-0g today</p>
    </div>
  </div>

  <!-- Customers Card -->
  <div class="bg-gradient-to-br from-white to-gray-50 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden group">
    <div class="p-2 pb-1 border-b border-gray-100">
      <div class="flex justify-between items-center">
        <div class="flex items-center">
          <div class="w-2 h-2 rounded-full bg-amber-500 mr-1"></div>
          <p class="text-gray-600 text-xs font-medium uppercase tracking-wider">Customers</p>
        </div>
        <span class="flex items-center bg-green-50 text-green-600 text-xs font-semibold px-1.5 py-0.5 rounded">
          <i class="ti ti-trending-up mr-0.5"></i>15%
        </span>
      </div>
    </div>
    <div class="p-3 pt-1">
      <h3 id="dashboard-customers" class="text-lg font-bold text-gray-800">0</h3>
      <p class="text-xs font-medium text-green-600 mt-0.2">+0 this week</p>
    </div>
  </div>
</section>

    <!-- Main Charts Grid - More compact -->
    <section class="px-2 mt-1 grid grid-cols-1 md:grid-cols-2 gap-3">
      <div class="bg-white rounded-xl p-1 shadow-sm chart-container">
        <div class="flex justify-between items-center mb-2">
          <h3 class="font-medium text-xs text-gray-700">Monthly Sales</h3>
          <div class="flex space-x-1">
            <button class="px-2 py-1 bg-violet-100 text-violet-600 rounded-md text-xs font-medium">Weekly</button>
            <button class="px-2 py-1 bg-gray-100 text-gray-600 rounded-md text-xs font-medium">Monthly</button>
          </div>
        </div>
        <div style="height: 180px">
          <canvas id="salesChart"></canvas>
        </div>
      </div>
      <div class="bg-white rounded-xl p-2 shadow-sm chart-container">
        <div class="flex justify-between items-center mb-1">
          <h3 class="font-medium text-xs text-gray-700">Inventory Status</h3>
          <i class="ti ti-dots text-gray-500 cursor-pointer"></i>
        </div>
        <div style="height: 180px">
          <canvas id="stockChart"></canvas>
        </div>
      </div>
    </section>

    <!-- Additional Charts Grid - More compact -->
    <section class="px-4 mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
      <div class="bg-white rounded-xl p-3 shadow-sm chart-container">
        <div class="flex justify-between items-center mb-2">
          <h3 class="font-medium text-sm text-gray-700">Product Categories</h3>
          <i class="ti ti-refresh text-gray-500 cursor-pointer text-sm"></i>
        </div>
        <div style="height: 160px">
          <canvas id="categoryChart"></canvas>
        </div>
      </div>
      <div class="bg-white rounded-xl p-3 shadow-sm chart-container">
        <div class="flex justify-between items-center mb-2">
          <h3 class="font-medium text-sm text-gray-700">Sales by Store</h3>
          <i class="ti ti-refresh text-gray-500 cursor-pointer text-sm"></i>
        </div>
        <div style="height: 160px">
          <canvas id="storeChart"></canvas>
        </div>
      </div>
      <div class="bg-white rounded-xl p-3 shadow-sm chart-container">
        <div class="flex justify-between items-center mb-2">
          <h3 class="font-medium text-sm text-gray-700">Customer Age Groups</h3>
          <i class="ti ti-refresh text-gray-500 cursor-pointer text-sm"></i>
        </div>
        <div style="height: 160px">
          <canvas id="ageChart"></canvas>
        </div>
      </div>
    </section>

    <!-- Quick Access Menu - More compact -->
    <section class="px-4 mt-3">
      <h3 class="font-medium text-sm text-gray-700 mb-2">Quick Access</h3>
      <div class="grid grid-cols-4 md:grid-cols-6 gap-2 text-center">
        <div class="bg-white rounded-xl p-2 shadow-sm hover:shadow-md cursor-pointer menu-item">
          <div class="bg-violet-50 rounded-full w-8 h-8 flex items-center justify-center mx-auto mb-1">
            <i class="ti ti-certificate text-violet-600"></i>
          </div>
          <p class="font-medium text-xs">Certificate</p>
        </div>
        <div class="bg-white rounded-xl p-2 shadow-sm hover:shadow-md cursor-pointer menu-item">
          <div class="bg-green-50 rounded-full w-8 h-8 flex items-center justify-center mx-auto mb-1">
            <i class="ti ti-file text-green-600"></i>
          </div>
          <p class="font-medium text-xs">Documents</p>
        </div>
        <div class="bg-white rounded-xl p-2 shadow-sm hover:shadow-md cursor-pointer menu-item">
          <div class="bg-purple-50 rounded-full w-8 h-8 flex items-center justify-center mx-auto mb-1">
            <i class="ti ti-users text-purple-600"></i>
          </div>
          <p class="font-medium text-xs">Customers</p>
        </div>
        <div class="bg-white rounded-xl p-2 shadow-sm hover:shadow-md cursor-pointer menu-item">
          <div class="bg-amber-50 rounded-full w-8 h-8 flex items-center justify-center mx-auto mb-1">
            <i class="ti ti-building-store text-amber-600"></i>
          </div>
          <p class="font-medium text-xs">Stores</p>
        </div>
        <div class="hidden md:block bg-white rounded-xl p-2 shadow-sm hover:shadow-md cursor-pointer menu-item">
          <div class="bg-indigo-50 rounded-full w-8 h-8 flex items-center justify-center mx-auto mb-1">
            <i class="ti ti-credit-card text-indigo-600"></i>
          </div>
          <p class="font-medium text-xs">Payments</p>
        </div>
        <div class="hidden md:block bg-white rounded-xl p-2 shadow-sm hover:shadow-md cursor-pointer menu-item">
          <div class="bg-gray-50 rounded-full w-8 h-8 flex items-center justify-center mx-auto mb-1">
            <i class="ti ti-report-analytics text-gray-600"></i>
          </div>
          <p class="font-medium text-xs">Reports</p>
        </div>
      </div>
    </section>

    <!-- Recent Sales Table - More compact -->
    <section class="px-4 mt-3 mb-6">
      <div class="flex justify-between items-center mb-2">
        <h3 class="font-medium text-sm text-gray-700">Recent Transactions</h3>
        <button class="text-violet-600 text-xs font-medium flex items-center">
          View All <i class="ti ti-arrow-right ml-1"></i>
        </button>
      </div>
      <div class="bg-white rounded-xl p-3 shadow-sm overflow-x-auto">
        <table class="w-full min-w-[500px] text-sm">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="text-left py-2 px-2 text-gray-500 font-medium text-xs">Invoice</th>
              <th class="text-left py-2 px-2 text-gray-500 font-medium text-xs">Customer</th>
              <th class="text-left py-2 px-2 text-gray-500 font-medium text-xs">Product</th>
              <th class="text-left py-2 px-2 text-gray-500 font-medium text-xs">Amount</th>
              <th class="text-left py-2 px-2 text-gray-500 font-medium text-xs">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-b border-gray-50 hover:bg-gray-50">
              <td class="py-2 px-2 text-xs">#INV-001</td>
              <td class="py-2 px-2 flex items-center">
                <img src="/api/placeholder/24/24" class="w-5 h-5 rounded-full mr-2" alt="customer"/>
                <span class="text-xs">Rahul Sharma</span>
              </td>
              <td class="py-2 px-2 text-xs">Diamond Ring</td>
              <td class="py-2 px-2 font-medium text-xs">₹45,000</td>
              <td class="py-2 px-2"><span class="px-2 py-0.5 bg-green-50 text-green-600 rounded-full text-xs">Completed</span></td>
            </tr>
            <tr class="border-b border-gray-50 hover:bg-gray-50">
              <td class="py-2 px-2 text-xs">#INV-002</td>
              <td class="py-2 px-2 flex items-center">
                <img src="/api/placeholder/24/24" class="w-5 h-5 rounded-full mr-2" alt="customer"/>
                <span class="text-xs">Priya Singh</span>
              </td>
              <td class="py-2 px-2 text-xs">Gold Necklace</td>
              <td class="py-2 px-2 font-medium text-xs">₹32,500</td>
              <td class="py-2 px-2"><span class="px-2 py-0.5 bg-amber-50 text-amber-600 rounded-full text-xs">Pending</span></td>
            </tr>
            <tr class="hover:bg-gray-50">
              <td class="py-2 px-2 text-xs">#INV-003</td>
              <td class="py-2 px-2 flex items-center">
                <img src="/api/placeholder/24/24" class="w-5 h-5 rounded-full mr-2" alt="customer"/>
                <span class="text-xs">Amit Patel</span>
              </td>
              <td class="py-2 px-2 text-xs">Silver Bracelet</td>
              <td class="py-2 px-2 font-medium text-xs">₹8,900</td>
              <td class="py-2 px-2"><span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-xs">Processing</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <!-- Bottom Navigation - More compact -->
 <nav class="bottom-nav">
   <!-- Home -->
   <a href="main.php" class="nav-item">
     <i class="nav-icon fas fa-home"></i>
     <span class="nav-text">Home</span>
   </a>
   

    <a href="add-stock.php" class="nav-item ">
     <i class="nav-icon fa-solid fa-store"></i>
     <span class="nav-text">Stock</span>
   </a>
 
 <a href="order.php" class="nav-item">
     <i class="nav-icon fa-solid fa-user-tag"></i>
     <span class="nav-text">Orders</span>
   </a>
  
 <a href="sale-entry.php" class="nav-item">
     <i class="nav-icon fas fa-shopping-cart"></i>
     <span class="nav-text">Sale</span>
   </a>
   <!-- Sales List -->
  

   <!-- Reports -->
   <a href="reports.php" class="nav-item">
     <i class="nav-icon fas fa-chart-pie"></i>
     <span class="nav-text">Reports</span>
   </a>
 </nav>
  


  <script>
    // FIX: Prevent chart continuous redrawing by defining chart height
    document.addEventListener('DOMContentLoaded', function() {
      // Monthly Sales Chart
      const salesCtx = document.getElementById('salesChart').getContext('2d');
      const salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
          datasets: [{
            label: 'Sales (₹)',
            data: [12, 14, 10, 18, 16, 19],
            backgroundColor: 'rgba(124, 58, 237, 0.1)',
            borderColor: '#7c3aed',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#7c3aed',
            pointBorderWidth: 1.5
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function(value) {
                  return '₹' + value + 'L';
                },
                font: {
                  size: 9
                }
              },
              grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.03)'
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                font: {
                  size: 9
                }
              }
            }
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(255, 255, 255, 0.9)',
              titleColor: '#6b7280',
              bodyColor: '#374151',
              bodyFont: {
                weight: 'bold'
              },
              borderColor: 'rgba(0, 0, 0, 0.1)',
              borderWidth: 1,
              padding: 8,
              displayColors: false,
              callbacks: {
                label: function(context) {
                  return '₹' + context.raw + ' Lakhs';
                }
              }
            }
          }
        }
      });

      // Inventory Status Chart
      const stockCtx = document.getElementById('stockChart').getContext('2d');
      const stockChart = new Chart(stockCtx, {
        type: 'bar',
        data: {
          labels: ['Gold', 'Silver', 'Diamond', 'Platinum'],
          datasets: [{
            label: 'Current Stock',
            data: [400, 300, 350, 150],
            backgroundColor: [
              'rgba(245, 158, 11, 0.8)',
              'rgba(156, 163, 175, 0.8)',
              'rgba(219, 39, 119, 0.8)',
              'rgba(59, 130, 246, 0.8)'
            ],
            borderRadius: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.03)'
              },
              ticks: {
                font: {
                  size: 9
                }
              }
            },
            x: {
              grid: {
                display: false
              },
              ticks: {
                font: {
                  size: 9
                }
              }
            }
          },
          plugins: {
            legend: {
              display: false
            }
          }
        }
      });

      // Product Categories Chart
      const categoryCtx = document.getElementById('categoryChart').getContext('2d');
      const categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
          labels: ['Rings', 'Necklaces', 'Earrings', 'Bracelets', 'Watches'],
          datasets: [{
            data: [35, 25, 20, 15, 5],
            backgroundColor: [
              'rgba(124, 58, 237, 0.8)',
              'rgba(236, 72, 153, 0.8)',
              'rgba(245, 158, 11, 0.8)',
              'rgba(59, 130, 246, 0.8)',
              'rgba(16, 185, 129, 0.8)'
            ],
            borderWidth: 0,
            hoverOffset: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              labels: {
                usePointStyle: true,
                padding: 10,
                boxWidth: 8,
                font: {
                  size: 9
                }
              }
            }
          },
          cutout: '70%'
        }
      });

      // Sales by Store Chart
      const storeCtx = document.getElementById('storeChart').getContext('2d');
      const storeChart = new Chart(storeCtx, {
        type: 'polarArea',
        data: {
          labels: ['Main Store', 'Mall', 'Airport', 'Online'],
          datasets: [{
            data: [40, 25, 15, 20],
            backgroundColor: [
              'rgba(124, 58, 237, 0.7)',
              'rgba(236, 72, 153, 0.7)',
              'rgba(245, 158, 11, 0.7)',
              'rgba(59, 130, 246, 0.7)'
            ],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
              labels: {
                usePointStyle: true,
                padding: 10,
                boxWidth: 8,
                font: {
                  size: 9
                }
              }
            }
          },
          scales: {
            r: {
              ticks: {
                display: false
              },
              grid: {
                color: 'rgba(0, 0, 0, 0.03)'
              }
            }
          }
        }
      });

      // Customer Age Groups Chart
      const ageCtx = document.getElementById('ageChart').getContext('2d');
      const ageChart = new Chart(ageCtx, {
        type: 'radar',
        data: {
          labels: ['18-24', '25-34', '35-44', '45-54', '55+'],
          datasets: [{
            label: 'Purchase Value (₹L)',
            data: [2, 8, 15, 10, 5],
            backgroundColor: 'rgba(124, 58, 237, 0.2)',
            borderColor: 'rgba(124, 58, 237, 0.8)',
            borderWidth: 2,
            pointBackgroundColor: '#7c3aed',
            pointRadius: 3
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            r: {
              beginAtZero: true,
              ticks: {
                display: false
              },
              grid: {
                color: 'rgba(0, 0, 0, 0.03)'
              },
              pointLabels: {
                font: {
                  size: 9
                }
              }
            }
          },
          plugins: {
            legend: {
              display: false
            }
          }
        }
      });
    });
  </script>
  <script src="https://cdn.jsdelivr.net/jquery/latest/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
  <script>
  $(function() {
    // Set default date range (last 30 days)
    const start = moment().subtract(29, 'days');
    const end = moment();

    function fetchDashboardData(startDate, endDate) {
        $.ajax({
            url: 'api/get_report_data.php',
            type: 'GET',
            data: {
                start_date: startDate,
                end_date: endDate
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    updateDashboardUI(response.data);
                } else {
                    console.error("Error fetching dashboard data:", response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
            }
        });
    }

    function formatCurrency(val) {
        val = Number(val) || 0;
        return '₹' + val.toLocaleString('en-IN', {maximumFractionDigits: 2});
    }

    function updateDashboardUI(data) {
        // Sales
        $('#dashboard-sales').text(formatCurrency(data.summary.total_revenue));
        // Orders (items sold)
        $('#dashboard-orders').text(data.summary.items_sold_count || 0);
        // Gold Stock (items added weight)
        $('#dashboard-gold-stock').text((data.summary.items_added_weight || 0) + 'g');
        // Customers (count unique customers from sales)
        if (data.cash_flow && data.cash_flow.income) {
            const uniqueCustomers = new Set(data.cash_flow.income.map(row => row.customer_name));
            $('#dashboard-customers').text(uniqueCustomers.size);
        }
    }

    // Initial fetch
    fetchDashboardData(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
  });
  </script>
</body>
</html>