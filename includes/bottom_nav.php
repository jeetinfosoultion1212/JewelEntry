<style>
    .nav-btn .nav-indicator {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        margin: 4px auto 0 auto;
        display: none;
    }
    .nav-btn.active .nav-indicator {
        display: block;
    }
    .nav-btn .nav-icon, .nav-btn .nav-label {
        transition: color 0.2s;
    }
</style>
<nav class="bottom-nav fixed bottom-0 left-0 right-0 shadow-xl">
    <div class="px-4 py-2">
        <div class="flex justify-around">
            <?php $page = basename($_SERVER['PHP_SELF']); ?>
            <a href="home.php" data-nav-id="home" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300<?php echo $page == 'home.php' ? ' active' : ''; ?>">
                <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                    <i class="fas fa-home nav-icon <?php echo $page == 'home.php' ? 'text-blue-600' : 'text-gray-400'; ?> text-sm"></i>
                </div>
                <span class="nav-label text-xs font-medium <?php echo $page == 'home.php' ? 'text-blue-600' : 'text-gray-400'; ?>">Home</span>
                <span class="nav-indicator" style="background: #2563eb;<?php echo $page == 'home.php' ? '' : 'display:none;'; ?>"></span>
            </a>
            <button data-nav-id="search" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                    <i class="fas fa-search nav-icon text-gray-400 text-sm"></i>
                </div>
                <span class="nav-label text-xs font-medium text-gray-400">Search</span>
                <span class="nav-indicator" style="background: #a3a3a3; display:none;"></span>
            </button>
            <button data-nav-id="add" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300">
                <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                    <i class="fas fa-plus-circle nav-icon text-gray-400 text-sm"></i>
                </div>
                <span class="nav-label text-xs font-medium text-gray-400">Add</span>
                <span class="nav-indicator" style="background: #a3a3a3; display:none;"></span>
            </button>
            <a href="reports.php" data-nav-id="daybook" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300<?php echo $page == 'reports.php' ? ' active' : ''; ?>">
                <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                    <i class="fas fa-book nav-icon <?php echo $page == 'reports.php' ? 'text-purple-600' : 'text-gray-400'; ?> text-sm"></i>
                </div>
                <span class="nav-label text-xs font-medium <?php echo $page == 'reports.php' ? 'text-purple-600 font-bold' : 'text-gray-400'; ?>">Day Book</span>
                <span class="nav-indicator" style="background: #a21caf;<?php echo $page == 'reports.php' ? '' : 'display:none;'; ?>"></span>
            </a>
            <a href="profile.php" data-nav-id="profile" class="nav-btn flex flex-col items-center space-y-1 py-2 px-3 rounded-xl transition-all duration-300<?php echo $page == 'profile.php' ? ' active' : ''; ?>">
                <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-circle nav-icon <?php echo $page == 'profile.php' ? 'text-blue-600' : 'text-gray-400'; ?> text-sm"></i>
                </div>
                <span class="nav-label text-xs font-medium <?php echo $page == 'profile.php' ? 'text-blue-600' : 'text-gray-400'; ?>">Profile</span>
                <span class="nav-indicator" style="background: #2563eb;<?php echo $page == 'profile.php' ? '' : 'display:none;'; ?>"></span>
            </a>
        </div>
    </div>
</nav>
<script>
// JS for nav active state and feature lock modal (if needed)
// You can add more nav logic here as needed
</script> 