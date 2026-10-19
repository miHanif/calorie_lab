<?php
require_once 'koneksi.php';
wajib_login();

function rp($n) { return 'Rp' . number_format($n, 0, ',', '.'); }

function cari_menu($pdo, $kid, $waktu, $harga, $veg, $acak = false) {
    $sql = 'SELECT m.* FROM menu_diet m
            JOIN menu_kategori mk ON mk.menu_id = m.id
            WHERE mk.kategori_id = ?';
    $param = [$kid];
    if ($waktu !== '') { $sql .= ' AND m.waktu_makan = ?'; $param[] = $waktu; }
    if ($harga > 0)    { $sql .= ' AND m.harga <= ?';      $param[] = $harga; }
    if ($veg)          { $sql .= ' AND m.vegetarian = 1'; }
    $sql .= $acak ? ' ORDER BY RAND() LIMIT 1' : ' ORDER BY m.waktu_makan, m.kalori';
    $q = $pdo->prepare($sql);
    $q->execute($param);
    return $q->fetchAll();
}

$q = $pdo->prepare('SELECT r.*, k.nama AS kat_nama, k.warna
                    FROM riwayat_bmi r JOIN kategori_bmi k ON k.id = r.kategori_id
                    WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 1');
$q->execute([$_SESSION['user_id']]);
$terakhir = $q->fetch();

$daftar_waktu = ['pagi', 'siang', 'malam', 'camilan'];
$waktu = in_array($_GET['waktu'] ?? '', $daftar_waktu) ? $_GET['waktu'] : '';
$harga = (int)($_GET['harga'] ?? 0);
$veg   = isset($_GET['veg']);
$budget = (int)($_GET['budget'] ?? 0);

$menu = [];
$menu_hari = [];
if ($terakhir) {
    $kid = $terakhir['kategori_id'];
        if (isset($_GET['hari'])) {
        $kand = [];
        foreach ($daftar_waktu as $w) {
            $kand[] = cari_menu($pdo, $kid, $w, $harga, $veg);
        }
        $kand = array_filter($kand);   
        $cocok = [];
        for ($i = 0; $i < 300 && $kand; $i++) {
            $kom = [];
            foreach ($kand as $list) $kom[] = $list[array_rand($list)];
            $hrg = array_sum(array_column($kom, 'harga'));
            if ($budget === 0 || $hrg <= $budget) $cocok[] = $kom;
        }
        if ($cocok) $menu_hari = $cocok[array_rand($cocok)];
    }
    $menu = cari_menu($pdo, $kid, $waktu, $harga, $veg);
}

$judul = 'Menu Rekomendasi';
include 'header.php';
?>

<h2>Menu rekomendasi</h2>

<?php if (!$terakhir): ?>
  <div class="kosong">
    Kamu belum menghitung BMI, jadi sistem belum tahu menu yang cocok.<br><br>
    <a class="tombol" href="bmi.php">Hitung BMI dulu</a>
  </div>

<?php else: ?>
  <p class="teks-redup">
    Berdasarkan BMI terakhirmu (<?= h(number_format($terakhir['bmi'], 1, ',', '.')) ?>):
    <span class="badge" style="background:<?= h($terakhir['warna']) ?>"><?= h($terakhir['kat_nama']) ?></span>
    &middot; Target kalori harian: <b><?= h(number_format((int)$terakhir['target_kalori'], 0, ',', '.')) ?> kkal</b>
  </p>

  <form method="get" class="filter-bar">
    <div>
      <label for="waktu">Waktu makan</label>
      <select id="waktu" name="waktu">
        <option value="">Semua</option>
        <?php foreach ($daftar_waktu as $w): ?>
          <option value="<?= $w ?>" <?= $waktu === $w ? 'selected' : '' ?>><?= ucfirst($w) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>

      <label for="harga">Harga maks. per menu</label>
      <select id="harga" name="harga">
        <option value="0">Bebas</option>
        <?php foreach ([8000, 10000, 15000, 20000] as $hg): ?>
          <option value="<?= $hg ?>" <?= $harga === $hg ? 'selected' : '' ?>><?= rp($hg) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label for="budget">Budget sehari</label>
      <select id="budget" name="budget">
        <option value="0">Bebas</option>
        <?php foreach ([35000, 40000, 50000] as $b): ?>
          <option value="<?= $b ?>" <?= $budget === $b ? 'selected' : '' ?>><?= rp($b) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="cek">
      <label><input type="checkbox" name="veg" <?= $veg ? 'checked' : '' ?>> Vegetarian saja</label>
    </div>

    <button type="submit" class="tombol">Terapkan</button>
    <button type="submit" name="hari" value="1" class="tombol kuning">Buat menu 1 hari</button>
  </form>

  <?php if (isset($_GET['hari'])): ?>
    <?php if ($menu_hari):
        $total_kal = array_sum(array_column($menu_hari, 'kalori'));
        $total_hrg = array_sum(array_column($menu_hari, 'harga')); ?>
      <div class="menu-hari">
        <h3>Menu sehari untukmu</h3>
        <ul>
          <?php foreach ($menu_hari as $m): ?>
            <li><span><b><?= h(ucfirst($m['waktu_makan'])) ?></b>: <?= h($m['nama_menu']) ?> (<?= (int)$m['kalori'] ?> kkal)</span><span><?= rp($m['harga']) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <b>Total <?= number_format($total_kal, 0, ',', '.') ?> kkal &middot; <?= rp($total_hrg) ?></b>
        <p class="catatan">Target harianmu <?= number_format((int)$terakhir['target_kalori'], 0, ',', '.') ?> kkal. Klik lagi untuk kombinasi lain.</p>
      </div>

    <?php else: ?>
      <div class="kosong">Belum ada menu yang cocok untuk menu 1 hari dengan filter ini.</div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($menu): ?>
    <div class="daftar-menu">
      <?php foreach ($menu as $m): ?>
        <article class="menu-kartu">
          <small><?= h(ucfirst($m['waktu_makan'])) ?><?= $m['vegetarian'] ? ' &middot; Vegetarian' : '' ?></small>
          <h3><?= h($m['nama_menu']) ?></h3>
          <p><?= h($m['deskripsi']) ?></p>
          <div class="menu-info"><b><?= (int)$m['kalori'] ?> kkal</b><span><?= rp($m['harga']) ?></span></div>
          
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="kosong">Tidak ada menu yang cocok. Coba longgarkan filter harga atau waktu makan.</div>
  <?php endif; ?>

  <p class="catatan">Kalori merupakan perkiraan per porsi standar dan bersifat edukatif, bukan pengganti saran ahli gizi.</p>
<?php endif; ?>

<?php include 'footer.php'; ?>