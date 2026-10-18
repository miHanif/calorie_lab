<?php
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama  = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($nama === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Isi nama dan gunakan format email yang benar.';
    } elseif (strlen($pass) < 6) {
        $error = 'Kata sandi minimal 6 karakter.';
    } else {
        $cek = $pdo->prepare('SELECT id FROM users WHERE email = ?');
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
    <label for="nama">Nama lengkap</label>
    <input type="text" id="nama" name="nama" value="<?= h($_POST['nama'] ?? '') ?>" required>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>

    <label for="password">Kata sandi</label>
    <input type="password" id="password" name="password" minlength="6" required>

    <button type="submit" class="tombol penuh">Buat akun</button>
  </form>

  <p class="catatan">Sudah punya akun? <a href="login.php">Masuk</a></p>
</div>

<?php include 'footer.php'; ?>