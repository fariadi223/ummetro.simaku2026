-- =====================================================================
-- 01a. Tambah kolom kode_npm ke ueu_map_unit_prodi yang sudah ada
--
-- kode_npm = kode prodi di dalam NPM mahasiswa, boleh NULL dan diisi belakangan.
-- Hanya menambah kolom & indeks; data mapping yang ada tidak berubah.
-- Aman dijalankan berulang: kolom/indeks hanya ditambah jika belum ada.
-- =====================================================================

SET @ada_kolom := (SELECT COUNT(*) FROM information_schema.columns
                   WHERE table_schema = DATABASE() AND table_name = 'ueu_map_unit_prodi' AND column_name = 'kode_npm');
SET @sql := IF(@ada_kolom = 0,
  'ALTER TABLE ueu_map_unit_prodi ADD COLUMN kode_npm VARCHAR(10) NULL DEFAULT NULL AFTER kode_prg',
  'SELECT ''kolom kode_npm sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @ada_idx := (SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = 'ueu_map_unit_prodi' AND index_name = 'idx_map_npm');
SET @sql := IF(@ada_idx = 0,
  'ALTER TABLE ueu_map_unit_prodi ADD KEY idx_map_npm (kode_npm)',
  'SELECT ''indeks idx_map_npm sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
