-- =====================================================================
-- 00. Rekening yang TIDAK ditampilkan di Laporan Keuangan Unit
--
-- Satu baris = satu rekening (idrek6) dinonaktifkan; tidak ada baris = rekening tampil.
-- Diatur di Admin > Rekening Laporan Unit (/admin/rekening-laporan-unit).
-- Transaksi rekening ini dikeluarkan dari rekap, rincian rekening, dan rincian transaksi
-- (01/02/03 di folder ini). Jurnal tidak diubah. Aman dijalankan berulang.
-- =====================================================================

CREATE TABLE IF NOT EXISTS ueu_lap_unit_rek_nonaktif (
  idrek6      INT          NOT NULL,              -- ueu_tbl_rek6.idrek6
  keterangan  VARCHAR(255) NULL DEFAULT NULL,
  diubah_oleh VARCHAR(100) NULL DEFAULT NULL,
  created_at  TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (idrek6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Isian awal: Kas Universitas (1.1.01.01.01.01) tidak ditampilkan.
INSERT INTO ueu_lap_unit_rek_nonaktif (idrek6, keterangan, diubah_oleh, created_at)
SELECT r6.idrek6, 'Kas Universitas: transfer dari universitas, bukan transaksi unit', 'sql', NOW()
FROM ueu_tbl_rek6 r6
JOIN ueu_tbl_rek5 r5 ON r5.idrek5 = r6.idrek5
JOIN ueu_tbl_rek4 r4 ON r4.idrek4 = r5.idrek4
JOIN ueu_tbl_rek3 r3 ON r3.idrek3 = r4.idrek3
JOIN ueu_tbl_rek2 r2 ON r2.idrek2 = r3.idrek2
JOIN ueu_tbl_rek1 r1 ON r1.idrek1 = r2.idrek1
WHERE CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) = '1.1.01.01.01.01'
  AND NOT EXISTS (SELECT 1 FROM ueu_lap_unit_rek_nonaktif x WHERE x.idrek6 = r6.idrek6);
