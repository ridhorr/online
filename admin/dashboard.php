<?php
// Don't start session here - let database.php handle it
require_once '../config/database.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_product':
            $name = trim($_POST['name']);
            $type = $_POST['type'];
            $brand = trim($_POST['brand']);
            $price = (int)$_POST['price'];
            $is_available = isset($_POST['is_available']) ? 1 : 0;
            
            $stmt = $pdo->prepare("INSERT INTO products (name, type, brand, price, is_available) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $type, $brand, $price, $is_available]);
            break;
            
        case 'update_product':
            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $type = $_POST['type'];
            $brand = trim($_POST['brand']);
            $price = (int)$_POST['price'];
            $is_available = isset($_POST['is_available']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE products SET name = ?, type = ?, brand = ?, price = ?, is_available = ? WHERE id = ?");
            $stmt->execute([$name, $type, $brand, $price, $is_available, $id]);
            break;
            
        case 'delete_product':
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            break;
            
        case 'update_order_status':
            $id = (int)$_POST['id'];
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            break;
            
        case 'delete_order':
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$id]);
            break;
    }
    
    header('Location: dashboard.php');
    exit;
}

// Get all products
$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();

// Get all orders with user details
$orders = $pdo->query("
    SELECT o.*, u.name as user_name, u.phone as user_phone, u.address as user_address
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
")->fetchAll();

// Get order statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        SUM(CASE WHEN order_status = 'completed' THEN total_amount ELSE 0 END) as total_revenue
    FROM orders
")->fetch();

// Get product for editing if requested
$edit_product = null;
if (isset($_GET['edit_product'])) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['edit_product']]);
    $edit_product = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Delivery Galon</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            line-height: 1.5;
            color: #333;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }
        
        .container {
            padding: 1rem;
            max-width: 100%;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .stat-card h3 {
            color: #6c757d;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .stat-card .label {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            border: 1px solid #e9ecef;
        }
        
        .tab {
            flex: 1;
            padding: 1rem;
            background: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
            color: #6c757d;
        }
        
        .tab.active {
            background: #3498db;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:active {
            transform: translateY(1px);
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
        
        .btn-full {
            width: 100%;
            margin-bottom: 1rem;
        }
        
        /* Mobile-optimized table */
        .mobile-table {
            display: block;
        }
        
        .mobile-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .mobile-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .mobile-card-id {
            font-weight: 600;
            color: #3498db;
        }
        
        .mobile-card-content {
            margin-bottom: 1rem;
        }
        
        .mobile-card-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .mobile-card-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .mobile-card-value {
            color: #2c3e50;
        }
        
        .mobile-card-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-processing {
            background: #ffeaa7;
            color: #b8860b;
        }
        
        .status-delivering {
            background: #cce5ff;
            color: #0066cc;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }
        
        .close:hover {
            color: #000;
        }
        
        .form-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .form-actions .btn {
            flex: 1;
        }
        
        .quick-status {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .quick-status select {
            flex: 1;
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            font-size: 0.85rem;
        }
        
        .revenue-highlight {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .revenue-highlight .number {
            color: white;
        }
        
        .revenue-highlight h3 {
            color: rgba(255,255,255,0.9);
        }
        
        /* Responsive adjustments */
        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .container {
                padding: 2rem;
                max-width: 1200px;
                margin: 0 auto;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .user-info {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 480px) {
            .header {
                padding: 0.75rem;
            }
            
            .header h1 {
                font-size: 1rem;
            }
            
            .user-info span {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 1rem;
            }
            
            .mobile-card-actions {
                flex-direction: column;
            }
            
            .mobile-card-actions .btn {
                width: 100%;
            }
            .tabs {
        flex-direction: column;
    }
    
    .tab {
        flex: none;
        padding: 0.75rem;
    }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🏺 Admin Dashboard</h1>
            <div class="user-info">
                <span>Halo, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
            <!-- Users Tab -->
        <div id="users" class="tab-content">
            <div class="section">
                <h2>Kelola Users</h2>
                <div style="text-align: center; padding: 2rem;">
                    <p style="margin-bottom: 1rem; color: #6c757d;">Kelola data pengguna aplikasi</p>
                    <a href="delete_users.php" class="btn btn-danger">
                        🗑️ Hapus Users
                    </a>
                </div>
            </div>
        </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Pesanan</h3>
                <div class="number"><?php echo $stats['total_orders']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="number"><?php echo $stats['pending_orders']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Selesai</h3>
                <div class="number"><?php echo $stats['completed_orders']; ?></div>
            </div>
            <div class="stat-card revenue-highlight">
                <h3>Pendapatan</h3>
                <div class="number">Rp <?php echo number_format($stats['total_revenue']); ?></div>
            </div>
        </div>
        
        <!-- Tabs -->
<div class="tabs">
    <button class="tab active" onclick="showTab('orders')">📋 Pesanan</button>
    <button class="tab" onclick="showTab('products')">📦 Produk</button>
    <a href="delete_users.php" class="tab" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">👥 Users</a>
</div>
        
        <!-- Orders Tab -->
        <div id="orders" class="tab-content active">
            <div class="section">
                <h2>Kelola Pesanan</h2>
                <div class="mobile-table">
                    <?php foreach ($orders as $order): ?>
                    <div class="mobile-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-id">#<?php echo $order['id']; ?></div>
                            <div class="status-badge status-<?php echo $order['order_status']; ?>">
                                <?php echo ucfirst($order['order_status']); ?>
                            </div>
                        </div>
                        <div class="mobile-card-content">
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Pelanggan:</span>
                                <span class="mobile-card-value"><?php echo htmlspecialchars($order['user_name']); ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Telepon:</span>
                                <span class="mobile-card-value"><?php echo htmlspecialchars($order['user_phone']); ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Total:</span>
                                <span class="mobile-card-value"><strong>Rp <?php echo number_format($order['total_amount']); ?></strong></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Tanggal:</span>
                                <span class="mobile-card-value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                            </div>
                        </div>
                        <div class="mobile-card-actions">
                            <button class="btn btn-primary btn-small" onclick="showOrderDetails(<?php echo $order['id']; ?>)">
                                Detail
                            </button>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                <div class="quick-status">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="pending" <?php echo $order['order_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $order['order_status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="delivering" <?php echo $order['order_status'] == 'delivering' ? 'selected' : ''; ?>>Delivering</option>
                                        <option value="completed" <?php echo $order['order_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $order['order_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </form>
                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus pesanan ini?')">
                                <input type="hidden" name="action" value="delete_order">
                                <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-small">Hapus</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Products Tab -->
        <div id="products" class="tab-content">
            <div class="section">
                <h2>Kelola Produk</h2>
                
                <!-- Add/Edit Product Form -->
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $edit_product ? 'update_product' : 'add_product'; ?>">
                    <?php if ($edit_product): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_product['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Nama Produk</label>
                        <input type="text" id="name" name="name" value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Tipe</label>
                        <select id="type" name="type" required>
                            <option value="isi_ulang" <?php echo $edit_product && $edit_product['type'] == 'isi_ulang' ? 'selected' : ''; ?>>Isi Ulang</option>
                            <option value="original" <?php echo $edit_product && $edit_product['type'] == 'original' ? 'selected' : ''; ?>>Original</option>
                            <option value="gas" <?php echo $edit_product && $edit_product['type'] == 'gas' ? 'selected' : ''; ?>>Gas</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="brand">Merek</label>
                        <input type="text" id="brand" name="brand" value="<?php echo $edit_product ? htmlspecialchars($edit_product['brand']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Harga (Rp)</label>
                        <input type="number" id="price" name="price" value="<?php echo $edit_product ? $edit_product['price'] : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_available" name="is_available" <?php echo $edit_product && $edit_product['is_available'] ? 'checked' : (!$edit_product ? 'checked' : ''); ?>>
                            <label for="is_available">Tersedia</label>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_product ? 'Update' : 'Tambah'; ?>
                        </button>
                        <?php if ($edit_product): ?>
                            <a href="dashboard.php" class="btn btn-warning">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Products List -->
                <div class="mobile-table" style="margin-top: 2rem;">
                    <?php foreach ($products as $product): ?>
                    <div class="mobile-card">
                        <div class="mobile-card-header">
                            <div class="mobile-card-id">#<?php echo $product['id']; ?></div>
                            <div class="status-badge <?php echo $product['is_available'] ? 'status-completed' : 'status-cancelled'; ?>">
                                <?php echo $product['is_available'] ? 'Tersedia' : 'Habis'; ?>
                            </div>
                        </div>
                        <div class="mobile-card-content">
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Nama:</span>
                                <span class="mobile-card-value"><strong><?php echo htmlspecialchars($product['name']); ?></strong></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Tipe:</span>
                                <span class="mobile-card-value"><?php echo ucfirst(str_replace('_', ' ', $product['type'])); ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Merek:</span>
                                <span class="mobile-card-value"><?php echo htmlspecialchars($product['brand']); ?></span>
                            </div>
                            <div class="mobile-card-row">
                                <span class="mobile-card-label">Harga:</span>
                                <span class="mobile-card-value"><strong>Rp <?php echo number_format($product['price']); ?></strong></span>
                            </div>
                        </div>
                        <div class="mobile-card-actions">
                            <a href="dashboard.php?edit_product=<?php echo $product['id']; ?>" class="btn btn-warning btn-small">
                                Edit
                            </a>
                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus produk ini?')">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-small">Hapus</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Detail Pesanan</h2>
            <div id="orderDetails"></div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function showOrderDetails(orderId) {
            document.getElementById('orderModal').style.display = 'block';
            
            // You can implement AJAX call here to fetch order details
            document.getElementById('orderDetails').innerHTML = `
                <div style="text-align: center; padding: 2rem; color: #6c757d;">
                    <p>Loading order details for ID: #${orderId}</p>
                    <p style="margin-top: 1rem; font-size: 0.9rem;">Fitur detail pesanan akan menampilkan:</p>
                    <ul style="text-align: left; margin-top: 0.5rem; font-size: 0.9rem;">
                        <li>Item yang dipesan</li>
                        <li>Alamat pengiriman</li>
                        <li>Catatan khusus</li>
                        <li>Riwayat status</li>
                    </ul>
                </div>
            `;
        }
        
        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Add touch feedback
        document.addEventListener('touchstart', function() {}, true);
    </script>
</body>
</html>