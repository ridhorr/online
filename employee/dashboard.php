<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'karyawan') {
    header('Location: ../login.php');
    exit();
}

// Get statistics
$stats = [];

// Total orders
$stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $stmt->fetch()['total'];

// Orders by status
$stmt = $pdo->query("SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status");
$status_counts = [];
while ($row = $stmt->fetch()) {
    $status_counts[$row['order_status']] = $row['count'];
}

$stats['pending'] = $status_counts['pending'] ?? 0;
$stats['processing'] = $status_counts['processing'] ?? 0;
$stats['delivering'] = $status_counts['delivering'] ?? 0;
$stats['completed'] = $status_counts['completed'] ?? 0;
$stats['cancelled'] = $status_counts['cancelled'] ?? 0;

// Calculate orders that haven't been delivered yet (pending + processing)
$stats['not_delivered'] = $stats['pending'] + $stats['processing'];

// Today's orders
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()");
$stats['today_orders'] = $stmt->fetch()['count'];

// Recent orders (last 5)
$stmt = $pdo->query("SELECT o.*, u.name as customer_name, u.phone as customer_phone 
                     FROM orders o 
                     JOIN users u ON o.user_id = u.id 
                     ORDER BY o.created_at DESC 
                     LIMIT 5");
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly revenue
$stmt = $pdo->query("SELECT SUM(total_amount) as revenue 
                     FROM orders 
                     WHERE MONTH(created_at) = MONTH(CURDATE()) 
                     AND YEAR(created_at) = YEAR(CURDATE())
                     AND order_status = 'completed'");
$monthly_revenue = $stmt->fetch()['revenue'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Karyawan - Gallon Delivery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card .icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .stats-card h3 {
            font-size: 2rem;
            margin: 10px 0 5px 0;
        }
        
        .status-card {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }
        
        .status-pending { border-left: 4px solid #ffc107; }
        .status-processing { border-left: 4px solid #17a2b8; }
        .status-delivering { border-left: 4px solid #fd7e14; }
        .status-completed { border-left: 4px solid #28a745; }
        .status-cancelled { border-left: 4px solid #dc3545; }
        .status-not-delivered { border-left: 4px solid #6f42c1; }
        
        .recent-orders-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .order-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s ease;
        }
        
        .order-item:hover {
            background: #f8f9fa;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .status-badge.pending { background: #ffc107; color: #000; }
        .status-badge.processing { background: #17a2b8; color: #fff; }
        .status-badge.delivering { background: #fd7e14; color: #fff; }
        .status-badge.completed { background: #28a745; color: #fff; }
        .status-badge.cancelled { background: #dc3545; color: #fff; }
        
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .quick-action-btn {
            flex: 1;
            min-width: 150px;
            padding: 15px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-orders { background: #28a745; }
        .btn-not-delivered { background: #6f42c1; }
        .btn-completed { background: #17a2b8; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-water"></i> Gallon Delivery - Karyawan
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link active" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link" href="orders.php">
                    <i class="fas fa-box"></i> Pesanan
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="welcome-card">
            <h1><i class="fas fa-user-tie"></i> Selamat Datang, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
            <p class="mb-4">Kelola pesanan dan layani pelanggan dengan baik</p>
            <div class="quick-actions">
                <a href="orders.php" class="quick-action-btn btn-orders">
                    <i class="fas fa-list"></i><br>Lihat Semua Pesanan
                </a>
                <a href="orders.php?filter=not_delivered" class="quick-action-btn btn-not-delivered">
                    <i class="fas fa-clock"></i><br>Belum Diantar
                </a>
                <a href="orders.php?filter=completed" class="quick-action-btn btn-completed">
                    <i class="fas fa-check-circle"></i><br>Pesanan Selesai
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3><?php echo $stats['total_orders']; ?></h3>
                            <p class="mb-0">Total Pesanan</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3><?php echo $stats['today_orders']; ?></h3>
                            <p class="mb-0">Pesanan Hari Ini</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3><?php echo $stats['not_delivered']; ?></h3>
                            <p class="mb-0">Belum Diantar</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <h3>Rp <?php echo number_format($monthly_revenue, 0, ',', '.'); ?></h3>
                            <p class="mb-0">Revenue Bulan Ini</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Order Status Breakdown -->
            <div class="col-md-6">
                <div class="recent-orders-card">
                    <h5 class="mb-3"><i class="fas fa-chart-pie"></i> Status Pesanan</h5>
                    
                    <div class="status-card status-pending">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Menunggu</strong>
                                <small class="text-muted d-block">Pesanan baru masuk</small>
                            </div>
                            <div class="text-end">
                                <h4 class="mb-0"><?php echo $stats['pending']; ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="status-card status-processing">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Diproses</strong>
                                <small class="text-muted d-block">Sedang disiapkan</small>
                            </div>
                            <div class="text-end">
                                <h4 class="mb-0"><?php echo $stats['processing']; ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="status-card status-not-delivered">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Belum Diantar</strong>
                                <small class="text-muted d-block">Menunggu + Diproses</small>
                            </div>
                            <div class="text-end">
                                <h4 class="mb-0"><?php echo $stats['not_delivered']; ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="status-card status-delivering">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Sedang Diantar</strong>
                                <small class="text-muted d-block">Dalam perjalanan</small>
                            </div>
                            <div class="text-end">
                                <h4 class="mb-0"><?php echo $stats['delivering']; ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="status-card status-completed">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Selesai</strong>
                                <small class="text-muted d-block">Pesanan selesai</small>
                            </div>
                            <div class="text-end">
                                <h4 class="mb-0"><?php echo $stats['completed']; ?></h4>
                            </div>
                        </div>
                    </div>
                    
                    <div class="status-card status-cancelled">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Dibatalkan</strong>
                                <small class="text-muted d-block">Pesanan dibatalkan</small>
                            </div>
                            <div class="text-end">
                                <h4 class="mb-0"><?php echo $stats['cancelled']; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="col-md-6">
                <div class="recent-orders-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-clock"></i> Pesanan Terbaru</h5>
                        <a href="orders.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    
                    <?php if (!empty($recent_orders)): ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="order-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>#<?php echo $order['id']; ?></strong>
                                        <div class="text-muted small">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($order['customer_name']); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="mb-1">
                                            <span class="status-badge <?php echo $order['order_status']; ?>">
                                                <?php 
                                                $status_text = [
                                                    'pending' => 'Menunggu',
                                                    'processing' => 'Diproses',
                                                    'delivering' => 'Diantar',
                                                    'completed' => 'Selesai',
                                                    'cancelled' => 'Dibatalkan'
                                                ];
                                                echo $status_text[$order['order_status']];
                                                ?>
                                            </span>
                                        </div>
                                        <div class="text-muted small">
                                            Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Belum ada pesanan</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Tips -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="recent-orders-card">
                    <h5 class="mb-3"><i class="fas fa-lightbulb"></i> Tips untuk Karyawan</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center p-3">
                                <i class="fas fa-clock fa-2x text-primary mb-2"></i>
                                <h6>Respon Cepat</h6>
                                <p class="small text-muted">Segera proses pesanan yang masuk untuk kepuasan pelanggan</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3">
                                <i class="fas fa-map-marked-alt fa-2x text-success mb-2"></i>
                                <h6>Cek Lokasi</h6>
                                <p class="small text-muted">Selalu cek lokasi dan foto rumah sebelum pengiriman</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center p-3">
                                <i class="fas fa-phone fa-2x text-warning mb-2"></i>
                                <h6>Komunikasi</h6>
                                <p class="small text-muted">Hubungi pelanggan jika ada kendala atau pertanyaan</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh page every 5 minutes to get latest data
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>