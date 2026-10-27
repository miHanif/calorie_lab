<?php
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$db   = 'calorie_lab';
$user = '';   
$pass = '';       

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Koneksi database gagal. Periksa pengaturan di koneksi.php');
}

function h($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function wajib_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token($kunci = 'csrf_token') {
    if (empty($_SESSION[$kunci])) {
        $_SESSION[$kunci] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$kunci];
}

function csrf_valid($token, $kunci = 'csrf_token') {
    return is_string($token) && !empty($_SESSION[$kunci]) && hash_equals($_SESSION[$kunci], $token);
}
