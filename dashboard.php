<?php
require_once 'koneksi.php';
wajib_login();

$judul = 'Dashboard';
include 'header.php';
?>

<h2>Halo, <?= h($_SESSION['nama']) ?></h2>
<p class="teks-redup">Login berhasil. Pilih fitur yang ingin kamu gunakan:</p>

<div class="fitur">
  <a class="kartu kartu-link" href="bmi.php">
    <h3>Hitung BMI</h3>
    <p>Cek kategori tubuh dan target kalori harian.</p>
  </a>
  <a class="kartu kartu-link" href="menu.php">
    <h3>Menu rekomendasi</h3>
    <p>Menu yang cocok untuk kategori BMI-mu.</p>
  </a>
  <a class="kartu kartu-link" href="catatan.php">
    <h3>Catatan kalori</h3>
    <p>Catat makanan dan lihat sisa kalori hari ini.</p>
  </a>
</div>

<?php include 'footer.php'; ?>