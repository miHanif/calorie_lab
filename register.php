<?php
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$csrf = csrf_token('csrf_token_register');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $nama  = trim($_POST['nama'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!csrf_valid($token, 'csrf_token_register')) {
        $error = 'Sesi formulir kedaluwarsa. Silakan muat ulang halaman.';
    } elseif (mb_strlen($nama) < 2 || mb_strlen($nama) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Isi nama lengkap yang valid (2-100 karakter) dan gunakan format email yang benar.';
    } elseif (strlen($pass) < 6 || strlen($pass) > 72) {
        $error = 'Kata sandi minimal 6 karakter dan maksimal 72 karakter.';
    } else {
        $cek = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ?');
        $cek->execute([$email]);

        if ($cek->fetch()) {
            $error = 'Email sudah terdaftar. Silakan masuk.';
        } else {
            $simpan = $pdo->prepare('INSERT INTO users (nama, email, password_hash) VALUES (?, ?, ?)');
            $simpan->execute([$nama, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $_SESSION['pesan'] = 'Akun berhasil dibuat. Silakan masuk.';
            header('Location: login.php');
            exit;
        }
    }
}

$judul = 'Daftar';
include 'header.php';
?>

<div class="kotak-form">
  <h2>Buat akun</h2>

  <?php if ($error): ?>
    <div class="alert error"><?= h($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <label for="nama">Nama lengkap</label>
    <input type="text" id="nama" name="nama" value="<?= h($_POST['nama'] ?? '') ?>" required autofocus>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>

    <label for="password">Kata sandi</label>
    <input type="password" id="password" name="password" minlength="6" maxlength="72" required>

    <button type="submit" class="tombol penuh">Buat akun</button>
  </form>

  <p class="catatan">Sudah punya akun? <a href="login.php">Masuk</a></p>
</div>

<?php include 'footer.php'; ?>
