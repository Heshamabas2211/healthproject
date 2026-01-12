<?php
session_start();

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'home_health_care');
define('DB_USER', 'root');
define('DB_PASS', '');

// الاتصال بقاعدة البيانات
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// دوال مساعدة
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isPatient() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'patient';
}

function isDoctor() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'doctor';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>