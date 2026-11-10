-- =====================================================================
-- 01. Saldo anggaran unit: Kas di Unit Kerja per unit induk (FF) dan sub-unit (id_uk)
--
-- Parameter (jalankan dulu di sesi yang sama):
--   SET @p_tahun     = '2026';        -- T.A (ueu_tbl_jurnal_spm.tahun)
--   SET @p_idrek6    = 570;           -- Kas di Unit Kerja
--   SET @p_tgl_awal  = NULL;          -- NULL = sejak awal (saldo_awal 0); atau tanggal
--   SET @p_tgl_akhir = NULL;          -- NULL = sampai akhir; atau tanggal
--
-- Aturan v1 (Akuntansi::daftarsaldo_unit) dipertahankan:
--   kelompok  = kodeunit (kode unit induk FF sebagai angka; 0 -> "00" Rektorat, termasuk jurnal lama tanpa unit)
--   diterima  = Σ debet
--   realisasi = Σ kredit dengan idrinci > 0
--   disetor   = Σ kredit idrinci = 0 dan uraian "setor kas dari unit…"
--   lainnya   = Σ kredit idrinci = 0 selain setoran
--   saldo     = saldo_awal + diterima − realisasi − disetor − lainnya
-- Tambahan v2: rincian per sub-unit (id_uk; 0 = jurnal tanpa unit) dan filter periode.
-- Tanpa filter tanggal, saldo per FF = Σ debet − Σ kredit seperti v1.
-- Dipakai juga oleh App\Services\SaldoUnit::rekap().
-- =====================================================================

WITH
j AS (
  SELECT LPAD(COALESCE(j.kodeunit, 0), 2, '0')                      AS ff,
         j.id_uk,
         j.idrinci,
         COALESCE(j.debet, 0)                                       AS debet,
         COALESCE(j.kredit, 0)                                      AS kredit,
         (j.uraian LIKE 'setor kas dari unit%')                     AS setor,
         (@p_tgl_awal IS NOT NULL AND j.tgl_trk < CAST(@p_tgl_awal AS DATE)) AS sebelum
  FROM ueu_tbl_jurnal_spm j
  WHERE j.tahun = CONVERT(@p_tahun USING latin1)                     -- CONVERT agar indeks (tahun, bukti) terpakai
    AND j.idrek6 = @p_idrek6
    AND (@p_tgl_akhir IS NULL OR j.tgl_trk <= CAST(@p_tgl_akhir AS DATE))
)
SELECT ff,
       id_uk,
       SUM(CASE WHEN sebelum THEN debet - kredit ELSE 0 END)                                  AS saldo_awal,
       SUM(CASE WHEN NOT sebelum THEN debet ELSE 0 END)                                       AS diterima,
       SUM(CASE WHEN NOT sebelum AND idrinci > 0 THEN kredit ELSE 0 END)                      AS realisasi,
       SUM(CASE WHEN NOT sebelum AND idrinci = 0 AND setor THEN kredit ELSE 0 END)            AS disetor,
       SUM(CASE WHEN NOT sebelum AND idrinci = 0 AND NOT setor THEN kredit ELSE 0 END)        AS lainnya,
       SUM(CASE WHEN NOT sebelum THEN 1 ELSE 0 END)                                           AS jml_transaksi,
       SUM(debet - kredit)                                                                    AS saldo
FROM j
GROUP BY ff, id_uk
ORDER BY ff, id_uk;
