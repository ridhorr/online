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

// Ambil semua pesanan yang sedang dalam antrian (belum completed/cancelled)
$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name, u.phone as customer_phone, u.address as customer_address
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.order_status IN ('pending', 'processing', 'delivering') 
    ORDER BY o.created_at ASC
");
$stmt->execute();
$order_queue = $stmt->fetchAll();

// Auto refresh setiap 30 detik
$refresh_interval = 30;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian Pesanan - Delivery Galon</title>
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
        }
        
        .header {
            background: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .refresh-info {
            background: #e6fffa;
            color: #319795;
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-block;
            font-size: 14px;
            margin-top: 15px;
        }
        
        .order-queue {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f4ff;
        }
        
        .queue-count {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: bold;
        }
        
        .queue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            position: relative;
            transition: background 0.3s ease;
        }
        
        .queue-item:hover {
            background: #f8f9fa;
        }
        
        .queue-item:last-child {
            border-bottom: none;
        }
        
        .queue-number {
            background: #667eea;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .queue-number.current {
            background: #38a169;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .queue-info {
            flex: 1;
            display: flex;
            align-items: center;
        }
        
        .queue-details {
            flex: 1;
        }
        
        .order-id {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .order-time {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .your-order {
            background: #e6fffa;
            color: #319795;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .order-amount {
            text-align: right;
            margin-right: 15px;
            min-width: 120px;
        }
        
        .amount-text {
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
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
        
        .empty-queue {
            text-align: center;
            color: #666;
            padding: 60px 20px;
        }
        
        .empty-queue-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-links a {
            color: #667eea;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: #f0f4ff;
        }
        
        .nav-links a.active {
            background: #667eea;
            color: white;
        }
        
        .logout-btn {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .queue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .estimated-time {
            background: #f0f4ff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .queue-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .queue-info {
                width: 100%;
            }
            
            .order-amount {
                margin-right: 0;
                text-align: left;
                min-width: auto;
            }
            
            .nav-links {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🏺 Delivery Galon</div>
        <div class="user-info">
            <div class="nav-links">
                <a href="home.php">Home</a>
                <a href="orders.php">Pesanan Saya</a>
                <a href="queue.php" class="active">Antrian Pesanan</a>
                <a href="profile.php">Profil</a>
            </div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h1>🚚 Antrian Pesanan</h1>
            <p>Pantau posisi antrian pesanan secara real-time</p>
            <div class="refresh-info">
                🔄 Halaman akan refresh otomatis setiap <?php echo $refresh_interval; ?> detik
            </div>
        </div>
        
        <?php
        $pending_count = 0;
        $processing_count = 0;
        $delivering_count = 0;
        
        foreach ($order_queue as $order) {
            switch ($order['order_status']) {
                case 'pending':
                    $pending_count++;
                    break;
                case 'processing':
                    $processing_count++;
                    break;
                case 'delivering':
                    $delivering_count++;
                    break;
            }
        }
        ?>
        
        <div class="queue-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">Menunggu</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $processing_count; ?></div>
                <div class="stat-label">Diproses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $delivering_count; ?></div>
                <div class="stat-label">Dikirim</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($order_queue); ?></div>
                <div class="stat-label">Total Antrian</div>
            </div>
        </div>
        
        <div class="order-queue">
            <div class="queue-header">
                <h2>Daftar Antrian Pesanan</h2>
                <span class="queue-count"><?php echo count($order_queue); ?> pesanan</span>
            </div>
            
            <?php if ($order_queue): ?>
                <?php foreach ($order_queue as $index => $order): ?>
                <div class="queue-item">
                    <div class="queue-info">
                        <div class="queue-number <?php echo $index < 3 ? 'current' : ''; ?>">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="queue-details">
                            <div class="order-id">
                                Pesanan #<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?>
                                <?php if ($order['user_id'] == $_SESSION['user_id']): ?>
                                    <span class="your-order">Pesanan Anda</span>
                                <?php endif; ?>
                            </div>
                            <div class="order-time">
                                📅 <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                <?php
                                $time_diff = time() - strtotime($order['created_at']);
                                $hours = floor($time_diff / 3600);
                                $minutes = floor(($time_diff % 3600) / 60);
                                echo " ({$hours}j {$minutes}m yang lalu)";
                                ?>
                            </div>
                            
                            <?php if ($order['order_status'] == 'pending'): ?>
                                <div class="estimated-time">
                                    ⏱️ Estimasi mulai diproses: <?php echo ($index * 15); ?> menit lagi
                                </div>
                            <?php elseif ($order['order_status'] == 'processing'): ?>
                                <div class="estimated-time">
                                    🔄 Sedang diproses - estimasi selesai: 30 menit
                                </div>
                            <?php elseif ($order['order_status'] == 'delivering'): ?>
                                <div class="estimated-time">
                                    🚚 Dalam perjalanan - estimasi tiba: 45 menit
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="order-amount">
                        <div class="amount-text"><?php echo formatRupiah($order['total_amount'] + $order['delivery_fee']); ?></div>
                        <div class="status <?php echo $order['order_status']; ?>">
                            <?php
                            switch ($order['order_status']) {
                                case 'pending':
                                    echo 'Menunggu';
                                    break;
                                case 'processing':
                                    echo 'Diproses';
                                    break;
                                case 'delivering':
                                    echo 'Dikirim';
                                    break;
                                default:
                                    echo ucfirst($order['order_status']);
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-queue">
                    <div class="empty-queue-icon">📝</div>
                    <h3>Tidak ada pesanan dalam antrian</h3>
                    <p>Semua pesanan telah selesai diproses</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Countdown untuk refresh
        let countdown = <?php echo $refresh_interval; ?>;
        
        function updateCountdown() {
            const refreshInfo = document.querySelector('.refresh-info');
            refreshInfo.innerHTML = `🔄 Halaman akan refresh otomatis dalam ${countdown} detik`;
            
            if (countdown > 0) {
                countdown--;
                setTimeout(updateCountdown, 1000);
            } else {
                refreshInfo.innerHTML = '🔄 Memuat ulang halaman...';
            }
        }
        
        // Mulai countdown
        setTimeout(updateCountdown, 1000);
        
        // Highlight user's own order if exists
        const userOrders = document.querySelectorAll('.queue-item');
        userOrders.forEach(item => {
            const yourOrderBadge = item.querySelector('.your-order');
            if (yourOrderBadge) {
                item.style.background = '#f0f4ff';
                item.style.border = '2px solid #667eea';
                item.style.borderRadius = '10px';
            }
        });
    </script>
</body>
</html>