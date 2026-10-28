<?php
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$pesan = $_SESSION['pesan'] ?? '';
unset($_SESSION['pesan']);

$csrf = csrf_token('csrf_token_login');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!csrf_valid($token, 'csrf_token_login')) {
        $error = 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $q = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = ?');
        $q->execute([$email]);
        $user = $q->fetch();

        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['role']    = $user['role'];
            if ($user['role'] === 'admin') {
                header('Location: admin_dashboard.php');
            } elseif ($user['role'] === 'pakar') {
                header('Location: pakar_dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
        $error = 'Email atau kata sandi salah.';
    }
}

$judul = 'Masuk';
include 'header.php';
?>

<div class="kotak-form">
  <h2>Masuk</h2>

  <?php if ($pesan): ?>
    <div class="alert sukses"><?= h($pesan) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>

    <label for="password">Kata sandi</label>
    <div class="password-input-group">
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <button type="button" class="toggle-pass-btn" id="btn-toggle-pass" aria-label="Lihat atau sembunyikan kata sandi">
        Lihat
      </button>
    </div>

    <button type="submit" class="tombol penuh">Masuk</button>
  </form>

  <p class="catatan">Belum punya akun? <a href="register.php">Daftar</a></p>
  <p class="catatan" style="margin-top: 10px; font-size: 0.8rem;">
    Pemilik website? <a href="login_admin.php" style="color: var(--hijau); font-weight: 600;">Login Administrator</a>
  </p>
  <p class="catatan" style="margin-top: 6px; font-size: 0.8rem;">
    Ahli gizi / pakar? <a href="login_pakar.php" style="color: #4F46E5; font-weight: 600;">Login Portal Pakar</a>
  </p>
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

<?php include 'footer.php'; ?>
