-- =====================================================================
-- 01c. Pemetaan unit ke FAKULTAS (selain ke prodi)
--
-- Unit seperti 01.01 "Wakil Dekan II FKIP" bukan prodi, jadi dipetakan ke fakultas.
-- * ueu_ref_fakultas  : salinan PostgreSQL ummetro_pdptumm.tbl_fak (diisi php artisan simaku:sinkron-prodi)
-- * ueu_map_unit_prodi: + jenis ('prodi' / 'fakultas'), + kode_fak; id_sms boleh NULL untuk jenis fakultas.
-- Mapping yang sudah ada tetap jenis 'prodi'. Hanya menambah/melonggarkan kolom; data tidak berubah.
-- Aman dijalankan berulang.
-- =====================================================================

CREATE TABLE IF NOT EXISTS ueu_ref_fakultas (
  kode_fak       VARCHAR(4)   NOT NULL,              -- tbl_fak.kode_fak (= ueu_tbl_unit.kode_fak pada unit FF.00)
  nama_fak       VARCHAR(150) NOT NULL DEFAULT '',   -- mis. "Keguruan dan Ilmu Pendidikan"
  nama_fak2      VARCHAR(150) NOT NULL DEFAULT '',   -- mis. "Fakultas Keguruan dan Ilmu Pendidikan"
  disinkron_pada DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (kode_fak)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- jenis
SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'ueu_map_unit_prodi' AND column_name = 'jenis');
SET @sql := IF(@ada = 0,
  'ALTER TABLE ueu_map_unit_prodi ADD COLUMN jenis ENUM(''prodi'',''fakultas'') NOT NULL DEFAULT ''prodi'' AFTER id_unit',
  'SELECT ''kolom jenis sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- kode_fak
SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'ueu_map_unit_prodi' AND column_name = 'kode_fak');
SET @sql := IF(@ada = 0,
  'ALTER TABLE ueu_map_unit_prodi ADD COLUMN kode_fak VARCHAR(4) NULL DEFAULT NULL AFTER id_sms, ADD KEY idx_map_fak (kode_fak)',
  'SELECT ''kolom kode_fak sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- id_sms boleh NULL (mapping fakultas tidak punya prodi)
SET @wajib := (SELECT COUNT(*) FROM information_schema.columns
               WHERE table_schema = DATABASE() AND table_name = 'ueu_map_unit_prodi' AND column_name = 'id_sms' AND is_nullable = 'NO');
SET @sql := IF(@wajib = 1,
  'ALTER TABLE ueu_map_unit_prodi MODIFY id_sms VARCHAR(36) NULL DEFAULT NULL',
  'SELECT ''id_sms sudah boleh NULL'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
