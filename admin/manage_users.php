<?php
session_start();

// Cek apakah user sudah login dan memiliki role boss
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'boss') {
    header('Location: ../index.php');
    exit;
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gallon_delivery');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

$message = '';
$error = '';

// Proses update user
if (isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $new_phone = $_POST['new_phone'];
    $new_password = $_POST['new_password'];
    
    try {
        // Cek apakah nomor telepon sudah digunakan oleh user lain
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $stmt->execute([$new_phone, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $error = "Nomor telepon sudah digunakan oleh user lain!";
        } else {
            // Update data
            if (!empty($new_password)) {
                // Update dengan password baru
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET phone = ?, password = ? WHERE id = ?");
                $stmt->execute([$new_phone, $hashed_password, $user_id]);
            } else {
                // Update hanya nomor telepon
                $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
                $stmt->execute([$new_phone, $user_id]);
            }
            
            $message = "Data berhasil diperbarui!";
        }
    } catch(PDOException $e) {
        $error = "Gagal memperbarui data: " . $e->getMessage();
    }
}

// Ambil data admin dan karyawan
$users = [];
$stmt = $pdo->prepare("SELECT id, name, phone, role FROM users WHERE role IN ('admin', 'karyawan', 'boss') ORDER BY FIELD(role, 'boss', 'admin', 'karyawan'), name");
$stmt->execute();
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Gallon Delivery</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 800px;
            width: 100%;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .content {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        input[type="password"],
        input[type="tel"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="tel"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }

        .btn-small {
            padding: 8px 20px;
            font-size: 14px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
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

        .user-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .user-details h3 {
            color: #333;
            margin-bottom: 5px;
        }

        .user-details p {
            color: #666;
            margin: 2px 0;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-boss {
            background: #6f42c1;
            color: white;
        }

        .badge-admin {
            background: #dc3545;
            color: white;
        }

        .badge-karyawan {
            background: #28a745;
            color: white;
        }

        .update-form {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }

        .update-form.active {
            display: block;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .toggle-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .toggle-btn:hover {
            background: #5a6268;
        }

        .welcome-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }

        .welcome-info h3 {
            color: #1976d2;
            margin-bottom: 5px;
        }

        .welcome-info p {
            color: #424242;
            margin: 0;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-row .form-group {
                margin-bottom: 15px;
            }
            
            .user-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .logout-btn {
                position: static;
                display: block;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="../index.php" class="logout-btn">🚪 Logout</a>
            <h1>🚚 Manage Users</h1>
            <p>Kelola Data Admin & Karyawan</p>
        </div>

        <div class="content">
            <div class="welcome-info">
                <h3>👋 Selamat datang, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h3>
                <p>Anda login sebagai: <strong><?php echo ucfirst($_SESSION['user_role']); ?></strong></p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- User Management -->
            <div style="text-align: center; margin-bottom: 30px;">
                <h2>Kelola Data Admin & Karyawan</h2>
                <p style="color: #666; margin-top: 10px;">Kelola data admin dan karyawan di bawah ini</p>
            </div>

            <?php foreach ($users as $user): ?>
                <div class="user-card">
                    <div class="user-info">
                        <div class="user-details">
                            <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                            <p><strong>Telepon:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                            <p><strong>Role:</strong> <span class="badge badge-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></p>
                        </div>
                        <button type="button" class="toggle-btn" onclick="toggleUpdateForm(<?php echo $user['id']; ?>)">
                            ✏️ Edit
                        </button>
                    </div>

                    <div id="updateForm<?php echo $user['id']; ?>" class="update-form">
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_phone<?php echo $user['id']; ?>">Nomor Telepon Baru:</label>
                                    <input type="tel" id="new_phone<?php echo $user['id']; ?>" name="new_phone" 
                                           value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_password<?php echo $user['id']; ?>">Password Baru:</label>
                                    <input type="password" id="new_password<?php echo $user['id']; ?>" name="new_password" 
                                           placeholder="Kosongkan jika tidak ingin mengubah">
                                </div>
                            </div>
                            <div style="text-align: center;">
                                <button type="submit" name="update_user" class="btn">💾 Simpan Perubahan</button>
                                <button type="button" class="btn btn-danger" onclick="toggleUpdateForm(<?php echo $user['id']; ?>)">❌ Batal</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($users)): ?>
                <div style="text-align: center; color: #666; margin-top: 50px;">
                    <p>Tidak ada data admin atau karyawan.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleUpdateForm(userId) {
            const form = document.getElementById('updateForm' + userId);
            const isActive = form.classList.contains('active');
            
            // Tutup semua form yang terbuka
            document.querySelectorAll('.update-form').forEach(f => {
                f.classList.remove('active');
            });
            
            // Buka form yang diklik jika sebelumnya tertutup
            if (!isActive) {
                form.classList.add('active');
            }
        }

        // Auto hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>