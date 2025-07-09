<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'pembeli') {
    header('Location: ../index.php');
    exit;
}

// Ambil order_id dari URL
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header('Location: home.php');
    exit;
}

$order_id = (int)$_GET['order_id'];

// Ambil data order beserta item-itemnya
$stmt = $pdo->prepare("
    SELECT o.*, u.name as user_name, u.phone, u.address 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

// Jika order tidak ditemukan atau bukan milik user
if (!$order) {
    header('Location: home.php');
    exit;
}

// Ambil item-item dalam order
$stmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.type, p.brand
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Hitung total item
$total_items = 0;
foreach ($order_items as $item) {
    $total_items += $item['quantity'];
}

// Estimasi waktu pengiriman
$estimated_time = '';
if ($order['distance_km'] <= 3) {
    $estimated_time = '30-45 menit';
} elseif ($order['distance_km'] <= 7) {
    $estimated_time = '45-60 menit';
} else {
    $estimated_time = '60-90 menit';
}

// Status order dalam bahasa Indonesia
$order_status_id = [
    'pending' => 'Menunggu Konfirmasi',
    'processing' => 'Sedang Diproses',
    'delivering' => 'Sedang Dikirim',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan'
];

$payment_status_id = [
    'pending' => 'Menunggu Pembayaran',
    'paid' => 'Sudah Dibayar',
    'failed' => 'Gagal'
];

$payment_method_id = [
    'cod' => 'Bayar di Tempat (COD)',
    'transfer' => 'Transfer Bank'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Berhasil - Delivery Galon</title>
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
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .order-number {
            background: #f0f4ff;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 30px;
            border: 2px solid #667eea;
        }
        
        .order-number h2 {
            color: #667eea;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .order-number p {
            color: #666;
            font-size: 14px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .info-card h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .info-card p {
            color: #666;
            margin: 5px 0;
        }
        
        .status-card {
            background: #e6fffa;
            border: 2px solid #38a169;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .status-card h3 {
            color: #38a169;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-badge {
            background: #38a169;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .payment-info {
            background: #fef5e7;
            border: 2px solid #dd6b20;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .payment-info h3 {
            color: #dd6b20;
            margin-bottom: 15px;
        }
        
        .payment-info.paid {
            background: #e6fffa;
            border-color: #38a169;
        }
        
        .payment-info.paid h3 {
            color: #38a169;
        }
        
        .order-items {
            margin-bottom: 30px;
        }
        
        .order-items h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .item:last-child {
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
        
        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
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
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
        }
        
        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            font-weight: bold;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .btn-success {
            background: #38a169;
            color: white;
        }
        
        .btn-success:hover {
            background: #2f855a;
        }
        
        .contact-info {
            background: #e6fffa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
        }
        
        .contact-info h3 {
            color: #38a169;
            margin-bottom: 15px;
        }
        
        .contact-info p {
            color: #2d3748;
            margin: 5px 0;
        }
        
        .whatsapp-btn {
            background: #25d366;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-weight: bold;
        }
        
        .whatsapp-btn:hover {
            background: #128c7e;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .content {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">✅</div>
            <h1>Pesanan Berhasil!</h1>
            <p>Terima kasih telah memesan di Delivery Galon</p>
        </div>
        
        <div class="content">
            <div class="order-number">
                <h2>Order #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></h2>
                <p>Dibuat pada <?php echo date('d F Y, H:i', strtotime($order['created_at'])); ?></p>
            </div>
            
            <div class="status-card">
                <h3>
                    📦 Status Pesanan
                    <span class="status-badge"><?php echo $order_status_id[$order['order_status']]; ?></span>
                </h3>
                <p><strong>Estimasi Pengiriman:</strong> <?php echo $estimated_time; ?></p>
                <p><strong>Jarak:</strong> <?php echo number_format($order['distance_km'], 2); ?> km dari depot</p>
                <?php if ($order['notes']): ?>
                <p><strong>Catatan:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($order['payment_method'] === 'transfer'): ?>
            <div class="payment-info <?php echo $order['payment_status'] === 'paid' ? 'paid' : ''; ?>">
                <h3>
                    💳 Informasi Pembayaran
                    <span class="status-badge" style="background: <?php echo $order['payment_status'] === 'paid' ? '#38a169' : '#dd6b20'; ?>">
                        <?php echo $payment_status_id[$order['payment_status']]; ?>
                    </span>
                </h3>
                <?php if ($order['payment_status'] === 'pending'): ?>
                <p><strong>Metode:</strong> Transfer Bank</p>
                <p><strong>Bank BCA</strong></p>
                <p><strong>No. Rekening:</strong> 1234567890</p>
                <p><strong>Atas Nama:</strong> Toko Galon Sejahtera</p>
                <p><strong>Jumlah Transfer:</strong> <?php echo formatRupiah($order['total_amount']); ?></p>
                <p style="margin-top: 10px; font-weight: bold; color: #dd6b20;">
                    Silakan transfer dan kirim bukti pembayaran via WhatsApp
                </p>
                <?php else: ?>
                <p style="color: #38a169; font-weight: bold;">✅ Pembayaran telah dikonfirmasi</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="payment-info">
                <h3>💵 Informasi Pembayaran</h3>
                <p><strong>Metode:</strong> Bayar di Tempat (COD)</p>
                <p><strong>Jumlah yang harus dibayar:</strong> <?php echo formatRupiah($order['total_amount']); ?></p>
                <p style="margin-top: 10px; font-weight: bold; color: #dd6b20;">
                    Siapkan uang pas saat kurir tiba
                </p>
            </div>
            <?php endif; ?>
            
            <div class="info-grid">
                <div class="info-card">
                    <h3>📍 Alamat Pengiriman</h3>
                    <p><strong><?php echo htmlspecialchars($order['user_name']); ?></strong></p>
                    <p><?php echo htmlspecialchars($order['phone']); ?></p>
                    <p><?php echo htmlspecialchars($order['address']); ?></p>
                </div>
                
                <div class="info-card">
                    <h3>📊 Ringkasan Pesanan</h3>
                    <p><strong>Total Item:</strong> <?php echo $total_items; ?> pcs</p>
                    <p><strong>Metode Pembayaran:</strong> <?php echo $payment_method_id[$order['payment_method']]; ?></p>
                    <p><strong>Ongkos Kirim:</strong> <?php echo $order['delivery_fee'] == 0 ? 'GRATIS' : formatRupiah($order['delivery_fee']); ?></p>
                </div>
            </div>
            
            <div class="order-items">
                <h3>📋 Detail Pesanan</h3>
                <?php foreach ($order_items as $item): ?>
                <div class="item">
                    <div class="item-info">
                        <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="item-details">
                            <?php echo $item['quantity']; ?> x <?php echo formatRupiah($item['price']); ?>
                            <span style="margin-left: 10px; background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px;">
                                <?php echo ucfirst($item['type']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="item-price"><?php echo formatRupiah($item['price'] * $item['quantity']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="order-summary">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span><?php echo formatRupiah($order['total_amount'] - $order['delivery_fee']); ?></span>
                </div>
                <div class="summary-row">
                    <span>Ongkos Kirim:</span>
                    <span><?php echo $order['delivery_fee'] == 0 ? 'GRATIS' : formatRupiah($order['delivery_fee']); ?></span>
                </div>
                <div class="summary-row">
                    <span>Total Pembayaran:</span>
                    <span><?php echo formatRupiah($order['total_amount']); ?></span>
                </div>
            </div>
            
            <div class="actions">
                <a href="home.php" class="btn btn-primary">🏠 Kembali ke Beranda</a>
                <a href="orders.php" class="btn btn-secondary">📋 Lihat Semua Pesanan</a>
                <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-success">📄 Detail Pesanan</a>
            </div>
            
            <div class="contact-info">
                <h3>📞 Butuh Bantuan?</h3>
                <p>Hubungi kami jika ada pertanyaan tentang pesanan Anda</p>
                <p><strong>WhatsApp:</strong> 0812-3456-7890</p>
                <a href="https://wa.me/6281234567890?text=Halo, saya butuh bantuan untuk pesanan #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>" class="whatsapp-btn" target="_blank">
                    📱 Chat WhatsApp
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto refresh halaman setiap 30 detik untuk update status
        setTimeout(function() {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 30000);
        
        // Tampilkan notifikasi browser jika didukung
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Pesanan Berhasil!', {
                body: 'Pesanan #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?> telah berhasil dibuat.',
                icon: '🏺'
            });
        }
        
        // Smooth scroll untuk navigasi
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>