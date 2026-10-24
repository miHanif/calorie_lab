<?php
require_once 'koneksi.php';
wajib_login();
date_default_timezone_set('Asia/Jakarta');

$uid      = $_SESSION['user_id'];
$hari_ini = date('Y-m-d');
$bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
          'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$label = ['turun' => 'Menurunkan berat badan', 'naik' => 'Menaikkan berat badan',
          'pertahankan' => 'Mempertahankan berat badan'];

function nf($n, $d = 0) { return number_format($n, $d, ',', '.'); }
function tgl($s) { global $bulan; $t = strtotime($s); return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t); }

// Data dari hitung BMI terakhir
$q = $pdo->prepare('SELECT r.*, k.kode AS kat_kode, k.nama AS kat_nama, k.warna
                    FROM riwayat_bmi r JOIN kategori_bmi k ON k.id = r.kategori_id
                    WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 1');
$q->execute([$uid]);
$bmi = $q->fetch();

// Rencana yang sedang aktif
$q = $pdo->prepare("SELECT * FROM rencana_diet WHERE user_id = ? AND status = 'aktif' ORDER BY id DESC LIMIT 1");
$q->execute([$uid]);
$aktif = $q->fetch();

$error = '';
$pesan = $_SESSION['pesan'] ?? '';
unset($_SESSION['pesan']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // Batalkan rencana
    if ($aksi === 'batal' && $aktif) {
        $pdo->prepare("UPDATE rencana_diet SET status = 'batal' WHERE id = ? AND user_id = ?")
            ->execute([$aktif['id'], $uid]);
        $_SESSION['pesan'] = 'Rencana dibatalkan. Kamu bisa membuat rencana baru.';
        header('Location: rencana.php');
        exit;
    }

    // Buat rencana baru
    if ($aksi === 'buat' && $bmi && !$aktif) {
        $tujuan = $_POST['tujuan'] ?? '';
        $durasi = (int)($_POST['durasi'] ?? 0);
        $aktv   = (float)($_POST['aktivitas'] ?? 1.2);

        $berat  = (float)$bmi['berat_kg'];
        $tinggi = (float)$bmi['tinggi_cm'];
        $usia   = (int)$bmi['usia'];
        $jk     = $bmi['jenis_kelamin'];
        $target_berat = ($tujuan === 'pertahankan') ? $berat : (float)($_POST['berat_target'] ?? 0);

        $tm = $tinggi / 100;
        $min_berat  = 18.5 * $tm * $tm;   // batas BMI normal bawah
        $maks_berat = 25.0 * $tm * $tm;   // batas BMI normal atas

        if (!in_array($tujuan, ['turun', 'naik', 'pertahankan'], true)
            || !in_array($durasi, [30, 60, 90, 180], true)
            || !in_array($aktv, [1.2, 1.375, 1.55], true)) {
            $error = 'Isian tidak valid. Silakan ulangi.';
        } elseif ($usia < 18) {
            $error = 'Rencana otomatis hanya untuk usia 18 tahun ke atas. Untuk usia di bawah itu, sebaiknya konsultasi ke dokter atau ahli gizi.';
        } elseif ($tujuan === 'turun') {
            if ($bmi['kat_kode'] === 'kurus')            $error = 'BMI-mu masuk kategori Kurus, jadi menurunkan berat badan tidak disarankan.';
            elseif ($target_berat >= $berat)             $error = 'Berat target harus lebih rendah dari berat sekarang.';
            elseif ($target_berat < $min_berat)          $error = 'Berat target terlalu rendah. Untuk tinggimu, batas amannya sekitar ' . nf($min_berat, 1) . ' kg (BMI 18,5).';
            elseif ($berat - $target_berat < 1)          $error = 'Selisih minimal 1 kg. Untuk selisih lebih kecil, pilih mempertahankan berat.';
        } elseif ($tujuan === 'naik') {
            if (in_array($bmi['kat_kode'], ['gemuk', 'obesitas'], true)) $error = 'BMI-mu di atas normal, jadi menaikkan berat badan tidak disarankan.';
            elseif ($target_berat <= $berat)             $error = 'Berat target harus lebih tinggi dari berat sekarang.';
            elseif ($target_berat > $maks_berat)         $error = 'Berat target terlalu tinggi. Untuk tinggimu, batas atas normalnya sekitar ' . nf($maks_berat, 1) . ' kg (BMI 25).';
            elseif ($target_berat - $berat < 1)          $error = 'Selisih minimal 1 kg. Untuk selisih lebih kecil, pilih mempertahankan berat.';
        }

        // Cek laju perubahan yang aman: turun maks 1 kg/minggu, naik maks 0,5 kg/minggu
        if (!$error && $tujuan !== 'pertahankan') {
            $selisih   = abs($target_berat - $berat);
            $laju_maks = ($tujuan === 'turun') ? 1.0 : 0.5;
            if ($selisih / ($durasi / 7) > $laju_maks) {
                $min_hari = (int)ceil($selisih / $laju_maks * 7);
                $error = 'Terlalu cepat. Dengan laju aman maksimal ' . nf($laju_maks, 1) . ' kg per minggu, perubahan '
                       . nf($selisih, 1) . ' kg butuh sekitar ' . ceil($min_hari / 30) . ' bulan'
                       . ($min_hari > 180 ? ' (melebihi 6 bulan, coba perkecil targetmu)' : ', pilih durasi yang lebih panjang') . '.';
            }
        }

        if (!$error) {
            // Kebutuhan kalori (Mifflin-St Jeor), lalu sesuaikan dengan target
            $bmr  = 10 * $berat + 6.25 * $tinggi - 5 * $usia + ($jk === 'L' ? 5 : -161);
            $tdee = $bmr * $aktv;
            $per_hari = ($target_berat - $berat) * 7700 / $durasi;   // perkiraan 7.700 kkal per kg
            $per_hari = max(-1000, min(500, $per_hari));
            $batas = ($jk === 'L') ? 1500 : 1200;
            $target_kal = $tdee + $per_hari;
            $dibatasi = false;
            if ($target_kal < $batas) { $target_kal = $batas; $dibatasi = true; }
            $target_kal = (int)(round($target_kal / 10) * 10);

            $pdo->beginTransaction();
            $ins = $pdo->prepare('INSERT INTO rencana_diet
                (user_id, tujuan, berat_awal, berat_target, tinggi_cm, usia, jenis_kelamin, aktivitas, tanggal_mulai, durasi_hari, target_kalori)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([$uid, $tujuan, $berat, $target_berat, $tinggi, $usia, $jk, $aktv, $hari_ini, $durasi, $target_kal]);
            $rid = $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO catatan_berat (user_id, rencana_id, berat_kg, tanggal, catatan) VALUES (?, ?, ?, ?, ?)')
                ->execute([$uid, $rid, $berat, $hari_ini, 'Berat awal rencana']);
            $pdo->commit();

            $_SESSION['pesan'] = 'Rencana dibuat. Target kalori harianmu ' . nf($target_kal) . ' kkal.'
                . ($dibatasi ? ' Target dinaikkan ke batas aman minimal, jadi perubahan berat bisa lebih lambat dari rencana.' : '');
            header('Location: rencana.php');
            exit;
        }
    }
}

$judul = 'Rencana Diet';
include 'header.php';
?>

<h2>Rencana diet</h2>

<?php if ($pesan): ?><div class="alert sukses"><?= h($pesan) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>

<?php if (!$bmi): ?>
  <div class="kosong">
    Rencana dibuat dari data BMI-mu. Hitung BMI dulu ya.<br><br>
    <a class="tombol" href="bmi.php">Hitung BMI dulu</a>
  </div>

<?php elseif ($aktif):
    $mulai   = new DateTime($aktif['tanggal_mulai']);
    $selesai = (clone $mulai)->modify('+' . (int)$aktif['durasi_hari'] . ' days');
    $hari_ke = min((int)$aktif['durasi_hari'], (int)$mulai->diff(new DateTime($hari_ini))->days + 1);
    $persen  = (int)round($hari_ke / $aktif['durasi_hari'] * 100); ?>

  <p class="teks-redup"><?= h($label[$aktif['tujuan']]) ?> &middot; <?= h(tgl($aktif['tanggal_mulai'])) ?> sampai <?= h($selesai->format('Y-m-d') ? tgl($selesai->format('Y-m-d')) : '') ?></p>

  <div class="ringkas">
    <div class="kartu"><small>Berat awal</small><div class="angka-besar"><?= nf($aktif['berat_awal'], 1) ?></div>kg</div>
    <div class="kartu"><small>Berat target</small><div class="angka-besar"><?= nf($aktif['berat_target'], 1) ?></div>kg</div>
    <div class="kartu"><small>Target kalori harian</small><div class="angka-besar"><?= nf($aktif['target_kalori']) ?></div>kkal</div>
  </div>

  <div class="progres"><div class="progres-isi" style="width:<?= $persen ?>%"></div></div>
  <p class="catatan">Hari ke-<?= $hari_ke ?> dari <?= (int)$aktif['durasi_hari'] ?> hari (<?= $persen ?>%)</p>

  <div class="hasil" style="margin-top:20px">
    <p>Pantau perkembangan beratmu dan ikuti menu rekomendasi setiap hari. Perkiraan kebutuhan kalori bersifat edukatif, bukan pengganti saran dokter atau ahli gizi.</p>
    <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
      <a class="tombol" href="menu.php">Lihat menu rekomendasi</a>
      <a class="tombol garis" href="catatan.php">Catatan kalori</a>
    </div>
    <form method="post" style="margin-top:14px">
      <input type="hidden" name="aksi" value="batal">
      <button type="submit" class="tombol hapus" onclick="return confirm('Batalkan rencana ini?')">Batalkan rencana</button>
    </form>
  </div>

<?php else:
    $tm = $bmi['tinggi_cm'] / 100; ?>

  <div class="hasil" style="margin-bottom:20px">
    <small>Data awalmu (dari hitung BMI terakhir)</small>
    <p style="margin-top:6px">
      Berat <b><?= nf($bmi['berat_kg'], 1) ?> kg</b> &middot; Tinggi <b><?= nf($bmi['tinggi_cm'], 0) ?> cm</b> &middot; Usia <b><?= (int)$bmi['usia'] ?></b> &middot;
      BMI <b><?= nf($bmi['bmi'], 1) ?></b> <span class="badge kategori-<?= h($bmi['kat_kode']) ?>"><?= h($bmi['kat_nama']) ?></span>
    </p>
    <p class="catatan">Rentang berat yang masuk BMI normal untuk tinggimu: sekitar <?= nf(18.5 * $tm * $tm, 1) ?> sampai <?= nf(25 * $tm * $tm, 1) ?> kg.
      Berubah? <a href="bmi.php">Hitung BMI lagi</a>.</p>
  </div>

  <div class="kotak-form" style="max-width:none">
    <form method="post">
      <input type="hidden" name="aksi" value="buat">

      <label for="tujuan">Tujuan</label>
      <select id="tujuan" name="tujuan">
        <option value="turun" <?= ($_POST['tujuan'] ?? '') === 'turun' ? 'selected' : '' ?>>Menurunkan berat badan</option>
        <option value="naik" <?= ($_POST['tujuan'] ?? '') === 'naik' ? 'selected' : '' ?>>Menaikkan berat badan</option>
        <option value="pertahankan" <?= ($_POST['tujuan'] ?? '') === 'pertahankan' ? 'selected' : '' ?>>Mempertahankan berat badan</option>
      </select>

      <div id="kotak-target">
        <label for="berat_target">Berat target (kg)</label>
        <input type="number" id="berat_target" name="berat_target" step="0.1" value="<?= h($_POST['berat_target'] ?? '') ?>">
      </div>

      <label for="durasi">Durasi</label>
      <select id="durasi" name="durasi">
        <?php foreach ([30 => '1 bulan', 60 => '2 bulan', 90 => '3 bulan', 180 => '6 bulan'] as $hr => $tx): ?>
          <option value="<?= $hr ?>" <?= (int)($_POST['durasi'] ?? 90) === $hr ? 'selected' : '' ?>><?= $tx ?></option>
        <?php endforeach; ?>
      </select>

      <label for="aktivitas">Aktivitas harian</label>
      <select id="aktivitas" name="aktivitas">
        <option value="1.2">Jarang olahraga (banyak duduk)</option>
        <option value="1.375" <?= ($_POST['aktivitas'] ?? '') === '1.375' ? 'selected' : '' ?>>Olahraga ringan 1-3x seminggu</option>
        <option value="1.55" <?= ($_POST['aktivitas'] ?? '') === '1.55' ? 'selected' : '' ?>>Olahraga sedang 3-5x seminggu</option>
      </select>

      <button type="submit" class="tombol penuh">Buat rencana</button>
    </form>
    <p class="catatan">Laju perubahan dibatasi supaya aman: turun maksimal sekitar 1 kg per minggu, naik sekitar 0,5 kg per minggu. Perhitungan bersifat perkiraan.</p>
  </div>

  <script>
    const t = document.getElementById('tujuan'), b = document.getElementById('kotak-target');
    function ubah() { b.style.display = (t.value === 'pertahankan') ? 'none' : 'block'; }
    t.addEventListener('change', ubah); ubah();
  </script>
<?php endif; ?>

<?php include 'footer.php'; ?>