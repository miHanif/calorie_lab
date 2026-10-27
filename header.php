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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styling.css">
</head>
<body>

<?php if ($sudah_login): ?>
  <input class="sidebar-toggle" type="checkbox" id="sidebar-toggle" aria-label="Buka atau tutup navigasi" aria-controls="sidebar-navigasi">
<?php endif; ?>

<nav class="navbar">
  <div class="container nav-isi <?= $sudah_login ? 'nav-isi-aplikasi' : '' ?>">
    <div class="nav-brand">
      <?php if ($sudah_login): ?>
        <label class="sidebar-toggle-label sidebar-toggle-desktop" for="sidebar-toggle" aria-label="Buka navigasi"><span aria-hidden="true">☰</span></label>
      <?php endif; ?>
      <a class="logo" href="index.php">
        <span class="logo-ikon" aria-hidden="true">🌿</span>
        <span class="logo-teks">Calorie <em>Lab</em></span>
      </a>
    </div>
    <div class="nav-menu">
      <?php if ($sudah_login): ?>
        <a class="nav-link-subtle" href="dashboard.php">Dashboard</a>
        <a class="tombol kecil tombol-keluar" href="logout.php">Keluar (<?= h($_SESSION['nama'] ?? 'Akun') ?>)</a>
      <?php else: ?>
        <a class="nav-link-subtle" href="login.php">Masuk</a>
        <a class="tombol kecil" href="register.php">Daftar Akun</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<?php if ($sudah_login): ?>
  <label class="sidebar-backdrop" for="sidebar-toggle" aria-label="Tutup navigasi"></label>
  <div class="app-layout container">
    <label class="sidebar-toggle-label sidebar-toggle-mobile" for="sidebar-toggle"><span aria-hidden="true">☰</span> Navigasi</label>
    <aside class="app-sidebar" id="sidebar-navigasi">
      <div class="sidebar-drawer-heading">
        <label class="sidebar-close" for="sidebar-toggle" aria-label="Tutup navigasi">&times;</label>
      </div>
      <nav aria-label="Navigasi utama">
        <?php foreach ($tautan_navigasi as $file => [$label_navigasi, $nomor]): ?>
          <a class="sidebar-link <?= $halaman_aktif === $file ? 'aktif' : '' ?>"
             href="<?= h($file) ?>"
             <?= $halaman_aktif === $file ? 'aria-current="page"' : '' ?>>
            <span class="sidebar-number"><?= h($nomor) ?></span>
            <span><?= h($label_navigasi) ?></span>
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
