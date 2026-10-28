# Basis Data Calorie Lab

Folder ini berisi skema master basis data dan konfigurasi koneksi untuk Calorie Lab.

## Berkas

1. **`calorie_lab.sql`**  
   Skema master basis data MySQL yang mencakup seluruh tabel sistem:
   - `users` (dengan role: `user`, `pakar`, `admin`)
   - `kategori_bmi` (kategori klinis: Kurus, Normal, Gemuk, Obesitas)
   - `menu_diet` & `menu_kategori` (katalog 240+ menu makanan terkurasi)
   - `riwayat_bmi` & `catatan_berat` (tracking perkembangan fisik)
   - `catatan_makan` & `menu_harian` (tracking kalori harian)
   - `saran_pakar` & `catatan_gizi` (fitur telaah nutrisi pakar gizi)

2. **`koneksi_contoh.php`**  
   Contoh templat konfigurasi PDO MySQL jika aplikasi dipindahkan ke server atau lingkungan hosting baru.

## Akun Bawaan Sistem

| Role | Email | Password | Kegunaan |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@calorielab.com` | `admin123` | Manajemen menu, pengguna, dan pengaturan sistem |
| **Pakar Gizi** | `pakar@calorielab.com` | `pakar123` | Tinjauan nutrisi menu, edukasi gizi, dan statistik klinis |
