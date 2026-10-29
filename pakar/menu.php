<?php
require_once dirname(__DIR__) . '/config/koneksi.php';
wajib_pakar();

$judul = 'Tinjauan Menu Gizi - Calorie Lab';
include BASE_DIR . 'includes/header.php';
$pakar_id = (int)$_SESSION['user_id'];
$csrf = csrf_token('csrf_pakar_menu');

$pesan = $_SESSION['pesan_pakar_menu'] ?? '';
$pesan_tipe = $_SESSION['pesan_pakar_menu_tipe'] ?? 'sukses';
unset($_SESSION['pesan_pakar_menu'], $_SESSION['pesan_pakar_menu_tipe']);

// ── Proses Form: Simpan / Edit Catatan Gizi ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token   = $_POST['csrf_token'] ?? '';
    $aksi    = $_POST['aksi'] ?? '';
    $menu_id = (int)($_POST['menu_id'] ?? 0);

    if (!csrf_valid($token, 'csrf_pakar_menu')) {
        $_SESSION['pesan_pakar_menu'] = 'Sesi formulir tidak valid.';
        $_SESSION['pesan_pakar_menu_tipe'] = 'error';
        header('Location: ' . base_url('pakar/menu.php'));
        exit;
    }

    if ($aksi === 'simpan_catatan' && $menu_id > 0) {
        $catatan = trim($_POST['catatan'] ?? '');
        $status  = $_POST['status'] ?? 'disetujui';
        if (!in_array($status, ['disetujui','perlu_tinjau','ditandai'], true)) $status = 'disetujui';

        if ($catatan === '') {
            $_SESSION['pesan_pakar_menu'] = 'Catatan tidak boleh kosong.';
            $_SESSION['pesan_pakar_menu_tipe'] = 'error';
        } else {
            // Cek apakah sudah ada catatan dari pakar ini untuk menu ini
            $cek = $pdo->prepare("SELECT id FROM catatan_gizi WHERE menu_id = ? AND pakar_id = ?");
            $cek->execute([$menu_id, $pakar_id]);
            $ada = $cek->fetch();

            if ($ada) {
                $upd = $pdo->prepare("UPDATE catatan_gizi SET catatan = ?, status = ?, created_at = NOW() WHERE id = ?");
                $upd->execute([$catatan, $status, $ada['id']]);
                $_SESSION['pesan_pakar_menu'] = 'Catatan gizi berhasil diperbarui.';
            } else {
                $ins = $pdo->prepare("INSERT INTO catatan_gizi (menu_id, pakar_id, catatan, status) VALUES (?, ?, ?, ?)");
                $ins->execute([$menu_id, $pakar_id, $catatan, $status]);
                $_SESSION['pesan_pakar_menu'] = 'Catatan gizi berhasil disimpan.';
            }
            $_SESSION['pesan_pakar_menu_tipe'] = 'sukses';
        }
        header('Location: ' . base_url('pakar/menu.php'));
        exit;
    }

    if ($aksi === 'hapus_catatan') {
        $catatan_id = (int)($_POST['catatan_id'] ?? 0);
        if ($catatan_id > 0) {
            $del = $pdo->prepare("DELETE FROM catatan_gizi WHERE id = ? AND pakar_id = ?");
            $del->execute([$catatan_id, $pakar_id]);
            $_SESSION['pesan_pakar_menu'] = 'Catatan gizi dihapus.';
            $_SESSION['pesan_pakar_menu_tipe'] = 'sukses';
        }
        header('Location: ' . base_url('pakar/menu.php'));
        exit;
    }
}

// ── Filter & Pencarian ──────────────────────────────────────────────────
$cari     = trim($_GET['cari'] ?? '');
$wkt      = $_GET['waktu'] ?? '';
$veg      = $_GET['veg'] ?? '';
$status_f = $_GET['status'] ?? '';

$kondisi = ['1=1'];
$params  = [];

if ($cari !== '') {
    $kondisi[] = 'md.nama_menu LIKE ?';
    $params[] = '%' . $cari . '%';
}
if ($wkt !== '' && in_array($wkt, ['pagi','siang','malam','camilan'], true)) {
    $kondisi[] = 'md.waktu_makan = ?';
    $params[] = $wkt;
}
if ($veg === '1') {
    $kondisi[] = 'md.vegetarian = 1';
}
if ($status_f === 'ada_catatan') {
    $kondisi[] = 'cg.id IS NOT NULL';
} elseif ($status_f === 'perlu_tinjau') {
    $kondisi[] = "cg.status = 'perlu_tinjau'";
} elseif ($status_f === 'ditandai') {
    $kondisi[] = "cg.status = 'ditandai'";
} elseif ($status_f === 'tanpa_catatan') {
    $kondisi[] = 'cg.id IS NULL';
}

$sql = "
    SELECT md.*,
           cg.id AS catatan_id,
           cg.catatan AS isi_catatan,
           cg.status  AS status_catatan,
           cg.created_at AS catatan_tgl,
           (SELECT COUNT(*) FROM catatan_makan cm WHERE cm.menu_id = md.id) AS frekuensi
    FROM menu_diet md
    LEFT JOIN catatan_gizi cg ON cg.menu_id = md.id AND cg.pakar_id = ?
    WHERE " . implode(' AND ', $kondisi) . "
    ORDER BY md.waktu_makan, md.nama_menu
";
array_unshift($params, $pakar_id);

$st = $pdo->prepare($sql);
$st->execute($params);
$daftar_menu = $st->fetchAll();

// Pagination
$per_hal = 20;
$total   = count($daftar_menu);
$hal     = max(1, (int)($_GET['hal'] ?? 1));
$total_hal = max(1, (int)ceil($total / $per_hal));
$hal     = min($hal, $total_hal);
$offset  = ($hal - 1) * $per_hal;
$daftar_halaman = array_slice($daftar_menu, $offset, $per_hal);

$label_status = ['disetujui'=>'Disetujui','perlu_tinjau'=>'Perlu Tinjau','ditandai'=>'Ditandai'];
$warna_status = ['disetujui'=>'#D1FAE5;color:#065F46','perlu_tinjau'=>'#FEF3C7;color:#92400E','ditandai'=>'#FEE2E2;color:#991B1B'];
$label_waktu  = ['pagi'=>'Pagi','siang'=>'Siang','malam'=>'Malam','camilan'=>'Camilan'];

include __DIR__ . '/nav.php';
?>

<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 20px;">
  <div>
    <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--gelap); margin: 0 0 4px;">Tinjauan Menu</h1>
    <p class="teks-redup" style="margin: 0; font-size: 0.9rem;">Tinjau kualitas gizi menu dan tambahkan catatan nutrisi.</p>
  </div>
  <div style="font-size: 0.85rem; color: var(--teks-redup); background: var(--latar); border: 1px solid var(--garis); border-radius: var(--radius-kecil); padding: 8px 14px;">
    Total: <strong><?= $total ?> menu</strong> ditemukan
  </div>
</div>

<?php if ($pesan): ?>
  <div class="alert <?= $pesan_tipe === 'error' ? 'error' : '' ?>" style="margin-bottom: 16px;"><?= h($pesan) ?></div>
<?php endif; ?>

<!-- Filter Bar -->
<form method="get" action="<?= base_url('pakar/menu.php') ?>" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
  <input type="text" name="cari" placeholder="Cari nama menu..." value="<?= h($cari) ?>"
         style="flex: 1; min-width: 180px; padding: 8px 12px; font-size: 0.88rem;">
  <select name="waktu" style="padding: 8px 12px; font-size: 0.88rem; border: 1px solid var(--garis); border-radius: var(--radius-kecil); background: var(--permukaan);">
    <option value="">Semua Waktu</option>
    <?php foreach (['pagi','siang','malam','camilan'] as $w): ?>
      <option value="<?= $w ?>" <?= $wkt === $w ? 'selected' : '' ?>><?= $label_waktu[$w] ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status" style="padding: 8px 12px; font-size: 0.88rem; border: 1px solid var(--garis); border-radius: var(--radius-kecil); background: var(--permukaan);">
    <option value="">Semua Status</option>
    <option value="tanpa_catatan" <?= $status_f === 'tanpa_catatan' ? 'selected' : '' ?>>Belum Dicatat</option>
    <option value="ada_catatan"   <?= $status_f === 'ada_catatan'   ? 'selected' : '' ?>>Ada Catatan</option>
    <option value="perlu_tinjau"  <?= $status_f === 'perlu_tinjau'  ? 'selected' : '' ?>>Perlu Tinjau</option>
    <option value="ditandai"      <?= $status_f === 'ditandai'      ? 'selected' : '' ?>>Ditandai</option>
  </select>
  <select name="veg" style="padding: 8px 12px; font-size: 0.88rem; border: 1px solid var(--garis); border-radius: var(--radius-kecil); background: var(--permukaan);">
    <option value="">Semua Jenis</option>
    <option value="1" <?= $veg === '1' ? 'selected' : '' ?>>Vegetarian</option>
  </select>
  <button type="submit" class="tombol kecil" style="padding: 8px 18px; font-size: 0.88rem;">Cari</button>
  <?php if ($cari || $wkt || $veg || $status_f): ?>
    <a href="<?= base_url('pakar/menu.php') ?>" class="tombol sekunder kecil" style="padding: 8px 14px; font-size: 0.88rem;">Reset</a>
  <?php endif; ?>
</form>

<!-- Tabel Menu -->
<div class="kartu" style="padding: 0; overflow-x: auto;">
  <table class="admin-table" style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
    <thead>
      <tr style="background: var(--latar); border-bottom: 2px solid var(--garis);">
        <th style="padding: 12px 16px; text-align: left; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gelap);">Menu</th>
        <th style="padding: 12px 10px; text-align: center; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gelap);">Waktu</th>
        <th style="padding: 12px 10px; text-align: center; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gelap);">Kalori</th>
        <th style="padding: 12px 10px; text-align: center; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gelap);">Log</th>
        <th style="padding: 12px 10px; text-align: center; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gelap);">Status Gizi</th>
        <th style="padding: 12px 16px; text-align: right; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gelap);">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($daftar_halaman)): ?>
        <tr><td colspan="6" style="padding: 32px; text-align: center; color: var(--teks-redup);">Tidak ada menu yang sesuai filter.</td></tr>
      <?php else: ?>
        <?php foreach ($daftar_halaman as $m): ?>
          <tr style="border-bottom: 1px solid var(--garis); transition: background 0.15s;" onmouseover="this.style.background='var(--latar)'" onmouseout="this.style.background=''">
            <td style="padding: 12px 16px;">
              <strong style="color: var(--gelap);"><?= h($m['nama_menu']) ?></strong>
              <?php if ($m['vegetarian']): ?>
                <span class="badge" style="background: #E8F5E9; color: #2E7D32; font-size: 0.7rem; padding: 2px 8px; margin-left: 5px;">Vegetarian</span>
              <?php endif; ?>
              <?php if ($m['isi_catatan']): ?>
                <div style="font-size: 0.78rem; color: var(--teks-redup); margin-top: 3px; max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  <?= h($m['isi_catatan']) ?>
                </div>
              <?php endif; ?>
            </td>
            <td style="padding: 12px 10px; text-align: center;">
              <span class="badge-waktu badge-waktu-<?= h($m['waktu_makan']) ?>"><?= h($label_waktu[$m['waktu_makan']] ?? $m['waktu_makan']) ?></span>
            </td>
            <td style="padding: 12px 10px; text-align: center; font-weight: 700; color: var(--hijau);"><?= $m['kalori'] ?> kkal</td>
            <td style="padding: 12px 10px; text-align: center; color: var(--teks-redup); font-size: 0.82rem;"><?= $m['frekuensi'] ?>x</td>
            <td style="padding: 12px 10px; text-align: center;">
              <?php if ($m['catatan_id']): ?>
                <span style="font-size: 0.75rem; font-weight: 700; padding: 3px 9px; border-radius: 10px; background: <?= $warna_status[$m['status_catatan']] ?? '#eee;color:#333' ?>;">
                  <?= h($label_status[$m['status_catatan']] ?? $m['status_catatan']) ?>
                </span>
              <?php else: ?>
                <span style="font-size: 0.75rem; color: var(--teks-redup);">Belum ditinjau</span>
              <?php endif; ?>
            </td>
            <td style="padding: 12px 16px; text-align: right;">
              <button type="button" class="tombol sekunder kecil"
                      style="font-size: 0.78rem; padding: 5px 12px;"
                      onclick="bukaModal(<?= $m['id'] ?>, <?= h(json_encode($m['nama_menu'])) ?>, <?= h(json_encode($m['isi_catatan'] ?? '')) ?>, <?= h(json_encode($m['status_catatan'] ?? 'disetujui')) ?>, <?= (int)($m['catatan_id'] ?? 0) ?>)">
                <?= $m['catatan_id'] ? 'Edit Catatan' : 'Beri Catatan' ?>
              </button>
              <?php if ($m['catatan_id']): ?>
                <form method="post" action="<?= base_url('pakar/menu.php') ?>" style="display:inline; margin-left:4px;"
                      onsubmit="return confirm('Hapus catatan gizi untuk menu ini?');">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="aksi" value="hapus_catatan">
                  <input type="hidden" name="catatan_id" value="<?= (int)$m['catatan_id'] ?>">
                  <button type="submit" class="tombol kecil" style="font-size: 0.75rem; padding: 5px 10px; background: #C44536;">Hapus</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($total_hal > 1): ?>
  <?php
    $qs = http_build_query(array_filter(['cari'=>$cari,'waktu'=>$wkt,'veg'=>$veg,'status'=>$status_f]));
    $qs_prefix = $qs ? $qs . '&' : '';
    $awal = max(1, $hal - 2);
    $akhir = min($total_hal, $hal + 2);
  ?>
  <div style="display: flex; justify-content: center; gap: 6px; margin-top: 20px; flex-wrap: wrap;">
    <?php if ($hal > 1): ?>
      <a href="<?= base_url('pakar/menu.php?' . $qs_prefix . 'hal=' . ($hal - 1)) ?>" class="tombol sekunder kecil" style="padding: 6px 12px;">Sebelumnya</a>
    <?php endif; ?>
    <?php for ($p = $awal; $p <= $akhir; $p++): ?>
      <a href="<?= base_url('pakar/menu.php?' . $qs_prefix . 'hal=' . $p) ?>"
         class="tombol <?= $p === $hal ? '' : 'sekunder' ?> kecil"
         style="padding: 6px 12px;"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($hal < $total_hal): ?>
      <a href="<?= base_url('pakar/menu.php?' . $qs_prefix . 'hal=' . ($hal + 1)) ?>" class="tombol sekunder kecil" style="padding: 6px 12px;">Berikutnya</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Modal Catatan Gizi -->
<div id="modal-catatan" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:200; align-items:center; justify-content:center; padding:20px;">
  <div style="background:var(--permukaan); border-radius:var(--radius-kartu); padding:28px; max-width:520px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <h3 id="modal-judul" style="margin:0 0 4px; font-size:1.1rem; font-weight:800; color:var(--gelap);"></h3>
    <p style="margin:0 0 18px; font-size:0.82rem; color:var(--teks-redup);">Tambahkan catatan atau penilaian gizi untuk menu ini.</p>

    <form method="post" action="<?= base_url('pakar/menu.php') ?>" id="form-catatan">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="aksi" value="simpan_catatan">
      <input type="hidden" name="menu_id" id="modal-menu-id" value="">

      <label style="font-size:0.88rem;">Catatan Gizi</label>
      <textarea id="modal-catatan-isi" name="catatan" rows="4"
                placeholder="Contoh: Menu ini tinggi serat, cocok untuk pengguna dengan kategori gemuk. Kurangi garam..."
                style="width:100%; font-family:inherit; font-size:0.88rem; resize:vertical;"></textarea>

      <label style="font-size:0.88rem; margin-top:12px;">Status</label>
      <select name="status" id="modal-status"
              style="width:100%; padding:10px 12px; font-size:0.88rem; border:1.5px solid #DFD9CE; border-radius:var(--radius-kecil); font-family:inherit; background:var(--permukaan-subtle);">
        <option value="disetujui">Disetujui — Menu gizi sudah baik</option>
        <option value="perlu_tinjau">Perlu Tinjau — Ada yang perlu diperhatikan</option>
        <option value="ditandai">Ditandai — Perlu perbaikan atau peringatan</option>
      </select>

      <div style="display:flex; gap:10px; margin-top:18px; justify-content:flex-end;">
        <button type="button" onclick="tutupModal()" class="tombol sekunder kecil" style="padding:8px 18px;">Batal</button>
        <button type="submit" class="tombol kecil" style="padding:8px 20px;">Simpan Catatan</button>
      </div>
    </form>
  </div>
</div>

<script>
  function bukaModal(menuId, namaMenu, isiCatatan, statusCatatan, catatanId) {
    document.getElementById('modal-judul').textContent = namaMenu;
    document.getElementById('modal-menu-id').value = menuId;
    document.getElementById('modal-catatan-isi').value = isiCatatan || '';
    document.getElementById('modal-status').value = statusCatatan || 'disetujui';
    document.getElementById('modal-catatan').style.display = 'flex';
    document.getElementById('modal-catatan-isi').focus();
  }
  function tutupModal() {
    document.getElementById('modal-catatan').style.display = 'none';
  }
  document.getElementById('modal-catatan').addEventListener('click', function(e) {
    if (e.target === this) tutupModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') tutupModal();
  });
</script>

<?php include BASE_DIR . 'includes/footer.php'; ?>
