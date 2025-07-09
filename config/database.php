<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gallon_delivery');

// Depot location (Imronqua)
define('DEPOT_LAT', -5.3541332);
define('DEPOT_LNG', 105.2461687);

// Delivery settings
define('MAX_DELIVERY_KM', 5);
define('DELIVERY_RATE_PER_KM', 1000); // 1000 per km

// Payment settings
define('PAYMENT_ACCOUNT', '7292650366');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper functions
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

function calculateDeliveryFee($distance) {
    if ($distance <= 2) {
        return 0; // Free delivery for 2km or less
    } else {
        return ceil($distance - 2) * DELIVERY_RATE_PER_KM;
    }
}

function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Helper functions for authentication
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!hasRole('admin')) {
        header('Location: index.php');
        exit;
    }
}

function requireEmployee() {
    requireLogin();
    if (!hasRole('employee') && !hasRole('admin')) {
        header('Location: index.php');
        exit;
    }
}

// Start session only if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>