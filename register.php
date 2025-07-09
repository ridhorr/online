<?php
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $address = trim($_POST['address']);
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    
    // Validasi input
    if (empty($name) || empty($phone) || empty($password) || empty($address) || empty($latitude) || empty($longitude)) {
        $error = 'Semua field harus diisi';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter';
    } else {
        // Cek jarak dari depot
        $distance = calculateDistance(DEPOT_LAT, DEPOT_LNG, $latitude, $longitude);
        if ($distance > MAX_DELIVERY_KM) {
            $error = 'Maaf, lokasi Anda terlalu jauh dari area layanan kami (maksimal ' . MAX_DELIVERY_KM . ' km)';
        } else {
            // Cek apakah nomor telepon sudah digunakan
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'Nomor telepon sudah terdaftar';
            } else {
                // Handle upload foto rumah
                $house_photo = null;
                if (isset($_FILES['house_photo']) && $_FILES['house_photo']['error'] == 0) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                    if (in_array($_FILES['house_photo']['type'], $allowed_types)) {
                        $upload_dir = 'uploads/houses/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['house_photo']['name'], PATHINFO_EXTENSION);
                        $house_photo = 'house_' . time() . '.' . $file_extension;
                        
                        if (move_uploaded_file($_FILES['house_photo']['tmp_name'], $upload_dir . $house_photo)) {
                            $house_photo = $upload_dir . $house_photo;
                        } else {
                            $house_photo = null;
                        }
                    }
                }
                
                // Insert user baru
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, phone, password, address, latitude, longitude, house_photo, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'pembeli')");
                
                if ($stmt->execute([$name, $phone, $hashed_password, $address, $latitude, $longitude, $house_photo])) {
                    $success = 'Pendaftaran berhasil! Silakan login dengan akun Anda.';
                } else {
                    $error = 'Terjadi kesalahan saat mendaftar';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Delivery Galon</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        
        input[type="text"], input[type="password"], input[type="number"], textarea, input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #4a5568;
            margin-bottom: 10px;
        }
        
        .btn-secondary:hover {
            background: #2d3748;
        }
        
        .error {
            background: #fee;
            color: #c53030;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #fed7d7;
        }
        
        .success {
            background: #f0fff4;
            color: #22543d;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #9ae6b4;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .location-helper {
            background: #f7fafc;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .location-helper p {
            margin: 5px 0;
            color: #4a5568;
        }
        
        #map-container {
            height: 300px;
            margin-top: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>🏺 Delivery Galon</h1>
            <p>Daftar akun baru</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Nomor Telepon</label>
                    <input type="text" id="phone" name="phone" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Alamat Lengkap</label>
                <textarea id="address" name="address" required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="latitude">Latitude</label>
                    <input type="number" id="latitude" name="latitude" step="any" required>
                </div>
                
                <div class="form-group">
                    <label for="longitude">Longitude</label>
                    <input type="number" id="longitude" name="longitude" step="any" required>
                </div>
            </div>
            
            <button type="button" class="btn btn-secondary" onclick="getLocation()">
                📍 Dapatkan Lokasi Saya
            </button>
            
            <div class="form-group">
                <label for="house_photo">Foto Rumah (Opsional)</label>
                <input type="file" id="house_photo" name="house_photo" accept="image/*">
            </div>
            
            <button type="submit" class="btn">Daftar</button>
        </form>
        
        <div class="login-link">
            <p>Sudah punya akun? <a href="index.php">Masuk disini</a></p>
        </div>
        
        <div class="location-helper">
            <p><strong>Cara mendapatkan koordinat:</strong></p>
            <p>1. Buka Google Maps di browser</p>
            <p>2. Cari alamat rumah Anda</p>
            <p>3. Klik kanan pada titik lokasi</p>
            <p>4. Pilih koordinat yang muncul (angka pertama = latitude, kedua = longitude)</p>
            <p>5. Atau klik tombol "Dapatkan Lokasi Saya" di atas</p>
        </div>
    </div>
    
    <script>
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition, showError);
            } else {
                alert("Geolocation tidak didukung browser ini.");
            }
        }
        
        function showPosition(position) {
            document.getElementById('latitude').value = position.coords.latitude;
            document.getElementById('longitude').value = position.coords.longitude;
            
            // Hitung jarak dari depot
            const depotLat = <?php echo DEPOT_LAT; ?>;
            const depotLng = <?php echo DEPOT_LNG; ?>;
            const distance = calculateDistance(depotLat, depotLng, position.coords.latitude, position.coords.longitude);
            
            if (distance > <?php echo MAX_DELIVERY_KM; ?>) {
                alert('Maaf, lokasi Anda terlalu jauh dari area layanan kami (maksimal <?php echo MAX_DELIVERY_KM; ?> km)');
            } else {
                alert('Lokasi berhasil diambil! Jarak dari depot: ' + distance.toFixed(2) + ' km');
            }
        }
        
        function showError(error) {
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    alert("Izin lokasi ditolak.");
                    break;
                case error.POSITION_UNAVAILABLE:
                    alert("Informasi lokasi tidak tersedia.");
                    break;
                case error.TIMEOUT:
                    alert("Timeout untuk mendapatkan lokasi.");
                    break;
                default:
                    alert("Terjadi kesalahan yang tidak diketahui.");
                    break;
            }
        }
        
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // radius bumi dalam km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }
    </script>
</body>
</html>