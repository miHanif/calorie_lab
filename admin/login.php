<?php
require_once dirname(__DIR__) . '/config/koneksi.php';

// Inisialisasi variabel pesan
$error = '';
$pesan = '';

// Deteksi status sesi pengguna saat ini
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: ' . base_url('admin/index.php'));
        exit;
    } elseif (($_SESSION['role'] ?? '') === 'pakar') {
        header('Location: ' . base_url('pakar/index.php'));
        exit;
    } else {
        // User login sebagai role 'user'
        $error = 'Saat ini Anda sedang masuk sebagai <strong>' . h($_SESSION['nama'] ?? 'Pengguna Biasa') . '</strong>. Masuklah menggunakan akun Administrator atau beralih akun.';
    }
}

// Inisialisasi otomatis akun default admin jika belum ada
try {
    $cek_admin_default = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $cek_admin_default->execute(['admin@calorielab.com']);
    if (!$cek_admin_default->fetch()) {
        $ins_default = $pdo->prepare("INSERT INTO users (nama, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $ins_default->execute(['Administrator Utama', 'admin@calorielab.com', password_hash('admin123', PASSWORD_DEFAULT)]);
    }
} catch (Exception $e) {
    // Abaikan jika tabel belum siap/kendala minor
}

$csrf = csrf_token('csrf_token_admin_login');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!csrf_valid($token, 'csrf_token_admin_login')) {
        $error = 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.';
    } elseif ($email === '' || $pass === '') {
        $error = 'Harap isi email dan kata sandi administrator.';
    } else {
        $q = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = ?');
        $q->execute([$email]);
        $user = $q->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            // Validasi hak akses khusus administrator
            if ($user['role'] !== 'admin') {
                $error = 'Akses ditolak: Akun (' . h($user['email']) . ') terdaftar sebagai pengguna biasa dan tidak memiliki hak akses Administrator.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama']    = $user['nama'];
                $_SESSION['role']    = $user['role'];
                header('Location: ' . base_url('admin/index.php'));
                exit;
            }
        } else {
            $error = 'Email atau kata sandi administrator tidak cocok.';
        }
    }
}

$judul = 'Login Administrator';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($judul) ?> - Calorie Lab</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= base_url('assets/css/styling.css') ?>">
  <style>
    .admin-login-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(circle at 50% 20%, #FAF8F5 0%, #EDE9E3 100%);
      padding: 24px 16px;
      margin: 0;
    }
    .admin-login-card {
      max-width: 440px;
      width: 100%;
      background: var(--permukaan);
      border: 1px solid var(--garis);
      border-radius: var(--radius-kartu);
      box-shadow: 0 16px 36px rgba(44, 42, 38, 0.08);
      padding: clamp(26px, 5vw, 36px);
      margin: 0 auto;
    }
  </style>
</head>
<body class="admin-login-wrapper">

<div class="admin-login-card">
  
  <div style="text-align: center; margin-bottom: 22px;">
    <span class="badge" style="background: var(--mint); color: #264A38; font-weight: 700; font-size: 0.78rem; letter-spacing: 0.05em; padding: 4px 12px; margin-bottom: 8px; display: inline-block;">
      PORTAL KHUSUS ADMIN
    </span>
    <h2 style="margin: 6px 0; font-size: 1.55rem; color: var(--gelap); font-weight: 800;">Calorie Lab Admin</h2>
    <p class="teks-redup" style="font-size: 0.88rem; margin: 0; line-height: 1.5;">
      Masuk untuk mengelola basis data makanan, pengguna, dan pengaturan sistem.
    </p>
  </div>

  <?php if ($error): ?>
    <div class="alert error" style="margin-bottom: 20px; font-size: 0.88rem;"><?= $error ?></div>
  <?php endif; ?>

  <form method="post" action="<?= base_url('admin/login.php') ?>" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <label for="email" style="font-size: 0.88rem; margin-top: 0;">Email Administrator</label>
    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus placeholder="nama@email.com">

    <label for="password" style="font-size: 0.88rem; margin-top: 14px;">Kata Sandi</label>
    <div class="password-input-group">
      <input type="password" id="password" name="password" required placeholder="••••••••" autocomplete="current-password">
      <button type="button" class="toggle-pass-btn" id="btn-toggle-pass" aria-label="Lihat atau sembunyikan kata sandi" title="Lihat/Sembunyikan kata sandi" style="font-size: 0.78rem; font-weight: 700;">
        Lihat
      </button>
    </div>

    <button type="submit" class="tombol penuh" style="margin-top: 20px; min-height: 46px;">
      Masuk ke Panel Admin
    </button>
  </form>

  <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') !== 'admin'): ?>
    <div style="margin-top: 14px; text-align: center;">
      <a href="<?= base_url('logout.php') ?>" class="tombol sekunder kecil" style="font-size: 0.8rem; padding: 6px 14px; width: 100%;">
        Keluar dari Akun Saat Ini (<?= h($_SESSION['nama'] ?? '') ?>)
      </a>
    </div>
  <?php endif; ?>

  <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--garis); text-align: center; font-size: 0.85rem;">
    <span class="teks-redup">Bukan administrator?</span>
    <a href="<?= base_url('login.php') ?>" style="font-weight: 600; color: var(--hijau); text-decoration: none; margin-left: 4px;">
      Masuk sebagai Pengguna
    </a>
    &nbsp;&bull;&nbsp;
    <a href="<?= base_url('pakar/login.php') ?>" style="font-weight: 600; color: #4F46E5; text-decoration: none;">
      Portal Pakar
    </a>
  </div>

  <div style="text-align: center; margin-top: 12px;">
    <a href="<?= base_url('index.php') ?>" class="teks-redup" style="font-size: 0.8rem; text-decoration: none;">
      Kembali ke Beranda
    </a>
  </div>
</div>

<script>
  // Fitur interaktif: Intip kata sandi (Show/Hide Password)
  const btnToggle = document.getElementById('btn-toggle-pass');
  const inputPass = document.getElementById('password');
  
  if (btnToggle && inputPass) {
    btnToggle.addEventListener('click', function() {
      const isPassword = inputPass.getAttribute('type') === 'password';
      inputPass.setAttribute('type', isPassword ? 'text' : 'password');
      this.textContent = isPassword ? 'Sembunyikan' : 'Lihat';
    });
  }
</script>

</body>
</html>
