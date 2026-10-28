<?php
// pakar_nav.php — Navigasi tab portal pakar terintegrasi Calorie Lab
$pakar_halaman = basename($_SERVER['PHP_SELF'] ?? '');
?>
<div class="admin-header-card kartu" style="margin-bottom: 24px; padding: 22px 24px; background: linear-gradient(135deg, #FFFFFF 0%, #F5F3EF 100%); border: 1px solid var(--garis);">
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
    <div>
      <span class="badge" style="background: var(--mint); color: #264A38; font-weight: 700; margin-bottom: 6px; display: inline-block;">
        Portal Pakar Gizi
      </span>
      <h2 style="margin: 0; font-size: 1.6rem; color: var(--gelap);">Workspace Peninjauan & Edukasi Gizi</h2>
      <p class="teks-redup" style="margin: 4px 0 0; font-size: 0.92rem;">
        Tinjau kualitas nutrisi menu makanan, berikan edukasi gizi untuk pengguna, dan pantau ringkasan statistik klinis secara anonim.
      </p>
    </div>
    <div>
      <a href="dashboard.php" class="tombol sekunder kecil">Ke Aplikasi Pengguna</a>
    </div>
  </div>

  <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; border-top: 1px solid var(--garis); padding-top: 16px;">
    <a href="pakar_dashboard.php" class="kategori-waktu-tab <?= $pakar_halaman === 'pakar_dashboard.php' ? 'aktif' : '' ?>" style="text-decoration:none;">
      Ringkasan & Statistik
    </a>
    <a href="pakar_menu.php" class="kategori-waktu-tab <?= $pakar_halaman === 'pakar_menu.php' ? 'aktif' : '' ?>" style="text-decoration:none;">
      Tinjauan Menu Gizi
    </a>
    <a href="pakar_saran.php" class="kategori-waktu-tab <?= $pakar_halaman === 'pakar_saran.php' ? 'aktif' : '' ?>" style="text-decoration:none;">
      Saran untuk Pengguna
    </a>
  </div>
</div>
