-- SIMAKU v2: unit kerja (sub unit, kode bukan *.00) pada BKK non anggaran.
-- Kolom boleh NULL agar insert dari v1 (yang tidak mengisi id_unit) tetap berjalan,
-- dan BKK lama tetap tanpa unit.
ALTER TABLE ueu_tbl_bkk
  ADD COLUMN id_unit INT NULL DEFAULT NULL AFTER tahun,
  ADD INDEX idx_bkk_id_unit (id_unit);
