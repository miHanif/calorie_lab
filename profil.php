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
function selisih_hari($a, $b) { return (int)(new DateTime($a))->diff(new DateTime($b))->format('%r%a'); }

$q = $pdo->prepare('SELECT nama, email FROM users WHERE id = ?');
$q->execute([$uid]);
$user = $q->fetch();

$q = $pdo->prepare("SELECT * FROM rencana_diet WHERE user_id = ? AND status = 'aktif' ORDER BY id DESC LIMIT 1");
$q->execute([$uid]);
$aktif = $q->fetch();

$error = '';
$pesan = $_SESSION['pesan'] ?? '';
unset($_SESSION['pesan']);

// Catat berat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'catat' && $aktif) {
    $berat   = (float)($_POST['berat'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? '';
    $cat     = mb_substr(trim($_POST['catatan'] ?? ''), 0, 255);
    $d = DateTime::createFromFormat('Y-m-d', $tanggal);

    if (!$d || $d->format('Y-m-d') !== $tanggal || $tanggal > $hari_ini || $tanggal < $aktif['tanggal_mulai']) {
        $error = 'Tanggal tidak valid. Pilih tanggal antara awal rencana dan hari ini.';
    } elseif ($berat < 20 || $berat > 250) {
        $error = 'Berat harus antara 20 dan 250 kg.';
    } else {
        // Berat sebelumnya (untuk cek laju perubahan)
        $q = $pdo->prepare('SELECT berat_kg, tanggal FROM catatan_berat WHERE user_id = ? AND rencana_id = ? AND tanggal < ? ORDER BY tanggal DESC LIMIT 1');
        $q->execute([$uid, $aktif['id'], $tanggal]);
        $prev = $q->fetch();

        // Satu catatan per tanggal: perbarui kalau sudah ada
        $q = $pdo->prepare('SELECT id FROM catatan_berat WHERE user_id = ? AND rencana_id = ? AND tanggal = ?');
        $q->execute([$uid, $aktif['id'], $tanggal]);
        if ($ada = $q->fetch()) {
            $pdo->prepare('UPDATE catatan_berat SET berat_kg = ?, catatan = ? WHERE id = ?')->execute([$berat, $cat ?: null, $ada['id']]);
        } else {
            $pdo->prepare('INSERT INTO catatan_berat (user_id, rencana_id, berat_kg, tanggal, catatan) VALUES (?, ?, ?, ?, ?)')
                ->execute([$uid, $aktif['id'], $berat, $tanggal, $cat ?: null]);
        }

        // Berat terbaru di rencana ini
        $q = $pdo->prepare('SELECT berat_kg FROM catatan_berat WHERE user_id = ? AND rencana_id = ? ORDER BY tanggal DESC, id DESC LIMIT 1');
        $q->execute([$uid, $aktif['id']]);
        $terbaru = (float)$q->fetchColumn();

        $msg = 'Berat ' . nf($berat, 1) . ' kg tersimpan.';

        // Cek laju perubahan (hanya kalau jarak catatan minimal 7 hari)
        if ($prev) {
            $hari = selisih_hari($prev['tanggal'], $tanggal);
            if ($hari >= 7) {
                $per_minggu = ($prev['berat_kg'] - $berat) / ($hari / 7);
                if ($aktif['tujuan'] === 'turun' && $per_minggu > 1.0) {
                    $msg .= ' Peringatan: turunmu sekitar ' . nf($per_minggu, 1) . ' kg per minggu, lebih cepat dari laju aman. Pertimbangkan berkonsultasi dengan ahli gizi.';
                } elseif ($aktif['tujuan'] === 'naik' && -$per_minggu > 0.5) {
                    $msg .= ' Catatan: kenaikanmu sekitar ' . nf(-$per_minggu, 1) . ' kg per minggu, lebih cepat dari laju yang disarankan.';
                }
            }
        }

        // Target tercapai?
        $tercapai = ($aktif['tujuan'] === 'turun' && $terbaru <= $aktif['berat_target'])
                 || ($aktif['tujuan'] === 'naik'  && $terbaru >= $aktif['berat_target']);

        if ($tercapai) {
            $pdo->prepare("UPDATE rencana_diet SET status = 'selesai' WHERE id = ?")->execute([$aktif['id']]);
            $msg .= ' Selamat, kamu mencapai berat targetmu! Rencana ditandai selesai.';
        } else {
            // Hitung ulang target kalori dengan berat terbaru
            $selesai = (new DateTime($aktif['tanggal_mulai']))->modify('+' . (int)$aktif['durasi_hari'] . ' days')->format('Y-m-d');
            $sisa    = max(14, selisih_hari($hari_ini, $selesai));
            $bmr     = 10 * $terbaru + 6.25 * $aktif['tinggi_cm'] - 5 * $aktif['usia'] + ($aktif['jenis_kelamin'] === 'L' ? 5 : -161);
            $tdee    = $bmr * $aktif['aktivitas'];
            $per_hari = ($aktif['tujuan'] === 'pertahankan') ? 0 : ($aktif['berat_target'] - $terbaru) * 7700 / $sisa;
            $per_hari = max(-1000, min(500, $per_hari));
            $batas   = ($aktif['jenis_kelamin'] === 'L') ? 1500 : 1200;
            $target  = (int)(round(max($tdee + $per_hari, $batas) / 10) * 10);

            if ($target !== (int)$aktif['target_kalori']) {
                $pdo->prepare('UPDATE rencana_diet SET target_kalori = ? WHERE id = ?')->execute([$target, $aktif['id']]);
                $msg .= ' Target kalorimu diperbarui menjadi ' . nf($target) . ' kkal.';
            }
        }
        $_SESSION['pesan'] = $msg;
        header('Location: profil.php');
        exit;
    }
}

// Data tampilan
$catatan = [];
$rencana_lama = [];
if ($aktif) {
    $q = $pdo->prepare('SELECT * FROM catatan_berat WHERE user_id = ? AND rencana_id = ? ORDER BY tanggal, id');
    $q->execute([$uid, $aktif['id']]);
    $catatan = $q->fetchAll();
}
$q = $pdo->prepare("SELECT * FROM rencana_diet WHERE user_id = ? AND status <> 'aktif' ORDER BY id DESC LIMIT 5");
$q->execute([$uid]);
$rencana_lama = $q->fetchAll();

if ($aktif && $catatan) {
    $berat_skrg = (float)end($catatan)['berat_kg'];
    $durasi     = (int)$aktif['durasi_hari'];
    $lewat      = max(0, min($durasi, selisih_hari($aktif['tanggal_mulai'], $hari_ini)));
    $hari_ke    = min($durasi, $lewat + 1);
    $ideal      = $aktif['berat_awal'] + ($aktif['berat_target'] - $aktif['berat_awal']) * $lewat / $durasi;
    $beda       = $berat_skrg - $ideal;   // positif = lebih berat dari jalur ideal
    $sel_total  = $aktif['berat_target'] - $aktif['berat_awal'];
    $capai      = $sel_total != 0 ? max(0, min(100, (int)round(($berat_skrg - $aktif['berat_awal']) / $sel_total * 100))) : 0;

    if ($aktif['tujuan'] === 'pertahankan') {
        $status = abs($beda) <= 1 ? ['Sesuai jalur', 'var(--normal)'] : ['Menyimpang dari berat target', 'var(--gemuk)'];
    } else {
        $arah = ($aktif['tujuan'] === 'turun') ? -$beda : $beda;   // positif = lebih maju dari jalur
        if ($arah > 0.5)       $status = ['Lebih cepat dari rencana', 'var(--kurus)'];
        elseif ($arah >= -0.5) $status = ['Sesuai jalur', 'var(--normal)'];
        else                   $status = ['Tertinggal dari jalur', 'var(--gemuk)'];
    }

    $titik = [];
    foreach ($catatan as $c) {
        $titik[] = ['x' => selisih_hari($aktif['tanggal_mulai'], $c['tanggal']), 'y' => (float)$c['berat_kg']];
    }
}

$judul = 'Profil dan Perkembangan';
include 'header.php';
?>

<h2>Profil dan perkembangan</h2>

<?php if ($pesan): ?><div class="alert sukses"><?= h($pesan) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>

<div class="hasil" style="margin-bottom:20px">
  <small>Data akun</small>
  <p style="margin-top:6px"><b><?= h($user['nama']) ?></b> &middot; <?= h($user['email']) ?>
  <?php if ($aktif): ?> &middot; Tinggi <b><?= nf($aktif['tinggi_cm']) ?> cm</b> &middot; Usia <b><?= (int)$aktif['usia'] ?></b><?php endif; ?></p>
</div>

<?php if (!$aktif): ?>
  <div class="kosong">
    Kamu belum punya rencana diet yang aktif, jadi belum ada perkembangan yang bisa dipantau.<br><br>
    <a class="tombol" href="rencana.php">Buat rencana diet</a>
  </div>

<?php else: ?>
  <p class="teks-redup">
    <?= h($label[$aktif['tujuan']]) ?> &middot; hari ke-<?= $hari_ke ?> dari <?= $durasi ?> hari &middot;
    <span class="badge" style="background:<?= h($status[1]) ?>"><?= h($status[0]) ?></span>
  </p>

  <div class="ringkas">
    <div class="kartu"><small>Berat awal</small><div class="angka-besar"><?= nf($aktif['berat_awal'], 1) ?></div>kg</div>
    <div class="kartu"><small>Berat sekarang</small><div class="angka-besar"><?= nf($berat_skrg, 1) ?></div>kg</div>
    <div class="kartu"><small>Berat target</small><div class="angka-besar"><?= nf($aktif['berat_target'], 1) ?></div>kg</div>
    <div class="kartu"><small>Target kalori harian</small><div class="angka-besar"><?= nf($aktif['target_kalori']) ?></div>kkal</div>
  </div>

  <?php if ($aktif['tujuan'] !== 'pertahankan'): ?>
    <div class="progres"><div class="progres-isi" style="width:<?= $capai ?>%"></div></div>
    <p class="catatan"><?= $capai ?>% menuju berat target</p>
  <?php endif; ?>

  <div class="hasil" style="margin:20px 0">
    <h3 style="margin-bottom:10px">Grafik berat badan</h3>
    <canvas id="grafik" height="110"></canvas>
    <p class="catatan">Garis putus-putus adalah jalur ideal dari berat awal ke target.</p>
  </div>

  <div class="hasil" style="margin-bottom:20px">
    <h3 style="margin-bottom:6px">Catat berat badan</h3>
    <form method="post" class="form-tambah">
      <input type="hidden" name="aksi" value="catat">
      <div style="max-width:180px"><label for="tanggal">Tanggal</label>
        <input type="date" id="tanggal" name="tanggal" value="<?= h($hari_ini) ?>" min="<?= h($aktif['tanggal_mulai']) ?>" max="<?= h($hari_ini) ?>" required></div>
      <div style="max-width:160px"><label for="berat">Berat (kg)</label>
        <input type="number" id="berat" name="berat" step="0.1" required></div>
      <div><label for="catatan">Catatan (opsional)</label>
        <input type="text" id="catatan" name="catatan" maxlength="255" placeholder="Misalnya: minggu ini banyak lembur"></div>
      <button type="submit" class="tombol">Simpan</button>
    </form>
    <p class="catatan">Menimbanglah seminggu sekali pada hari dan jam yang sama. Berat harian naik turun karena cairan. Setiap catatan baru memperbarui target kalorimu.</p>
  </div>

  <h3 style="margin-bottom:10px">Riwayat berat</h3>
  <div class="gulir">
    <table class="tabel">
      <thead><tr><th>Tanggal</th><th>Berat</th><th>Perubahan</th><th>Catatan</th></tr></thead>
      <tbody>
      <?php
      $sebelum = null;
      $baris = [];
      foreach ($catatan as $c) { $baris[] = [$c, $sebelum]; $sebelum = (float)$c['berat_kg']; }
      foreach (array_reverse($baris) as [$c, $sb]):
          $selisih = $sb === null ? null : (float)$c['berat_kg'] - $sb; ?>
        <tr>
          <td><?= h(tgl($c['tanggal'])) ?></td>
          <td><?= nf($c['berat_kg'], 1) ?> kg</td>
          <td><?= $selisih === null ? '-' : (($selisih > 0 ? '+' : '') . nf($selisih, 1) . ' kg') ?></td>
          <td><?= h($c['catatan'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    new Chart(document.getElementById('grafik'), {
      type: 'line',
      data: { datasets: [
        { label: 'Berat kamu (kg)', data: <?= json_encode($titik) ?>, borderColor: '#1F8A5B', backgroundColor: '#1F8A5B', tension: .25, pointRadius: 4 },
        { label: 'Jalur ideal', data: [{x:0, y:<?= (float)$aktif['berat_awal'] ?>}, {x:<?= $durasi ?>, y:<?= (float)$aktif['berat_target'] ?>}],
          borderColor: '#F5C542', borderDash: [6,6], pointRadius: 0 }
      ]},
      options: { scales: { x: { type: 'linear', min: 0, max: <?= $durasi ?>, title: { display: true, text: 'Hari ke-' } },
                           y: { title: { display: true, text: 'kg' } } } }
    });
  </script>
<?php endif; ?>

<?php if ($rencana_lama): ?>
  <h3 style="margin:24px 0 10px">Rencana sebelumnya</h3>
  <?php foreach ($rencana_lama as $r): ?>
    <div class="baris-catatan">
      <div><b><?= h($label[$r['tujuan']]) ?></b><br>
        <small><?= h(tgl($r['tanggal_mulai'])) ?> &middot; <?= nf($r['berat_awal'], 1) ?> ke <?= nf($r['berat_target'], 1) ?> kg &middot; <?= (int)$r['durasi_hari'] ?> hari</small></div>
      <span class="badge" style="background:<?= $r['status'] === 'selesai' ? 'var(--normal)' : '#888' ?>"><?= h(ucfirst($r['status'])) ?></span>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<p class="catatan">Perhitungan bersifat perkiraan dan edukatif, bukan pengganti saran dokter atau ahli gizi.</p>

<?php include 'footer.php'; ?>