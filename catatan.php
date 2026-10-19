<?php
require_once 'koneksi.php';
wajib_login();
date_default_timezone_set('Asia/Jakarta');

$uid      = $_SESSION['user_id'];
$hari_ini = date('Y-m-d');
$porsi_ok = [0.5, 1.0, 1.5, 2.0];

function nf($n) { return number_format($n, 0, ',', '.'); }

// Tambah atau hapus catatan (lalu muat ulang halaman supaya tidak terkirim dua kali)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $menu_id = (int)($_POST['menu_id'] ?? 0);
        $porsi   = (float)($_POST['porsi'] ?? 1);
        $cek = $pdo->prepare('SELECT id FROM menu_diet WHERE id = ?');
        $cek->execute([$menu_id]);
        if ($cek->fetch() && in_array($porsi, $porsi_ok, true)) {
            $ins = $pdo->prepare('INSERT INTO catatan_makan (user_id, menu_id, porsi, tanggal) VALUES (?, ?, ?, ?)');
            $ins->execute([$uid, $menu_id, $porsi, $hari_ini]);
        }
    } elseif ($aksi === 'hapus') {
        $id  = (int)($_POST['id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM catatan_makan WHERE id = ? AND user_id = ?');
        $del->execute([$id, $uid]);
    }
    header('Location: catatan.php');
    exit;
}

// BMI dan target terakhir
$q = $pdo->prepare('SELECT r.target_kalori, r.kategori_id, k.nama AS kat_nama, k.warna
                    FROM riwayat_bmi r JOIN kategori_bmi k ON k.id = r.kategori_id
                    WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 1');
$q->execute([$uid]);
$terakhir = $q->fetch();

$catatan = [];
$semua_menu = [];
$saran = [];
$total = 0;

if ($terakhir) {
    $target = (int)$terakhir['target_kalori'];

    // Catatan makan hari ini
    $q = $pdo->prepare("SELECT c.id, c.porsi, m.nama_menu, m.waktu_makan, m.kalori
                        FROM catatan_makan c JOIN menu_diet m ON m.id = c.menu_id
                        WHERE c.user_id = ? AND c.tanggal = ?
                        ORDER BY FIELD(m.waktu_makan,'pagi','siang','malam','camilan'), c.id");
    $q->execute([$uid, $hari_ini]);
    $catatan = $q->fetchAll();
    foreach ($catatan as $c) $total += $c['kalori'] * $c['porsi'];
    $total = (int)round($total);

    $sisa   = $target - $total;
    $persen = $target > 0 ? (int)round($total / $target * 100) : 0;
    $kelas  = $persen > 100 ? 'merah' : ($persen >= 90 ? 'kuning' : '');

    // Daftar menu untuk form
    $semua_menu = $pdo->query("SELECT id, nama_menu, waktu_makan, kalori FROM menu_diet
                               ORDER BY FIELD(waktu_makan,'pagi','siang','malam','camilan'), nama_menu")->fetchAll();

    // Saran menu yang masih muat di sisa kalori
    if ($sisa > 0) {
        $q = $pdo->prepare('SELECT m.* FROM menu_diet m
                            JOIN menu_kategori mk ON mk.menu_id = m.id
                            WHERE mk.kategori_id = ? AND m.kalori <= ?
                            ORDER BY RAND() LIMIT 4');
        $q->execute([$terakhir['kategori_id'], $sisa]);
        $saran = $q->fetchAll();
    }
}

$bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
          'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tanggal_teks = date('j') . ' ' . $bulan[(int)date('n')] . ' ' . date('Y');

$judul = 'Catatan Kalori';
include 'header.php';
?>

<h2>Catatan kalori</h2>

<?php if (!$terakhir): ?>
  <div class="kosong">
    Kamu belum punya target kalori. Hitung BMI dulu supaya sistem bisa menentukan targetmu.<br><br>
    <a class="tombol" href="bmi.php">Hitung BMI dulu</a>
  </div>

<?php else: ?>
  <p class="teks-redup">
    <?= h($tanggal_teks) ?> &middot; Kategori BMI:
    <span class="badge" style="background:<?= h($terakhir['warna']) ?>"><?= h($terakhir['kat_nama']) ?></span>
  </p>

  <div class="ringkas">
    <div class="kartu"><small>Target harian</small><div class="angka-besar"><?= nf($target) ?></div>kkal</div>
    <div class="kartu"><small>Sudah dimakan</small><div class="angka-besar"><?= nf($total) ?></div>kkal</div>
    <div class="kartu">
      <small><?= $sisa >= 0 ? 'Sisa kalori' : 'Melebihi target' ?></small>
      <div class="angka-besar"><?= nf(abs($sisa)) ?></div>kkal
    </div>
  </div>

  <div class="progres"><div class="progres-isi <?= $kelas ?>" style="width:<?= min(100, $persen) ?>%"></div></div>
  <p class="catatan"><?= $persen ?>% dari target
    <?php if ($sisa < 0): ?> &middot; Kamu sudah melewati target hari ini. Pilih menu yang lebih ringan untuk sisa hari.<?php endif; ?>
  </p>

  <div class="hasil" style="margin-bottom:20px">
    <h3 style="margin-bottom:10px">Catat makanan</h3>
    <form method="post" class="form-tambah">
      <input type="hidden" name="aksi" value="tambah">
      <div>
        <label for="menu_id">Menu</label>
        <select id="menu_id" name="menu_id" required>
          <option value="">Pilih menu...</option>
          <?php $w_sebelum = '';
          foreach ($semua_menu as $m):
              if ($m['waktu_makan'] !== $w_sebelum):
                  if ($w_sebelum !== '') echo '</optgroup>';
                  echo '<optgroup label="' . h(ucfirst($m['waktu_makan'])) . '">';
                  $w_sebelum = $m['waktu_makan'];
              endif; ?>
            <option value="<?= (int)$m['id'] ?>"><?= h($m['nama_menu']) ?> (<?= (int)$m['kalori'] ?> kkal)</option>
          <?php endforeach;
          if ($w_sebelum !== '') echo '</optgroup>'; ?>
        </select>
      </div>
      <div style="max-width:180px">
        <label for="porsi">Porsi</label>
        <select id="porsi" name="porsi">
          <option value="0.5">&frac12; porsi</option>
          <option value="1" selected>1 porsi</option>
          <option value="1.5">1,5 porsi</option>
          <option value="2">2 porsi</option>
        </select>
      </div>
      <button type="submit" class="tombol">Tambah</button>
    </form>
  </div>

  <div class="hasil" style="margin-bottom:20px">
    <h3 style="margin-bottom:6px">Yang sudah kamu makan hari ini</h3>
    <?php if (!$catatan): ?>
      <p class="teks-redup" style="margin:8px 0 0">Belum ada catatan. Tambahkan makanan pertamamu di atas.</p>
    <?php else: foreach ($catatan as $c):
        $porsi_teks = rtrim(rtrim(number_format($c['porsi'], 1, ',', ''), '0'), ','); ?>
      <div class="baris-catatan">
        <div>
          <b><?= h($c['nama_menu']) ?></b><br>
          <small><?= h(ucfirst($c['waktu_makan'])) ?> &middot; <?= h($porsi_teks) ?> porsi</small>
        </div>
        <div style="text-align:right">
          <b><?= nf($c['kalori'] * $c['porsi']) ?> kkal</b>
          <form method="post" style="margin-top:6px">
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="tombol hapus" onclick="return confirm('Hapus catatan ini?')">Hapus</button>
          </form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <?php if ($saran): ?>
    <h3 style="margin-bottom:10px">Masih muat di sisa kalorimu</h3>
    <div class="daftar-menu">
      <?php foreach ($saran as $m): ?>
        <article class="menu-kartu">
          <small><?= h(ucfirst($m['waktu_makan'])) ?><?= $m['vegetarian'] ? ' &middot; Vegetarian' : '' ?></small>
          <h3><?= h($m['nama_menu']) ?></h3>
          <div class="menu-info"><b><?= (int)$m['kalori'] ?> kkal</b></div>
          <form method="post">
            <input type="hidden" name="aksi" value="tambah">
            <input type="hidden" name="menu_id" value="<?= (int)$m['id'] ?>">
            <input type="hidden" name="porsi" value="1">
            <button type="submit" class="tombol kecil">Catat ini</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p class="catatan">Kalori merupakan perkiraan per porsi standar dan bersifat edukatif, bukan pengganti saran ahli gizi.</p>
<?php endif; ?>

<?php include 'footer.php'; ?>