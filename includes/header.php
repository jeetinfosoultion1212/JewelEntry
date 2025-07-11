<?php
// Header include for JewelEntryApp
if (!isset($userInfo)) {
    // If not set, fetch user info as in home.php
    $user_id = $_SESSION['id'];
    $firm_id = $_SESSION['firmID'];
    $conn = new mysqli($servername, $username, $password, $dbname);
    $userQuery = "SELECT u.Name, u.Role, u.image_path, f.FirmName, f.City, f.Logo FROM Firm_Users u JOIN Firm f ON f.id = u.FirmID WHERE u.id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $userInfo = $userResult->fetch_assoc();
}
?>
<header class="header-glass sticky top-0 z-50 shadow-md">
    <div class="px-3 py-2">
        <div class="flex items-center justify-between">
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
                    <p class="text-xs text-gray-600 font-medium">Powered by JewelEntry</p>
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
    </div>
</header> 