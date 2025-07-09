<?php
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'pembeli') {
    header('Location: ../index.php');
    exit;
}

// Ambil order_id dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = (int)$_GET['id'];

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
    header('Location: orders.php');
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

// Estimasi waktu pengiriman berdasarkan jarak
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

// Fungsi untuk menentukan warna status
function getStatusColor($status) {
    switch ($status) {
        case 'pending': return '#fbbf24';
        case 'processing': return '#f59e0b';
        case 'delivering': return '#06b6d4';
        case 'completed': return '#10b981';
        case 'cancelled': return '#ef4444';
        default: return '#6b7280';
    }
}

function getPaymentStatusColor($status) {
    switch ($status) {
        case 'pending': return '#f59e0b';
        case 'paid': return '#10b981';
        case 'failed': return '#ef4444';
        default: return '#6b7280';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?> - Delivery Galon</title>
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
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .back-btn {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .content {
            padding: 30px;
        }
        
        .order-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .status-info {
            flex: 1;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: bold;
            color: white;
            margin-bottom: 10px;
        }
        
        .order-timeline {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            position: relative;
        }
        
        .timeline-item:last-child {
            margin-bottom: 0;
        }
        
        .timeline-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .timeline-dot.active {
            background: #10b981;
        }
        
        .timeline-dot.inactive {
            background: #d1d5db;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-title {
            font-weight: bold;
            color: #333;
            margin-bottom: 2px;
        }
        
        .timeline-time {
            font-size: 12px;
            color: #666;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card p {
            color: #666;
            margin: 8px 0;
        }
        
        .info-card strong {
            color: #333;
        }
        
        .payment-info {
            background: #fef5e7;
            border: 2px solid #f59e0b;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .payment-info.paid {
            background: #ecfdf5;
            border-color: #10b981;
        }
        
        .payment-info.failed {
            background: #fef2f2;
            border-color: #ef4444;
        }
        
        .payment-info h3 {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .payment-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        
        .bank-details {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .bank-details p {
            margin: 8px 0;
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
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .items-table th,
        .items-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .items-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .product-type {
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
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
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
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
            display: inline-block;
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
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .contact-info {
            background: #ecfdf5;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
        }
        
        .contact-info h3 {
            color: #059669;
            margin-bottom: 15px;
        }
        
        .whatsapp-btn {
            background: #25d366;
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .whatsapp-btn:hover {
            background: #128c7e;
        }
        
        .refresh-notice {
            background: #dbeafe;
            color: #1e40af;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .content {
                padding: 20px;
            }
            
            .order-info {
                grid-template-columns: 1fr;
            }
            
            .actions {
                grid-template-columns: 1fr;
            }
            
            .order-status {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .items-table {
                font-size: 14px;
            }
            
            .items-table th,
            .items-table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="orders.php" class="back-btn">← Kembali</a>
            <h1>Detail Pesanan</h1>
            <p>Order #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></p>
        </div>
        
        <div class="content">
            <?php if ($order['order_status'] !== 'completed' && $order['order_status'] !== 'cancelled'): ?>
            <div class="refresh-notice">
                ℹ️ Halaman ini akan memperbarui status secara otomatis setiap 30 detik
            </div>
            <?php endif; ?>
            
            <div class="order-status">
                <div class="status-info">
                    <div class="status-badge" style="background: <?php echo getStatusColor($order['order_status']); ?>;">
                        <?php echo $order_status_id[$order['order_status']]; ?>
                    </div>
                    <p><strong>Dibuat:</strong> <?php echo date('d F Y, H:i', strtotime($order['created_at'])); ?></p>
                    <p><strong>Estimasi Pengiriman:</strong> <?php echo $estimated_time; ?></p>
                </div>
            </div>
            
            <div class="order-timeline">
                <h3 style="margin-bottom: 20px;">📋 Timeline Pesanan</h3>
                <div class="timeline-item">
                    <div class="timeline-dot active"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Pesanan Dibuat</div>
                        <div class="timeline-time"><?php echo date('d F Y, H:i', strtotime($order['created_at'])); ?></div>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot <?php echo in_array($order['order_status'], ['processing', 'delivering', 'completed']) ? 'active' : 'inactive'; ?>"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Sedang Diproses</div>
                        <div class="timeline-time"><?php echo in_array($order['order_status'], ['processing', 'delivering', 'completed']) ? 'Sedang berlangsung' : 'Menunggu'; ?></div>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot <?php echo in_array($order['order_status'], ['delivering', 'completed']) ? 'active' : 'inactive'; ?>"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Sedang Dikirim</div>
                        <div class="timeline-time"><?php echo in_array($order['order_status'], ['delivering', 'completed']) ? 'Dalam perjalanan' : 'Menunggu'; ?></div>
                    </div>
                </div>
                
                <div class="timeline-item">
                    <div class="timeline-dot <?php echo $order['order_status'] === 'completed' ? 'active' : 'inactive'; ?>"></div>
                    <div class="timeline-content">
                        <div class="timeline-title">Pesanan Selesai</div>
                        <div class="timeline-time"><?php echo $order['order_status'] === 'completed' ? 'Pesanan telah diterima' : 'Menunggu'; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="payment-info <?php echo $order['payment_status']; ?>">
                <h3>
                    💳 Informasi Pembayaran
                    <span class="payment-badge" style="background: <?php echo getPaymentStatusColor($order['payment_status']); ?>;">
                        <?php echo $payment_status_id[$order['payment_status']]; ?>
                    </span>
                </h3>
                
                <p><strong>Metode Pembayaran:</strong> <?php echo $payment_method_id[$order['payment_method']]; ?></p>
                <p><strong>Total yang Harus Dibayar:</strong> <?php echo formatRupiah($order['total_amount']); ?></p>
                
                <?php if ($order['payment_method'] === 'transfer'): ?>
                    <?php if ($order['payment_status'] === 'pending'): ?>
                    <div class="bank-details">
                        <p><strong>Bank:</strong> BCA</p>
                        <p><strong>No. Rekening:</strong> 1234567890</p>
                        <p><strong>Atas Nama:</strong> Toko Galon Sejahtera</p>
                        <p style="margin-top: 10px; color: #f59e0b; font-weight: bold;">
                            Silakan transfer dan kirim bukti pembayaran via WhatsApp
                        </p>
                    </div>
                    <?php elseif ($order['payment_status'] === 'paid'): ?>
                    <p style="color: #10b981; font-weight: bold; margin-top: 10px;">
                        ✅ Pembayaran telah dikonfirmasi
                    </p>
                    <?php elseif ($order['payment_status'] === 'failed'): ?>
                    <p style="color: #ef4444; font-weight: bold; margin-top: 10px;">
                        ❌ Pembayaran gagal - Silakan hubungi customer service
                    </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="margin-top: 10px; color: #f59e0b; font-weight: bold;">
                        Siapkan uang pas saat kurir tiba
                    </p>
                <?php endif; ?>
            </div>
            
            <div class="order-info">
                <div class="info-card">
                    <h3>📍 Alamat Pengiriman</h3>
                    <p><strong><?php echo htmlspecialchars($order['user_name']); ?></strong></p>
                    <p><strong>Telepon:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
                    <p><strong>Alamat:</strong> <?php echo htmlspecialchars($order['address']); ?></p>
                    <p><strong>Jarak:</strong> <?php echo number_format($order['distance_km'], 2); ?> km dari depot</p>
                </div>
                
                <div class="info-card">
                    <h3>📊 Ringkasan Pesanan</h3>
                    <p><strong>Total Item:</strong> <?php echo $total_items; ?> pcs</p>
                    <p><strong>Subtotal:</strong> <?php echo formatRupiah($order['total_amount'] - $order['delivery_fee']); ?></p>
                    <p><strong>Ongkos Kirim:</strong> <?php echo $order['delivery_fee'] == 0 ? 'GRATIS' : formatRupiah($order['delivery_fee']); ?></p>
                    <?php if ($order['notes']): ?>
                    <p><strong>Catatan:</strong> <?php echo htmlspecialchars($order['notes']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="order-items">
                <h3>📦 Detail Produk</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Jenis</th>
                            <th>Harga</th>
                            <th>Jumlah</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                <?php if ($item['brand']): ?>
                                <br><small><?php echo htmlspecialchars($item['brand']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="product-type"><?php echo ucfirst($item['type']); ?></span>
                            </td>
                            <td><?php echo formatRupiah($item['price']); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><strong><?php echo formatRupiah($item['price'] * $item['quantity']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="order-summary">
                <h3 style="margin-bottom: 15px;">💰 Ringkasan Pembayaran</h3>
                <div class="summary-row">
                    <span>Subtotal Produk:</span>
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
                <a href="orders.php" class="btn btn-secondary">📋 Semua Pesanan</a>
                
                <?php if ($order['order_status'] === 'pending'): ?>
                <button class="btn btn-danger" onclick="cancelOrder()">❌ Batalkan Pesanan</button>
                <?php endif; ?>
                
                <?php if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'pending'): ?>
                <a href="https://wa.me/6281234567890?text=Halo, saya ingin mengirim bukti pembayaran untuk pesanan %23<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>" class="btn btn-success" target="_blank">
                    📱 Kirim Bukti Bayar
                </a>
                <?php endif; ?>
                
                <?php if ($order['order_status'] === 'completed'): ?>
                <a href="reorder.php?order_id=<?php echo $order['id']; ?>" class="btn btn-warning">🔄 Pesan Lagi</a>
                <?php endif; ?>
            </div>
            
            <div class="contact-info">
                <h3>📞 Butuh Bantuan?</h3>
                <p>Hubungi kami jika ada pertanyaan tentang pesanan Anda</p>
                <p><strong>Customer Service:</strong> 0812-3456-7890</p>
                <p><strong>Jam Operasional:</strong> 08:00 - 20:00 WIB</p>
                <a href="https://wa.me/6281234567890?text=Halo, saya butuh bantuan untuk pesanan %23<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>" class="whatsapp-btn" target="_blank">
                    📱 Chat WhatsApp
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto refresh halaman setiap 30 detik untuk update status (kecuali jika sudah completed atau cancelled)
        <?php if ($order['order_status'] !== 'completed' && $order['order_status'] !== 'cancelled'): ?>
        setTimeout(function() {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 30000);
        <?php endif; ?>
        
        // Fungsi untuk membatalkan pesanan
        function cancelOrder() {
            if (confirm('Apakah Anda yakin ingin membatalkan pesanan ini?')) {
                // Kirim request untuk membatalkan pesanan
                fetch('cancel_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: <?php echo $order['id']; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pesanan berhasil dibatalkan');
                        window.location.reload();
                    } else {
                        alert('Gagal membatalkan pesanan: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat membatalkan pesanan');
                });
            }
        }
        
        // Tampilkan notifikasi jika status berubah
        <?php if (isset($_GET['status_updated'])): ?>
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Status Pesanan Diperbarui', {
                body: 'Pesanan #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?> - <?php echo $order_status_id[$order['order_status']]; ?>',
                icon: '📦'
            });
        }
        <?php endif; ?>
        
        // Request permission untuk notifikasi
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // Smooth scroll untuk navigasi
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>