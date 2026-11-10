-- =====================================================================
-- 01. Tabel baru untuk laporan per prodi (database ummetro_simaku, MySQL)
--
-- Tidak ada perubahan pada tabel lama (ueu_tbl_jurnal_spm, ueu_tbl_unit, ueu_tbl_rek6).
-- Aman dijalankan berulang (IF NOT EXISTS).
--
-- Catatan:
-- * Tabel prodi ada di PostgreSQL (ummetro_pdptumm). MySQL tidak bisa JOIN ke PostgreSQL,
--   jadi salinannya disimpan di ueu_ref_prodi dan diisi lewat:
--       php artisan simaku:sinkron-prodi
-- * Tanpa FOREIGN KEY ke ueu_tbl_unit, agar perilaku v1 (hapus/ubah unit) tidak berubah.
-- * Charset utf8mb4_unicode_ci (sama dengan koneksi Laravel). Tabel lama latin1, jadi
--   perbandingan teks lintas tabel memakai CONVERT(... USING utf8mb4) COLLATE.
-- =====================================================================

-- Salinan referensi prodi dari PostgreSQL ummetro_pdptumm.tbl_sms
CREATE TABLE IF NOT EXISTS ueu_ref_prodi (
  id_sms         VARCHAR(36)  NOT NULL,              -- UUID prodi PDDikti
  kode_prodi     VARCHAR(10)  NOT NULL DEFAULT '',
  jenjang_didik  VARCHAR(20)  NOT NULL DEFAULT '',   -- D3 / S1 / S2 / Profesi (tbl_sms.jenjang_didik)
  nm_lemb        VARCHAR(150) NOT NULL DEFAULT '',
  disinkron_pada DATETIME     NULL DEFAULT NULL,     -- waktu sinkron terakhir
  PRIMARY KEY (id_sms),
  KEY idx_ref_prodi_kode (kode_prodi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Tabel yang sudah terlanjur dibuat tanpa jenjang_didik: jalankan 01b_tambah_jenjang_didik.sql.

-- Pemetaan unit kerja -> prodi. Satu unit hanya ke satu prodi (id_unit UNIQUE);
-- satu prodi boleh punya beberapa unit (mis. D3 Akuntansi 02.04 dan 26.02).
CREATE TABLE IF NOT EXISTS ueu_map_unit_prodi (
  id_map       INT          NOT NULL AUTO_INCREMENT,
  id_unit      INT          NOT NULL,                -- ueu_tbl_unit.id_unit (= ueu_tbl_jurnal_spm.id_uk)
  jenis        ENUM('prodi','fakultas') NOT NULL DEFAULT 'prodi', -- unit prodi, atau unit fakultas (mis. Wakil Dekan)
  id_sms       VARCHAR(36)  NULL DEFAULT NULL,       -- ueu_ref_prodi.id_sms (jenis prodi)
  kode_fak     VARCHAR(4)   NULL DEFAULT NULL,       -- ueu_ref_fakultas.kode_fak (jenis fakultas)
  kode_prg     VARCHAR(10)  NULL DEFAULT NULL,       -- kode prodi saat dipetakan (prodi.kode_prodi)
  kode_npm     VARCHAR(10)  NULL DEFAULT NULL,       -- kode prodi di dalam NPM mahasiswa
  berlaku_dari DATE         NULL DEFAULT NULL,       -- NULL = berlaku untuk semua transaksi;
                                                     -- jika diisi, transaksi sebelum tanggal ini dianggap belum dipetakan
  PRIMARY KEY (id_map),
  UNIQUE KEY uq_map_unit (id_unit),
  KEY idx_map_sms (id_sms),
  KEY idx_map_npm (kode_npm),
  KEY idx_map_fak (kode_fak)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Tabel yang sudah terlanjur dibuat tanpa kode_npm: jalankan 01a_tambah_kode_npm.sql.
-- Referensi fakultas (ueu_ref_fakultas) & kolom jenis/kode_fak untuk tabel lama: jalankan 01c_pemetaan_fakultas.sql.
