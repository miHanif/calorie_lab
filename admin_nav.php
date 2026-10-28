<?php
// Navigasi tab admin
$admin_halaman = basename($_SERVER['PHP_SELF'] ?? '');
?>
<div class="admin-header-card kartu" style="margin-bottom: 24px; padding: 22px 24px; background: linear-gradient(135deg, #FFFFFF 0%, #F5F3EF 100%); border: 1px solid var(--garis);">
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
    <div>
      <span class="badge" style="background: var(--mint); color: #264A38; font-weight: 700; margin-bottom: 6px; display: inline-block;">
        Administrator Workspace
      </span>
      <h2 style="margin: 0; font-size: 1.6rem; color: var(--gelap);">Kontrol Panel Pemilik Website</h2>
      <p class="teks-redup" style="margin: 4px 0 0; font-size: 0.92rem;">
        Kelola basis data makanan, pengguna, serta pantau statistik dan kesehatan sistem Calorie Lab.
      </p>
    </div>
    <div>
      <a href="dashboard.php" class="tombol sekunder kecil">← Ke Aplikasi Pengguna</a>
    </div>
  </div>

  <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; border-top: 1px solid var(--garis); padding-top: 16px;">
    <a href="admin_dashboard.php" class="kategori-waktu-tab <?= $admin_halaman === 'admin_dashboard.php' ? 'aktif' : '' ?>" style="text-decoration:none;">
      Ringkasan & Statistik
    </a>
    <a href="admin_makanan.php" class="kategori-waktu-tab <?= $admin_halaman === 'admin_makanan.php' ? 'aktif' : '' ?>" style="text-decoration:none;">
      Basis Data Makanan (CRUD)
    </a>
    <a href="admin_pengguna.php" class="kategori-waktu-tab <?= $admin_halaman === 'admin_pengguna.php' ? 'aktif' : '' ?>" style="text-decoration:none;">
      Manajemen Pengguna
    </a>
  </div>
</div>
