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

if (!$user) {
    header('Location: ../logout.php');
    exit;
}

// Hitung jarak ke depot
$distance = calculateDistance($user['latitude'], $user['longitude'], DEPOT_LAT, DEPOT_LNG);
$delivery_fee = calculateDeliveryFee($distance);

// Hitung total pesanan user
$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$orders_count = $stmt->fetch()['total_orders'];

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $latitude = (float)$_POST['latitude'];
        $longitude = (float)$_POST['longitude'];
        
        // Validasi input
        if (empty($name) || empty($phone) || empty($address)) {
            $message = 'Semua field harus diisi!';
            $message_type = 'error';
        } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            $message = 'Format nomor telepon tidak valid!';
            $message_type = 'error';
        } elseif ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            $message = 'Koordinat tidak valid!';
            $message_type = 'error';
        } else {
            // Cek apakah phone sudah digunakan user lain
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
            $stmt->execute([$phone, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $message = 'Nomor telepon sudah digunakan!';
                $message_type = 'error';
            } else {
                // Update profile
                $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ?, latitude = ?, longitude = ? WHERE id = ?");
                if ($stmt->execute([$name, $phone, $address, $latitude, $longitude, $_SESSION['user_id']])) {
                    $message = 'Profil berhasil diperbarui!';
                    $message_type = 'success';
                    
                    // Update data user
                    $user['name'] = $name;
                    $user['phone'] = $phone;
                    $user['address'] = $address;
                    $user['latitude'] = $latitude;
                    $user['longitude'] = $longitude;
                    
                    // Update session name
                    $_SESSION['user_name'] = $name;
                    
                    // Recalculate distance and delivery fee
                    $distance = calculateDistance($latitude, $longitude, DEPOT_LAT, DEPOT_LNG);
                    $delivery_fee = calculateDeliveryFee($distance);
                } else {
                    $message = 'Gagal memperbarui profil!';
                    $message_type = 'error';
                }
            }
        }
    }
    
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validasi input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = 'Semua field password harus diisi!';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = 'Password baru minimal 6 karakter!';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = 'Konfirmasi password tidak cocok!';
            $message_type = 'error';
        } elseif (!password_verify($current_password, $user['password'])) {
            $message = 'Password lama tidak benar!';
            $message_type = 'error';
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                $message = 'Password berhasil diperbarui!';
                $message_type = 'success';
            } else {
                $message = 'Gagal memperbarui password!';
                $message_type = 'error';
            }
        }
    }
}

// Format tanggal member sejak
$member_since = date('d F Y', strtotime($user['created_at']));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Delivery Galon</title>
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
            padding-bottom: 80px; /* Space for bottom navigation */
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
        }
        
        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .profile-header {
            background: #667eea;
            color: white;
            padding: 20px 15px;
            text-align: center;
            position: relative;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
            margin: 0 auto 15px;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .profile-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .profile-since {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .info-stats {
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
        
        .stat-icon {
            font-size: 20px;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 3px;
        }
        
        .stat-label {
            color: #666;
            font-size: 11px;
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .section-header {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: center;
        }
        
        .section-header h2 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .section-subtitle {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .form-content {
            padding: 20px 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .coordinates-input {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .coordinates-input input {
            flex: 1;
        }
        
        .map-btn {
            background: #38a169;
            color: white;
            border: none;
            padding: 12px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .map-btn:hover {
            background: #2f855a;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
            width: 100%;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .alert.success {
            background: #f0fff4;
            color: #38a169;
            border: 1px solid #9ae6b4;
        }
        
        .alert.error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
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
        
        /* Responsive adjustments */
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .coordinates-input {
                flex-direction: column;
                align-items: stretch;
            }
            
            .info-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            
            .stat-card {
                padding: 12px;
            }
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
        
        /* Animation for form updates */
        .form-updated {
            animation: formUpdateFlash 0.5s ease-in-out;
        }
        
        @keyframes formUpdateFlash {
            0% { background: #f0fff4; }
            50% { background: #c6f6d5; }
            100% { background: white; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">🏺 Delivery Galon</div>
            <div class="user-info">
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h1>👤 Profil Saya</h1>
            <p>Kelola informasi profil dan pengaturan akun</p>
        </div>
        
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                <div class="profile-since">Member sejak <?php echo $member_since; ?></div>
            </div>
        </div>
        
        <div class="info-stats">
            <div class="stat-card">
                <div class="stat-icon">📍</div>
                <div class="stat-value"><?php echo number_format($distance, 1); ?> km</div>
                <div class="stat-label">Jarak Depot</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚚</div>
                <div class="stat-value"><?php echo $delivery_fee == 0 ? 'GRATIS' : formatRupiah($delivery_fee); ?></div>
                <div class="stat-label">Ongkir</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📞</div>
                <div class="stat-value"><?php echo htmlspecialchars($user['phone']); ?></div>
                <div class="stat-label">Telepon</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?php echo $orders_count; ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if ($message): ?>
        <div class="alert <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="form-section">
            <div class="section-header">
                <h2>📝 Informasi Profil</h2>
                <div class="section-subtitle">Perbarui data profil Anda</div>
            </div>
            <div class="form-content">
                <form method="POST">
                    <div class="form-group">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Nomor Telepon</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Alamat Lengkap</label>
                        <textarea id="address" name="address" required><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="latitude">Koordinat Lintang</label>
                            <div class="coordinates-input">
                                <input type="number" id="latitude" name="latitude" step="0.000001" value="<?php echo $user['latitude']; ?>" required>
                                <button type="button" class="map-btn" onclick="getLocation()">📍 Lokasi</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="longitude">Koordinat Bujur</label>
                            <input type="number" id="longitude" name="longitude" step="0.000001" value="<?php echo $user['longitude']; ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn">💾 Simpan Perubahan</button>
                </form>
            </div>
        </div>
        
        <div class="form-section">
            <div class="section-header">
                <h2>🔐 Ubah Password</h2>
                <div class="section-subtitle">Perbarui password untuk keamanan akun</div>
            </div>
            <div class="form-content">
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Password Lama</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">Password Baru</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_password" class="btn">🔑 Ubah Password</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <div class="nav-links">
            <a href="home.php">
                <span class="nav-icon">🏠</span>
                Home
            </a>
            <a href="orders.php">
                <span class="nav-icon">📋</span>
                Pesanan
            </a>
            <a href="queue.php">
                <span class="nav-icon">🚚</span>
                Antrian
            </a>
            <a href="profile.php" class="active">
                <span class="nav-icon">👤</span>
                Profil
            </a>
        </div>
    </div>
    
    <!-- Loading Indicator -->
    <div class="loading" id="loading">
        🔄 Memuat...
    </div>
    
    <script>
        function getLocation() {
            if (navigator.geolocation) {
                document.getElementById('loading').style.display = 'block';
                
                navigator.geolocation.getCurrentPosition(function(position) {
                    document.getElementById('latitude').value = position.coords.latitude.toFixed(6);
                    document.getElementById('longitude').value = position.coords.longitude.toFixed(6);
                    document.getElementById('loading').style.display = 'none';
                    
                    // Vibration feedback
                    if ('vibrate' in navigator) {
                        navigator.vibrate(200);
                    }
                }, function(error) {
                    document.getElementById('loading').style.display = 'none';
                    alert('Gagal mengambil lokasi: ' + error.message);
                });
            } else {
                alert('Geolocation tidak didukung oleh browser ini.');
            }
        }
        
        // Input validation
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('phone');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            phoneInput.addEventListener('input', function() {
                const phone = this.value;
                if (phone && !phone.match(/^[0-9]{10,15}$/)) {
                    this.setCustomValidity('Nomor telepon harus 10-15 digit angka');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            function validatePassword() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (newPassword && newPassword.length < 6) {
                    newPasswordInput.setCustomValidity('Password minimal 6 karakter');
                } else {
                    newPasswordInput.setCustomValidity('');
                }
                
                if (confirmPassword && newPassword !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity('Konfirmasi password tidak cocok');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            }
            
            newPasswordInput.addEventListener('input', validatePassword);
            confirmPasswordInput.addEventListener('input', validatePassword);
        });
        
        // Prevent zoom on double tap
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
        
        // Service Worker for offline support
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(console.error);
        }
    </script>
</body>
</html>