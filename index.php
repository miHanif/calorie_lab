<?php
session_start();
$sudah_login = isset($_SESSION['user_id']);
$nama = $_SESSION['nama'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Calorie Lab - Filter Menu Diet Cerdas</title>
  <link rel="stylesheet" href="styling.css">
</head>
<body>

<nav class="navbar">
  <div class="container nav-isi">
    <a class="logo" href="index.php">Calorie<span>Lab</span></a>
    <div class="nav-menu">
      <?php if ($sudah_login): ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Keluar (<?= htmlspecialchars($nama) ?>)</a>
      <?php else: ?>
        <a href="login.php">Masuk</a>
        <a class="tombol kecil" href="register.php">Daftar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<header class="hero">
  <div class="container">
    <h1>Makan sesuai tubuhmu, bukan sesuai saldo ATM.</h1>
    <p>Hitung BMI-mu, dapatkan menu yang cocok, dan pantau kalori harianmu.</p>
    <?php if ($sudah_login): ?>
      <a class="tombol" href="dashboard.php">Ke Dashboard</a>
    <?php else: ?>
      <a class="tombol" href="register.php">Mulai sekarang</a>
      <a class="tombol garis" href="login.php">Masuk</a>
    <?php endif; ?>
  </div>
</header>

<section class="container fitur">
  <div class="kartu">
    <h3>Hitung BMI</h3>
    <p>Masukkan tinggi dan berat badan, lalu lihat kategori tubuhmu.</p>
  </div>
  <div class="kartu">
    <h3>Menu rekomendasi</h3>
    <p>Menu otomatis sesuai kategori BMI, bisa difilter harga dan waktu makan.</p>
  </div>
  <div class="kartu">
    <h3>Catatan kalori</h3>
    <p>Catat makananmu dan lihat sisa kalori harian.</p>
  </div>
</section>

<footer>
  Calorie Lab bersifat edukatif dan bukan pengganti konsultasi dengan dokter atau ahli gizi.
</footer>

</body>
</html>