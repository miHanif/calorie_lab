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
  <title>Calorie Lab — Nutrisi Berbasis Data untuk Tubuhmu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styling.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
  <div class="container nav-isi">
    <div class="nav-brand">
      <a class="logo" href="index.php">
        <span class="logo-ikon" aria-hidden="true">🌿</span>
        <span class="logo-teks">Calorie <em>Lab</em></span>
      </a>
    </div>
    <div class="nav-menu">
      <?php if ($sudah_login): ?>
        <a href="dashboard.php">Dashboard</a>
        <a class="tombol kecil" href="logout.php">Keluar</a>
      <?php else: ?>
        <a href="login.php">Masuk</a>
        <a class="tombol kecil" href="register.php">Daftar Akun</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- Hero -->
<header class="hero">
  <div class="container">
    <div style="display:inline-flex;align-items:center;gap:8px;padding:6px 16px;background:var(--mint);border-radius:var(--radius-pil);color:var(--hijau);font-size:0.84rem;font-weight:700;margin-bottom:18px;">
      <span>🌱</span> Nutrisi Cerdas Berbasis Sains
    </div>
    <h1>Nutrisi terukur,<br><em style="font-style:normal;color:var(--hijau)">sesuai kebutuhan tubuhmu.</em></h1>
    <p>Hitung BMI, rancang program diet seimbang, dan catat kalori harianmu secara presisi. Bebas dari metode diet ekstrem — hanya konsistensi terukur yang berkelanjutan.</p>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
      <?php if ($sudah_login): ?>
        <a class="tombol tombol-besar" href="dashboard.php">Buka Dashboard Saya →</a>
        <a class="tombol tombol-sekunder tombol-besar" href="menu.php">Lihat Menu Rekomendasi</a>
      <?php else: ?>
        <a class="tombol tombol-besar" href="register.php">Mulai Hitung BMI Gratis →</a>
        <a class="tombol tombol-sekunder tombol-besar" href="login.php">Sudah punya akun? Masuk</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- Fitur -->
<section style="padding:56px 0 72px;">
  <div class="container">
    <div style="text-align:center;max-width:640px;margin:0 auto 44px;">
      <span style="color:var(--hijau);font-size:0.78rem;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;">FITUR UTAMA</span>
      <h2 style="margin-top:6px;margin-bottom:10px;">Langkah terarah untuk pola makan sehat</h2>
      <p style="color:var(--teks-redup);margin:0;">Dirancang khusus dengan prinsip nutrisi klinis untuk membantumu mencapai target kebugaran secara aman dan terukur.</p>
    </div>
    <div class="fitur">
      <a class="kartu kartu-link" href="<?= $sudah_login ? 'bmi.php' : 'register.php' ?>">
        <div style="width:48px;height:48px;border-radius:var(--radius-pil);background:var(--mint);display:grid;place-items:center;font-size:1.4rem;margin-bottom:16px;">🔬</div>
        <h3>Kalkulator BMI & Kalori</h3>
        <p>Hitung indeks massa tubuh dan estimasi kebutuhan kalori harian menggunakan formula Mifflin-St Jeor.</p>
      </a>
      <a class="kartu kartu-link" href="<?= $sudah_login ? 'rencana.php' : 'register.php' ?>">
        <div style="width:48px;height:48px;border-radius:var(--radius-pil);background:var(--mint);display:grid;place-items:center;font-size:1.4rem;margin-bottom:16px;">📋</div>
        <h3>Rencana Diet Personal</h3>
        <p>Tentukan tujuan (turun, naik, atau pertahankan berat) dan durasi program dietmu secara terstruktur.</p>
      </a>
      <a class="kartu kartu-link" href="<?= $sudah_login ? 'menu.php' : 'register.php' ?>">
        <div style="width:48px;height:48px;border-radius:var(--radius-pil);background:var(--mint);display:grid;place-items:center;font-size:1.4rem;margin-bottom:16px;">🥗</div>
        <h3>Rekomendasi Menu Sehat</h3>
        <p>Pilih menu sarapan, makan siang, makan malam, dan camilan yang pas dengan budget serta target kalorimu.</p>
      </a>
      <a class="kartu kartu-link" href="<?= $sudah_login ? 'catatan.php' : 'register.php' ?>">
        <div style="width:48px;height:48px;border-radius:var(--radius-pil);background:var(--mint);display:grid;place-items:center;font-size:1.4rem;margin-bottom:16px;">📝</div>
        <h3>Catatan Kalori Harian</h3>
        <p>Catat asupan makanan per porsi dan pantau sisa kuota kalori harianmu secara langsung.</p>
      </a>
      <a class="kartu kartu-link" href="<?= $sudah_login ? 'profil.php' : 'register.php' ?>">
        <div style="width:48px;height:48px;border-radius:var(--radius-pil);background:var(--mint);display:grid;place-items:center;font-size:1.4rem;margin-bottom:16px;">📈</div>
        <h3>Grafik Progres Berat</h3>
        <p>Pantau timbangan berkala dan lihat perbandingan perkembangan berat badanmu menuju target.</p>
      </a>
    </div>
  </div>
</section>

<!-- CTA bawah -->
<?php if (!$sudah_login): ?>
<section style="padding:60px 0 76px;text-align:center;">
  <div class="container" style="max-width:620px;">
    <div style="background:var(--permukaan);border:1px solid var(--garis);border-radius:var(--radius-kartu);padding:clamp(32px, 5vw, 48px);box-shadow:var(--bayangan);">
      <span style="color:var(--hijau);font-size:0.78rem;font-weight:800;letter-spacing:0.1em;text-transform:uppercase;">MULAI SEKARANG</span>
      <h2 style="margin:8px 0 12px;">Mulai perjalanan sehatmu hari ini</h2>
      <p style="color:var(--teks-redup);margin-bottom:28px;">Daftar akun gratis untuk menghitung target kalori dan mengatur pola makan harianmu secara cerdas.</p>
      <a class="tombol tombol-besar" href="register.php">Daftar Akun Gratis Sekarang →</a>
    </div>
  </div>
</section>
<?php endif; ?>

<footer>
  <p>© <?= date('Y') ?> Calorie Lab · Informasi bersifat edukatif, bukan pengganti saran medis.</p>
</footer>

</body>
</html>
