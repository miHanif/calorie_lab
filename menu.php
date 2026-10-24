<?php
require_once 'koneksi.php';
wajib_login();
date_default_timezone_set('Asia/Jakarta');

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

$q = $pdo->prepare('SELECT r.*, k.nama AS kat_nama, k.kode AS kat_kode
                    FROM riwayat_bmi r JOIN kategori_bmi k ON k.id = r.kategori_id
                    WHERE r.user_id = ? ORDER BY r.id DESC LIMIT 1');
$q->execute([$_SESSION['user_id']]);
$terakhir = $q->fetch();

$q = $pdo->prepare("SELECT target_kalori FROM rencana_diet WHERE user_id = ? AND status = 'aktif' ORDER BY id DESC LIMIT 1");
$q->execute([$_SESSION['user_id']]);
$rencana = $q->fetch();
if ($rencana && $terakhir) $terakhir['target_kalori'] = $rencana['target_kalori'];

$daftar_waktu = ['pagi', 'siang', 'malam', 'camilan'];
$bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
          'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$filter = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan_menu_harian' ? $_POST : $_GET;
$waktu = in_array($filter['waktu'] ?? '', $daftar_waktu, true) ? $filter['waktu'] : '';
$harga = max(0, (int)($filter['harga'] ?? 0));
$veg   = isset($filter['veg']) && $filter['veg'] === '1';
$budget = max(0, (int)($filter['budget'] ?? 0));
$hari_ini = date('Y-m-d');
$csrf_token = $_SESSION['csrf_token_menu_harian'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token_menu_harian'] = $csrf_token;

$error_menu_harian = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'catat_dimakan') {
    $token = $_POST['csrf_token'] ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);
    $porsi_dimakan = (float)($_POST['porsi'] ?? 1);
    if (!is_string($token) || !hash_equals($csrf_token, $token)) {
        $_SESSION['pesan_menu_harian'] = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
        $_SESSION['pesan_menu_harian_tipe'] = 'error';
    } elseif (!in_array($porsi_dimakan, [0.5, 1.0, 1.5, 2.0], true)) {
        $_SESSION['pesan_menu_harian'] = 'Jumlah porsi tidak valid. Pilih jumlah porsi yang tersedia.';
        $_SESSION['pesan_menu_harian_tipe'] = 'error';
    } else {
        $pdo->beginTransaction();
        $st = $pdo->prepare(
            'SELECT i.menu_id, i.catatan_makan_id, mh.tanggal
             FROM menu_harian_item i
             JOIN menu_harian mh ON mh.id = i.menu_harian_id
             WHERE i.id = ? AND mh.user_id = ? AND mh.tanggal <= ?
             FOR UPDATE'
        );
        $st->execute([$item_id, $_SESSION['user_id'], $hari_ini]);
        $item = $st->fetch();

        if (!$item) {
            $pdo->rollBack();
            $_SESSION['pesan_menu_harian'] = 'Menu tidak ditemukan di rencana harian akunmu.';
            $_SESSION['pesan_menu_harian_tipe'] = 'error';
        } elseif ($item['catatan_makan_id'] !== null) {
            $pdo->commit();
            $_SESSION['pesan_menu_harian'] = 'Menu tersebut sudah tercatat sebagai makanan.';
            $_SESSION['pesan_menu_harian_tipe'] = 'sukses';
        } else {
            $st = $pdo->prepare('INSERT INTO catatan_makan (user_id, menu_id, porsi, tanggal) VALUES (?, ?, ?, ?)');
            $st->execute([$_SESSION['user_id'], $item['menu_id'], $porsi_dimakan, $item['tanggal']]);
            $catatan_makan_id = (int)$pdo->lastInsertId();
            $st = $pdo->prepare('UPDATE menu_harian_item SET catatan_makan_id = ? WHERE id = ?');
            $st->execute([$catatan_makan_id, $item_id]);
            $pdo->commit();
            $_SESSION['pesan_menu_harian'] = 'Menu tercatat di Catatan Kalori sebagai makanan yang kamu konsumsi.';
            $_SESSION['pesan_menu_harian_tipe'] = 'sukses';
        }
    }

    header('Location: menu.php#riwayat-menu');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan_menu_harian') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($csrf_token, $token)) {
        $error_menu_harian = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (!$terakhir) {
        $error_menu_harian = 'Hitung BMI terlebih dahulu sebelum membuat menu harian.';
    } else {
        $harga_post = max(0, (int)($_POST['harga'] ?? 0));
        $budget_post = max(0, (int)($_POST['budget'] ?? 0));
        $veg_post = ($_POST['veg'] ?? '') === '1';
        $kandidat = [];
        foreach ($daftar_waktu as $w) {
            $daftar = cari_menu($pdo, (int)$terakhir['kategori_id'], $w, $harga_post, $veg_post);
            if ($daftar) {
                $kandidat[] = $daftar;
            }
        }

        $menu_baru = [];
        for ($i = 0; $i < 300 && $kandidat; $i++) {
            $kombinasi = [];
            foreach ($kandidat as $daftar) {
                $kombinasi[] = $daftar[array_rand($daftar)];
            }
            $total_harga = array_sum(array_column($kombinasi, 'harga'));
            if ($budget_post === 0 || $total_harga <= $budget_post) {
                $menu_baru = $kombinasi;
                break;
            }
        }

        if (!$menu_baru) {
            $error_menu_harian = 'Belum ada kombinasi menu yang cocok dengan filter dan budget. Coba longgarkan filter atau naikkan budget.';
        } else {
            $pdo->beginTransaction();
            $simpan_rencana = $pdo->prepare(
                'INSERT INTO menu_harian (user_id, tanggal) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $simpan_rencana->execute([$_SESSION['user_id'], $hari_ini]);
            $menu_harian_id = (int)$pdo->lastInsertId();

            $pdo->prepare('DELETE FROM menu_harian_item WHERE menu_harian_id = ?')->execute([$menu_harian_id]);
            $simpan_item = $pdo->prepare(
                'INSERT INTO menu_harian_item (menu_harian_id, menu_id, urutan) VALUES (?, ?, ?)'
            );
            foreach ($menu_baru as $urutan => $item) {
                $simpan_item->execute([$menu_harian_id, $item['id'], $urutan + 1]);
            }
            $pdo->commit();

            $_SESSION['pesan_menu_harian'] = 'Menu harian tersimpan. Menu ini tetap tersedia saat kamu kembali ke halaman rekomendasi.';
            $_SESSION['pesan_menu_harian_tipe'] = 'sukses';
            $params = ['hari_ini' => 1, 'harga' => $harga_post, 'budget' => $budget_post];
            if ($veg_post) {
                $params['veg'] = 1;
            }
            header('Location: menu.php?' . http_build_query($params));
            exit;
        }
    }
}

$menu = [];
$menu_hari = [];
$menu_harian_id = null;
$pesan_menu_harian = $_SESSION['pesan_menu_harian'] ?? '';
$pesan_menu_harian_tipe = $_SESSION['pesan_menu_harian_tipe'] ?? 'sukses';
unset($_SESSION['pesan_menu_harian']);
unset($_SESSION['pesan_menu_harian_tipe']);
$st = $pdo->prepare('SELECT id FROM menu_harian WHERE user_id = ? AND tanggal = ?');
$st->execute([$_SESSION['user_id'], $hari_ini]);
$menu_harian_id = $st->fetchColumn();

if ($menu_harian_id) {
    $st = $pdo->prepare('SELECT i.id AS item_id, i.catatan_makan_id, m.* FROM menu_harian_item i
                         JOIN menu_diet m ON m.id = i.menu_id
                         WHERE i.menu_harian_id = ? ORDER BY i.urutan');
    $st->execute([$menu_harian_id]);
    $menu_hari = $st->fetchAll();
}

$st = $pdo->prepare(
    'SELECT mh.id AS plan_id, mh.tanggal, i.id AS item_id, i.catatan_makan_id,
            m.id AS menu_id, m.nama_menu, m.waktu_makan, m.kalori, m.harga
     FROM (
         SELECT id, user_id, tanggal
         FROM menu_harian
         WHERE user_id = ? AND tanggal < ?
         ORDER BY tanggal DESC
         LIMIT 14
     ) mh
     JOIN menu_harian_item i ON i.menu_harian_id = mh.id
     JOIN menu_diet m ON m.id = i.menu_id
     ORDER BY mh.tanggal DESC, i.urutan'
);
$st->execute([$_SESSION['user_id'], $hari_ini]);
$riwayat_menu = [];
foreach ($st->fetchAll() as $item) {
    $plan_id = (int)$item['plan_id'];
    if (!isset($riwayat_menu[$plan_id])) {
        $riwayat_menu[$plan_id] = [
            'tanggal' => $item['tanggal'],
            'items' => [],
            'total_kalori' => 0,
            'total_harga' => 0,
        ];
    }
    $riwayat_menu[$plan_id]['items'][] = $item;
    $riwayat_menu[$plan_id]['total_kalori'] += (int)$item['kalori'];
    $riwayat_menu[$plan_id]['total_harga'] += (int)$item['harga'];
}

if ($terakhir) {
    $kid = $terakhir['kategori_id'];
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
    <span class="badge kategori-<?= h($terakhir['kat_kode']) ?>"><?= h($terakhir['kat_nama']) ?></span>
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
      <label><input type="checkbox" name="veg" value="1" <?= $veg ? 'checked' : '' ?>> Vegetarian saja</label>
    </div>

    <button type="submit" class="tombol">Terapkan filter</button>
  </form>

  <?php if ($error_menu_harian): ?>
    <div class="alert error"><?= h($error_menu_harian) ?></div>
  <?php endif; ?>

  <?php if ($pesan_menu_harian): ?>
    <div class="alert <?= $pesan_menu_harian_tipe === 'error' ? 'error' : 'sukses' ?>"><?= h($pesan_menu_harian) ?></div>
  <?php endif; ?>

  <?php if ($menu_hari):
      $total_kal = array_sum(array_column($menu_hari, 'kalori'));
      $total_hrg = array_sum(array_column($menu_hari, 'harga')); ?>
    <div class="menu-hari">
      <div class="menu-hari-heading">
        <div>
          <small>TERSIMPAN UNTUK HARI INI · <?= h(date('d-m-Y', strtotime($hari_ini))) ?></small>
          <h3>Menu harianmu</h3>
        </div>
        <span class="menu-hari-status">Tersimpan</span>
      </div>
      <ul>
        <?php foreach ($menu_hari as $m): ?>
          <li>
            <span><b><?= h(ucfirst($m['waktu_makan'])) ?></b>: <?= h($m['nama_menu']) ?> (<?= (int)$m['kalori'] ?> kkal)</span>
            <span class="menu-hari-item-aksi">
              <?= rp($m['harga']) ?>
              <?php if ($m['catatan_makan_id'] !== null): ?>
                <span class="menu-tercatat">Sudah dicatat</span>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="aksi" value="catat_dimakan">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                  <input type="hidden" name="item_id" value="<?= (int)$m['item_id'] ?>">
                  <select class="menu-porsi" name="porsi" aria-label="Porsi yang dimakan">
                    <option value="0.5">½ porsi</option>
                    <option value="1" selected>1 porsi</option>
                    <option value="1.5">1,5 porsi</option>
                    <option value="2">2 porsi</option>
                  </select>
                  <button type="submit" class="tombol kecil">Catat dimakan</button>
                </form>
              <?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <b>Total <?= number_format($total_kal, 0, ',', '.') ?> kkal &middot; <?= rp($total_hrg) ?></b>
      <p class="catatan">Target harianmu <?= number_format((int)$terakhir['target_kalori'], 0, ',', '.') ?> kkal. Rencana ini tidak otomatis masuk ke catatan makanan.</p>
      <form method="post" class="buat-menu-harian">
        <input type="hidden" name="aksi" value="simpan_menu_harian">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <input type="hidden" name="waktu" value="<?= h($waktu) ?>">
        <input type="hidden" name="harga" value="<?= $harga ?>">
        <input type="hidden" name="budget" value="<?= $budget ?>">
        <?php if ($veg): ?><input type="hidden" name="veg" value="1"><?php endif; ?>
        <button type="submit" class="tombol kuning">Buat kombinasi lain</button>
      </form>
    </div>
  <?php else: ?>
    <div class="menu-hari menu-hari-kosong">
      <div>
        <h3>Rencanakan menu sehari</h3>
        <p class="catatan">Buat kombinasi menu berdasarkan kategori BMI dan budget. Rencana tersimpan untuk hari ini dan bisa dibuka lagi nanti.</p>
      </div>
      <form method="post" class="buat-menu-harian">
        <input type="hidden" name="aksi" value="simpan_menu_harian">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
        <input type="hidden" name="waktu" value="<?= h($waktu) ?>">
        <input type="hidden" name="harga" value="<?= $harga ?>">
        <input type="hidden" name="budget" value="<?= $budget ?>">
        <?php if ($veg): ?><input type="hidden" name="veg" value="1"><?php endif; ?>
        <button type="submit" class="tombol kuning">Buat menu 1 hari</button>
      </form>
    </div>
  <?php endif; ?>

  <section class="riwayat-menu" id="riwayat-menu" aria-labelledby="riwayat-menu-judul">
    <div class="dashboard-section-heading">
      <div>
        <h3 id="riwayat-menu-judul">Riwayat menu harian</h3>
        <p class="teks-redup">Menampilkan hingga 14 rencana terakhir. Catat hanya menu yang benar-benar kamu makan.</p>
      </div>
    </div>
    <?php if ($riwayat_menu): ?>
      <div class="riwayat-menu-daftar">
        <?php foreach ($riwayat_menu as $plan_id => $plan): ?>
          <article class="riwayat-menu-kartu">
            <header>
              <div>
                <small>MENU TERSIMPAN</small>
                <h4><?= h(date('j', strtotime($plan['tanggal'])) . ' ' . $bulan[(int)date('n', strtotime($plan['tanggal']))] . ' ' . date('Y', strtotime($plan['tanggal']))) ?></h4>
              </div>
              <div class="riwayat-menu-ringkasan">
                <strong><?= number_format($plan['total_kalori'], 0, ',', '.') ?> kkal · <?= rp($plan['total_harga']) ?></strong>
                <a href="catatan.php?<?= h(http_build_query(['tanggal' => $plan['tanggal']])) ?>">Lihat catatan tanggal ini</a>
              </div>
            </header>
            <ul>
              <?php foreach ($plan['items'] as $item): ?>
                <li>
                  <span><b><?= h(ucfirst($item['waktu_makan'])) ?></b>: <?= h($item['nama_menu']) ?> · <?= (int)$item['kalori'] ?> kkal</span>
                  <?php if ($item['catatan_makan_id'] !== null): ?>
                    <span class="menu-tercatat">Sudah dicatat</span>
                  <?php else: ?>
                    <form method="post">
                      <input type="hidden" name="aksi" value="catat_dimakan">
                      <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                      <input type="hidden" name="item_id" value="<?= (int)$item['item_id'] ?>">
                      <select class="menu-porsi" name="porsi" aria-label="Porsi yang dimakan">
                        <option value="0.5">½ porsi</option>
                        <option value="1" selected>1 porsi</option>
                        <option value="1.5">1,5 porsi</option>
                        <option value="2">2 porsi</option>
                      </select>
                      <button type="submit" class="tombol kecil">Catat dimakan</button>
                    </form>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="kosong">Belum ada rencana menu dari hari sebelumnya.</div>
    <?php endif; ?>
  </section>

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