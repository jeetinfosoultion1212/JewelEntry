<?php
// No database connection for now, just static values
$totalProducts = "15,000+";
$totalRevenue = "₹2.5Cr+";
$activeFirms = "500+";
$totalOrders = "25,000+";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JewelEntry - Welcome</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');
        body { font-family: Poppins, sans-serif; }
        .carousel-container.dragging, .carousel-container.dragging .carousel-slide { user-select: none; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-[#FFF9F3] text-gray-800">
    <!-- Top Header Menu -->
    <nav class="w-full bg-white shadow-md py-3 px-6 flex justify-between items-center fixed top-0 left-0 z-50">
        <div class="flex items-center gap-2">
            <img src="uploads/logo.png" alt="JewelEntry Logo" class="w-8 h-8 object-contain">
            <span class="font-bold text-xl text-orange-600">JewelEntry</span>
        </div>
        <ul class="flex gap-6 items-center text-gray-700 font-medium">
            <li><a href="#" class="hover:text-orange-500">Home</a></li>
            <li><a href="#features" class="hover:text-orange-500">Features</a></li>
            <li><a href="#pricing" class="hover:text-orange-500">Pricing</a></li>
            <li><a href="login.php" class="hover:text-orange-500">Login</a></li>
            <li><a href="register.php" class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition">Register</a></li>
        </ul>
    </nav>

    <div class="container mx-auto flex flex-col items-center justify-start min-h-screen w-full pt-24 pb-10 px-4 sm:px-6">
        <!-- Logo & App Name -->
        <div class="flex flex-col items-center mb-5">
            <img src="uploads/logo.png" alt="JewelEntry Logo" class="w-14 h-14 object-contain mb-2">
            <h1 class="text-4xl font-bold text-gray-800 mb-1">JewelEntry</h1>
            <p class="text-base text-gray-500 mb-2">Your Jewelry Business ERP</p>
        </div>

        <!-- Illustration -->
        <div class="w-full max-w-[280px] sm:max-w-xs mb-2">
            <img src="uploads/hero.png" alt="Woman at laptop with checklist and growth chart" class="w-full h-auto object-contain rounded-lg">
        </div>

        <!-- Carousel for Features -->
        <div id="features" class="carousel-container w-full max-w-xs sm:max-w-sm mb-2 relative overflow-hidden cursor-grab">
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
        <!-- Metric Cards -->
        <div class="flex justify-center gap-4 sm:gap-6 w-full max-w-sm mb-8 overflow-x-auto p-2 hide-scrollbar">
            <div class="flex items-center justify-center flex-shrink-0 w-[90px] h-[75px]">
                <i class="fas fa-gem text-orange-500 text-base"></i>
                <div class="ml-2 text-center">
                    <p class="font-bold text-gray-800 text-sm" id="totalProducts"><?php echo $totalProducts; ?></p>
                    <p class="text-gray-500 text-xs leading-tight">Products</p>
                </div>
            </div>
            <div class="flex items-center justify-center flex-shrink-0 w-[90px] h-[75px]">
                <i class="fas fa-rupee-sign text-orange-500 text-base"></i>
                <div class="ml-2 text-center">
                    <p class="font-bold text-gray-800 text-sm" id="totalRevenue"><?php echo $totalRevenue; ?></p>
                    <p class="text-gray-500 text-xs leading-tight">Revenue</p>
                </div>
            </div>
            <div class="flex items-center justify-center flex-shrink-0 w-[90px] h-[75px]">
                <i class="fas fa-store text-orange-500 text-base"></i>
                <div class="ml-2 text-center">
                    <p class="font-bold text-gray-800 text-sm" id="activeFirms"><?php echo $activeFirms; ?></p>
                    <p class="text-gray-500 text-xs leading-tight">Firms</p>
                </div>
            </div>
            <div class="flex items-center justify-center flex-shrink-0 w-[90px] h-[75px]">
                <i class="fas fa-shopping-cart text-orange-500 text-base"></i>
                <div class="ml-2 text-center">
                    <p class="font-bold text-gray-800 text-sm" id="totalOrders"><?php echo $totalOrders; ?></p>
                    <p class="text-gray-500 text-xs leading-tight">Orders</p>
                </div>
            </div>
        </div>
        <a href="register.php" class="bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-semibold py-4 px-12 rounded-lg shadow-lg text-lg w-full max-w-xs sm:max-w-sm mb-6 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-opacity-50 transition duration-150 ease-in-out transform hover:scale-105 text-center">
            Start Your Free Trial
        </a>
        <p class="text-sm text-gray-600 mb-8">
            Already have an account? <a href="login.php" class="text-orange-500 font-semibold hover:underline">Log in</a>
        </p>
        <p class="text-xs text-gray-400 mb-4 text-center">
            Develop and maintain by <span class="font-semibold text-orange-500">Prosenjit Tech Hub</span>
        </p>
    </div>
    <script>
        // Carousel JS (same as before)
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
            const AUTO_SWIPE_DELAY = 3000;
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
                if (!isAutoTriggered) { stopAutoSwipe(); }
                currentIndex = index;
                currentTranslate = -currentIndex * slideWidth();
                prevTranslate = currentTranslate;
                slidesWrapper.style.transform = `translateX(${currentTranslate}px)`;
                updateDots();
                if (!isAutoTriggered) { setTimeout(startAutoSwipe, AUTO_SWIPE_DELAY / 2); }
            }
            function nextSlide() {
                const newIndex = (currentIndex + 1) % slides.length;
                goToSlide(newIndex, true);
            }
            function startAutoSwipe() {
                stopAutoSwipe();
                autoSwipeInterval = setInterval(nextSlide, AUTO_SWIPE_DELAY);
            }
            function stopAutoSwipe() {
                clearInterval(autoSwipeInterval);
            }
            function getPositionX(event) {
                return event.type.includes('mouse') ? event.pageX : event.touches[0].clientX;
            }
            function dragStartHandler(event) {
                stopAutoSwipe();
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
                goToSlide(targetIndex);
            }
            carousel.addEventListener('mousedown', dragStartHandler);
            carousel.addEventListener('mousemove', dragHandler);
            carousel.addEventListener('mouseup', dragEndHandler);
            carousel.addEventListener('mouseleave', () => {
                if (isDragging) { dragEndHandler(); }
                startAutoSwipe();
            });
            carousel.addEventListener('mouseenter', stopAutoSwipe);
            carousel.addEventListener('touchstart', dragStartHandler, { passive: true });
            carousel.addEventListener('touchmove', dragHandler, { passive: true });
            carousel.addEventListener('touchend', dragEndHandler);
            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    const slideIndex = parseInt(dot.getAttribute('data-slide-to'));
                    goToSlide(slideIndex);
                });
            });
            goToSlide(0);
            startAutoSwipe();
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    const currentSlideBeforeResize = currentIndex;
                    stopAutoSwipe();
                    goToSlide(currentSlideBeforeResize);
                    startAutoSwipe();
                }, 200);
            });
        });
    </script>
</body>
</html> 