<?php
require_once 'koneksi.php';
$sudah_login = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($judul ?? 'Calorie Lab') ?> - Calorie Lab</title>
  <link rel="stylesheet" href="styling.css">
</head>
<body>

<nav class="navbar">
  <div class="container nav-isi">
    <a class="logo" href="index.php">Calorie<span>Lab</span></a>
    <div class="nav-menu">
      <?php if ($sudah_login): ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Keluar (<?= h($_SESSION['nama']) ?>)</a>
      <?php else: ?>
        <a href="login.php">Masuk</a>
        <a class="tombol kecil" href="register.php">Daftar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="container halaman">