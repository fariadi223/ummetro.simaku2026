-- SIMAKU v2: buka penjurnalan T.A di luar periode berjalan (Setting > Periode, admin).
-- Periode berjalan tetap ditandai penjurnalan = 'open' (dipakai v1: login, laporan, tutup buku),
-- jadi kolom itu tidak disentuh. Kolom baru ini hanya dibaca v2; DEFAULT 'tidak' agar insert
-- T.A baru dari v1 (yang tidak mengisi kolom ini) tetap berjalan dan T.A lama tetap tertutup.
ALTER TABLE ueu_tbl_info
  ADD COLUMN jurnal_dibuka ENUM('ya', 'tidak') NOT NULL DEFAULT 'tidak' AFTER penjurnalan;
