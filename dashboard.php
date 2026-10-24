<?php
require_once 'koneksi.php';
wajib_login();
date_default_timezone_set('Asia/Jakarta');

$uid = $_SESSION['user_id'];
$hari_ini = date('Y-m-d');

// Rencana diet yang sedang aktif (kalau ada)
$st = $pdo->prepare("SELECT * FROM rencana_diet WHERE user_id = ? AND status = 'aktif' ORDER BY id DESC LIMIT 1");
$st->execute([$uid]);
$rencana = $st->fetch(PDO::FETCH_ASSOC);

// Hasil BMI terakhir (kalau ada)
$st = $pdo->prepare("SELECT r.bmi, r.target_kalori, k.nama AS kategori
                     FROM riwayat_bmi r
                     JOIN kategori_bmi k ON k.id = r.kategori_id
                     WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 1");
$st->execute([$uid]);
$bmi = $st->fetch(PDO::FETCH_ASSOC);

$st = $pdo->prepare('SELECT mh.id, COUNT(i.id) AS jumlah_menu
                     FROM menu_harian mh
                     LEFT JOIN menu_harian_item i ON i.menu_harian_id = mh.id
                     WHERE mh.user_id = ? AND mh.tanggal = ?
                     GROUP BY mh.id');
$st->execute([$uid, $hari_ini]);
$menu_harian = $st->fetch(PDO::FETCH_ASSOC);

// Berat terakhir yang dicatat pada rencana aktif
$berat_terakhir = null;
if ($rencana) {
    $st = $pdo->prepare("SELECT berat_kg FROM catatan_berat WHERE user_id = ? AND rencana_id = ? ORDER BY tanggal DESC, id DESC LIMIT 1");
    $st->execute([$uid, $rencana['id']]);
    $berat_terakhir = $st->fetchColumn();
    if ($berat_terakhir === false) {
        $berat_terakhir = $rencana['berat_awal'];
    }
}

// Target kalori: utamakan rencana aktif, kalau tidak ada pakai BMI
$target_kalori = $rencana['target_kalori'] ?? ($bmi['target_kalori'] ?? null);

$jumlah_makanan_hari_ini = 0;
if ($bmi) {
    date_default_timezone_set('Asia/Jakarta');
    $st = $pdo->prepare('SELECT COUNT(*) FROM catatan_makan WHERE user_id = ? AND tanggal = ?');
    $st->execute([$uid, date('Y-m-d')]);
    $jumlah_makanan_hari_ini = (int)$st->fetchColumn();
}

if (!$bmi) {
    $langkah = [
        'judul' => 'Mulai dengan menghitung BMI',
        'deskripsi' => 'Isi data dasar untuk melihat ringkasan dan target kalori perkiraan.',
        'tautan' => 'bmi.php',
        'tombol' => 'Hitung BMI',
    ];
} elseif (!$rencana) {
    $langkah = [
        'judul' => 'Tentukan rencana dietmu',
        'deskripsi' => 'Pilih tujuan dan durasi agar perkembanganmu punya target yang jelas.',
        'tautan' => 'rencana.php',
        'tombol' => 'Buat rencana',
    ];
} elseif ($jumlah_makanan_hari_ini === 0) {
    $langkah = [
        'judul' => 'Catat makanan hari ini',
        'deskripsi' => 'Mulai catat makanan agar ringkasan kalori harianmu terisi.',
        'tautan' => 'catatan.php',
        'tombol' => 'Buka catatan kalori',
    ];
} else {
    $langkah = [
        'judul' => 'Lanjutkan memantau progres',
        'deskripsi' => 'Kamu sudah mencatat makanan hari ini. Periksa perkembangan berat secara berkala.',
        'tautan' => 'profil.php',
        'tombol' => 'Lihat progres',
    ];
}

$label_tujuan = ['turun' => 'Menurunkan berat', 'naik' => 'Menaikkan berat', 'pertahankan' => 'Mempertahankan berat'];

$judul = 'Dashboard';
include 'header.php';
?>

<header class="dashboard-heading">
  <div>
    <p class="dashboard-eyebrow">DASHBOARD</p>
    <h2>Halo, <?= h($_SESSION['nama']) ?></h2>
    <p class="teks-redup">Ini ringkasan dan langkah kecil yang bisa kamu lakukan hari ini.</p>
  </div>
  <a class="tombol tombol-sekunder" href="profil.php">Lihat perkembangan</a>
</header>

<section aria-labelledby="ringkasan-judul">
  <div class="dashboard-section-heading">
    <div>
      <h3 id="ringkasan-judul">Ringkasan dietmu</h3>
      <p class="teks-redup">Angka bersifat perkiraan edukatif, bukan saran medis.</p>
    </div>
  </div>

  <div class="dashboard-ringkasan">
    <article class="dashboard-stat">
      <span class="dashboard-stat-label">BMI terakhir</span>
      <?php if ($bmi): ?>
        <strong class="dashboard-stat-value"><?= h($bmi['bmi']) ?></strong>
        <span class="dashboard-stat-note"><?= h($bmi['kategori']) ?></span>
      <?php else: ?>
        <strong class="dashboard-stat-empty">Belum dihitung</strong>
        <a class="dashboard-stat-link" href="bmi.php">Hitung BMI <span aria-hidden="true">&rarr;</span></a>
      <?php endif; ?>
    </article>

    <article class="dashboard-stat dashboard-stat-highlight">
      <span class="dashboard-stat-label">Target kalori harian</span>
      <?php if ($target_kalori): ?>
        <strong class="dashboard-stat-value"><?= number_format((float)$target_kalori, 0, ',', '.') ?></strong>
        <span class="dashboard-stat-note">kkal · perkiraan</span>
      <?php else: ?>
        <strong class="dashboard-stat-empty">Belum tersedia</strong>
        <span class="dashboard-stat-note">Hitung BMI untuk melihat perkiraan target.</span>
      <?php endif; ?>
    </article>

    <article class="dashboard-stat">
      <span class="dashboard-stat-label">Rencana diet</span>
      <?php if ($rencana): ?>
        <strong class="dashboard-stat-plan"><?= h($label_tujuan[$rencana['tujuan']] ?? $rencana['tujuan']) ?></strong>
        <span class="dashboard-stat-note">
          <?= h($berat_terakhir) ?> kg sekarang <span aria-hidden="true">&rarr;</span>
          <?= h($rencana['berat_target']) ?> kg target
        </span>
        <a class="dashboard-stat-link" href="rencana.php">Lihat rencana <span aria-hidden="true">&rarr;</span></a>
      <?php else: ?>
        <strong class="dashboard-stat-empty">Belum ada rencana</strong>
        <a class="dashboard-stat-link" href="rencana.php">Buat rencana <span aria-hidden="true">&rarr;</span></a>
      <?php endif; ?>
    </article>
  </div>
</section>

<section class="langkah-berikutnya" aria-labelledby="judul-langkah">
  <div>
    <small>LANGKAH BERIKUTNYA</small>
    <h3 id="judul-langkah"><?= h($langkah['judul']) ?></h3>
    <p><?= h($langkah['deskripsi']) ?></p>
  </div>
  <a class="tombol" href="<?= h($langkah['tautan']) ?>"><?= h($langkah['tombol']) ?></a>
</section>

<?php if ($menu_harian): ?>
  <section class="menu-harian-dashboard" aria-labelledby="menu-harian-judul">
    <div>
      <span class="dashboard-eyebrow">RENCANA MENU · <?= h(date('d-m-Y', strtotime($hari_ini))) ?></span>
      <h3 id="menu-harian-judul">Menu harianmu tersimpan</h3>
      <p><?= (int)$menu_harian['jumlah_menu'] ?> pilihan menu tersimpan. Rencana ini tidak otomatis masuk ke catatan makanan.</p>
    </div>
    <a class="tombol tombol-sekunder" href="menu.php?hari_ini=1">Buka menu harian</a>
  </section>
<?php endif; ?>

<section class="dashboard-fitur" aria-labelledby="fitur-judul">
  <div class="dashboard-section-heading">
    <div>
      <h3 id="fitur-judul">Fitur Calorie Lab</h3>
      <p class="teks-redup">Pilih bagian yang ingin kamu buka.</p>
    </div>
  </div>

<div class="dashboard-fitur-grid">
  <a class="kartu kartu-link" href="bmi.php">
    <span class="dashboard-card-label">01 · UKUR</span>
    <h3>Hitung BMI</h3>
    <p>Cek kategori tubuh dan target kalori harian.</p>
  </a>
  <a class="kartu kartu-link" href="menu.php">
    <span class="dashboard-card-label">02 · PILIH</span>
    <h3>Menu rekomendasi</h3>
    <p>Menu yang cocok untuk kategori BMI-mu.</p>
  </a>
  <a class="kartu kartu-link" href="catatan.php">
    <span class="dashboard-card-label">03 · CATAT</span>
    <h3>Catatan kalori</h3>
    <p>Catat makanan dan lihat sisa kalori hari ini.</p>
  </a>
  <a class="kartu kartu-link" href="profil.php">
    <span class="dashboard-card-label">04 · PANTAU</span>
    <h3>Profil dan perkembangan</h3>
    <p>Catat berat badan dan pantau progres dietmu.</p>
  </a>
  <a class="kartu kartu-link" href="rencana.php">
    <span class="dashboard-card-label">05 · RENCANAKAN</span>
    <h3>Rencana diet</h3>
    <p>Tentukan target berat badan dan durasi dietmu.</p>
  </a>
</div>
</section>

<?php include 'footer.php'; ?>