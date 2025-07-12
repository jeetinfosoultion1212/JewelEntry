<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "Reached start of default.php<br>";

echo "Config loaded<br>";


echo "Hallmark config loaded<br>";

// Initialize variables with dummy values, which will be overwritten by real data if available
$totalProducts = "15,000+"; // Dummy data
$totalRevenue = "₹2.5Cr+"; // Dummy data
$activeFirms = "500+"; // Dummy data
$totalOrders = "25,000+"; // Dummy data

// Debugging variables to capture raw data
$debugRawProducts = 'N/A';
$debugRawRevenue = 'N/A';
$debugRawFirms = 'N/A';
$debugRawOrders = 'N/A';

// --- PAGE VIEW TRACKER (backend only, not shown on frontend) ---
// (Keep this for now, but if it hangs, we can comment it out too)
try {
    $conn2 = $hallmarkpro_conn; // For compatibility with huid_data.php logic
    // Check if table exists, create if not
    $table_check = $conn2->query("SHOW TABLES LIKE 'page_views'");
    if ($table_check && $table_check->num_rows === 0) {
        $create_table = "CREATE TABLE IF NOT EXISTS page_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_name VARCHAR(255) NOT NULL,
            view_count INT DEFAULT 1,
            first_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_viewed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_page (page_name)
        )";
        $conn2->query($create_table);
    }
    $page_name = 'default_page';
    // Check if record exists
    $check_stmt = $conn2->prepare("SELECT id, view_count FROM page_views WHERE page_name = ?");
    if ($check_stmt) {
        $check_stmt->bind_param('s', $page_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $view_record = $result->fetch_assoc();
        $check_stmt->close();
        if ($view_record) {
            // Update existing record
            $new_count = $view_record['view_count'] + 1;
            $update_stmt = $conn2->prepare("UPDATE page_views SET view_count = ?, last_viewed = NOW() WHERE page_name = ?");
            if ($update_stmt) {
                $update_stmt->bind_param('is', $new_count, $page_name);
                $update_stmt->execute();
                $update_stmt->close();
            }
        } else {
            // Insert new record
            $insert_stmt = $conn2->prepare("INSERT INTO page_views (page_name, view_count, first_viewed, last_viewed) VALUES (?, 1, NOW(), NOW())");
            if ($insert_stmt) {
                $insert_stmt->bind_param('s', $page_name);
                $insert_stmt->execute();
                $insert_stmt->close();
            }
        }
    }
    echo "Page view tracker completed<br>";
} catch(Exception $e) {
    // Silently fail, do not show error on frontend
    error_log("Page view tracker error: " . $e->getMessage());
    echo "Page view tracker error<br>";
}
// --- END PAGE VIEW TRACKER ---

// --- DATABASE BLOCK TEMPORARILY COMMENTED OUT FOR DEBUGGING ---
/*
try {
    // Use mysqli connection like other files in the project
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Fetch Total Products
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_products FROM jewellery_items");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && $result['total_products'] !== null) {
        $totalProducts = number_format($result['total_products']);
        $debugRawProducts = $result['total_products'];
    }

    // Fetch Total Revenue
    $stmt = $conn->prepare("SELECT SUM(grand_total) AS total_revenue FROM jewellery_sales");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && $result['total_revenue'] !== null) {
        // Format revenue to Cr+ if value is large, otherwise just format number
        $revenue = $result['total_revenue'];
        if ($revenue >= 10000000) { // 1 Crore = 10,000,000
            $totalRevenue = "₹" . number_format($revenue / 10000000, 1) . "Cr+";
        } else {
            $totalRevenue = "₹" . number_format($revenue);
        }
        $debugRawRevenue = $revenue;
    }

    // Fetch Active Firms
    $stmt = $conn->prepare("SELECT COUNT(id) AS active_firms FROM firm");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && $result['active_firms'] !== null) {
        $activeFirms = number_format($result['active_firms']) . "+";
        $debugRawFirms = $result['active_firms'];
    }

    // Fetch Total Orders
    $stmt = $conn->prepare("SELECT COUNT(id) AS total_orders FROM jewellery_sales");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && $result['total_orders'] !== null) {
        $totalOrders = number_format($result['total_orders']) . "+";
        $debugRawOrders = $result['total_orders'];
    }

} catch(Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    // Output error message for debugging purposes on the page
    // echo "<p style=\"color:red;\">Database Error: " . $e->getMessage() . "</p>";
}
$conn = null;
*/
echo "Database block skipped<br>";

// The rest of your HTML and PHP code continues here...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JewelEntry - Welcome</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
        }
        
        /* Prevent text selection during drag */
        .carousel-container.dragging,
        .carousel-container.dragging .carousel-slide {
            user-select: none;
        }

        /* Hide scrollbar for horizontal scrolling metrics */
        .hide-scrollbar::-webkit-scrollbar {
            display: none; /* For Chrome, Safari, and Opera */
        }

        .hide-scrollbar {
            -ms-overflow-style: none;  /* For Internet Explorer and Edge */
            scrollbar-width: none;  /* For Firefox */
        }
    </style>
</head>
<body class="bg-[#FFF9F3] text-gray-800">
    <div class="container mx-auto flex flex-col items-center justify-start min-h-screen w-full pt-10 pb-10 px-4 sm:px-6">

        <!-- Logo -->
        <div class="flex items-center justify-center mb-5">
            <img src="uploads/logo.png" alt="JewelEntry Logo" class="w-14 h-14 object-contain">
        </div>

        <!-- App Name -->
        <h1 class="text-4xl sm:text-xl font-bold text-gray-800 mb-1">JewelEntry</h1>
        <!-- Subtitle -->
        <p class="text-base text-gray-500 mb-2">Your Jewelry Business ERP</p>

        <!-- Illustration -->
        <div class="w-full max-w-[280px] sm:max-w-xs mb-2">
            <img src="uploads/hero.png" alt="Woman at laptop with checklist and growth chart" class="w-full h-auto object-contain rounded-lg">
        </div>

        <!-- Carousel for Features -->
        <div id="featuresCarousel" class="carousel-container w-full max-w-xs sm:max-w-sm mb-2 relative overflow-hidden cursor-grab">
            <div id="slidesWrapper" class="flex transition-transform duration-300 ease-in-out">
                <!-- Slide 1: Digital Certificates -->
                <div class="carousel-slide w-full flex-shrink-0 px-px"> 
                    <div class="bg-white rounded-xl shadow-md p-4 flex items-center justify-between h-full">
                        <div class="flex items-center">
                            <i class="fas fa-qrcode text-gray-600 text-4xl mr-3 sm:mr-4"></i>
                            <div>
                                <p class="text-md sm:text-lg font-semibold text-gray-800">Digital Certificates</p>
                                <p class="text-xs sm:text-sm text-gray-500">Secure QR codes for authenticity</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 text-xl"></i>
                    </div>
                </div>
                <!-- Slide 2: Inventory Management -->
                <div class="carousel-slide w-full flex-shrink-0 px-px">
                     <div class="bg-white rounded-xl shadow-md p-4 flex items-center justify-between h-full">
                        <div class="flex items-center">
                            <i class="fas fa-cogs text-gray-600 text-4xl mr-3 sm:mr-4"></i>
                            <div>
                                <p class="text-md sm:text-lg font-semibold text-gray-800">Smart Inventory</p>
                                <p class="text-xs sm:text-sm text-gray-500">Real-time stock tracking & alerts</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 text-xl"></i>
                    </div>
                </div>
                <!-- Slide 3: Sales Analytics -->
                <div class="carousel-slide w-full flex-shrink-0 px-px">
                    <div class="bg-white rounded-xl shadow-md p-4 flex items-center justify-between h-full">
                        <div class="flex items-center">
                            <i class="fas fa-chart-line text-gray-600 text-4xl mr-3 sm:mr-4"></i>
                            <div>
                                <p class="text-md sm:text-lg font-semibold text-gray-800">Advanced Analytics</p>
                                <p class="text-xs sm:text-sm text-gray-500">Business insights & reports</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 text-xl"></i>
                    </div>
                </div>
                <!-- Slide 4: Multi-Branch Management -->
                <div class="carousel-slide w-full flex-shrink-0 px-px">
                    <div class="bg-white rounded-xl shadow-md p-4 flex items-center justify-between h-full">
                        <div class="flex items-center">
                            <i class="fas fa-store text-gray-600 text-4xl mr-3 sm:mr-4"></i>
                            <div>
                                <p class="text-md sm:text-lg font-semibold text-gray-800">Multi-Branch</p>
                                <p class="text-xs sm:text-sm text-gray-500">Manage multiple locations</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 text-xl"></i>
                    </div>
                </div>
                <!-- Slide 5: Customer Management -->
                <div class="carousel-slide w-full flex-shrink-0 px-px">
                    <div class="bg-white rounded-xl shadow-md p-4 flex items-center justify-between h-full">
                        <div class="flex items-center">
                            <i class="fas fa-users text-gray-600 text-4xl mr-3 sm:mr-4"></i>
                            <div>
                                <p class="text-md sm:text-lg font-semibold text-gray-800">CRM System</p>
                                <p class="text-xs sm:text-sm text-gray-500">Customer loyalty & history</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 text-xl"></i>
                    </div>
                </div>
                <!-- Slide 6: Financial Management -->
                <div class="carousel-slide w-full flex-shrink-0 px-px">
                    <div class="bg-white rounded-xl shadow-md p-4 flex items-center justify-between h-full">
                        <div class="flex items-center">
                            <i class="fas fa-wallet text-gray-600 text-4xl mr-3 sm:mr-4"></i>
                            <div>
                                <p class="text-md sm:text-lg font-semibold text-gray-800">Financial Suite</p>
                                <p class="text-xs sm:text-sm text-gray-500">Billing, payments & accounting</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carousel Dots -->
        <div id="carouselDots" class="flex justify-center space-x-2 my-6">
            <span data-slide-to="0" class="carousel-dot h-2.5 w-2.5 bg-orange-500 rounded-full cursor-pointer transition-all duration-300"></span>
            <span data-slide-to="1" class="carousel-dot h-2 w-2 bg-gray-300 rounded-full cursor-pointer transition-all duration-300"></span>
            <span data-slide-to="2" class="carousel-dot h-2 w-2 bg-gray-300 rounded-full cursor-pointer transition-all duration-300"></span>
            <span data-slide-to="3" class="carousel-dot h-2 w-2 bg-gray-300 rounded-full cursor-pointer transition-all duration-300"></span>
            <span data-slide-to="4" class="carousel-dot h-2 w-2 bg-gray-300 rounded-full cursor-pointer transition-all duration-300"></span>
            <span data-slide-to="5" class="carousel-dot h-2 w-2 bg-gray-300 rounded-full cursor-pointer transition-all duration-300"></span>
        </div>
        
        <!-- Metric Cards (Dynamically populated) -->
        <div class="flex justify-center gap-4 sm:gap-6 w-full max-w-sm mb-8 overflow-x-auto p-2 hide-scrollbar">
            <!-- Product Metric -->
            <div class="flex items-center justify-center flex-shrink-0 w-[90px] h-[75px]">
                <i class="fas fa-gem text-orange-500 text-base"></i>
                <div class="ml-2 text-center">
                    <p class="font-bold text-gray-800 text-sm" id="totalProducts"><?php echo $totalProducts; ?></p>
                    <p class="text-gray-500 text-xs leading-tight">Products</p>
                </div>
            </div>
            <!-- Revenue Metric -->
            <div class="flex items-center justify-center flex-shrink-0 w-[90px] h-[75px]">
                <i class="fas fa-rupee-sign text-orange-500 text-base"></i>
                <div class="ml-2 text-center">
                    <p class="font-bold text-gray-800 text-sm" id="totalRevenue"><?php echo $totalRevenue; ?></p>
                    <p class="text-gray-500 text-xs leading-tight">Revenue</p>
                </div>
            </div>
            <!-- Firms Metric -->
            <div class="flex items-center justify-center flex-shrink-0 w-[90px] h-[75px]">
                <i class="fas fa-store text-orange-500 text-base"></i>
                <div class="ml-2 text-center">
                    <p class="font-bold text-gray-800 text-sm" id="activeFirms"><?php echo $activeFirms; ?></p>
                    <p class="text-gray-500 text-xs leading-tight">Firms</p>
                </div>
            </div>
            <!-- Orders Metric -->
            <div class="flex items-center justify-center flex-shrink-0 w-[90px] h-[75px]">
                <i class="fas fa-shopping-cart text-orange-500 text-base"></i>
                <div class="ml-2 text-center">
                    <p class="font-bold text-gray-800 text-sm" id="totalOrders"><?php echo $totalOrders; ?></p>
                    <p class="text-gray-500 text-xs leading-tight">Orders</p>
                </div>
            </div>
        </div>

        <!-- Get Started Button -->
        <a href="register.php" class="bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-semibold py-4 px-12 rounded-lg shadow-lg text-lg w-full max-w-xs sm:max-w-sm mb-6 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 transition duration-150 ease-in-out transform hover:scale-105 text-center">
            Start Your Free Trial
        </a>

        <!-- Login Link -->
        <p class="text-sm text-gray-600 mb-8">
            Already have an account? <a href="login.php" class="text-orange-500 font-semibold hover:underline">Log in</a>
        </p>
        <!-- Credit Line -->
        <p class="text-xs text-gray-400 mb-4 text-center">
            Develop and maintain by <span class="font-semibold text-orange-500">Prosenjit Tech Hub</span>
        </p>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const carousel = document.getElementById('featuresCarousel');
            const slidesWrapper = document.getElementById('slidesWrapper');
            const slides = Array.from(slidesWrapper.children);
            const dotsContainer = document.getElementById('carouselDots');
            const dots = Array.from(dotsContainer.children);
            
            if (!carousel || !slidesWrapper || slides.length === 0 || !dotsContainer || dots.length === 0) {
                console.error('Carousel elements not found');
                return;
            }

            let currentIndex = 0;
            let isDragging = false;
            let startX = 0;
            let currentTranslate = 0;
            let prevTranslate = 0;
            const slideWidth = () => slides[0].offsetWidth;

            let autoSwipeInterval;
            const AUTO_SWIPE_DELAY = 3000; // Reduced to 3 seconds for better engagement

            function updateDots() {
                dots.forEach((dot, index) => {
                    if (index === currentIndex) {
                        dot.classList.add('bg-orange-500', 'h-2.5', 'w-2.5');
                        dot.classList.remove('bg-gray-300', 'h-2', 'w-2');
                    } else {
                        dot.classList.remove('bg-orange-500', 'h-2.5', 'w-2.5');
                        dot.classList.add('bg-gray-300', 'h-2', 'w-2');
                    }
                });
            }

            function goToSlide(index, isAutoTriggered = false) {
                if (index < 0 || index >= slides.length) return;

                if (!isAutoTriggered) { // If manual interaction or dot click
                    stopAutoSwipe();
                }
                
                currentIndex = index;
                currentTranslate = -currentIndex * slideWidth();
                prevTranslate = currentTranslate;
                slidesWrapper.style.transform = `translateX(${currentTranslate}px)`;
                updateDots();

                if (!isAutoTriggered) { // Restart auto-swipe after manual interaction, slight delay
                    setTimeout(startAutoSwipe, AUTO_SWIPE_DELAY / 2);
                }
            }
            
            function nextSlide() {
                const newIndex = (currentIndex + 1) % slides.length;
                goToSlide(newIndex, true); // true indicates auto-triggered
            }

            function startAutoSwipe() {
                stopAutoSwipe(); // Clear existing interval
                autoSwipeInterval = setInterval(nextSlide, AUTO_SWIPE_DELAY);
            }

            function stopAutoSwipe() {
                clearInterval(autoSwipeInterval);
            }


            function getPositionX(event) {
                return event.type.includes('mouse') ? event.pageX : event.touches[0].clientX;
            }

            function dragStartHandler(event) {
                stopAutoSwipe(); // Pause auto-swipe on drag start
                isDragging = true;
                startX = getPositionX(event);
                slidesWrapper.classList.remove('transition-transform', 'duration-300', 'ease-in-out');
                carousel.classList.add('dragging');
                carousel.style.cursor = 'grabbing';
            }

            function dragHandler(event) {
                if (isDragging) {
                    const currentPosition = getPositionX(event);
                    currentTranslate = prevTranslate + currentPosition - startX;
                    slidesWrapper.style.transform = `translateX(${currentTranslate}px)`;
                }
            }

            function dragEndHandler() {
                if (!isDragging) return;
                isDragging = false;
                slidesWrapper.classList.add('transition-transform', 'duration-300', 'ease-in-out');
                carousel.classList.remove('dragging');
                carousel.style.cursor = 'grab';

                const movedBy = currentTranslate - prevTranslate;
                const sensitivity = slideWidth() / 4; 

                let targetIndex = currentIndex;
                if (movedBy < -sensitivity && currentIndex < slides.length - 1) {
                    targetIndex = currentIndex + 1;
                } else if (movedBy > sensitivity && currentIndex > 0) {
                    targetIndex = currentIndex - 1;
                }
                goToSlide(targetIndex); // goToSlide handles restarting auto-swipe
            }

            // Mouse events
            carousel.addEventListener('mousedown', dragStartHandler);
            carousel.addEventListener('mousemove', dragHandler);
            carousel.addEventListener('mouseup', dragEndHandler);
            carousel.addEventListener('mouseleave', () => { // Modified mouseleave
                if (isDragging) { // If mouse leaves while still dragging
                    dragEndHandler();
                }
                startAutoSwipe(); // Resume auto-swipe when mouse leaves, unless dragging was ongoing
            });
            carousel.addEventListener('mouseenter', stopAutoSwipe); // Pause on hover


            // Touch events
            carousel.addEventListener('touchstart', dragStartHandler, { passive: true });
            carousel.addEventListener('touchmove', dragHandler, { passive: true });
            carousel.addEventListener('touchend', dragEndHandler);
            
            // Dot navigation
            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    // stopAutoSwipe(); // goToSlide will handle this
                    const slideIndex = parseInt(dot.getAttribute('data-slide-to'));
                    goToSlide(slideIndex); // goToSlide handles restarting auto-swipe
                });
            });
            
            // Initial setup
            goToSlide(0); 
            startAutoSwipe(); // Start auto-swiping

            // Recalculate on resize
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    // Re-initialize slide position and restart auto-swipe
                    // This ensures correct slideWidth and translation
                    const currentSlideBeforeResize = currentIndex;
                    stopAutoSwipe(); 
                    goToSlide(currentSlideBeforeResize); // Recalculates position
                    startAutoSwipe(); 
                }, 200);
            });
        });
    </script>
</body>
</html>
