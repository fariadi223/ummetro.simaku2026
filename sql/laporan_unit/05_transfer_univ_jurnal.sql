-- =====================================================================
-- 05. Laporan keuangan unit: jurnal transfer dari Kas Universitas (satu unit / satu kelompok FF.00)
--
-- Parameter (jalankan dulu di sesi yang sama):
--   SET @p_tahun     = '2025';
--   SET @p_tgl_awal  = '2025-09-01';
--   SET @p_tgl_akhir = '2026-08-31';
--   SET @p_ff        = '01';          -- kelompok induk; NULL = semua
--   SET @p_id_unit   = NULL;          -- satu unit; NULL = semua unit terpetakan di kelompok @p_ff
--
-- Cakupan sama dengan 04_transfer_univ_rekap.sql: baris 569 Kas Universitas berisi kredit.
-- nilai = kredit 569 (= debet 570 Kas di Unit Kerja pada pasangan jurnalnya).
-- Dipakai juga oleh App\Services\LaporanUnit::transferJurnal().
-- =====================================================================

WITH
unit AS (
  SELECT u.id_unit, u.kode, m.berlaku_dari
  FROM ueu_map_unit_prodi m
  JOIN ueu_tbl_unit u ON u.id_unit = m.id_unit
  WHERE (@p_ff IS NULL OR LEFT(u.kode, 2) = CONVERT(@p_ff USING latin1))
    AND (@p_id_unit IS NULL OR u.id_unit = @p_id_unit)
)
SELECT j.id_trans, j.tgl_trk AS tanggal, j.bukti, j.uraian, un.kode AS kode_unit,
       COALESCE(k.uraiankeg, '') AS kegiatan, j.kredit AS nilai
FROM ueu_tbl_jurnal_spm j
JOIN unit un ON un.id_unit = j.id_uk
           AND (un.berlaku_dari IS NULL OR j.tgl_trk >= un.berlaku_dari)
LEFT JOIN ueu_tbl_kegiatan k ON k.idkeg = j.idkeg AND j.idkeg <> 0
WHERE j.tahun = CONVERT(@p_tahun USING latin1)
  AND j.idrek6 = 569                                                -- Kas Universitas
  AND COALESCE(j.kredit, 0) <> 0
  AND j.tgl_trk BETWEEN CAST(@p_tgl_awal AS DATE) AND CAST(@p_tgl_akhir AS DATE)
ORDER BY j.tgl_trk, j.id_trans;
