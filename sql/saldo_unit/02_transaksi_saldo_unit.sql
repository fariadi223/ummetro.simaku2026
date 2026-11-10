-- =====================================================================
-- 02. Saldo anggaran unit: rincian transaksi Kas di Unit Kerja satu induk (FF) atau satu sub-unit
--
-- Parameter (jalankan dulu di sesi yang sama):
--   SET @p_tahun     = '2026';
--   SET @p_idrek6    = 570;
--   SET @p_tgl_awal  = NULL;          -- NULL = sejak awal; jika diisi, mutasi sebelumnya jadi baris "Saldo awal"
--   SET @p_tgl_akhir = NULL;
--   SET @p_ff        = '01';          -- kelompok (kodeunit), wajib
--   SET @p_id_uk     = NULL;          -- NULL = semua sub-unit di @p_ff; 0 = jurnal tanpa unit; atau id_unit
--
-- jenis_kredit: diterima (debet) / realisasi / disetor / lainnya, sama dengan 01_saldo_unit.sql.
-- Saldo berjalan = SUM(debet − kredit) OVER (ORDER BY jenis, tanggal, id_trans);
-- saldo baris terakhir = saldo di 01_saldo_unit.sql untuk induk/sub-unit yang sama.
-- Dipakai juga oleh App\Services\SaldoUnit::transaksi().
-- =====================================================================

WITH
j AS (
  SELECT j.id_trans, j.tgl_trk, j.bukti, j.uraian, j.id_uk, j.idrinci,
         COALESCE(j.debet, 0) AS debet, COALESCE(j.kredit, 0) AS kredit
  FROM ueu_tbl_jurnal_spm j
  WHERE j.tahun = CONVERT(@p_tahun USING latin1)
    AND j.idrek6 = @p_idrek6
    AND LPAD(COALESCE(j.kodeunit, 0), 2, '0') = @p_ff
    AND (@p_id_uk IS NULL OR j.id_uk = @p_id_uk)
    AND (@p_tgl_akhir IS NULL OR j.tgl_trk <= CAST(@p_tgl_akhir AS DATE))
),
baris AS (
  SELECT 0 AS jenis, CAST(@p_tgl_awal AS DATE) AS tgl, 0 AS id_trans, '' AS bukti, 'Saldo awal' AS uraian,
         NULL AS id_uk, '' AS jenis_kredit, NULL AS debet, NULL AS kredit, SUM(debet) - SUM(kredit) AS nilai
  FROM j
  WHERE @p_tgl_awal IS NOT NULL AND tgl_trk < CAST(@p_tgl_awal AS DATE)
  HAVING COUNT(*) > 0

  UNION ALL

  SELECT 1, tgl_trk, id_trans, bukti, uraian, id_uk,
         CASE WHEN kredit = 0 THEN 'diterima'
              WHEN idrinci > 0 THEN 'realisasi'
              WHEN uraian LIKE 'setor kas dari unit%' THEN 'disetor'
              ELSE 'lainnya' END,
         debet, kredit, debet - kredit
  FROM j
  WHERE @p_tgl_awal IS NULL OR tgl_trk >= CAST(@p_tgl_awal AS DATE)
)
SELECT b.jenis, b.tgl AS tanggal, b.id_trans, b.bukti, b.uraian, b.jenis_kredit,
       COALESCE(u.kode, IF(b.id_uk = 0, '-', ''))  AS kode_unit,
       b.debet, b.kredit,
       SUM(b.nilai) OVER (ORDER BY b.jenis, b.tgl, b.id_trans ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS saldo
FROM baris b
LEFT JOIN ueu_tbl_unit u ON u.id_unit = b.id_uk
ORDER BY b.jenis, b.tgl, b.id_trans;
