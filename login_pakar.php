<?php
require_once 'koneksi.php';

$error = '';

// Jika sudah login sebagai pakar, langsung redirect
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'pakar') {
        header('Location: pakar_dashboard.php');
        exit;
    } elseif ($role === 'admin') {
        header('Location: admin_dashboard.php');
        exit;
    }
    // Jika login sebagai user biasa, tampilkan pesan
    $error = 'Anda sedang masuk sebagai <strong>' . h($_SESSION['nama'] ?? 'Pengguna') . '</strong>. Keluar terlebih dahulu untuk masuk sebagai Pakar.';
}

// Inisialisasi otomatis: Pastikan skema database dan role akun pakar siap
try {
    // 1. Perbarui kolom role jika belum mendukung 'pakar'
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','pakar') NOT NULL DEFAULT 'user'");
} catch (Exception $e) {
    // Abaikan jika sudah diubah
}

try {
    // 2. Buat tabel saran_pakar & catatan_gizi jika belum ada
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS saran_pakar (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            pakar_id         INT NOT NULL,
            judul            VARCHAR(200) NOT NULL,
            isi_saran        TEXT NOT NULL,
            target_kategori  ENUM('semua','kurus','normal','gemuk','obesitas') NOT NULL DEFAULT 'semua',
            created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (pakar_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS catatan_gizi (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            menu_id     INT NOT NULL,
            pakar_id    INT NOT NULL,
            catatan     TEXT NOT NULL,
            status      ENUM('disetujui','perlu_tinjau','ditandai') NOT NULL DEFAULT 'disetujui',
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (menu_id)  REFERENCES menu_diet(id) ON DELETE CASCADE,
            FOREIGN KEY (pakar_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    // Abaikan jika tabel sudah siap
}

try {
    // 3. Pastikan akun pakar@calorielab.com terdaftar dengan role 'pakar'
    $cek = $pdo->prepare("SELECT id, role FROM users WHERE LOWER(email) = ?");
    $cek->execute(['pakar@calorielab.com']);
    $user_pakar = $cek->fetch();
    if (!$user_pakar) {
        $ins = $pdo->prepare("INSERT INTO users (nama, email, password_hash, role) VALUES (?, ?, ?, 'pakar')");
        $ins->execute(['Pakar Gizi', 'pakar@calorielab.com', password_hash('pakar123', PASSWORD_DEFAULT)]);
    } elseif ($user_pakar['role'] !== 'pakar') {
        // Perbaiki role menjadi 'pakar' jika sebelumnya tersimpan sebagai 'user'
        $upd = $pdo->prepare("UPDATE users SET role = 'pakar', password_hash = ? WHERE LOWER(email) = ?");
        $upd->execute([password_hash('pakar123', PASSWORD_DEFAULT), 'pakar@calorielab.com']);
    }
} catch (Exception $e) {
    // Abaikan kendala minor
}

$csrf = csrf_token('csrf_token_pakar_login');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!csrf_valid($token, 'csrf_token_pakar_login')) {
        $error = 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.';
    } elseif ($email === '' || $pass === '') {
        $error = 'Harap isi email dan kata sandi.';
    } else {
        $q = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = ?');
        $q->execute([$email]);
        $user = $q->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            if (!in_array($user['role'], ['pakar', 'admin'], true)) {
                $error = 'Akses ditolak: Akun ini tidak memiliki hak akses Portal Pakar.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama']    = $user['nama'];
                $_SESSION['role']    = $user['role'];
                header('Location: pakar_dashboard.php');
                exit;
            }
        } else {
            $error = 'Email atau kata sandi tidak cocok.';
        }
    }
}

$judul = 'Login Portal Pakar';
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
  <link rel="stylesheet" href="styling.css">
  <style>
    .pakar-login-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(circle at 50% 20%, #FAF8F5 0%, #EDE9E3 100%);
      padding: 24px 16px;
      margin: 0;
    }
    .pakar-login-card {
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
<body class="pakar-login-wrapper">

<div class="pakar-login-card">

  <div style="text-align: center; margin-bottom: 22px;">
    <span class="badge" style="background: var(--mint); color: #264A38; font-weight: 700; font-size: 0.78rem; letter-spacing: 0.05em; padding: 4px 12px; margin-bottom: 8px; display: inline-block;">
      PORTAL KHUSUS PAKAR GIZI
    </span>
    <h2 style="margin: 6px 0; font-size: 1.55rem; color: var(--gelap); font-weight: 800;">Calorie Lab Pakar</h2>
    <p class="teks-redup" style="font-size: 0.88rem; margin: 0; line-height: 1.5;">
      Masuk untuk meninjau menu, memberikan saran gizi, dan memantau ringkasan statistik pengguna.
    </p>
  </div>

  <?php if ($error): ?>
    <div class="alert error" style="margin-bottom: 20px; font-size: 0.88rem;"><?= $error ?></div>
  <?php endif; ?>

  <form method="post" action="login_pakar.php" autocomplete="on">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <label for="email" style="font-size: 0.88rem; margin-top: 0;">Email Pakar</label>
    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>"
           required autofocus placeholder="nama@email.com">

    <label for="password" style="font-size: 0.88rem; margin-top: 14px;">Kata Sandi</label>
    <div class="password-input-group">
      <input type="password" id="password" name="password" required
             placeholder="••••••••" autocomplete="current-password">
      <button type="button" class="toggle-pass-btn" id="btn-toggle-pass"
              aria-label="Lihat atau sembunyikan kata sandi">Lihat</button>
    </div>

    <button type="submit" class="tombol penuh" style="margin-top: 20px; min-height: 46px;">
      Masuk ke Portal Pakar
    </button>
  </form>

  <?php if (isset($_SESSION['user_id']) && !in_array($_SESSION['role'] ?? '', ['pakar','admin'])): ?>
    <div style="margin-top: 14px; text-align: center;">
      <a href="logout.php" class="tombol sekunder kecil" style="font-size: 0.8rem; padding: 6px 14px; width: 100%;">
        Keluar dari Akun Saat Ini (<?= h($_SESSION['nama'] ?? '') ?>)
      </a>
    </div>
  <?php endif; ?>

  <div style="margin-top: 22px; padding-top: 16px; border-top: 1px solid var(--garis); text-align: center; font-size: 0.85rem;">
    <span class="teks-redup">Bukan pakar gizi?</span>
    <a href="login.php" style="font-weight: 600; color: var(--hijau); text-decoration: none; margin-left: 4px;">
      Masuk sebagai Pengguna
    </a>
    &nbsp;&bull;&nbsp;
    <a href="login_admin.php" style="font-weight: 600; color: var(--hijau); text-decoration: none;">
      Panel Admin
    </a>
  </div>

  <div style="text-align: center; margin-top: 12px;">
    <a href="index.php" class="teks-redup" style="font-size: 0.8rem; text-decoration: none;">
      Kembali ke Beranda
    </a>
  </div>

</div>

<script>
  const btnToggle = document.getElementById('btn-toggle-pass');
  const inputPass = document.getElementById('password');
  if (btnToggle && inputPass) {
    btnToggle.addEventListener('click', function () {
      const isPassword = inputPass.getAttribute('type') === 'password';
      inputPass.setAttribute('type', isPassword ? 'text' : 'password');
      this.textContent = isPassword ? 'Sembunyikan' : 'Lihat';
    });
  }
</script>

</body>
</html>
