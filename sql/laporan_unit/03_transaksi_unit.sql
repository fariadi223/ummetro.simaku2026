-- =====================================================================
-- 03. Laporan keuangan unit: rincian transaksi satu rekening untuk satu unit atau satu kelompok FF.00
--
-- Parameter (jalankan dulu di sesi yang sama):
--   SET @p_tahun     = '2025';
--   SET @p_tgl_awal  = '2025-09-01';
--   SET @p_tgl_akhir = '2026-08-31';
--   SET @p_ff        = '01';          -- kelompok induk; NULL = semua
--   SET @p_id_unit   = NULL;          -- satu unit; NULL = semua unit terpetakan di kelompok @p_ff
--   SET @p_idrek6    = 570;           -- rekening (wajib)
--   SET @p_kepala    = NULL;          -- NULL = semua rekening; '1,2,3' = hanya kepala 1 (aset), 2 (kewajiban), 3 (aset neto)
--
-- Cakupan & aturan sama dengan 02_rekening_unit.sql:
--   baris "Saldo awal" = mutasi tahun @p_tahun dengan tgl_trk < @p_tgl_awal (hanya jika ada)
--   saldo berjalan     = SUM() OVER (ORDER BY jenis, tanggal, id_trans), arah saldo normal rekening
-- Saldo akhir baris terakhir = saldo_akhir rekening itu di 02_rekening_unit.sql.
-- Dipakai juga oleh App\Services\LaporanUnit::transaksi().
-- Rekening di ueu_lap_unit_rek_nonaktif (Admin > Rekening Laporan Unit) tidak dihitung.
-- =====================================================================

WITH
unit AS (
  SELECT u.id_unit, u.kode, m.berlaku_dari
  FROM ueu_map_unit_prodi m
  JOIN ueu_tbl_unit u ON u.id_unit = m.id_unit
  WHERE (@p_ff IS NULL OR LEFT(u.kode, 2) = CONVERT(@p_ff USING latin1))
    AND (@p_id_unit IS NULL OR u.id_unit = @p_id_unit)
),
normal AS (
  -- 1 = saldo normal debet (kepala 1 & 5), 0 = kredit
  SELECT COALESCE(MAX(r1.koderek1 IN ('1', '5')), 1) AS debet, MAX(r1.koderek1) AS kepala
  FROM ueu_tbl_rek6 r6
  LEFT JOIN ueu_tbl_rek5 r5 ON r5.idrek5 = r6.idrek5
  LEFT JOIN ueu_tbl_rek4 r4 ON r4.idrek4 = r5.idrek4
  LEFT JOIN ueu_tbl_rek3 r3 ON r3.idrek3 = r4.idrek3
  LEFT JOIN ueu_tbl_rek2 r2 ON r2.idrek2 = r3.idrek2
  LEFT JOIN ueu_tbl_rek1 r1 ON r1.idrek1 = r2.idrek1
  WHERE r6.idrek6 = @p_idrek6
),
jur AS (
  SELECT j.id_trans, j.tgl_trk, j.bukti, j.uraian, un.kode AS kode_unit,
         COALESCE(j.debet, 0) AS debet, COALESCE(j.kredit, 0) AS kredit
  FROM ueu_tbl_jurnal_spm j
  JOIN unit un ON un.id_unit = j.id_uk
             AND (un.berlaku_dari IS NULL OR j.tgl_trk >= un.berlaku_dari)
  WHERE j.tahun = CONVERT(@p_tahun USING latin1)
    AND j.idrek6 = @p_idrek6
    AND j.tgl_trk <= CAST(@p_tgl_akhir AS DATE)
    AND NOT EXISTS (SELECT 1 FROM ueu_lap_unit_rek_nonaktif x WHERE x.idrek6 = j.idrek6)   -- rekening nonaktif (00_rekening_nonaktif.sql)
),
baris AS (
  SELECT 0 AS jenis, CAST(@p_tgl_awal AS DATE) AS tgl, 0 AS id_trans, '' AS bukti, 'Saldo awal' AS uraian, '' AS kode_unit,
         NULL AS debet, NULL AS kredit, SUM(debet) AS d_nilai, SUM(kredit) AS k_nilai
  FROM jur
  WHERE tgl_trk < CAST(@p_tgl_awal AS DATE)
  HAVING COUNT(*) > 0

  UNION ALL

  SELECT 1, tgl_trk, id_trans, bukti, uraian, kode_unit, debet, kredit, debet, kredit
  FROM jur
  WHERE tgl_trk >= CAST(@p_tgl_awal AS DATE)
)
SELECT b.jenis, b.tgl AS tanggal, b.id_trans, b.bukti, b.uraian, b.kode_unit, b.debet, b.kredit,
       SUM(IF(n.debet, b.d_nilai - b.k_nilai, b.k_nilai - b.d_nilai))
         OVER (ORDER BY b.jenis, b.tgl, b.id_trans ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS saldo
FROM baris b
CROSS JOIN normal n
WHERE @p_kepala IS NULL OR FIND_IN_SET(n.kepala, @p_kepala) > 0                          -- mode: hanya kepala tertentu
ORDER BY b.jenis, b.tgl, b.id_trans;
