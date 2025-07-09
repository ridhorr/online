<?php
// Start session first
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Try different paths for database config
$config_paths = [
    'config/database.php',
    '../config/database.php',
    dirname(__DIR__) . '/config/database.php',
    $_SERVER['DOCUMENT_ROOT'] . '/TOKO/config/database.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    die("Error: config/database.php not found. Please check your folder structure.");
}

// Require admin access
requireAdmin();

$message = '';
$error = '';

// Handle delete action
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    try {
        // Get user info first for confirmation (only pembeli)
        $stmt = $pdo->prepare("SELECT name, phone, house_photo FROM users WHERE id = ? AND role = 'pembeli'");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Delete user's house photo if exists
            if ($user['house_photo'] && file_exists($user['house_photo'])) {
                unlink($user['house_photo']);
            }
            
            // Delete user from database
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'pembeli'");
            if ($stmt->execute([$user_id])) {
                $message = "User '{$user['name']}' ({$user['phone']}) berhasil dihapus!";
            } else {
                $error = "Gagal menghapus user!";
            }
        } else {
            $error = "User tidak ditemukan atau bukan pembeli!";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_users'])) {
    $selected_users = $_POST['selected_users'];
    $deleted_count = 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($selected_users as $user_id) {
            $user_id = intval($user_id);
            
            // Get user info for photo deletion (only pembeli)
            $stmt = $pdo->prepare("SELECT house_photo FROM users WHERE id = ? AND role = 'pembeli'");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user && $user['house_photo'] && file_exists($user['house_photo'])) {
                unlink($user['house_photo']);
            }
            
            // Delete user (only pembeli)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'pembeli'");
            if ($stmt->execute([$user_id])) {
                $deleted_count++;
            }
        }
        
        $pdo->commit();
        $message = "$deleted_count user berhasil dihapus!";
    } catch (PDOException $e) {
        $pdo->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

// Handle delete all users (only pembeli)
if (isset($_POST['delete_all_users'])) {
    try {
        // First, get all house photos to delete (only pembeli)
        $stmt = $pdo->prepare("SELECT house_photo FROM users WHERE role = 'pembeli' AND house_photo IS NOT NULL");
        $stmt->execute();
        $photos = $stmt->fetchAll();
        
        // Delete all photos
        foreach ($photos as $photo) {
            if (file_exists($photo['house_photo'])) {
                unlink($photo['house_photo']);
            }
        }
        
        // Delete all users with role pembeli
        $stmt = $pdo->prepare("DELETE FROM users WHERE role = 'pembeli'");
        $affected = $stmt->execute();
        
        if ($affected) {
            $count = $stmt->rowCount();
            $message = "Semua user pembeli berhasil dihapus! ($count user)";
        } else {
            $error = "Gagal menghapus user!";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get all users for display (only pembeli)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT id, name, phone, address, role, house_photo, latitude, longitude FROM users WHERE role = 'pembeli'";
$params = [];

if ($search) {
    $query .= " AND (name LIKE ? OR phone LIKE ? OR address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User Pembeli - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .controls {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-group {
            flex: 1;
            min-width: 250px;
        }
        
        .search-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .user-photo {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .role-pembeli {
            background: #6c757d;
            color: white;
        }
        
        .bulk-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .coordinates {
            font-size: 11px;
            color: #666;
        }
        
        .back-link {
            margin-bottom: 20px;
        }
        
        .back-link a {
            color: #007bff;
            text-decoration: none;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 5px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .danger-zone h3 {
            color: #c53030;
            margin-bottom: 15px;
        }
        
        .confirm-checkbox {
            margin: 15px 0;
        }
        
        .confirm-checkbox input {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="dashboard.php">← Kembali ke Dashboard</a>
        </div>
        
        <div class="header">
            <h1>🛒 Kelola Data User Pembeli</h1>
            <p>Hapus dan kelola data user pembeli yang terdaftar</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Search Controls -->
        <form method="GET" class="controls">
            <div class="search-group">
                <input type="text" name="search" placeholder="Cari nama, telepon, atau alamat..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Cari</button>
            <a href="delete_users.php" class="btn btn-secondary">Reset</a>
        </form>
        
        <!-- Users Table -->
        <div class="table-container">
            <form method="POST" id="bulk-form">
                <table>
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="select-all">
                            </th>
                            <th>Foto</th>
                            <th>Nama</th>
                            <th>Telepon</th>
                            <th>Alamat</th>
                            <th>Role</th>
                            <th>Koordinat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="user-checkbox">
                                    </td>
                                    <td>
                                        <?php if ($user['house_photo']): ?>
                                            <img src="<?php echo htmlspecialchars($user['house_photo']); ?>" alt="Foto Rumah" class="user-photo">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #ddd; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 12px;">No Photo</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($user['address'], 0, 50)) . (strlen($user['address']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="role-badge role-pembeli">
                                            Pembeli
                                        </span>
                                    </td>
                                    <td class="coordinates">
                                        <?php if ($user['latitude'] && $user['longitude']): ?>
                                            <?php echo number_format($user['latitude'], 6); ?>,<br>
                                            <?php echo number_format($user['longitude'], 6); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus user <?php echo htmlspecialchars($user['name']); ?>?')">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                    Tidak ada user pembeli yang ditemukan
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Bulk Actions -->
                <div class="bulk-actions">
                    <button type="submit" name="bulk_delete" class="btn btn-warning" onclick="return confirm('Yakin ingin menghapus user pembeli yang dipilih?')">
                        Hapus Terpilih
                    </button>
                    <span style="margin-left: 20px; color: #666;">
                        Total user pembeli: <?php echo count($users); ?>
                    </span>
                </div>
            </form>
        </div>
        
        <!-- Danger Zone -->
        <div class="danger-zone">
            <h3>⚠️ Danger Zone</h3>
            <p>Aksi di bawah ini akan menghapus data secara permanen dan tidak dapat dikembalikan!</p>
            
            <form method="POST" onsubmit="return confirmDeleteAll()">
                <div class="confirm-checkbox">
                    <input type="checkbox" id="confirm-delete-all" required>
                    <label for="confirm-delete-all">Saya mengerti bahwa ini akan menghapus SEMUA user pembeli secara permanen</label>
                </div>
                <button type="submit" name="delete_all_users" class="btn btn-danger">
                    🗑️ Hapus Semua User Pembeli
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Select all checkbox functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // Update select all when individual checkboxes change
        document.querySelectorAll('.user-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.user-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
                const selectAll = document.getElementById('select-all');
                
                if (checkedCheckboxes.length === allCheckboxes.length) {
                    selectAll.checked = true;
                    selectAll.indeterminate = false;
                } else if (checkedCheckboxes.length > 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = true;
                } else {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }
            });
        });
        
        function confirmDeleteAll() {
            const userCount = <?php echo count($users); ?>;
            const confirmed = confirm(`PERINGATAN!\n\nAnda akan menghapus SEMUA user pembeli (${userCount} user).\n\nTindakan ini tidak dapat dibatalkan!\n\nApakah Anda benar-benar yakin?`);
            
            if (confirmed) {
                return confirm('Konfirmasi terakhir: Yakin ingin menghapus SEMUA data user pembeli?');
            }
            
            return false;
        }
    </script>
</body>
</html>