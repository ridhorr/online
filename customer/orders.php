<?php
require_once '../config/database.php';

// Cek login
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'pembeli') {
    header('Location: ../index.php');
    exit;
}

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Ambil riwayat pesanan
$stmt = $pdo->prepare("
    SELECT o.*, 
           GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, 'x)') SEPARATOR ', ') as items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// Fungsi untuk status badge
function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="status pending">Menunggu</span>';
        case 'processing':
            return '<span class="status processing">Diproses</span>';
        case 'delivering':
            return '<span class="status delivering">Dikirim</span>';
        case 'completed':
            return '<span class="status completed">Selesai</span>';
        case 'cancelled':
            return '<span class="status cancelled">Dibatalkan</span>';
        default:
            return '<span class="status unknown">Unknown</span>';
    }
}

function getPaymentBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="payment-status pending">Menunggu</span>';
        case 'paid':
            return '<span class="payment-status paid">Lunas</span>';
        case 'failed':
            return '<span class="payment-status failed">Gagal</span>';
        default:
            return '<span class="payment-status unknown">Unknown</span>';
    }
}

// Auto refresh setiap 30 detik
$refresh_interval = 30;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Delivery Galon</title>
    <meta http-equiv="refresh" content="<?php echo $refresh_interval; ?>">
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
            padding-bottom: 80px;
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
        
        .refresh-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
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
        
        .refresh-info {
            background: #e6fffa;
            color: #319795;
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
            font-size: 12px;
        }
        
        .order-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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
        
        .orders-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .orders-header {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: center;
        }
        
        .orders-header h2 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .orders-count {
            background: rgba(255,255,255,0.2);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .order-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            position: relative;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item.user-order {
            background: #f0f4ff;
            border-left: 4px solid #667eea;
        }
        
        .order-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .order-left {
            flex: 1;
        }
        
        .order-id {
            color: #667eea;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        
        .order-time {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .order-status-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: flex-end;
        }
        
        .order-items {
            margin-bottom: 10px;
        }
        
        .order-items h4 {
            font-size: 12px;
            margin-bottom: 5px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .order-items p {
            color: #666;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .detail-item {
            font-size: 12px;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            color: #333;
            font-weight: bold;
        }
        
        .order-total {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 12px;
        }
        
        .total-final {
            font-weight: bold;
            color: #667eea;
            font-size: 14px;
            border-top: 1px solid #eee;
            padding-top: 5px;
            margin-top: 5px;
        }
        
        .order-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn-outline {
            background: transparent;
            color: #667eea;
            border: 1px solid #667eea;
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        /* Status badges */
        .status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status.pending { 
            background: #fed7d7; 
            color: #c53030; 
        }
        
        .status.processing { 
            background: #fef5e7; 
            color: #dd6b20; 
        }
        
        .status.delivering { 
            background: #e6fffa; 
            color: #319795; 
        }
        
        .status.completed { 
            background: #c6f6d5; 
            color: #38a169; 
        }
        
        .status.cancelled { 
            background: #fed7d7; 
            color: #e53e3e; 
        }
        
        .status.unknown { 
            background: #e2e8f0; 
            color: #4a5568; 
        }
        
        .payment-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .payment-status.pending { 
            background: #fef5e7; 
            color: #dd6b20; 
        }
        
        .payment-status.paid { 
            background: #c6f6d5; 
            color: #38a169; 
        }
        
        .payment-status.failed { 
            background: #fed7d7; 
            color: #e53e3e; 
        }
        
        .payment-status.unknown { 
            background: #e2e8f0; 
            color: #4a5568; 
        }
        
        .notes {
            background: #fef5e7;
            padding: 8px 10px;
            border-radius: 6px;
            border-left: 3px solid #dd6b20;
            margin-top: 8px;
            font-size: 12px;
        }
        
        .notes strong {
            color: #dd6b20;
        }
        
        .empty-state {
            text-align: center;
            color: #666;
            padding: 40px 20px;
        }
        
        .empty-state-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            font-size: 14px;
            margin-bottom: 20px;
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
        
        /* Loading indicator */
        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            z-index: 1000;
        }
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            
            .order-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .order-item {
                padding: 12px;
            }
            
            .logo {
                font-size: 16px;
            }
            
            .order-details {
                grid-template-columns: 1fr;
            }
            
            .order-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏺 Delivery Galon</div>
            <div class="user-info">
                <button class="refresh-btn" onclick="refreshOrders()">
                    <span>🔄</span>
                    <span>Refresh</span>
                </button>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h1>📋 Riwayat Pesanan</h1>
            <p>Lihat semua pesanan Anda dan status pengirimannya</p>
            <div class="refresh-info">
                🔄 Refresh otomatis setiap <?php echo $refresh_interval; ?> detik
            </div>
        </div>
        
        <?php
        $total_orders = count($orders);
        $pending_count = 0;
        $completed_count = 0;
        $cancelled_count = 0;
        $total_spent = 0;
        
        foreach ($orders as $order) {
            $total_spent += $order['total_amount'];
            switch ($order['order_status']) {
                case 'pending':
                case 'processing':
                case 'delivering':
                    $pending_count++;
                    break;
                case 'completed':
                    $completed_count++;
                    break;
                case 'cancelled':
                    $cancelled_count++;
                    break;
            }
        }
        ?>
        
        <div class="order-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_orders; ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">Sedang Proses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completed_count; ?></div>
                <div class="stat-label">Selesai</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo formatRupiah($total_spent); ?></div>
                <div class="stat-label">Total Belanja</div>
            </div>
        </div>
        
        <div class="orders-container">
            <div class="orders-header">
                <h2>Daftar Pesanan</h2>
                <span class="orders-count"><?php echo $total_orders; ?> pesanan</span>
            </div>
            
            <?php if ($orders): ?>
                <?php foreach ($orders as $order): ?>
                <div class="order-item user-order">
                    <div class="order-top">
                        <div class="order-left">
                            <div class="order-id">
                                Pesanan #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>
                            </div>
                            <div class="order-time">
                                📅 <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                        <div class="order-status-group">
                            <?php echo getStatusBadge($order['order_status']); ?>
                            <?php echo getPaymentBadge($order['payment_status']); ?>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        <h4>📋 Item Pesanan</h4>
                        <p><?php echo htmlspecialchars($order['items']); ?></p>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-item">
                            <div class="detail-label">Jarak:</div>
                            <div class="detail-value"><?php echo number_format($order['distance_km'], 2); ?> km</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Metode Bayar:</div>
                            <div class="detail-value"><?php echo ucfirst($order['payment_method']); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($order['notes']): ?>
                        <div class="notes">
                            <strong>Catatan:</strong> <?php echo htmlspecialchars($order['notes']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="order-total">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span><?php echo formatRupiah($order['total_amount'] - $order['delivery_fee']); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Ongkos kirim:</span>
                            <span><?php echo $order['delivery_fee'] == 0 ? 'GRATIS' : formatRupiah($order['delivery_fee']); ?></span>
                        </div>
                        <div class="total-row total-final">
                            <span>Total:</span>
                            <span><?php echo formatRupiah($order['total_amount']); ?></span>
                        </div>
                    </div>
                    
                    <div class="order-actions">
                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn-outline">
                            <span>📋</span>
                            <span>Detail</span>
                        </a>
                        
                        <?php if ($order['order_status'] === 'completed'): ?>
                            <a href="reorder.php?order_id=<?php echo $order['id']; ?>" class="btn-outline">
                                <span>🔄</span>
                                <span>Pesan Lagi</span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'pending'): ?>
                            <a href="https://wa.me/6281234567890?text=Halo, saya ingin mengirim bukti pembayaran untuk pesanan %23<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>" class="btn-outline" target="_blank">
                                <span>📱</span>
                                <span>Kirim Bukti</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h3>Belum Ada Pesanan</h3>
                    <p>Anda belum pernah melakukan pesanan. Mulai pesan sekarang!</p>
                    <a href="home.php" class="btn">
                        <span>🛒</span>
                        Mulai Pesan
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="nav-links">
            <a href="home.php">
                <span class="nav-icon">🏠</span>
                Home
            </a>
            <a href="orders.php" class="active">
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
    
    <!-- Loading Indicator -->
    <div class="loading" id="loading">
        🔄 Memuat ulang...
    </div>
    
    <script>
        // Countdown untuk refresh
        let countdown = <?php echo $refresh_interval; ?>;
        
        function updateCountdown() {
            const refreshInfo = document.querySelector('.refresh-info');
            refreshInfo.innerHTML = `🔄 Refresh otomatis dalam ${countdown} detik`;
            
            if (countdown > 0) {
                countdown--;
                setTimeout(updateCountdown, 1000);
            } else {
                refreshInfo.innerHTML = '🔄 Memuat ulang...';
                document.getElementById('loading').style.display = 'block';
            }
        }
        
        // Mulai countdown
        setTimeout(updateCountdown, 1000);
        
        // Refresh function
        function refreshOrders() {
            document.getElementById('loading').style.display = 'block';
            window.location.reload();
        }
        
        // Touch gestures untuk refresh
        let startY = 0;
        let currentY = 0;
        let isPulling = false;
        
        document.addEventListener('touchstart', function(e) {
            startY = e.touches[0].pageY;
            isPulling = window.pageYOffset === 0;
        });
        
        document.addEventListener('touchmove', function(e) {
            if (!isPulling) return;
            
            currentY = e.touches[0].pageY;
            const diff = currentY - startY;
            
            if (diff > 50) {
                e.preventDefault();
                document.body.style.transform = `translateY(${Math.min(diff / 3, 30)}px)`;
            }
        });
        
        document.addEventListener('touchend', function(e) {
            if (!isPulling) return;
            
            const diff = currentY - startY;
            document.body.style.transform = '';
            
            if (diff > 80) {
                refreshOrders();
            }
            
            isPulling = false;
        });
        
        // Add loading animation to buttons
        document.querySelectorAll('.btn, .btn-outline').forEach(button => {
            button.addEventListener('click', function() {
                if (!this.href || this.href.includes('javascript:')) return;
                
                const originalText = this.innerHTML;
                this.innerHTML = '<span>⏳</span> Memuat...';
                this.style.pointerEvents = 'none';
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.pointerEvents = 'auto';
                }, 3000);
            });
        });
        
        // Service Worker untuk offline support
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(console.error);
        }
        
        // Prevent zoom on double tap
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    </script>
</body>
</html>