<?php
require_once 'koneksi.php';
wajib_login();

$error = '';
$hasil = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tinggi = (float)($_POST['tinggi'] ?? 0);
    $berat  = (float)($_POST['berat'] ?? 0);
    $usia   = (int)($_POST['usia'] ?? 0);
    $jk     = $_POST['jk'] ?? '';
    $aktif  = (float)($_POST['aktivitas'] ?? 1.2);

    if ($tinggi < 100 || $tinggi > 230 || $berat < 20 || $berat > 250
        || $usia < 10 || $usia > 100 || !in_array($jk, ['L', 'P'])) {
        $error = 'Periksa isianmu. Tinggi 100-230 cm, berat 20-250 kg, usia 10-100 tahun.';
    } else {
        // 1. Hitung BMI dan cari kategorinya di database
        $bmi = $berat / pow($tinggi / 100, 2);
        $q = $pdo->prepare('SELECT * FROM kategori_bmi WHERE ? BETWEEN bmi_min AND bmi_max LIMIT 1');
        $q->execute([round($bmi, 2)]);
        $kat = $q->fetch();

        // 2. Kebutuhan kalori (rumus Mifflin-St Jeor)
        $bmr  = 10 * $berat + 6.25 * $tinggi - 5 * $usia + ($jk === 'L' ? 5 : -161);
        $tdee = $bmr * $aktif;
        $penyesuaian = ['kurus' => 300, 'normal' => 0, 'gemuk' => -300, 'obesitas' => -500];
        $batas_bawah = ($jk === 'L') ? 1500 : 1200;   // batas aman
        $target = max($tdee + ($penyesuaian[$kat['kode']] ?? 0), $batas_bawah);
        $target = (int)(round($target / 10) * 10);

        // 3. Simpan ke riwayat
        $simpan = $pdo->prepare(
            'INSERT INTO riwayat_bmi (user_id, tinggi_cm, berat_kg, usia, jenis_kelamin, bmi, kategori_id, target_kalori)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $simpan->execute([$_SESSION['user_id'], $tinggi, $berat, $usia, $jk, round($bmi, 1), $kat['id'], $target]);

        $hasil = ['bmi' => round($bmi, 1), 'kat' => $kat, 'target' => $target];
    }
}

$judul = 'Hitung BMI';
include 'header.php';
?>

<h2>Hitung BMI</h2>
<p class="teks-redup">Isi data tubuhmu untuk mengetahui kategori BMI dan target kalori harian.</p>

<?php if ($error): ?>
  <div class="alert error"><?= h($error) ?></div>
<?php endif; ?>

<div class="dua-kolom">
  <div class="kotak-form" style="margin:0; max-width:none;">
    <form method="post">
      <label for="tinggi">Tinggi badan (cm)</label>
      <input type="number" id="tinggi" name="tinggi" step="0.1" value="<?= h($_POST['tinggi'] ?? '') ?>" required>

      <label for="berat">Berat badan (kg)</label>
      <input type="number" id="berat" name="berat" step="0.1" value="<?= h($_POST['berat'] ?? '') ?>" required>

      <label for="usia">Usia (tahun)</label>
      <input type="number" id="usia" name="usia" value="<?= h($_POST['usia'] ?? '') ?>" required>

      <label for="jk">Jenis kelamin</label>
      <select id="jk" name="jk" required>
        <option value="L" <?= ($_POST['jk'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
        <option value="P" <?= ($_POST['jk'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
      </select>

      <label for="aktivitas">Aktivitas harian</label>
      <select id="aktivitas" name="aktivitas">
        <option value="1.2">Jarang olahraga (banyak duduk)</option>
        <option value="1.375" <?= ($_POST['aktivitas'] ?? '') === '1.375' ? 'selected' : '' ?>>Olahraga ringan 1-3x seminggu</option>
        <option value="1.55" <?= ($_POST['aktivitas'] ?? '') === '1.55' ? 'selected' : '' ?>>Olahraga sedang 3-5x seminggu</option>
      </select>

      <button type="submit" class="tombol penuh">Hitung</button>
    </form>
  </div>

  <div>
    <?php if ($hasil): ?>
      <div class="hasil">
        <small>BMI kamu</small>
        <div class="angka-besar"><?= h(number_format($hasil['bmi'], 1, ',', '.')) ?></div>
        <span class="badge kategori-<?= h($hasil['kat']['kode']) ?>"><?= h($hasil['kat']['nama']) ?></span>
        <p style="margin-top:14px"><?= h($hasil['kat']['saran']) ?></p>
        <hr>
        <small>Target kalori harian</small>
        <div class="angka-besar"><?= h(number_format($hasil['target'], 0, ',', '.')) ?> <small>kkal</small></div>
        <a class="tombol" href="menu.php">Lihat menu yang cocok</a>
      </div>
    <?php else: ?>
      <div class="kosong">Hasilnya akan muncul di sini setelah kamu menekan <b>Hitung</b>.</div>
    <?php endif; ?>
    <p class="catatan">BMI hanya indikator kasar dan kurang cocok untuk atlet, ibu hamil, dan usia di bawah 18 tahun. Target kalori bersifat perkiraan.</p>
  </div>
</div>

<?php include 'footer.php'; ?>