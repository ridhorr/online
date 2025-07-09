<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'pembeli') {
    header('Location: ../index.php');
    exit;
}

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Ambil produk yang tersedia
$stmt = $pdo->prepare("SELECT * FROM products WHERE is_available = 1 ORDER BY type, name");
$stmt->execute();
$products = $stmt->fetchAll();

// Hitung jarak dari depot
$distance = calculateDistance(DEPOT_LAT, DEPOT_LNG, $user['latitude'], $user['longitude']);
$delivery_fee = calculateDeliveryFee($distance);

// Hitung statistik produk
$total_products = count($products);
$air_products = count(array_filter($products, function($p) { return $p['type'] == 'air'; }));
$gas_products = count(array_filter($products, function($p) { return $p['type'] == 'gas'; }));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Delivery Galon</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 80px; /* Space for bottom navigation */
        }
        
        .header {
            background: white;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logout-btn {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        
        .container {
            padding: 15px;
        }
        
        .page-header {
            background: white;
            padding: 20px 15px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .page-header h1 {
            color: #333;
            margin-bottom: 8px;
            font-size: 20px;
        }
        
        .page-header p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .delivery-info-badge {
            background: #e6fffa;
            color: #319795;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
            margin-bottom: 15px;
        }
        
        .delivery-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 15px;
        }
        
        .delivery-info-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            font-size: 12px;
            text-align: left;
        }
        
        .delivery-info-item strong {
            display: block;
            margin-bottom: 3px;
            color: #333;
        }
        
        .product-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 3px;
        }
        
        .stat-label {
            color: #666;
            font-size: 12px;
        }
        
        .products-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .products-header {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: center;
        }
        
        .products-header h2 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .products-count {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .product-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            position: relative;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-item.selected {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
        }
        
        .product-top {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .product-type-badge {
            background: #667eea;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .product-type-badge.air {
            background: #3182ce;
        }
        
        .product-type-badge.gas {
            background: #dd6b20;
        }
        
        .product-header {
            flex: 1;
        }
        
        .product-name {
            color: #333;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .product-price {
            color: #667eea;
            font-size: 16px;
            font-weight: bold;
        }
        
        .product-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            background: #667eea;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            touch-action: manipulation;
        }
        
        .quantity-btn:active {
            transform: scale(0.95);
        }
        
        .quantity-input {
            width: 50px;
            text-align: center;
            border: 2px solid #ddd;
            padding: 5px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .cart-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .cart-header {
            background: #38a169;
            color: white;
            padding: 15px;
            text-align: center;
        }
        
        .cart-header h2 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .cart-count {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .cart-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 3px;
        }
        
        .cart-item-detail {
            color: #666;
            font-size: 12px;
        }
        
        .cart-item-price {
            font-weight: bold;
            color: #38a169;
        }
        
        .cart-total {
            background: #f8f9fa;
            padding: 15px;
            border-top: 2px solid #eee;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .total-final {
            font-weight: bold;
            font-size: 16px;
            border-top: 1px solid #ddd;
            padding-top: 8px;
            margin-top: 8px;
        }
        
        .empty-cart {
            text-align: center;
            color: #666;
            padding: 30px 20px;
        }
        
        .empty-cart-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .empty-cart h3 {
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .empty-cart p {
            font-size: 14px;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            width: 100%;
            margin-top: 10px;
            touch-action: manipulation;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .btn:active {
            transform: scale(0.98);
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #eee;
            padding: 10px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-links {
            display: flex;
            justify-content: space-around;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .nav-links a {
            color: #666;
            text-decoration: none;
            text-align: center;
            font-size: 11px;
            padding: 5px;
            border-radius: 8px;
            transition: all 0.3s;
            min-width: 60px;
        }
        
        .nav-links a.active {
            color: #667eea;
            background: #f0f4ff;
        }
        
        .nav-links a:hover {
            color: #667eea;
        }
        
        .nav-icon {
            font-size: 16px;
            margin-bottom: 2px;
            display: block;
        }
        
        /* Floating cart button */
        .floating-cart {
            position: fixed;
            bottom: 90px;
            right: 20px;
            background: #38a169;
            color: white;
            border: none;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            font-size: 24px;
            cursor: pointer;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            touch-action: manipulation;
        }
        
        .floating-cart.show {
            display: flex;
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e53e3e;
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            
            .product-stats {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .product-item {
                padding: 12px;
            }
            
            .logo {
                font-size: 16px;
            }
            
            .delivery-info-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏺 Delivery Galon</div>
            <div class="user-info">
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h1>🏠 Selamat Datang, <?php echo htmlspecialchars($user['name']); ?>!</h1>
            <p>Pesan air galon dan gas LPG dengan mudah dan cepat</p>
            <div class="delivery-info-badge">
                📍 Jarak: <?php echo number_format($distance, 2); ?> km
            </div>
            
            <div class="delivery-info-grid">
                <div class="delivery-info-item">
                    <strong>🚚 Ongkos Kirim</strong>
                    <?php echo $delivery_fee == 0 ? 'GRATIS' : formatRupiah($delivery_fee); ?>
                </div>
                <div class="delivery-info-item">
                    <strong>📞 Nomor Telepon</strong>
                    <?php echo htmlspecialchars($user['phone']); ?>
                </div>
            </div>
        </div>
        
        <div class="product-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Produk</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $air_products; ?></div>
                <div class="stat-label">Air Galon</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $gas_products; ?></div>
                <div class="stat-label">Gas LPG</div>
            </div>
        </div>
        
        <div class="products-section">
            <div class="products-header">
                <h2>📦 Pilih Produk</h2>
                <span class="products-count"><?php echo $total_products; ?> produk tersedia</span>
            </div>
            
            <?php foreach ($products as $product): ?>
            <div class="product-item" data-product-id="<?php echo $product['id']; ?>" data-product-name="<?php echo htmlspecialchars($product['name']); ?>" data-product-price="<?php echo $product['price']; ?>">
                <div class="product-top">
                    <div class="product-type-badge <?php echo $product['type']; ?>">
                        <?php echo $product['type'] == 'air' ? '💧' : '🔥'; ?>
                    </div>
                    <div class="product-header">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="product-price"><?php echo formatRupiah($product['price']); ?></div>
                    </div>
                </div>
                
                <div class="product-bottom">
                    <div style="font-size: 12px; color: #666;">
                        <?php echo ucfirst($product['type']); ?>
                    </div>
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="changeQuantity(<?php echo $product['id']; ?>, -1)">-</button>
                        <input type="number" class="quantity-input" id="qty-<?php echo $product['id']; ?>" value="0" min="0" onchange="updateCart()" readonly>
                        <button class="quantity-btn" onclick="changeQuantity(<?php echo $product['id']; ?>, 1)">+</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="cart-section">
            <div class="cart-header">
                <h2>🛒 Keranjang Belanja</h2>
                <span class="cart-count" id="cart-count">0 item</span>
            </div>
            
            <div id="cart-items">
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Keranjang masih kosong</h3>
                    <p>Pilih produk yang ingin Anda pesan</p>
                </div>
            </div>
            
            <div class="cart-total" id="cart-total" style="display: none;">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">Rp 0</span>
                </div>
                <div class="total-row">
                    <span>Ongkos Kirim:</span>
                    <span><?php echo $delivery_fee == 0 ? 'GRATIS' : formatRupiah($delivery_fee); ?></span>
                </div>
                <div class="total-row total-final">
                    <span>Total:</span>
                    <span id="total">Rp 0</span>
                </div>
                <button class="btn" onclick="checkout()" id="checkout-btn" disabled>Pesan Sekarang</button>
            </div>
        </div>
    </div>
    
    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="nav-links">
            <a href="home.php" class="active">
                <span class="nav-icon">🏠</span>
                Home
            </a>
            <a href="orders.php">
                <span class="nav-icon">📋</span>
                Pesanan
            </a>
            <a href="queue.php">
                <span class="nav-icon">🚚</span>
                Antrian
            </a>
            <a href="profile.php">
                <span class="nav-icon">👤</span>
                Profil
            </a>
        </div>
    </div>
    
    <!-- Floating cart button -->
    <button class="floating-cart" id="floating-cart" onclick="scrollToCart()">
        🛒
        <div class="cart-badge" id="cart-badge" style="display: none;">0</div>
    </button>
    
    <script>
        let cart = {};
        const deliveryFee = <?php echo $delivery_fee; ?>;
        
        function changeQuantity(productId, change) {
            const qtyInput = document.getElementById('qty-' + productId);
            let currentQty = parseInt(qtyInput.value) || 0;
            let newQty = Math.max(0, currentQty + change);
            
            qtyInput.value = newQty;
            updateCart();
        }
        
        function updateCart() {
            cart = {};
            let hasItems = false;
            let totalItems = 0;
            
            document.querySelectorAll('.product-item').forEach(item => {
                const productId = item.dataset.productId;
                const productName = item.dataset.productName;
                const productPrice = parseInt(item.dataset.productPrice);
                const qty = parseInt(document.getElementById('qty-' + productId).value) || 0;
                
                if (qty > 0) {
                    cart[productId] = {
                        name: productName,
                        price: productPrice,
                        quantity: qty
                    };
                    hasItems = true;
                    totalItems += qty;
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
            
            displayCart();
            updateFloatingCart(hasItems, totalItems);
            updateCartCount(totalItems);
            document.getElementById('checkout-btn').disabled = !hasItems;
        }
        
        function displayCart() {
            const cartItems = document.getElementById('cart-items');
            const cartTotal = document.getElementById('cart-total');
            
            if (Object.keys(cart).length === 0) {
                cartItems.innerHTML = `
                    <div class="empty-cart">
                        <div class="empty-cart-icon">🛒</div>
                        <h3>Keranjang masih kosong</h3>
                        <p>Pilih produk yang ingin Anda pesan</p>
                    </div>
                `;
                cartTotal.style.display = 'none';
                return;
            }
            
            let html = '';
            let subtotal = 0;
            
            for (const [id, item] of Object.entries(cart)) {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                
                html += `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-detail">${item.quantity} x ${formatRupiah(item.price)}</div>
                        </div>
                        <div class="cart-item-price">${formatRupiah(itemTotal)}</div>
                    </div>
                `;
            }
            
            cartItems.innerHTML = html;
            cartTotal.style.display = 'block';
            
            const total = subtotal + deliveryFee;
            document.getElementById('subtotal').textContent = formatRupiah(subtotal);
            document.getElementById('total').textContent = formatRupiah(total);
        }
        
        function updateFloatingCart(hasItems, totalItems) {
            const floatingCart = document.getElementById('floating-cart');
            const cartBadge = document.getElementById('cart-badge');
            
            if (hasItems) {
                floatingCart.classList.add('show');
                cartBadge.style.display = 'flex';
                cartBadge.textContent = totalItems;
            } else {
                floatingCart.classList.remove('show');
                cartBadge.style.display = 'none';
            }
        }
        
        function updateCartCount(totalItems) {
            const cartCount = document.getElementById('cart-count');
            cartCount.textContent = totalItems + ' item' + (totalItems > 1 ? 's' : '');
        }
        
        function scrollToCart() {
            document.querySelector('.cart-section').scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
        }
        
        function formatRupiah(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }
        
        function checkout() {
            if (Object.keys(cart).length === 0) {
                alert('Keranjang masih kosong!');
                return;
            }
            
            // Kirim data ke halaman checkout
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'checkout.php';
            
            const cartInput = document.createElement('input');
            cartInput.type = 'hidden';
            cartInput.name = 'cart_data';
            cartInput.value = JSON.stringify(cart);
            
            form.appendChild(cartInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Prevent zoom on input focus (iOS)
        document.addEventListener('touchstart', function() {}, true);
        
        // Add touch feedback
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('button, .btn, a');
            buttons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.style.opacity = '0.7';
                });
                button.addEventListener('touchend', function() {
                    setTimeout(() => {
                        this.style.opacity = '1';
                    }, 100);
                });
            });
        });
    </script>
</body>
</html>