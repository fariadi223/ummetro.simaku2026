-- =====================================================================
-- 02. Laporan keuangan unit: neraca saldo per rekening untuk satu unit atau satu kelompok FF.00
--
-- Parameter (jalankan dulu di sesi yang sama):
--   SET @p_tahun     = '2025';
--   SET @p_tgl_awal  = '2025-09-01';
--   SET @p_tgl_akhir = '2026-08-31';
--   SET @p_ff        = '01';          -- kelompok induk (2 digit pertama kode unit); NULL = semua kelompok
--   SET @p_id_unit   = NULL;          -- satu unit; NULL = semua unit terpetakan di kelompok @p_ff
--   SET @p_kepala    = NULL;          -- NULL = semua rekening; '1,2,3' = hanya kepala 1 (aset), 2 (kewajiban), 3 (aset neto)
--
-- Cakupan sama dengan 01_rekap_unit.sql: hanya unit yang ada di ueu_map_unit_prodi.
--   saldo_awal   = mutasi tahun @p_tahun dengan tgl_trk < @p_tgl_awal (arah saldo normal)
--   total_debet / total_kredit = mutasi @p_tgl_awal .. @p_tgl_akhir
--   saldo_akhir  = saldo_awal + mutasi (saldo normal: kepala 1 & 5 = D, lainnya = K)
-- Dipakai juga oleh App\Services\LaporanUnit::rekening().
-- Rekening di ueu_lap_unit_rek_nonaktif (Admin > Rekening Laporan Unit) tidak dihitung.
-- =====================================================================

WITH
unit AS (
  SELECT u.id_unit, m.berlaku_dari
  FROM ueu_map_unit_prodi m
  JOIN ueu_tbl_unit u ON u.id_unit = m.id_unit
  WHERE (@p_ff IS NULL OR LEFT(u.kode, 2) = CONVERT(@p_ff USING latin1))
    AND (@p_id_unit IS NULL OR u.id_unit = @p_id_unit)
),
rek AS (
  SELECT r6.idrek6, r6.namarek6, r1.koderek1,
         CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) AS kode
  FROM ueu_tbl_rek6 r6
  LEFT JOIN ueu_tbl_rek5 r5 ON r5.idrek5 = r6.idrek5
  LEFT JOIN ueu_tbl_rek4 r4 ON r4.idrek4 = r5.idrek4
  LEFT JOIN ueu_tbl_rek3 r3 ON r3.idrek3 = r4.idrek3
  LEFT JOIN ueu_tbl_rek2 r2 ON r2.idrek2 = r3.idrek2
  LEFT JOIN ueu_tbl_rek1 r1 ON r1.idrek1 = r2.idrek1
),
agg AS (
  SELECT j.idrek6,
         SUM(CASE WHEN j.tgl_trk <  CAST(@p_tgl_awal AS DATE) THEN COALESCE(j.debet, 0)  ELSE 0 END) AS sa_d,
         SUM(CASE WHEN j.tgl_trk <  CAST(@p_tgl_awal AS DATE) THEN COALESCE(j.kredit, 0) ELSE 0 END) AS sa_k,
         SUM(CASE WHEN j.tgl_trk >= CAST(@p_tgl_awal AS DATE) THEN COALESCE(j.debet, 0)  ELSE 0 END) AS mut_d,
         SUM(CASE WHEN j.tgl_trk >= CAST(@p_tgl_awal AS DATE) THEN COALESCE(j.kredit, 0) ELSE 0 END) AS mut_k,
         SUM(CASE WHEN j.tgl_trk >= CAST(@p_tgl_awal AS DATE) THEN 1 ELSE 0 END)                     AS n
  FROM ueu_tbl_jurnal_spm j
  JOIN unit un ON un.id_unit = j.id_uk
             AND (un.berlaku_dari IS NULL OR j.tgl_trk >= un.berlaku_dari)
  WHERE j.tahun = CONVERT(@p_tahun USING latin1)
    AND j.tgl_trk <= CAST(@p_tgl_akhir AS DATE)
    AND NOT EXISTS (SELECT 1 FROM ueu_lap_unit_rek_nonaktif x WHERE x.idrek6 = j.idrek6)   -- rekening nonaktif (00_rekening_nonaktif.sql)
  GROUP BY j.idrek6
)
SELECT a.idrek6,
       COALESCE(r.kode, CONCAT('#', a.idrek6))                          AS kode_rek,
       COALESCE(r.namarek6, '(rekening tidak ditemukan)')               AS nama_rek,
       IF(r.koderek1 IN ('1', '5'), 'D', 'K')                           AS saldo_normal,
       a.n                                                              AS jml_transaksi,
       IF(r.koderek1 IN ('1', '5'), a.sa_d - a.sa_k, a.sa_k - a.sa_d)   AS saldo_awal,
       a.mut_d                                                          AS total_debet,
       a.mut_k                                                          AS total_kredit,
       IF(r.koderek1 IN ('1', '5'), (a.sa_d + a.mut_d) - (a.sa_k + a.mut_k),
                                    (a.sa_k + a.mut_k) - (a.sa_d + a.mut_d)) AS saldo_akhir
FROM agg a
LEFT JOIN rek r ON r.idrek6 = a.idrek6
WHERE @p_kepala IS NULL OR FIND_IN_SET(r.koderek1, @p_kepala) > 0                        -- mode: hanya kepala tertentu
ORDER BY kode_rek, a.idrek6;
