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

$error = '';
$success = '';

// Proses checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cart_data'])) {
    $cart_data = json_decode($_POST['cart_data'], true);
    
    if (empty($cart_data)) {
        $error = 'Keranjang belanja kosong';
    } else {
        $pdo->beginTransaction();
        
        try {
            // Hitung total
            $subtotal = 0;
            $valid_items = [];
            
            foreach ($cart_data as $product_id => $item) {
                // Verifikasi produk - sesuaikan dengan kolom database
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_available = 1");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if ($product) {
                    $item_total = $product['price'] * $item['quantity'];
                    $subtotal += $item_total;
                    $valid_items[] = [
                        'id' => $product_id,
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'quantity' => $item['quantity']
                    ];
                }
            }
            
            if (empty($valid_items)) {
                throw new Exception('Tidak ada produk yang valid');
            }
            
            // Hitung ongkir
            $distance = calculateDistance(DEPOT_LAT, DEPOT_LNG, $user['latitude'], $user['longitude']);
            $delivery_fee = calculateDeliveryFee($distance);
            $total_amount = $subtotal + $delivery_fee;
            
            // Validasi payment method
            if (!isset($_POST['payment_method']) || empty($_POST['payment_method'])) {
                throw new Exception('Metode pembayaran harus dipilih');
            }
            
            $payment_method = $_POST['payment_method'];
            if (!in_array($payment_method, ['transfer', 'cod'])) {
                throw new Exception('Metode pembayaran tidak valid');
            }
            
            // Simpan order - sesuaikan dengan kolom database
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, total_amount, delivery_fee, distance_km, payment_method, notes, payment_status, order_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', 'pending')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $total_amount,
                $delivery_fee,
                $distance,
                $payment_method,
                $_POST['notes'] ?? ''
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // Simpan order items
            foreach ($valid_items as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order_id,
                    $item['id'],
                    $item['quantity'],
                    $item['price']
                ]);
            }
            
            $pdo->commit();
            
            // Redirect ke halaman success
            header('Location: order_success.php?order_id=' . $order_id);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['cart_data'])) {
    $error = 'Data keranjang tidak valid';
}

// Jika tidak ada data cart, redirect ke home
if (!isset($_POST['cart_data']) && !$error) {
    header('Location: home.php');
    exit;
}

// Parse cart data untuk ditampilkan
$cart_data = isset($_POST['cart_data']) ? json_decode($_POST['cart_data'], true) : [];
$subtotal = 0;
$cart_items = [];

if (!empty($cart_data)) {
    foreach ($cart_data as $product_id => $item) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            $item_total = $product['price'] * $item['quantity'];
            $subtotal += $item_total;
            $cart_items[] = [
                'id' => $product_id,
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $item['quantity'],
                'total' => $item_total
            ];
        }
    }
}

$distance = calculateDistance(DEPOT_LAT, DEPOT_LNG, $user['latitude'], $user['longitude']);
$delivery_fee = calculateDeliveryFee($distance);
$total_amount = $subtotal + $delivery_fee;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Delivery Galon</title>
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
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: #667eea;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin-bottom: 10px;
        }
        
        .content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        
        .section h3 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: bold;
            color: #333;
        }
        
        .item-details {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .item-price {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .payment-option {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-option:hover {
            border-color: #667eea;
        }
        
        .payment-option.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .payment-option input[type="radio"] {
            margin-right: 10px;
        }
        
        .payment-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        
        .payment-info.show {
            display: block;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #6c757d;
            margin-bottom: 10px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .error {
            background: #fee;
            color: #c53030;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fed7d7;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .user-info h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .user-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .delivery-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 10px;
        }
        
        .info-card {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .info-card strong {
            color: #667eea;
            font-size: 18px;
        }
        
        .info-card small {
            color: #666;
            display: block;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .delivery-info {
                grid-template-columns: 1fr;
            }
            
            .container {
                margin: 10px;
            }
            
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏺 Checkout</h1>
            <p>Konfirmasi pesanan Anda</p>
        </div>
        
        <div class="content">
            <a href="home.php" class="back-link">← Kembali ke Beranda</a>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($cart_items)): ?>
            <form method="POST">
                <input type="hidden" name="cart_data" value="<?php echo htmlspecialchars(json_encode($cart_data)); ?>">
                
                <!-- Informasi Pengiriman -->
                <div class="section">
                    <h3>📍 Informasi Pengiriman</h3>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                        <p><strong>Telepon:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                        <p><strong>Alamat:</strong> <?php echo htmlspecialchars($user['address']); ?></p>
                    </div>
                    
                    <div class="delivery-info">
                        <div class="info-card">
                            <strong><?php echo number_format($distance, 2); ?> km</strong>
                            <small>Jarak dari Depot</small>
                        </div>
                        <div class="info-card">
                            <strong><?php echo $delivery_fee == 0 ? 'GRATIS' : formatRupiah($delivery_fee); ?></strong>
                            <small>Ongkos Kirim</small>
                        </div>
                    </div>
                </div>
                
                <!-- Daftar Pesanan -->
                <div class="section">
                    <h3>📋 Detail Pesanan</h3>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="order-item">
                            <div class="item-info">
                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="item-details"><?php echo $item['quantity']; ?> x <?php echo formatRupiah($item['price']); ?></div>
                            </div>
                            <div class="item-price"><?php echo formatRupiah($item['total']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Ringkasan Pembayaran -->
                <div class="section">
                    <h3>💰 Ringkasan Pembayaran</h3>
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span><?php echo formatRupiah($subtotal); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Ongkos Kirim:</span>
                        <span><?php echo $delivery_fee == 0 ? 'GRATIS' : formatRupiah($delivery_fee); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Total Pembayaran:</span>
                        <span><?php echo formatRupiah($total_amount); ?></span>
                    </div>
                </div>
                
                <!-- Metode Pembayaran -->
                <div class="section">
                    <h3>💳 Metode Pembayaran</h3>
                    <div class="payment-methods">
                        <div class="payment-option" onclick="selectPayment('cod')">
                            <label for="cod" style="cursor: pointer; display: block;">
                                <input type="radio" name="payment_method" value="cod" id="cod" required>
                                <strong>💵 Bayar di Tempat (COD)</strong><br>
                                <small>Bayar saat barang diterima</small>
                            </label>
                        </div>
                        <div class="payment-option" onclick="selectPayment('transfer')">
                            <label for="transfer" style="cursor: pointer; display: block;">
                                <input type="radio" name="payment_method" value="transfer" id="transfer" required>
                                <strong>🏦 Transfer Bank</strong><br>
                                <small>Transfer ke rekening toko</small>
                            </label>
                        </div>
                    </div>
                    
                    <div id="transfer-info" class="payment-info">
                        <h4>Informasi Transfer</h4>
                        <p><strong>Bank BCA</strong></p>
                        <p>No. Rekening: 1234567890</p>
                        <p>Atas Nama: Toko Galon Sejahtera</p>
                        <p><em>Silakan transfer sejumlah <?php echo formatRupiah($total_amount); ?> dan kirim bukti transfer via WhatsApp</em></p>
                    </div>
                    
                    <div id="cod-info" class="payment-info">
                        <h4>Pembayaran COD</h4>
                        <p>Pembayaran akan dilakukan saat barang diterima. Pastikan Anda menyiapkan uang pas sebesar <strong><?php echo formatRupiah($total_amount); ?></strong></p>
                    </div>
                </div>
                
                <!-- Catatan Tambahan -->
                <div class="section">
                    <h3>📝 Catatan Tambahan</h3>
                    <div class="form-group">
                        <label for="notes">Catatan untuk kurir (opsional):</label>
                        <textarea name="notes" id="notes" rows="3" placeholder="Contoh: Rumah cat biru, dekat warung Pak Budi..."></textarea>
                    </div>
                </div>
                
                <!-- Tombol Checkout -->
                <div class="section">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                        ← Kembali
                    </button>
                    <button type="submit" class="btn">
                        🛒 Pesan Sekarang
                    </button>
                </div>
            </form>
            
            <?php else: ?>
                <div class="section">
                    <h3>⚠️ Keranjang Kosong</h3>
                    <p>Keranjang belanja Anda kosong. Silakan pilih produk terlebih dahulu.</p>
                    <a href="home.php" class="btn" style="display: inline-block; text-decoration: none; text-align: center; margin-top: 15px;">
                        Kembali ke Beranda
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function selectPayment(method) {
            // Reset semua payment option
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Reset semua payment info
            document.querySelectorAll('.payment-info').forEach(info => {
                info.classList.remove('show');
            });
            
            // Pilih payment method
            const radioElement = document.getElementById(method);
            if (radioElement) {
                radioElement.checked = true;
                radioElement.dispatchEvent(new Event('change'));
            }
            
            // Highlight selected option
            const selectedOption = document.querySelector(`[onclick="selectPayment('${method}')"]`);
            if (selectedOption) {
                selectedOption.classList.add('selected');
            }
            
            // Tampilkan info yang sesuai
            const infoElement = document.getElementById(method + '-info');
            if (infoElement) {
                infoElement.classList.add('show');
            }
            
            console.log('Payment method selected:', method);
        }
        
        // Set default payment method
        document.addEventListener('DOMContentLoaded', function() {
            // Jika tidak ada yang dipilih, pilih COD sebagai default
            if (!document.querySelector('input[name="payment_method"]:checked')) {
                selectPayment('cod');
            }
        });
        
        // Validasi form sebelum submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                alert('Silakan pilih metode pembayaran');
                return false;
            }
            
            // Debug - pastikan payment method terkirim
            console.log('Selected payment method:', paymentMethod.value);
            
            // Konfirmasi sebelum submit
            if (!confirm('Apakah Anda yakin ingin melanjutkan pesanan?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>