<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$servername = "localhost";
$username = "u176143338_CnGFg";
$password = "1Bi9t52LyV";
$dbname = "u176143338_VGe2Q";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create Trak_page table if not exists (same as in jewellerspage.php)
$conn->query("CREATE TABLE IF NOT EXISTS Trak_page (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_name VARCHAR(255) NOT NULL,
    jeweller_name VARCHAR(255) NULL,
    licence_no VARCHAR(255) NULL,
    visit_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL
)");

// Get tracking data
$tracking_query = "
    SELECT 
        tp.jeweller_name,
        tp.licence_no,
        tp.page_name,
        COUNT(*) as view_count,
        MIN(tp.visit_time) as first_viewed,
        MAX(tp.visit_time) as last_viewed,
        tp.ip_address,
        tp.user_agent,
        j.Jewellers_Name as full_name,
        j.City,
        j.Contact_no
    FROM Trak_page tp
    LEFT JOIN jewellers j ON tp.licence_no = j.licence_no
    GROUP BY tp.licence_no, tp.page_name, tp.jeweller_name, j.Jewellers_Name, j.City, j.Contact_no
    ORDER BY last_viewed DESC
";

$result = $conn->query($tracking_query);
$tracking_data = [];
$total_views = 0;
$unique_jewellers = 0;
$unique_licences = [];

if (!$result) {
    die("Query failed: " . $conn->error);
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tracking_data[] = $row;
        $total_views += $row['view_count'];
        if (!in_array($row['licence_no'], $unique_licences)) {
            $unique_licences[] = $row['licence_no'];
            $unique_jewellers++;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jeweller Page Tracking - Mahalaxmi Hallmarking Centre</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-card-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .stats-card-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .stats-card-warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        .avatar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .badge-gray {
            background-color: #f3f4f6;
            color: #374151;
        }
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <!-- Header -->
    <div class="gradient-bg text-white py-8 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="text-center md:text-left mb-6 md:mb-0">
                    <h1 class="text-3xl md:text-4xl font-bold mb-2">
                        <i class="fas fa-chart-line mr-3"></i>
                        Jeweller Analytics
                    </h1>
                    <p class="text-blue-100 text-lg">Mahalaxmi Hallmarking Centre Dashboard</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-center">
                        <div class="text-3xl font-bold"><?php echo $unique_jewellers; ?></div>
                        <div class="text-blue-100 text-sm">Active Jewellers</div>
                    </div>
                    <div class="w-12 h-12 rounded-full glass-effect flex items-center justify-center">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="stats-card card p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-white bg-opacity-20">
                        <i class="fas fa-eye text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold"><?php echo number_format($total_views); ?></div>
                        <div class="text-blue-100 text-sm">Total Views</div>
                    </div>
                </div>
            </div>
            
            <div class="stats-card-secondary card p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-white bg-opacity-20">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold"><?php echo $unique_jewellers; ?></div>
                        <div class="text-pink-100 text-sm">Unique Jewellers</div>
                    </div>
                </div>
            </div>
            
            <div class="stats-card-success card p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-white bg-opacity-20">
                        <i class="fas fa-calendar text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold"><?php echo count($tracking_data); ?></div>
                        <div class="text-cyan-100 text-sm">Total Records</div>
                    </div>
                </div>
            </div>
            
            <div class="stats-card-warning card p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-white bg-opacity-20">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold"><?php echo date('d M'); ?></div>
                        <div class="text-yellow-100 text-sm">Last Updated</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card p-4 mb-6">
            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="searchInput" placeholder="Search jewellers..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button id="filterAll" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium">All</button>
                    <button id="filterWelcome" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Welcome</button>
                    <button id="filterRequest" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Requests</button>
                </div>
            </div>
        </div>

        <!-- Tracking Data Cards -->
        <div class="space-y-4">
            <?php if (empty($tracking_data)): ?>
            <div class="card p-12 text-center">
                <div class="text-gray-400 mb-6">
                    <i class="fas fa-chart-line text-8xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">No Tracking Data Available</h3>
                <p class="text-gray-500 text-lg">No jeweller page visits have been recorded yet. Data will appear here once jewellers start visiting the pages.</p>
                <div class="mt-6">
                    <div class="animate-pulse inline-flex items-center px-4 py-2 bg-blue-100 text-blue-700 rounded-lg">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Auto-refreshing every 30 seconds
                    </div>
                </div>
            </div>
            <?php else: ?>
            
                        <?php foreach ($tracking_data as $row): ?>
            <div class="card p-6 tracking-card" data-jeweller="<?php echo strtolower(htmlspecialchars($row['jeweller_name'] ?? $row['full_name'] ?? '')); ?>" 
                 data-page="<?php echo $row['page_name'] ?? ''; ?>">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <!-- Main Info -->
                    <div class="flex items-start space-x-4 mb-4 md:mb-0">
                        <div class="flex-shrink-0">
                            <div class="w-16 h-16 rounded-full avatar flex items-center justify-center">
                                <span class="text-white font-bold text-xl">
                                    <?php 
                                    $display_name = $row['jeweller_name'] ?? $row['full_name'] ?? 'J';
                                    echo strtoupper(substr($display_name, 0, 1)); 
                                    ?>
                                            </span>
                                        </div>
                                    </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-2">
                                <h3 class="text-lg font-bold text-gray-900 truncate">
                                    <?php 
                                    $display_name = $row['jeweller_name'] ?? $row['full_name'] ?? 'Unknown Jeweller';
                                    echo htmlspecialchars($display_name); 
                                    ?>
                                </h3>
                                <span class="badge <?php 
                                    $page_name = $row['page_name'] ?? '';
                                    if ($page_name === 'welcome_page') {
                                        echo 'badge-success';
                                    } elseif ($page_name === 'request_details') {
                                        echo 'badge-info';
                                    } else {
                                        echo 'badge-gray';
                                    }
                                ?>">
                                    <i class="fas <?php 
                                        if ($page_name === 'welcome_page') {
                                            echo 'fa-home';
                                        } elseif ($page_name === 'request_details') {
                                            echo 'fa-file-alt';
                                        } else {
                                            echo 'fa-globe';
                                        }
                                    ?> mr-1"></i>
                                    <?php 
                                    if ($page_name === 'welcome_page') {
                                        echo 'Welcome';
                                    } elseif ($page_name === 'request_details') {
                                        echo 'Request';
                                    } else {
                                        echo htmlspecialchars($page_name);
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="flex items-center space-x-4 text-sm text-gray-600">
                                <span><i class="fas fa-id-card mr-1"></i><?php echo htmlspecialchars($row['licence_no']); ?></span>
                                <span><i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($row['City'] ?? 'N/A'); ?></span>
                                <span><i class="fas fa-phone mr-1"></i><?php echo htmlspecialchars($row['Contact_no'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="flex items-center space-x-6">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600"><?php echo $row['view_count']; ?></div>
                            <div class="text-xs text-gray-500">Views</div>
                        </div>
                        <div class="text-center">
                            <div class="text-sm font-medium text-gray-900"><?php echo date('d M Y', strtotime($row['last_viewed'])); ?></div>
                            <div class="text-xs text-gray-500">Last Visit</div>
                        </div>
                    </div>
                </div>
                
                <!-- Secondary Info (Collapsible) -->
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <div class="text-gray-500 mb-1">First Visit</div>
                            <div class="font-medium"><?php echo date('d M Y H:i', strtotime($row['first_viewed'])); ?></div>
                        </div>
                        <div>
                            <div class="text-gray-500 mb-1">IP Address</div>
                            <div class="font-medium font-mono"><?php echo htmlspecialchars($row['ip_address'] ?? 'N/A'); ?></div>
                        </div>
                        <div>
                            <div class="text-gray-500 mb-1">Device Info</div>
                            <div class="font-medium truncate" title="<?php echo htmlspecialchars($row['user_agent'] ?? 'N/A'); ?>">
                                <?php 
                                $user_agent = $row['user_agent'] ?? '';
                                if (strpos($user_agent, 'Mobile') !== false) {
                                    echo '<i class="fas fa-mobile-alt mr-1"></i>Mobile';
                                } elseif (strpos($user_agent, 'Tablet') !== false) {
                                    echo '<i class="fas fa-tablet-alt mr-1"></i>Tablet';
                                } else {
                                    echo '<i class="fas fa-desktop mr-1"></i>Desktop';
                                }
                                ?>
                                        </div>
                                        </div>
                                    </div>
                                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="mt-12 text-center">
            <div class="card p-6">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div class="mb-4 md:mb-0">
                        <p class="text-gray-600">
                            <i class="fas fa-code text-green-500 mr-2"></i>
                            Developed & Maintained by 
                            <a href="https://prosenjittechhub.com/" target="_blank" class="text-blue-600 hover:underline font-semibold">
                                Prosenjit Tech Hub
                            </a>
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center text-sm text-gray-500">
                            <i class="fas fa-sync-alt mr-2"></i>
                            Auto-refresh: 30s
                        </div>
                        <div class="flex items-center text-sm text-gray-500">
                            <i class="fas fa-clock mr-2"></i>
                            <?php echo date('d M Y H:i:s'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.tracking-card');
            
            cards.forEach(card => {
                const jewellerName = card.getAttribute('data-jeweller');
                if (jewellerName.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Filter functionality
        function setActiveFilter(activeButton) {
            document.querySelectorAll('#filterAll, #filterWelcome, #filterRequest').forEach(btn => {
                btn.classList.remove('bg-blue-500', 'text-white');
                btn.classList.add('bg-gray-200', 'text-gray-700');
            });
            activeButton.classList.remove('bg-gray-200', 'text-gray-700');
            activeButton.classList.add('bg-blue-500', 'text-white');
        }

        document.getElementById('filterAll').addEventListener('click', function() {
            setActiveFilter(this);
            document.querySelectorAll('.tracking-card').forEach(card => {
                card.style.display = 'block';
            });
        });

        document.getElementById('filterWelcome').addEventListener('click', function() {
            setActiveFilter(this);
            document.querySelectorAll('.tracking-card').forEach(card => {
                const pageType = card.getAttribute('data-page');
                card.style.display = pageType === 'welcome_page' ? 'block' : 'none';
            });
        });

        document.getElementById('filterRequest').addEventListener('click', function() {
            setActiveFilter(this);
            document.querySelectorAll('.tracking-card').forEach(card => {
                const pageType = card.getAttribute('data-page');
                card.style.display = pageType === 'request_details' ? 'block' : 'none';
            });
        });

        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);

        // Add some interactive animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html> 