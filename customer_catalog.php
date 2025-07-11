<?php
// customer_catalog.php
// Customer-facing product catalog (no admin actions)

// --- CONFIG ---
$firm_id = isset($_GET['firm_id']) ? intval($_GET['firm_id']) : 1; // Or get from session if needed

// --- DB CONNECTION ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "jewelentrypro";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- FETCH FIRM DETAILS ---
$sql_firm = "SELECT FirmName, Address, city, state, PostalCode, PhoneNumber FROM Firm WHERE id = ? LIMIT 1";
$stmt_firm = $conn->prepare($sql_firm);
$stmt_firm->bind_param("i", $firm_id);
$stmt_firm->execute();
$firm = $stmt_firm->get_result()->fetch_assoc();

// --- FETCH CATEGORIES ---
$categories = [];
$sql_cat = "SELECT DISTINCT jewelry_type FROM jewellery_items WHERE status = 'Available' AND firm_id = ? ORDER BY jewelry_type";
$stmt_cat = $conn->prepare($sql_cat);
$stmt_cat->bind_param("i", $firm_id);
$stmt_cat->execute();
$res_cat = $stmt_cat->get_result();
while ($row = $res_cat->fetch_assoc()) {
    if (!empty($row['jewelry_type'])) $categories[] = $row['jewelry_type'];
}

// --- FETCH PRODUCTS ---
$products = [];
$sql = "SELECT ji.*, ji.Tray_no as tray_no, GROUP_CONCAT(jpi.image_url) as image_urls
        FROM jewellery_items ji
        LEFT JOIN jewellery_product_image jpi ON ji.id = jpi.product_id
        WHERE ji.status = 'Available' AND ji.firm_id = ?
        GROUP BY ji.id
        ORDER BY ji.created_at DESC LIMIT 100";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $firm_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $row['images'] = $row['image_urls'] ? explode(',', $row['image_urls']) : [];
    unset($row['image_urls']);
    $row['tray_no'] = isset($row['tray_no']) ? $row['tray_no'] : '';
    $products[$row['id']] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($firm['FirmName'] ?? 'Jewellery Catalog') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
        .tray-badge { display: inline-block; background: #fffde7; color: #b8860b; font-size: 11px; font-weight: 600; border-radius: 6px; padding: 2px 8px; margin: 2px 0 4px 0; letter-spacing: 1px; }
        .product-card-cust { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #0001; padding: 10px; position: relative; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: box-shadow 0.2s; }
        .product-card-cust:hover { box-shadow: 0 8px 24px #0002; }
        .product-image-cust { width: 100%; height: 120px; border-radius: 10px; overflow: hidden; background: #f5f5f5; display: flex; align-items: center; justify-content: center; }
        .product-image-cust img { width: 100%; height: 100%; object-fit: contain; }
        .product-title-cust { font-size: 14px; font-weight: 700; color: #1e293b; margin: 6px 0 2px 0; text-align: center; }
        .product-meta-cust { font-size: 12px; color: #555; text-align: center; margin-bottom: 2px; }
        .product-price-cust { font-size: 15px; font-weight: 800; color: #b8860b; text-align: center; margin-top: 2px; }
        .product-grid-cust { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; padding: 16px; }
        .category-filter-cust { display: flex; gap: 10px; overflow-x: auto; padding: 10px 0 6px 0; }
        .category-btn-cust { background: #f5f5f5; border-radius: 16px; padding: 6px 16px; font-size: 14px; color: #444; border: none; cursor: pointer; transition: background 0.15s, color 0.15s; white-space: nowrap; }
        .category-btn-cust.active, .category-btn-cust:hover { background: #b8860b; color: #fff; }
        /* Modal styles */
        .modal-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.25); z-index: 50; display: flex; align-items: center; justify-content: center; }
        .modal-content { background: #fff; border-radius: 18px; max-width: 95vw; width: 370px; box-shadow: 0 8px 32px #0003; padding: 0; overflow: hidden; position: relative; }
        .modal-header { padding: 16px 16px 0 16px; text-align: center; }
        .modal-title { font-size: 20px; font-weight: 800; color: #b8860b; margin-bottom: 2px; }
        .modal-close { position: absolute; top: 10px; right: 14px; font-size: 22px; color: #888; background: none; border: none; cursor: pointer; }
        .modal-image { width: 100%; height: 210px; object-fit: contain; background: #f5f5f5; border-radius: 12px; margin-bottom: 10px; }
        .modal-specs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 10px; background: #fffde7; border-radius: 10px; padding: 10px 10px 4px 10px; margin-bottom: 12px; font-size: 13px; }
        .modal-spec-label { font-size: 11px; color: #a1887f; font-weight: 500; }
        .modal-spec-value { font-size: 14px; font-weight: 700; color: #222; }
        .modal-price { font-size: 18px; font-weight: 900; color: #fff; background: linear-gradient(90deg, #ffd700 0%, #b8860b 100%); border-radius: 8px; padding: 4px 18px; box-shadow: 0 2px 8px #b8860b33; border: 2px solid #ffe082; letter-spacing: 1px; text-align: center; margin: 0 auto 10px auto; display: block; }
        .modal-footer { text-align: center; font-size: 12px; color: #8d6e63; margin-bottom: 10px; }
        @media (max-width: 600px) { .product-grid-cust { grid-template-columns: repeat(2, 1fr); gap: 8px; padding: 8px; } .modal-content { width: 98vw; } }
    </style>
</head>
<body>
    <header class="w-full bg-white shadow-sm py-3 px-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="text-xl font-bold text-yellow-800"><?= htmlspecialchars($firm['FirmName'] ?? 'Jewellers') ?></span>
        </div>
        <span class="text-xs text-gray-500">Customer Catalog</span>
    </header>
    <main class="max-w-5xl mx-auto">
        <div class="category-filter-cust" id="categoryFilter">
            <button class="category-btn-cust active" data-category="All">All</button>
            <?php foreach ($categories as $cat): ?>
                <button class="category-btn-cust" data-category="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
            <?php endforeach; ?>
        </div>
        <div class="product-grid-cust" id="productGrid">
            <?php foreach ($products as $product): ?>
                <div class="product-card-cust" data-category="<?= htmlspecialchars($product['jewelry_type']) ?>" onclick="showProductDetails(<?= (int)$product['id'] ?>)">
                    <div class="product-image-cust">
                        <img src="<?= !empty($product['images']) && !empty($product['images'][0]) ? htmlspecialchars($product['images'][0]) : 'uploads/jewelry/no_image.png' ?>" alt="<?= htmlspecialchars($product['product_name']) ?>">
                    </div>
                    <div class="product-title-cust"><?= htmlspecialchars($product['product_name']) ?></div>
                    <?php if (!empty($product['tray_no'])): ?>
                        <div class="tray-badge">Tray: <?= htmlspecialchars($product['tray_no']) ?></div>
                    <?php endif; ?>
                    <div class="product-meta-cust">
                        <?= htmlspecialchars($product['purity']) ?> | <?= htmlspecialchars($product['net_weight']) ?>g
                    </div>
                    <div class="product-price-cust">₹<?= number_format(round($product['net_weight'] * $product['rate_per_gram'])) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
    <!-- Product Details Modal -->
    <div id="productModal" class="modal-bg" style="display:none;">
        <div class="modal-content">
            <button class="modal-close" onclick="closeProductModal()">&times;</button>
            <div class="modal-header">
                <div class="modal-title" id="modalProductName"></div>
            </div>
            <img id="modalProductImage" class="modal-image" src="" alt="Product Image">
            <div class="modal-specs" id="modalSpecs"></div>
            <span class="modal-price" id="modalPrice"></span>
            <div class="modal-footer" id="modalFooter"></div>
        </div>
    </div>
    <script>
        // JS Data
        const products = <?= json_encode(array_values($products)) ?>;
        // Category Filter
        document.querySelectorAll('.category-btn-cust').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.category-btn-cust').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const cat = btn.getAttribute('data-category');
                document.querySelectorAll('.product-card-cust').forEach(card => {
                    if (cat === 'All' || card.getAttribute('data-category') === cat) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        // Product Details Modal
        function showProductDetails(id) {
            const product = products.find(p => p.id == id);
            if (!product) return alert('Product not found');
            document.getElementById('modalProductName').textContent = product.product_name;
            document.getElementById('modalProductImage').src = (product.images && product.images[0]) ? product.images[0] : 'uploads/jewelry/no_image.png';
            // Specs
            let specs = '';
            specs += `<div><span class='modal-spec-label'>Purity</span><span class='modal-spec-value'>${product.purity}</span></div>`;
            specs += `<div><span class='modal-spec-label'>Weight</span><span class='modal-spec-value'>${product.net_weight}g</span></div>`;
            specs += `<div><span class='modal-spec-label'>Tray No.</span><span class='modal-spec-value'>${product.tray_no ? product.tray_no : 'N/A'}</span></div>`;
            specs += `<div><span class='modal-spec-label'>Stone</span><span class='modal-spec-value'>${product.stone_type ? product.stone_type : 'N/A'}</span></div>`;
            document.getElementById('modalSpecs').innerHTML = specs;
            // Price
            document.getElementById('modalPrice').textContent = `₹${Math.round(product.net_weight * product.rate_per_gram).toLocaleString()}`;
            // Footer
            document.getElementById('modalFooter').textContent = 'For more details, contact ' + <?= json_encode($firm['FirmName'] ?? 'our store') ?>;
            document.getElementById('productModal').style.display = 'flex';
        }
        function closeProductModal() {
            document.getElementById('productModal').style.display = 'none';
        }
        // Modal close on background click
        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) closeProductModal();
        });
    </script>
</body>
</html> 