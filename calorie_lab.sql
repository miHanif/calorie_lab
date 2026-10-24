SET NAMES utf8mb4;

DROP TABLE IF EXISTS menu_harian_item;
DROP TABLE IF EXISTS menu_harian;
DROP TABLE IF EXISTS catatan_makan, favorit, riwayat_bmi, menu_kategori, menu_diet, kategori_bmi, users;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  nama          VARCHAR(100) NOT NULL,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,       
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kategori_bmi (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  kode    VARCHAR(20) NOT NULL UNIQUE,
  nama    VARCHAR(50) NOT NULL,
  bmi_min DECIMAL(5,2) NOT NULL,
  bmi_max DECIMAL(5,2) NOT NULL,
  warna   VARCHAR(7) NOT NULL,                 
  saran   TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu_diet (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nama_menu   VARCHAR(120) NOT NULL,
  waktu_makan ENUM('pagi','siang','malam','camilan') NOT NULL,
  kalori      INT NOT NULL,                     
  harga       INT NOT NULL,                     
  vegetarian  TINYINT(1) NOT NULL DEFAULT 0,
  deskripsi   TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu_kategori (
  menu_id     INT NOT NULL,
  kategori_id INT NOT NULL,
  PRIMARY KEY (menu_id, kategori_id),
  FOREIGN KEY (menu_id)     REFERENCES menu_diet(id)    ON DELETE CASCADE,
  FOREIGN KEY (kategori_id) REFERENCES kategori_bmi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE riwayat_bmi (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  tinggi_cm     DECIMAL(5,1) NOT NULL,
  berat_kg      DECIMAL(5,1) NOT NULL,
  usia          TINYINT UNSIGNED,
  jenis_kelamin ENUM('L','P'),
  bmi           DECIMAL(4,1) NOT NULL,
  kategori_id   INT NOT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)     REFERENCES users(id)        ON DELETE CASCADE,
  FOREIGN KEY (kategori_id) REFERENCES kategori_bmi(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE favorit (
  user_id INT NOT NULL,
  menu_id INT NOT NULL,
  PRIMARY KEY (user_id, menu_id),
  FOREIGN KEY (user_id) REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (menu_id) REFERENCES menu_diet(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE catatan_makan (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  menu_id    INT NOT NULL,
  porsi      DECIMAL(3,1) NOT NULL DEFAULT 1,
  tanggal    DATE NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (menu_id) REFERENCES menu_diet(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO kategori_bmi (id, kode, nama, bmi_min, bmi_max, warna, saran) VALUES
(1,'kurus','Kurus',0.00,18.49,'#3B82C4','Tambah asupan kalori dan protein secara bertahap dengan makanan bergizi.'),
(2,'normal','Normal',18.50,25.00,'#1F8A5B','Pertahankan pola makan seimbang dan aktivitas rutin.'),
(3,'gemuk','Gemuk',25.01,27.00,'#E0A100','Kurangi gula dan gorengan, perbanyak sayur dan serat.'),
(4,'obesitas','Obesitas',27.01,99.99,'#D6453D','Pilih menu rendah kalori tinggi serat dan sebaiknya konsultasi ke ahli gizi.');

INSERT INTO menu_diet (id, nama_menu, waktu_makan, kalori, harga, vegetarian, deskripsi) VALUES
(1,'Nasi, telur dadar, tempe goreng','pagi',520,12000,1,'Sarapan padat energi dan protein.'),
(2,'Oatmeal pisang susu','pagi',380,8000,1,'Praktis dibuat di kos.'),
(3,'Bubur ayam suwir','pagi',350,11000,0,'Tanpa kerupuk berlebih.'),
(4,'Telur rebus dan buah potong','pagi',210,7000,1,'Rendah kalori, mengenyangkan.'),
(5,'Nasi, ayam goreng, sayur bayam','siang',680,17000,0,'Porsi besar untuk menambah berat badan.'),
(6,'Nasi pecel dan tempe','siang',480,12000,1,'Seimbang dan terjangkau.'),
(7,'Gado-gado tanpa lontong','siang',320,13000,1,'Tinggi serat.'),
(8,'Sup bening dan dada ayam rebus','siang',290,15000,0,'Tinggi protein, rendah lemak.'),
(9,'Nasi, ikan bakar, lalapan','malam',520,16000,0,'Protein ikan dan sayur mentah.'),
(10,'Tumis tahu tauge, nasi setengah porsi','malam',340,9000,1,'Murah dan ringan.'),
(11,'Sup sayur dan telur','malam',250,8000,1,'Makan malam ringan.'),
(12,'Roti gandum selai kacang','camilan',300,6000,1,'Camilan penambah kalori.'),
(13,'Pisang dan segelas susu','camilan',260,6000,1,'Cepat dan murah.'),
(14,'Kacang rebus','camilan',150,4000,1,'Camilan protein nabati.'),
(15,'Apel atau jeruk','camilan',80,5000,1,'Camilan rendah kalori.'),
(16,'Salad sayur dan telur rebus','malam',230,12000,1,'Pilihan malam rendah kalori.');

INSERT INTO menu_kategori (menu_id, kategori_id) VALUES
(1,1),(1,2),(2,1),(2,2),(3,2),(3,3),(4,3),(4,4),
(5,1),(6,2),(6,3),(7,3),(7,4),(8,3),(8,4),
(9,1),(9,2),(10,2),(10,3),(10,4),(11,3),(11,4),
(12,1),(12,2),(13,1),(13,2),(14,2),(14,3),(14,4),(15,3),(15,4),(16,3),(16,4);

ALTER TABLE riwayat_bmi ADD target_kalori INT NULL;

CREATE TABLE rencana_diet (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  tujuan        ENUM('turun','naik','pertahankan') NOT NULL,
  berat_awal    DECIMAL(5,1) NOT NULL,
  berat_target  DECIMAL(5,1) NOT NULL,
  tinggi_cm     DECIMAL(5,1) NOT NULL,
  usia          TINYINT UNSIGNED NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL,
  aktivitas     DECIMAL(4,3) NOT NULL,
  tanggal_mulai DATE NOT NULL,
  durasi_hari   INT NOT NULL,
  target_kalori INT NOT NULL,
  status        ENUM('aktif','selesai','batal') NOT NULL DEFAULT 'aktif',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE catatan_berat (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  rencana_id INT NULL,
  berat_kg   DECIMAL(5,1) NOT NULL,
  tanggal    DATE NOT NULL,
  catatan    VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(id)        ON DELETE CASCADE,
  FOREIGN KEY (rencana_id) REFERENCES rencana_diet(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu_harian (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  tanggal    DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_menu_harian_user_tanggal (user_id, tanggal),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu_harian_item (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  menu_harian_id   INT NOT NULL,
  menu_id          INT NOT NULL,
  urutan           TINYINT UNSIGNED NOT NULL,
  catatan_makan_id INT NULL,
  UNIQUE KEY uq_menu_harian_item_urutan (menu_harian_id, urutan),
  UNIQUE KEY uq_menu_harian_item_catatan (catatan_makan_id),
  FOREIGN KEY (menu_harian_id) REFERENCES menu_harian(id) ON DELETE CASCADE,
  FOREIGN KEY (menu_id) REFERENCES menu_diet(id) ON DELETE CASCADE,
  FOREIGN KEY (catatan_makan_id) REFERENCES catatan_makan(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
