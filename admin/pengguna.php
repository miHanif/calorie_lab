<?php
require_once dirname(__DIR__) . '/config/koneksi.php';
wajib_admin();

$csrf = csrf_token('csrf_token_admin_pengguna');
$admin_id_login = (int)$_SESSION['user_id'];

$pesan = $_SESSION['pesan_admin_user'] ?? '';
$pesan_tipe = $_SESSION['pesan_admin_user_tipe'] ?? 'sukses';
unset($_SESSION['pesan_admin_user'], $_SESSION['pesan_admin_user_tipe']);

// PROSES POST: TAMBAH USER, EDIT USER, GANTI ROLE, RESET PASSWORD, ATAU HAPUS PENGGUNA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $aksi  = $_POST['aksi'] ?? '';

    if (!csrf_valid($token, 'csrf_token_admin_pengguna')) {
        $_SESSION['pesan_admin_user'] = 'Sesi formulir tidak valid atau kedaluwarsa.';
        $_SESSION['pesan_admin_user_tipe'] = 'error';
        header('Location: ' . base_url('admin/pengguna.php'));
        exit;
    }

    // Aksi 1: Tambah Akun Baru (User / Pakar / Admin)
    if ($aksi === 'tambah_user') {
        $nama_baru  = trim($_POST['nama'] ?? '');
        $email_baru = strtolower(trim($_POST['email'] ?? ''));
        $pass_baru  = trim($_POST['password'] ?? '');
        $role_pilih = $_POST['role'] ?? 'user';

        if (!in_array($role_pilih, ['user', 'pakar', 'admin'], true)) {
            $role_pilih = 'user';
        }

        if (mb_strlen($nama_baru) < 2 || mb_strlen($nama_baru) > 100) {
            $_SESSION['pesan_admin_user'] = 'Nama lengkap harus antara 2 sampai 100 karakter.';
            $_SESSION['pesan_admin_user_tipe'] = 'error';
        } elseif (!filter_var($email_baru, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['pesan_admin_user'] = 'Format email tidak valid.';
            $_SESSION['pesan_admin_user_tipe'] = 'error';
        } elseif (strlen($pass_baru) < 6 || strlen($pass_baru) > 72) {
            $_SESSION['pesan_admin_user'] = 'Kata sandi baru minimal 6 karakter.';
            $_SESSION['pesan_admin_user_tipe'] = 'error';
        } else {
            $cek_email = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
            $cek_email->execute([$email_baru]);
            if ($cek_email->fetch()) {
                $_SESSION['pesan_admin_user'] = 'Email (' . h($email_baru) . ') sudah terdaftar di sistem.';
                $_SESSION['pesan_admin_user_tipe'] = 'error';
            } else {
                $hash = password_hash($pass_baru, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO users (nama, email, password_hash, role) VALUES (?, ?, ?, ?)");
                $ins->execute([$nama_baru, $email_baru, $hash, $role_pilih]);
                $_SESSION['pesan_admin_user'] = 'Akun baru (' . h($nama_baru) . ' - ' . strtoupper($role_pilih) . ') berhasil ditambahkan.';
                $_SESSION['pesan_admin_user_tipe'] = 'sukses';
            }
        }
        header('Location: ' . base_url('admin/pengguna.php'));
        exit;
    }

    // Untuk aksi-aksi berikutnya, target user id wajib ada
    $target_uid = (int)($_POST['target_user_id'] ?? 0);
    if ($target_uid <= 0) {
        $_SESSION['pesan_admin_user'] = 'ID pengguna tidak valid.';
        $_SESSION['pesan_admin_user_tipe'] = 'error';
        header('Location: ' . base_url('admin/pengguna.php'));
        exit;
    }

    // Ambil data user target
    $st_target = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $st_target->execute([$target_uid]);
    $target_user = $st_target->fetch();

    if (!$target_user) {
        $_SESSION['pesan_admin_user'] = 'Pengguna tidak ditemukan.';
        $_SESSION['pesan_admin_user_tipe'] = 'error';
        header('Location: ' . base_url('admin/pengguna.php'));
        exit;
    }

    // Aksi 2: Edit Profil Pengguna (Nama & Email)
    if ($aksi === 'edit_user') {
        $nama_edit  = trim($_POST['nama'] ?? '');
        $email_edit = strtolower(trim($_POST['email'] ?? ''));

        if (mb_strlen($nama_edit) < 2 || mb_strlen($nama_edit) > 100) {
            $_SESSION['pesan_admin_user'] = 'Nama lengkap harus antara 2 sampai 100 karakter.';
            $_SESSION['pesan_admin_user_tipe'] = 'error';
        } elseif (!filter_var($email_edit, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['pesan_admin_user'] = 'Format email tidak valid.';
            $_SESSION['pesan_admin_user_tipe'] = 'error';
        } else {
            // Cek apakah email digunakan oleh user lain
            $cek_email = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ?");
            $cek_email->execute([$email_edit, $target_uid]);
            if ($cek_email->fetch()) {
                $_SESSION['pesan_admin_user'] = 'Email (' . h($email_edit) . ') sudah digunakan oleh akun lain.';
                $_SESSION['pesan_admin_user_tipe'] = 'error';
            } else {
                $up = $pdo->prepare("UPDATE users SET nama = ?, email = ? WHERE id = ?");
                $up->execute([$nama_edit, $email_edit, $target_uid]);
                if ($target_uid === $admin_id_login) {
                    $_SESSION['nama'] = $nama_edit;
                }
                $_SESSION['pesan_admin_user'] = 'Data akun ' . h($nama_edit) . ' berhasil diperbarui.';
                $_SESSION['pesan_admin_user_tipe'] = 'sukses';
            }
        }
        header('Location: ' . base_url('admin/pengguna.php'));
        exit;
    }

    // Aksi 3: Ubah Role (User <-> Admin <-> Pakar)
    if ($aksi === 'ubah_role') {
        $role_baru = $_POST['role_baru'] ?? 'user';
        if (!in_array($role_baru, ['user', 'admin', 'pakar'], true)) {
            $_SESSION['pesan_admin_user'] = 'Pilihan role tidak valid.';
            $_SESSION['pesan_admin_user_tipe'] = 'error';
        } elseif ($target_uid === $admin_id_login && $role_baru !== 'admin') {
            $_SESSION['pesan_admin_user'] = 'Keamanan: Anda tidak dapat menurunkan role akun admin Anda sendiri.';
            $_SESSION['pesan_admin_user_tipe'] = 'error';
        } else {
            $up = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $up->execute([$role_baru, $target_uid]);
            $_SESSION['pesan_admin_user'] = 'Role akun ' . h($target_user['nama']) . ' berhasil diubah menjadi ' . strtoupper($role_baru) . '.';
            $_SESSION['pesan_admin_user_tipe'] = 'sukses';
        }
        header('Location: ' . base_url('admin/pengguna.php'));
        exit;
    }

    // Aksi 4: Reset Password Pengguna
    if ($aksi === 'reset_pass') {
        $pass_baru = trim($_POST['password_baru'] ?? '');
        if (strlen($pass_baru) < 6 || strlen($pass_baru) > 72) {
            $_SESSION['pesan_admin_user'] = 'Kata sandi baru harus berukuran antara 6 sampai 72 karakter.';
            $_SESSION['pesan_admin_user_tipe'] = 'error';
        } else {
            $hash_baru = password_hash($pass_baru, PASSWORD_DEFAULT);
            $up = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $up->execute([$hash_baru, $target_uid]);
            $_SESSION['pesan_admin_user'] = 'Kata sandi pengguna ' . h($target_user['nama']) . ' berhasil diperbarui.';
            $_SESSION['pesan_admin_user_tipe'] = 'sukses';
        }
        header('Location: ' . base_url('admin/pengguna.php'));
        exit;
    }

    // Aksi 5: Hapus Pengguna
    if ($aksi === 'hapus_user') {
        if ($target_uid === $admin_id_login) {
            $_SESSION['pesan_admin_user'] = 'Keamanan: Anda tidak dapat menghapus akun admin yang sedang Anda gunakan saat ini.';
            $_SESSION['pesan_admin_user_tipe'] = 'error';
        } else {
            $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $del->execute([$target_uid]);
            $_SESSION['pesan_admin_user'] = 'Akun pengguna ' . h($target_user['nama']) . ' berhasil dihapus beserta seluruh riwayat terkait.';
            $_SESSION['pesan_admin_user_tipe'] = 'sukses';
        }
        header('Location: ' . base_url('admin/pengguna.php'));
        exit;
    }
}

// Pencarian Pengguna & Filter Role
$cari_user = trim($_GET['cari'] ?? '');
$filter_role = $_GET['role'] ?? '';

$sql_base = " FROM users u WHERE 1=1";
$params = [];

if ($cari_user !== '') {
    $sql_base .= " AND (u.nama LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $cari_user . '%';
    $params[] = '%' . $cari_user . '%';
}

if ($filter_role !== '' && in_array($filter_role, ['user', 'admin', 'pakar'], true)) {
    $sql_base .= " AND u.role = ?";
    $params[] = $filter_role;
}

// Hitung total untuk pagination
$st_count = $pdo->prepare("SELECT COUNT(*)" . $sql_base);
$st_count->execute($params);
$total_pengguna = (int)$st_count->fetchColumn();

// Pagination (20 per halaman)
$per_hal = 20;
$total_hal = max(1, (int)ceil($total_pengguna / $per_hal));
$hal = max(1, min((int)($_GET['hal'] ?? 1), $total_hal));
$offset = ($hal - 1) * $per_hal;

// Ambil data halaman aktif
$sql_data = "
    SELECT u.*,
           (SELECT COUNT(*) FROM catatan_makan WHERE user_id = u.id) AS total_catat,
           (SELECT COUNT(*) FROM rencana_diet WHERE user_id = u.id) AS total_rencana,
           (SELECT bmi FROM riwayat_bmi WHERE user_id = u.id ORDER BY id DESC LIMIT 1) AS bmi_terakhir
    " . $sql_base . "
    ORDER BY u.id DESC
    LIMIT $per_hal OFFSET $offset
";
$st_data = $pdo->prepare($sql_data);
$st_data->execute($params);
$daftar_user = $st_data->fetchAll();

$judul = 'Manajemen Pengguna & Role - Admin';
include BASE_DIR . 'includes/header.php';
?>

<?php include __DIR__ . '/nav.php'; ?>

<?php if ($pesan): ?>
  <div class="alert <?= $pesan_tipe === 'error' ? 'error' : 'sukses' ?>"><?= h($pesan) ?></div>
<?php endif; ?>

<!-- FILTER DAN PENCARIAN -->
<div class="kartu" style="padding: 20px; margin-bottom: 20px;">
  <form method="get" action="<?= base_url('admin/pengguna.php') ?>" style="display: flex; gap: 10px; flex-wrap: wrap;">
    <input type="text" name="cari" value="<?= h($cari_user) ?>" placeholder="Cari berdasarkan nama atau email..." style="flex: 2; min-width: 200px; margin: 0;">
    <select name="role" style="flex: 1; min-width: 140px; margin: 0;">
      <option value="">Semua Role</option>
      <option value="user"  <?= $filter_role === 'user'  ? 'selected' : '' ?>>User Biasa</option>
      <option value="pakar" <?= $filter_role === 'pakar' ? 'selected' : '' ?>>Pakar Gizi</option>
      <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
    </select>
    <button type="submit" class="tombol kecil" style="margin: 0; padding: 0 24px;">Filter</button>
    <?php if ($cari_user !== '' || $filter_role !== ''): ?>
      <a href="<?= base_url('admin/pengguna.php') ?>" class="tombol sekunder kecil" style="margin: 0;">Reset</a>
    <?php endif; ?>
  </form>
</div>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
  <span class="teks-redup" style="font-size: 0.9rem;">
    Menampilkan <b><?= count($daftar_user) ?></b> dari total <b><?= $total_pengguna ?></b> akun terdaftar
  </span>
  <button type="button" class="tombol kecil" onclick="bukaModalTambahUser()">
    + Tambah Pengguna Baru
  </button>
</div>

<!-- TABEL PENGGUNA -->
<div class="kartu" style="padding: 16px; overflow-x: auto;">
  <table class="admin-table">
    <thead>
      <tr>
        <th style="width: 28%;">Pengguna</th>
        <th class="col-role">Role</th>
        <th style="width: 22%;">Aktivitas</th>
        <th style="width: 16%;">Terdaftar</th>
        <th style="width: 24%; text-align: right;">Aksi & Kontrol</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($daftar_user)): ?>
        <tr>
          <td colspan="5" style="padding: 24px; text-align: center;" class="teks-redup">
            Tidak ada data pengguna yang sesuai dengan filter.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($daftar_user as $u): 
          $is_me = ($u['id'] == $admin_id_login);
        ?>
          <tr>
            <td>
              <strong><?= h($u['nama']) ?></strong> <?= $is_me ? '<span class="badge" style="background:var(--mint); font-size:0.7rem; color:#264A38; padding: 2px 8px; vertical-align: middle;">Akun Anda</span>' : '' ?>
              <div class="teks-redup" style="font-size: 0.8rem; margin-top: 2px;"><?= h($u['email']) ?></div>
            </td>

            <td class="col-role">
              <span class="badge-role <?= match($u['role']) { 'admin' => 'badge-role-admin', 'pakar' => 'badge-role-pakar', default => 'badge-role-user' } ?>">
                <?= strtoupper(h($u['role'])) ?>
              </span>
            </td>

            <td style="font-size: 0.84rem;">
              <div>Log Makan: <b><?= $u['total_catat'] ?></b></div>
              <div class="teks-redup" style="margin-top: 2px;">Rencana: <?= $u['total_rencana'] ?> | BMI: <?= $u['bmi_terakhir'] ? number_format((float)$u['bmi_terakhir'], 1) : '-' ?></div>
            </td>

            <td style="font-size: 0.82rem;" class="teks-redup">
              <?= date('d M Y, H:i', strtotime($u['created_at'])) ?>
            </td>

            <td style="text-align: right;">
              <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                
                <!-- GANTI ROLE FORM -->
                <form method="post" action="<?= base_url('admin/pengguna.php') ?>" style="margin: 0; display: inline-flex; align-items: center; gap: 4px;">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="aksi" value="ubah_role">
                  <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                  <select name="role_baru" style="font-size:0.78rem; padding:5px 8px; border:1px solid var(--garis); border-radius:var(--radius-kecil); background:var(--permukaan);"
                          <?= $is_me ? 'disabled title="Tidak bisa mengubah role sendiri"' : '' ?>>
                    <option value="user"  <?= $u['role'] === 'user'  ? 'selected' : '' ?>>User</option>
                    <option value="pakar" <?= $u['role'] === 'pakar' ? 'selected' : '' ?>>Pakar</option>
                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                  </select>
                  <button type="submit" class="tombol sekunder kecil"
                          <?= $is_me ? 'disabled' : '' ?>
                          style="font-size: 0.76rem; padding: 5px 8px;"
                          title="Simpan perubahan role"
                          onclick="return confirm('Ubah role <?= h(addslashes($u['nama'])) ?> sesuai pilihan?');">
                    Set
                  </button>
                </form>

                <!-- EDIT NAMA & EMAIL -->
                <button type="button" class="tombol sekunder kecil" style="font-size: 0.76rem; padding: 5px 9px;"
                        title="Edit nama dan email"
                        onclick="bukaModalEditUser(<?= $u['id'] ?>, <?= h(json_encode($u['nama'])) ?>, <?= h(json_encode($u['email'])) ?>)">
                  Edit
                </button>

                <!-- RESET PASSWORD -->
                <button type="button" class="tombol sekunder kecil" style="font-size: 0.76rem; padding: 5px 9px;"
                        title="Reset kata sandi pengguna"
                        onclick="
                          var p = prompt('Masukkan kata sandi baru untuk <?= h(addslashes($u['nama'])) ?> (minimal 6 karakter):');
                          if (p && p.length >= 6) {
                            var f = document.getElementById('form-reset-pass');
                            f.target_user_id.value = '<?= $u['id'] ?>';
                            f.password_baru.value = p;
                            f.submit();
                          } else if (p) {
                            alert('Kata sandi harus minimal 6 karakter!');
                          }
                        ">
                  Reset Sandi
                </button>

                <!-- HAPUS AKUN -->
                <?php if (!$is_me): ?>
                  <form method="post" action="<?= base_url('admin/pengguna.php') ?>" style="margin: 0; display: inline;"
                        onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin menghapus akun <?= h(addslashes($u['nama'])) ?>? Seluruh data riwayat dan catatannya akan terhapus permanen.');">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="aksi" value="hapus_user">
                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="tombol kecil" style="background: #C44536; font-size: 0.76rem; padding: 5px 9px;" title="Hapus akun">
                      Hapus
                    </button>
                  </form>
                <?php endif; ?>

              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- PAGINATION -->
<?php if ($total_hal > 1): ?>
  <?php
    $qs = http_build_query(array_filter(['cari' => $cari_user, 'role' => $filter_role]));
    $qs_prefix = $qs ? $qs . '&' : '';
    $awal = max(1, $hal - 2);
    $akhir = min($total_hal, $hal + 2);
  ?>
  <div style="display: flex; justify-content: center; gap: 6px; margin-top: 20px; flex-wrap: wrap;">
    <?php if ($hal > 1): ?>
      <a href="<?= base_url('admin/pengguna.php?' . $qs_prefix . 'hal=' . ($hal - 1)) ?>" class="tombol sekunder kecil" style="padding: 6px 12px;">Sebelumnya</a>
    <?php endif; ?>
    <?php for ($p = $awal; $p <= $akhir; $p++): ?>
      <a href="<?= base_url('admin/pengguna.php?' . $qs_prefix . 'hal=' . $p) ?>"
         class="tombol <?= $p === $hal ? '' : 'sekunder' ?> kecil"
         style="padding: 6px 12px;"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($hal < $total_hal): ?>
      <a href="<?= base_url('admin/pengguna.php?' . $qs_prefix . 'hal=' . ($hal + 1)) ?>" class="tombol sekunder kecil" style="padding: 6px 12px;">Berikutnya</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- FORM TERSEMBUNYI UNTUK PROSES RESET PASSWORD VIA PROMPT -->
<form id="form-reset-pass" method="post" action="<?= base_url('admin/pengguna.php') ?>" style="display: none;">
  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
  <input type="hidden" name="aksi" value="reset_pass">
  <input type="hidden" name="target_user_id" value="">
  <input type="hidden" name="password_baru" value="">
</form>

<!-- MODAL TAMBAH USER BARU -->
<div id="modal-tambah-user" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:200; align-items:center; justify-content:center; padding:20px;">
  <div style="background:var(--permukaan); border-radius:var(--radius-kartu); padding:28px; max-width:480px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <h3 style="margin:0 0 6px; font-size:1.2rem; font-weight:800; color:var(--gelap);">Tambah Pengguna Baru</h3>
    <p class="teks-redup" style="margin:0 0 18px; font-size:0.84rem;">Daftarkan akun baru ke sistem dengan role yang diinginkan.</p>

    <form method="post" action="<?= base_url('admin/pengguna.php') ?>">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="aksi" value="tambah_user">

      <label style="font-size:0.85rem; margin-top:0;">Nama Lengkap</label>
      <input type="text" name="nama" required placeholder="Contoh: Budi Santoso" style="margin-bottom:12px;">

      <label style="font-size:0.85rem;">Email</label>
      <input type="email" name="email" required placeholder="nama@email.com" style="margin-bottom:12px;">

      <label style="font-size:0.85rem;">Kata Sandi Awal</label>
      <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter" style="margin-bottom:12px;">

      <label style="font-size:0.85rem;">Pilih Role Akun</label>
      <select name="role" style="margin-bottom:18px;">
        <option value="user">User (Pengguna Biasa)</option>
        <option value="pakar">Pakar (Pakar Gizi)</option>
        <option value="admin">Admin (Administrator)</option>
      </select>

      <div style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="button" onclick="tutupModalTambahUser()" class="tombol sekunder kecil" style="padding:8px 18px;">Batal</button>
        <button type="submit" class="tombol kecil" style="padding:8px 20px;">Simpan Akun</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT PROFIL USER -->
<div id="modal-edit-user" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:200; align-items:center; justify-content:center; padding:20px;">
  <div style="background:var(--permukaan); border-radius:var(--radius-kartu); padding:28px; max-width:480px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <h3 style="margin:0 0 6px; font-size:1.2rem; font-weight:800; color:var(--gelap);">Edit Profil Pengguna</h3>
    <p class="teks-redup" style="margin:0 0 18px; font-size:0.84rem;">Perbarui nama lengkap atau alamat email pengguna.</p>

    <form method="post" action="<?= base_url('admin/pengguna.php') ?>">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="aksi" value="edit_user">
      <input type="hidden" name="target_user_id" id="edit-user-id" value="">

      <label style="font-size:0.85rem; margin-top:0;">Nama Lengkap</label>
      <input type="text" name="nama" id="edit-user-nama" required style="margin-bottom:12px;">

      <label style="font-size:0.85rem;">Email</label>
      <input type="email" name="email" id="edit-user-email" required style="margin-bottom:18px;">

      <div style="display:flex; gap:10px; justify-content:flex-end;">
        <button type="button" onclick="tutupModalEditUser()" class="tombol sekunder kecil" style="padding:8px 18px;">Batal</button>
        <button type="submit" class="tombol kecil" style="padding:8px 20px;">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>
  function bukaModalTambahUser() {
    document.getElementById('modal-tambah-user').style.display = 'flex';
  }
  function tutupModalTambahUser() {
    document.getElementById('modal-tambah-user').style.display = 'none';
  }

  function bukaModalEditUser(id, nama, email) {
    document.getElementById('edit-user-id').value = id;
    document.getElementById('edit-user-nama').value = nama;
    document.getElementById('edit-user-email').value = email;
    document.getElementById('modal-edit-user').style.display = 'flex';
    document.getElementById('edit-user-nama').focus();
  }
  function tutupModalEditUser() {
    document.getElementById('modal-edit-user').style.display = 'none';
  }

  // Tutup modal jika klik backdrop atau tekan Escape
  window.addEventListener('click', function(e) {
    const modalTambah = document.getElementById('modal-tambah-user');
    const modalEdit   = document.getElementById('modal-edit-user');
    if (e.target === modalTambah) tutupModalTambahUser();
    if (e.target === modalEdit) tutupModalEditUser();
  });
  window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      tutupModalTambahUser();
      tutupModalEditUser();
    }
  });
</script>

<?php include BASE_DIR . 'includes/footer.php'; ?>
