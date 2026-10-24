<?php
require_once 'koneksi.php';
$sudah_login = isset($_SESSION['user_id']);
$halaman_aktif = basename($_SERVER['PHP_SELF'] ?? '');
$tautan_navigasi = [
    'dashboard.php' => ['Dashboard', '01'],
    'bmi.php' => ['Hitung BMI', '02'],
    'menu.php' => ['Menu rekomendasi', '03'],
    'catatan.php' => ['Catatan kalori', '04'],
    'rencana.php' => ['Rencana diet', '05'],
    'profil.php' => ['Profil & perkembangan', '06'],
];
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
        <a href="logout.php">Keluar (<?= h($_SESSION['nama']) ?>)</a>
      <?php else: ?>
        <a href="login.php">Masuk</a>
        <a class="tombol kecil" href="register.php">Daftar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<?php if ($sudah_login): ?>
  <div class="app-layout container">
    <input class="sidebar-toggle" type="checkbox" id="sidebar-toggle" aria-controls="sidebar-navigasi">
    <label class="sidebar-toggle-label" for="sidebar-toggle"><span aria-hidden="true">☰</span> Navigasi</label>
    <aside class="app-sidebar" id="sidebar-navigasi">
      <p class="sidebar-heading">RUANG KERJAMU</p>
      <nav aria-label="Navigasi utama">
        <?php foreach ($tautan_navigasi as $file => [$label, $nomor]): ?>
          <a class="sidebar-link <?= $halaman_aktif === $file ? 'aktif' : '' ?>"
             href="<?= h($file) ?>"
             <?= $halaman_aktif === $file ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-number"><?= h($nomor) ?></span>
            <span><?= h($label) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-note">
        <strong>Calorie Lab</strong>
        <span>Catat, rencanakan, dan pantau progresmu.</span>
      </div>
    </aside>
  <main class="halaman app-content">
<?php else: ?>
  <main class="container halaman">
<?php endif; ?>