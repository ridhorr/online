<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is employee
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'karyawan') {
    header('Location: ../login.php');
    exit();
}

// Fungsi untuk mendapatkan path foto yang benar
function getPhotoPath($filename) {
    if (empty($filename)) {
        return null;
    }
    
    // Bersihkan filename dari path yang mungkin sudah ada
    $clean_filename = basename($filename);
    
    // Coba beberapa kemungkinan path berdasarkan struktur folder
    $possible_paths = [
        '../uploads/houses/' . $clean_filename,      // Path utama untuk houses
        './uploads/houses/' . $clean_filename,       // Alternatif dari current dir
        '../uploads/' . $clean_filename,             // Backup jika tidak ada folder houses
        './uploads/' . $clean_filename,              // Alternatif backup
        'uploads/houses/' . $clean_filename,         // Jika tidak ada ../
        'uploads/' . $clean_filename,                // Backup tanpa ../
        '../public/uploads/houses/' . $clean_filename, // Jika ada folder public
        '../public/uploads/' . $clean_filename,      // Public backup
    ];
    
    // Jika filename mengandung path lengkap dari database
    if (strpos($filename, '/') !== false) {
        array_unshift($possible_paths, '../' . $filename);
        array_unshift($possible_paths, './' . $filename);
        array_unshift($possible_paths, $filename);
    }
    
    // Debug log untuk troubleshooting
    error_log("Mencari foto: " . $filename);
    
    foreach ($possible_paths as $path) {
        error_log("Coba path: " . $path . " - " . (file_exists($path) ? "DITEMUKAN" : "TIDAK ADA"));
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return null;
}

// Fungsi untuk membuat URL foto yang benar
function getPhotoUrl($filename) {
    if (empty($filename)) {
        return null;
    }
    
    // Bersihkan filename
    $clean_filename = basename($filename);
    
    // Tentukan base URL untuk foto berdasarkan struktur folder
    // Sesuaikan dengan struktur web server Anda
    $base_url = '../uploads/houses/';
    
    // Jika file ada di path utama
    if (file_exists('../uploads/houses/' . $clean_filename)) {
        return $base_url . $clean_filename;
    }
    
    // Fallback ke uploads utama jika tidak ada folder houses
    if (file_exists('../uploads/' . $clean_filename)) {
        return '../uploads/' . $clean_filename;
    }
    
    // Default return
    return $base_url . $clean_filename;
}

// Fungsi untuk mengecek apakah foto valid
function isPhotoValid($filename) {
    if (empty($filename)) {
        return false;
    }
    
    $path = getPhotoPath($filename);
    return $path !== null && file_exists($path);
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
    
    if ($stmt->execute([$new_status, $order_id])) {
        $_SESSION['success'] = "Status pesanan berhasil diperbarui";
    } else {
        $_SESSION['error'] = "Gagal memperbarui status pesanan";
    }
    
    header('Location: orders.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
    exit();
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Build query based on filter
$query = "SELECT o.*, u.name as customer_name, u.phone as customer_phone, 
          u.address as customer_address, u.latitude, u.longitude, u.house_photo
          FROM orders o 
          JOIN users u ON o.user_id = u.id ";

$params = [];

switch ($filter) {
    case 'not_delivered':
        $query .= "WHERE o.order_status IN ('pending', 'processing') ";
        break;
    case 'completed':
        $query .= "WHERE o.order_status = 'completed' ";
        break;
    case 'pending':
        $query .= "WHERE o.order_status = 'pending' ";
        break;
    case 'processing':
        $query .= "WHERE o.order_status = 'processing' ";
        break;
    case 'delivering':
        $query .= "WHERE o.order_status = 'delivering' ";
        break;
    case 'cancelled':
        $query .= "WHERE o.order_status = 'cancelled' ";
        break;
}

$query .= "ORDER BY o.created_at DESC";

$result = $pdo->query($query);

// Get filter title
$filter_titles = [
    '' => 'Semua Pesanan',
    'not_delivered' => 'Belum Diantar',
    'completed' => 'Pesanan Selesai',
    'pending' => 'Menunggu',
    'processing' => 'Diproses',
    'delivering' => 'Sedang Diantar',
    'cancelled' => 'Dibatalkan'
];

$page_title = $filter_titles[$filter] ?? 'Semua Pesanan';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Karyawan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .order-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px 8px 0 0;
        }
        .order-body {
            padding: 20px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-pending { background: #ffc107; color: #000; }
        .status-processing { background: #17a2b8; color: #fff; }
        .status-delivering { background: #fd7e14; color: #fff; }
        .status-completed { background: #28a745; color: #fff; }
        .status-cancelled { background: #dc3545; color: #fff; }
        .customer-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .house-photo {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .house-photo:hover {
            border-color: #007bff;
            transform: scale(1.05);
        }
        .house-photo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            border: 2px solid #ddd;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
            padding: 10px;
        }
        .house-photo-error {
            background: #fff3cd;
            border-color: #ffc107;
        }
        .house-photo-not-found {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .map-link {
            color: #28a745;
            text-decoration: none;
        }
        .map-link:hover {
            color: #1e7e34;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }
        .filter-btn.active {
            background: #667eea;
            color: white;
        }
        .filter-btn:not(.active) {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }
        .filter-btn:hover {
            background: #667eea;
            color: white;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        /* Logout button specific styling */
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #c82333;
            color: white;
        }

        /* Modal untuk foto */
        .photo-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }

        .photo-modal-content {
            display: block;
            margin: auto;
            max-width: 90%;
            max-height: 90%;
            margin-top: 5%;
            border-radius: 8px;
        }

        .photo-modal-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .photo-modal-close:hover {
            color: #ccc;
        }

        /* Error state untuk foto */
        .photo-error {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
        }

        /* Debug info */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-water"></i> Gallon Delivery - Karyawan
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link active" href="orders.php">
                    <i class="fas fa-box"></i> Pesanan
                </a>
                <a class="nav-link logout-btn" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-box"></i> <?php echo $page_title; ?></h2>
                    <p class="mb-0">Kelola dan pantau semua pesanan</p>
                </div>
                <div class="text-end">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                </div>
            </div>
        </div>

        <!-- Filter Buttons -->
        <div class="filter-buttons">
            <a href="orders.php" class="filter-btn <?php echo $filter === '' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> Semua Pesanan
            </a>
            <a href="orders.php?filter=not_delivered" class="filter-btn <?php echo $filter === 'not_delivered' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Belum Diantar
            </a>
            <a href="orders.php?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-hourglass-start"></i> Menunggu
            </a>
            <a href="orders.php?filter=processing" class="filter-btn <?php echo $filter === 'processing' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i> Diproses
            </a>
            <a href="orders.php?filter=delivering" class="filter-btn <?php echo $filter === 'delivering' ? 'active' : ''; ?>">
                <i class="fas fa-truck"></i> Sedang Diantar
            </a>
            <a href="orders.php?filter=completed" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Selesai
            </a>
            <a href="orders.php?filter=cancelled" class="filter-btn <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Dibatalkan
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($result->rowCount() > 0): ?>
            <?php while ($order = $result->fetch(PDO::FETCH_ASSOC)): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-0">
                                    <i class="fas fa-receipt"></i> Pesanan #<?php echo $order['id']; ?>
                                </h5>
                                <small><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></small>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="status-badge status-<?php echo $order['order_status']; ?>">
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
                        </div>
                    </div>

                    <div class="order-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="customer-info">
                                    <h6><i class="fas fa-user"></i> Informasi Pembeli</h6>
                                    <div class="row">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>Nama:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                            <p class="mb-1"><strong>Telepon:</strong> 
                                                <a href="tel:<?php echo $order['customer_phone']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($order['customer_phone']); ?>
                                                </a>
                                            </p>
                                            <p class="mb-1"><strong>Alamat:</strong> <?php echo htmlspecialchars($order['customer_address']); ?></p>
                                            <p class="mb-0">
                                                <strong>Lokasi:</strong> 
                                                <a href="https://maps.google.com/?q=<?php echo $order['latitude']; ?>,<?php echo $order['longitude']; ?>" 
                                                   target="_blank" class="map-link">
                                                    <i class="fas fa-map-marker-alt"></i> Lihat di Map
                                                </a>
                                            </p>
                                        </div>
                                        <div class="col-md-4">
                                            <?php if (!empty($order['house_photo'])): ?>
                                                <?php 
                                                $photo_url = getPhotoUrl($order['house_photo']);
                                                $photo_path = getPhotoPath($order['house_photo']);
                                                $is_photo_valid = isPhotoValid($order['house_photo']);
                                                ?>
                                                
                                                <?php if ($is_photo_valid && $photo_path): ?>
                                                    <img src="<?php echo htmlspecialchars($photo_url); ?>" 
                                                         alt="Foto Rumah" 
                                                         class="house-photo"
                                                         onclick="showPhotoModal('<?php echo htmlspecialchars($photo_url); ?>')"
                                                         onerror="handlePhotoError(this)"
                                                         title="Klik untuk memperbesar">
                                                    
                                                    <!-- Debug info untuk troubleshooting -->
                                                    <?php if (isset($_GET['debug'])): ?>
                                                        <div class="debug-info">
                                                            <strong>Debug Info:</strong><br>
                                                            Original: <?php echo htmlspecialchars($order['house_photo']); ?><br>
                                                            Path: <?php echo htmlspecialchars($photo_path); ?><br>
                                                            URL: <?php echo htmlspecialchars($photo_url); ?><br>
                                                            Exists: <?php echo $is_photo_valid ? 'Yes' : 'No'; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="house-photo-placeholder house-photo-not-found">
                                                        <i class="fas fa-exclamation-triangle text-danger"></i>
                                                        <small class="text-danger">File tidak ditemukan</small>
                                                        
                                                        <!-- Debug info -->
                                                        <?php if (isset($_GET['debug'])): ?>
                                                            <div class="debug-info mt-2">
                                                                <strong>Debug:</strong><br>
                                                                File: <?php echo htmlspecialchars($order['house_photo']); ?><br>
                                                                Status: Not Found
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="house-photo-placeholder">
                                                    <i class="fas fa-home text-muted"></i>
                                                    <small class="text-muted">Tidak ada foto</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6><i class="fas fa-info-circle"></i> Detail Pesanan</h6>
                                <?php
                                // Get order items
                                $items_query = "SELECT oi.*, p.name as product_name, p.type, p.brand 
                                               FROM order_items oi 
                                               JOIN products p ON oi.product_id = p.id 
                                               WHERE oi.order_id = ?";
                                $items_stmt = $pdo->prepare($items_query);
                                $items_stmt->execute([$order['id']]);
                                $items_result = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Produk</th>
                                                <th>Qty</th>
                                                <th>Harga</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items_result as $item): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $item['quantity']; ?></td>
                                                    <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                                    <td>Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="border-top pt-2">
                                    <div class="row">
                                        <div class="col-6">
                                            <small>Total Produk:</small><br>
                                            <strong>Rp <?php echo number_format($order['total_amount'] - $order['delivery_fee'], 0, ',', '.'); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small>Ongkir (<?php echo $order['distance_km']; ?> km):</small><br>
                                            <strong>Rp <?php echo number_format($order['delivery_fee'], 0, ',', '.'); ?></strong>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="text-end">
                                        <h6>Total: Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></h6>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-credit-card"></i> Pembayaran: <?php echo ucfirst($order['payment_method']); ?>
                                        <span class="ms-2">
                                            <i class="fas fa-circle text-<?php echo $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'failed' ? 'danger' : 'warning'); ?>"></i>
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </small>
                                </div>

                                <?php if ($order['notes']): ?>
                                    <div class="mt-2">
                                        <small><strong>Catatan:</strong> <?php echo htmlspecialchars($order['notes']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Status Update Form -->
                        <?php if ($order['order_status'] !== 'completed' && $order['order_status'] !== 'cancelled'): ?>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="bg-light p-3 rounded">
                                        <h6><i class="fas fa-edit"></i> Update Status</h6>
                                        <form method="POST" class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <select name="new_status" class="form-select" style="width: auto;">
                                                <option value="pending" <?php echo $order['order_status'] === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                                                <option value="processing" <?php echo $order['order_status'] === 'processing' ? 'selected' : ''; ?>>Diproses</option>
                                                <option value="delivering" <?php echo $order['order_status'] === 'delivering' ? 'selected' : ''; ?>>Diantar</option>
                                                <option value="completed" <?php echo $order['order_status'] === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                                <i class="fas fa-save"></i> Update
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">Tidak ada pesanan <?php echo strtolower($page_title); ?></h4>
                <p class="text-muted">
                    <?php if ($filter === 'not_delivered'): ?>
                        Semua pesanan sudah diantar atau belum ada pesanan baru
                    <?php else: ?>
                        Belum ada pesanan dengan status ini
                    <?php endif; ?>
                </p>
                <a href="orders.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> Lihat Semua Pesanan
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal untuk menampilkan foto besar -->
    <div id="photoModal" class="photo-modal" onclick="closePhotoModal()">
        <span class="photo-modal-close" onclick="closePhotoModal()">&times;</span>
        <img class="photo-modal-content" id="modalImage" onclick="event.stopPropagation()">
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk menangani error foto
        function handlePhotoError(img) {
            const placeholder = document.createElement('div');
            placeholder.className = 'house-photo-placeholder house-photo-error';
            placeholder.innerHTML = `
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <small class="text-warning">Foto tidak dapat dimuat</small>
            `;
            img.parentNode.replaceChild(placeholder, img);
        }

        // Fungsi untuk menampilkan modal foto
        function showPhotoModal(src) {
            const modal = document.getElementById('photoModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = 'block';
            modalImg.src = src;
        }

        // Fungsi untuk menutup modal foto
        function closePhotoModal() {
            document.getElementById('photoModal').style.display = 'none';
        }

        // Tutup modal jika tekan ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePhotoModal();
            }
        });

        // Auto-hide alerts setelah 5 detik
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Debug helper - tambahkan ?debug=1 ke URL untuk melihat debug info
        if (window.location.search.includes('debug=1')) {
            console.log('Debug mode enabled');
        }
    </script>
</body>
</html>