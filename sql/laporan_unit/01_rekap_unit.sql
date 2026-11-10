-- =====================================================================
-- 01. Laporan keuangan unit: rekap per unit terpetakan, dikelompokkan ke unit induk FF.00
--
-- Parameter (jalankan dulu di sesi yang sama):
--   SET @p_tahun     = '2025';        -- T.A (ueu_tbl_jurnal_spm.tahun), periode 01-09-2025 s/d 31-08-2026
--   SET @p_tgl_awal  = '2025-09-01';
--   SET @p_tgl_akhir = '2026-08-31';
--   SET @p_kepala    = NULL;          -- NULL = semua rekening; '1,2,3' = hanya kepala 1 (aset), 2 (kewajiban), 3 (aset neto)
--
-- Cakupan: hanya unit yang ada di ueu_map_unit_prodi (jenis prodi maupun fakultas, mis. 01.01 Wakil Dekan).
-- Kelompok: unit induk berkode FF.00, dengan FF = 2 digit pertama kode unit.
--   Contoh: 01.02, 01.03, 01.06 masuk ke 01.00 (Dekan FKIP).
--   Transaksi unit induk sendiri hanya ikut jika unit induk itu juga ada di mapping.
-- Mutasi periode @p_tgl_awal .. @p_tgl_akhir. Transaksi sebelum berlaku_dari mapping tidak dihitung.
--   pendapatan = Σ (kredit - debet) rekening kepala 4
--   beban      = Σ (debet - kredit) rekening kepala 5
--   surplus    = pendapatan - beban
-- Dipakai juga oleh App\Services\LaporanUnit::rekap().
-- Rekening di ueu_lap_unit_rek_nonaktif (Admin > Rekening Laporan Unit) tidak dihitung.
-- =====================================================================

WITH
unit AS (
  SELECT u.id_unit, u.kode, u.nama, LEFT(u.kode, 2) AS ff,
         m.id_sms, m.kode_npm, m.berlaku_dari, m.jenis, m.kode_fak     -- jenis/kode_fak: 01c_pemetaan_fakultas.sql
  FROM ueu_map_unit_prodi m
  JOIN ueu_tbl_unit u ON u.id_unit = m.id_unit
),
rek AS (
  SELECT r6.idrek6, r1.koderek1
  FROM ueu_tbl_rek6 r6
  LEFT JOIN ueu_tbl_rek5 r5 ON r5.idrek5 = r6.idrek5
  LEFT JOIN ueu_tbl_rek4 r4 ON r4.idrek4 = r5.idrek4
  LEFT JOIN ueu_tbl_rek3 r3 ON r3.idrek3 = r4.idrek3
  LEFT JOIN ueu_tbl_rek2 r2 ON r2.idrek2 = r3.idrek2
  LEFT JOIN ueu_tbl_rek1 r1 ON r1.idrek1 = r2.idrek1
),
mutasi AS (
  SELECT j.id_uk,
         COUNT(*)                                                                         AS n,
         SUM(COALESCE(j.debet, 0))                                                        AS d,
         SUM(COALESCE(j.kredit, 0))                                                       AS k,
         SUM(CASE WHEN r.koderek1 = '4' THEN COALESCE(j.kredit, 0) - COALESCE(j.debet, 0) ELSE 0 END) AS pendapatan,
         SUM(CASE WHEN r.koderek1 = '5' THEN COALESCE(j.debet, 0) - COALESCE(j.kredit, 0) ELSE 0 END) AS beban
  FROM ueu_tbl_jurnal_spm j
  JOIN unit un ON un.id_unit = j.id_uk
             AND (un.berlaku_dari IS NULL OR j.tgl_trk >= un.berlaku_dari)
  LEFT JOIN rek r ON r.idrek6 = j.idrek6
  WHERE j.tahun = CONVERT(@p_tahun USING latin1)                 -- CONVERT agar indeks (tahun, bukti) terpakai
    AND j.tgl_trk BETWEEN CAST(@p_tgl_awal AS DATE) AND CAST(@p_tgl_akhir AS DATE)
    AND NOT EXISTS (SELECT 1 FROM ueu_lap_unit_rek_nonaktif x WHERE x.idrek6 = j.idrek6)   -- rekening nonaktif (00_rekening_nonaktif.sql)
    AND (@p_kepala IS NULL OR FIND_IN_SET(r.koderek1, @p_kepala) > 0)                      -- mode: hanya kepala tertentu
  GROUP BY j.id_uk
)
SELECT un.ff,
       i.id_unit                                            AS id_induk,
       COALESCE(i.kode, CONCAT(un.ff, '.00'))               AS kode_induk,
       COALESCE(i.nama, CONCAT('(unit ', un.ff, '.00 tidak ada)')) AS nama_induk,
       un.id_unit, un.kode, un.nama,
       COALESCE(p.kode_prodi, '')                           AS kode_prodi,
       COALESCE(p.jenjang_didik, '')                        AS jenjang_didik,   -- butuh 01b_tambah_jenjang_didik.sql
       COALESCE(p.nm_lemb, '')                              AS nama_prodi,
       COALESCE(un.kode_npm, '')                            AS kode_npm,
       un.jenis,                                                              -- 'prodi' / 'fakultas'
       COALESCE(NULLIF(f.nama_fak2, ''), f.nama_fak, '')    AS nama_fakultas,
       COALESCE(mu.n, 0)                                    AS jml_transaksi,
       COALESCE(mu.d, 0)                                    AS total_debet,
       COALESCE(mu.k, 0)                                    AS total_kredit,
       COALESCE(mu.pendapatan, 0)                           AS pendapatan,
       COALESCE(mu.beban, 0)                                AS beban,
       COALESCE(mu.pendapatan, 0) - COALESCE(mu.beban, 0)   AS surplus
FROM unit un
LEFT JOIN ueu_tbl_unit i   ON i.kode = CONCAT(un.ff, '.00')
LEFT JOIN mutasi mu        ON mu.id_uk = un.id_unit
LEFT JOIN ueu_ref_prodi p  ON p.id_sms = un.id_sms
LEFT JOIN ueu_ref_fakultas f ON f.kode_fak = un.kode_fak
ORDER BY un.ff, un.kode, un.id_unit;
