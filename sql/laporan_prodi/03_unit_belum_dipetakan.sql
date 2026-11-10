-- =====================================================================
-- 03. Unit yang belum terpetakan ke prodi, beserta jumlah transaksinya
--
-- Parameter (jalankan dulu di sesi yang sama):
--   SET @p_tahun = '2025';   -- T.A (kolom tahun di jurnal); NULL = semua tahun
--
-- Berisi dua kelompok:
--   * unit di ueu_tbl_unit yang belum punya baris di ueu_map_unit_prodi;
--   * id_uk di jurnal yang tidak ada di ueu_tbl_unit (mis. id_uk = 0 untuk penerimaan/mutasi).
-- Urutan: unit "Prodi" lebih dulu (prioritas pemetaan), lalu berdasarkan jumlah transaksi.
-- Dipakai juga oleh App\Services\PemetaanUnit::belumDipetakan().
-- =====================================================================

SELECT x.id_unit, x.kode, x.nama, x.unit_prodi, x.jml_transaksi, x.total_debet, x.total_kredit
FROM (
  -- Unit terdaftar yang belum dipetakan
  SELECT u.id_unit,
         u.kode,
         u.nama,
         (TRIM(u.nama) LIKE 'Prodi%')      AS unit_prodi,
         COUNT(j.id_trans)                 AS jml_transaksi,
         COALESCE(SUM(j.debet), 0)         AS total_debet,
         COALESCE(SUM(j.kredit), 0)        AS total_kredit
  FROM ueu_tbl_unit u
  LEFT JOIN ueu_map_unit_prodi m ON m.id_unit = u.id_unit
  LEFT JOIN ueu_tbl_jurnal_spm j
         ON j.id_uk = u.id_unit
        AND (@p_tahun IS NULL OR j.tahun = CONVERT(@p_tahun USING latin1))
  WHERE m.id_map IS NULL
  GROUP BY u.id_unit, u.kode, u.nama

  UNION ALL

  -- id_uk di jurnal yang tidak ada di ueu_tbl_unit
  SELECT j.id_uk,
         NULL,
         CASE WHEN j.id_uk = 0 THEN '(id_uk = 0: tanpa unit)' ELSE '(id_uk tidak ada di ueu_tbl_unit)' END,
         0,
         COUNT(*),
         COALESCE(SUM(j.debet), 0),
         COALESCE(SUM(j.kredit), 0)
  FROM ueu_tbl_jurnal_spm j
  LEFT JOIN ueu_tbl_unit u ON u.id_unit = j.id_uk
  WHERE u.id_unit IS NULL
    AND (@p_tahun IS NULL OR j.tahun = CONVERT(@p_tahun USING latin1))
  GROUP BY j.id_uk
) x
ORDER BY x.unit_prodi DESC, x.jml_transaksi DESC, x.kode;
