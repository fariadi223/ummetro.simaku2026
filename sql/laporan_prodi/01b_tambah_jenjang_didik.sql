-- =====================================================================
-- 01b. Tambah kolom jenjang_didik ke ueu_ref_prodi yang sudah ada
--
-- jenjang_didik = D3 / S1 / S2 / Profesi (dari tbl_sms.jenjang_didik), pembeda prodi bernama sama
-- (mis. Manajemen S1 61201 vs Manajemen S2 61101). Diisi oleh: php artisan simaku:sinkron-prodi
-- Hanya menambah kolom; data yang ada tidak berubah. Aman dijalankan berulang.
-- =====================================================================

SET @ada_kolom := (SELECT COUNT(*) FROM information_schema.columns
                   WHERE table_schema = DATABASE() AND table_name = 'ueu_ref_prodi' AND column_name = 'jenjang_didik');
SET @sql := IF(@ada_kolom = 0,
  'ALTER TABLE ueu_ref_prodi ADD COLUMN jenjang_didik VARCHAR(20) NOT NULL DEFAULT '''' AFTER kode_prodi',
  'SELECT ''kolom jenjang_didik sudah ada'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
