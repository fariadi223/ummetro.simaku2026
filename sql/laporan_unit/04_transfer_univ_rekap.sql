-- =====================================================================
-- 04. Laporan keuangan unit: rekap transfer dari Kas Universitas per unit terpetakan
--
-- Parameter (jalankan dulu di sesi yang sama):
--   SET @p_tahun     = '2025';
--   SET @p_tgl_awal  = '2025-09-01';
--   SET @p_tgl_akhir = '2026-08-31';
--
-- Transfer = jurnal pencairan (Akuntansi > Pencairan, v1 spm_jurnal):
--   debet 570 Kas di Unit Kerja / kredit 569 Kas Universitas, uraian "transfer ke unit ...".
-- Yang dihitung: baris rekening 569 (Kas Universitas) berisi kredit, id_uk = unit terpetakan.
-- Rekening 569 biasanya disembunyikan dari laporan unit (00_rekening_nonaktif.sql); query ini
-- sengaja TIDAK memakai ueu_lap_unit_rek_nonaktif agar transfer tetap terlihat terpisah.
-- Hanya unit yang punya transfer pada periode. Urut FF lalu kode unit.
-- Dipakai juga oleh App\Services\LaporanUnit::transferRekap().
-- =====================================================================

WITH
unit AS (
  SELECT u.id_unit, u.kode, u.nama, LEFT(u.kode, 2) AS ff, m.berlaku_dari
  FROM ueu_map_unit_prodi m
  JOIN ueu_tbl_unit u ON u.id_unit = m.id_unit
),
tf AS (
  SELECT j.id_uk, COUNT(*) AS n, SUM(j.kredit) AS nilai, MAX(j.tgl_trk) AS terakhir
  FROM ueu_tbl_jurnal_spm j
  JOIN unit un ON un.id_unit = j.id_uk
             AND (un.berlaku_dari IS NULL OR j.tgl_trk >= un.berlaku_dari)
  WHERE j.tahun = CONVERT(@p_tahun USING latin1)
    AND j.idrek6 = 569                                              -- Kas Universitas
    AND COALESCE(j.kredit, 0) <> 0
    AND j.tgl_trk BETWEEN CAST(@p_tgl_awal AS DATE) AND CAST(@p_tgl_akhir AS DATE)
  GROUP BY j.id_uk
)
SELECT un.ff,
       COALESCE(i.kode, CONCAT(un.ff, '.00'))                       AS kode_induk,
       COALESCE(i.nama, CONCAT('(unit ', un.ff, '.00 tidak ada)'))  AS nama_induk,
       un.id_unit, un.kode, un.nama,
       tf.n AS jml, tf.nilai, tf.terakhir
FROM tf
JOIN unit un             ON un.id_unit = tf.id_uk
LEFT JOIN ueu_tbl_unit i ON i.kode = CONCAT(un.ff, '.00')
ORDER BY un.ff, un.kode, un.id_unit;
