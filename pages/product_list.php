<?php
require_once 'config/database.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug database connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Fetch jewelry items with their primary images
$query = "SELECT ji.*, jpi.image_url 
          FROM jewellery_items ji 
          LEFT JOIN jewellery_product_image jpi 
          ON ji.product_id = jpi.product_id 
          WHERE jpi.is_primary = 1
          ORDER BY ji.created_at DESC";
$result = mysqli_query($conn, $query);

// Debug query
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jewelry Collection</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- Header Section -->
    <div class="bg-gradient-to-r from-amber-700 to-yellow-600 text-white">
        <div class="container mx-auto px-4 py-8">
            <h1 class="text-4xl font-bold mb-2">Jewelry Collection</h1>
            <p class="text-xl opacity-90">Discover our exclusive collection of fine jewelry</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <!-- Grid Layout -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if(mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden transform transition duration-300 hover:-translate-y-2 hover:shadow-2xl">
                        <!-- Product Image -->
                        <div class="relative">
                            <img src="<?php echo htmlspecialchars($row['image_url'] ?? 'assets/default-jewelry.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                 class="w-full h-64 object-cover">
                            
                            <!-- Action Icons -->
                            <div class="absolute top-4 right-4 bg-white/90 rounded-full px-4 py-2 shadow-md">
                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-800 mx-1">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="view.php?id=<?php echo $row['id']; ?>" class="text-green-600 hover:text-green-800 mx-1">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="#" class="text-purple-600 hover:text-purple-800 mx-1 share-btn"
                                   data-product-id="<?php echo $row['id']; ?>"
                                   data-product-name="<?php echo htmlspecialchars($row['product_name']); ?>">
                                    <i class="fas fa-share-alt"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Product Details -->
                        <div class="p-6">
                            <h3 class="text-xl font-bold text-amber-900 mb-4">
                                <?php echo htmlspecialchars($row['product_name']); ?>
                            </h3>
                            
                            <ul class="space-y-2 mb-4">
                                <li class="flex items-center text-gray-700">
                                    <i class="fas fa-gem w-6 text-amber-700"></i>
                                    <?php echo htmlspecialchars($row['jewelry_type']); ?>
                                </li>
                                <li class="flex items-center text-gray-700">
                                    <i class="fas fa-crown w-6 text-amber-700"></i>
                                    <?php echo htmlspecialchars($row['material_type']); ?> 
                                    (<?php echo htmlspecialchars($row['purity']); ?>)
                                </li>
                                <li class="flex items-center text-gray-700">
                                    <i class="fas fa-weight-hanging w-6 text-amber-700"></i>
                                    <?php echo htmlspecialchars($row['net_weight']); ?> g
                                </li>
                            </ul>

                            <div class="inline-block bg-amber-600 text-white px-4 py-2 rounded-full">
                                <i class="fas fa-rupee-sign"></i>
                                <?php echo number_format($row['rate_per_gram'] * $row['net_weight'], 2); ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-12">
                    <p class="text-2xl text-gray-500">No jewelry items found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Share Modal -->
    <div id="shareModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Share Product</h3>
                <div class="flex flex-col space-y-4">
                    <a href="#" class="whatsapp-btn bg-green-500 text-white px-4 py-3 rounded-lg hover:bg-green-600 transition">
                        <i class="fab fa-whatsapp mr-2"></i> Share on WhatsApp
                    </a>
                    <a href="#" class="facebook-btn bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700 transition">
                        <i class="fab fa-facebook mr-2"></i> Share on Facebook
                    </a>
                    <a href="#" class="twitter-btn bg-blue-400 text-white px-4 py-3 rounded-lg hover:bg-blue-500 transition">
                        <i class="fab fa-twitter mr-2"></i> Share on Twitter
                    </a>
                </div>
                <button id="closeModal" class="mt-4 bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const shareButtons = document.querySelectorAll('.share-btn');
        const shareModal = document.getElementById('shareModal');
        const closeModal = document.getElementById('closeModal');

        shareButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                
                const shareUrl = `${window.location.origin}/view.php?id=${productId}`;
                const whatsappBtn = document.querySelector('.whatsapp-btn');
                const facebookBtn = document.querySelector('.facebook-btn');
                const twitterBtn = document.querySelector('.twitter-btn');
                
                whatsappBtn.href = `https://api.whatsapp.com/send?text=${encodeURIComponent(productName + ' - ' + shareUrl)}`;
                facebookBtn.href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`;
                twitterBtn.href = `https://twitter.com/intent/tweet?text=${encodeURIComponent(productName)}&url=${encodeURIComponent(shareUrl)}`;
                
                shareModal.classList.remove('hidden');
            });
        });

        closeModal.addEventListener('click', function() {
            shareModal.classList.add('hidden');
        });
    });
    </script>
</body>
</html>