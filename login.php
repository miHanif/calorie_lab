<?php
require_once 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$pesan = $_SESSION['pesan'] ?? '';
unset($_SESSION['pesan']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $q = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $q->execute([$email]);
    $user = $q->fetch();

    if ($user && password_verify($pass, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nama']    = $user['nama'];
        $_SESSION['role']    = $user['role'];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Email atau kata sandi salah.';
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
    <label for="email">Email</label>
    <input type="email" id="email" name="email" required>

    <label for="password">Kata sandi</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="tombol penuh">Masuk</button>
  </form>

  <p class="catatan">Belum punya akun? <a href="register.php">Daftar</a></p>
</div>

<?php include 'footer.php'; ?>