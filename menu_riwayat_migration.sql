SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS menu_harian (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  tanggal    DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_menu_harian_user_tanggal (user_id, tanggal),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menu_harian_item (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  menu_harian_id INT NOT NULL,
  menu_id        INT NOT NULL,
  urutan         TINYINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_menu_harian_item_urutan (menu_harian_id, urutan),
  FOREIGN KEY (menu_harian_id) REFERENCES menu_harian(id) ON DELETE CASCADE,
  FOREIGN KEY (menu_id) REFERENCES menu_diet(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @has_catatan_makan_id = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'menu_harian_item'
    AND column_name = 'catatan_makan_id'
);
SET @migration_sql = IF(
  @has_catatan_makan_id = 0,
  'ALTER TABLE menu_harian_item
     ADD COLUMN catatan_makan_id INT NULL,
     ADD UNIQUE KEY uq_menu_harian_item_catatan (catatan_makan_id),
     ADD CONSTRAINT fk_menu_harian_item_catatan
       FOREIGN KEY (catatan_makan_id) REFERENCES catatan_makan(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
