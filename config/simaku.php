<?php

return [
  /*
  | Paksa semua kegiatan unit tetap bisa diubah/dihapus walaupun jadwal komite
  | sudah ditutup dan review sudah selesai (padanan UPDATE_FORCE_KEGIATAN di v1).
  */
  'update_force_kegiatan' => filter_var(env('UPDATE_FORCE_KEGIATAN', false), FILTER_VALIDATE_BOOLEAN),

  /*
  | Kode unit yang boleh menyusun SPP baru walaupun masih ada SPP yang sedang
  | diproses (v1: hanya 11.01, tombol "Tambah" di spp/proses1).
  */
  /*
  | Folder (di bawah storage/app/public/, disajikan lewat symlink public/storage)
  | berisi spesimen tanda tangan untuk cetak SPM:
  | <kode unit induk>.png (mis. 01.00.png), otorisator1.png, otorisator2.png (v1: images/specimen).
  */
  'specimen_dir' => env('SIMAKU_SPECIMEN_DIR', 'images/specimen'),

  /*
  | Kasir (Realisasi SPM): nama kasir pada BKK (v1: tertulis tetap di spm_ct) dan kode unit
  | yang SPM-nya boleh diberi nama penerima oleh kasir (v1: tautan "Penerima" hanya 11.01).
  */
  'kasir_nama' => env('SIMAKU_KASIR_NAMA', 'Luluk Ariyani, SE'),
  'kasir_unit_paraf' => array_filter(array_map('trim', explode(',', env('SIMAKU_KASIR_UNIT_PARAF', '11.01')))),

  /*
  | Penanda tangan BKK non anggaran (v1: tertulis tetap di bkk_ct_rinci / bkk_cp).
  | Kolom "Mengetahui Rektor" hanya dicetak bila nominal BKK > batas_rektor.
  */
  // Unit kerja BKK non anggaran: hanya sub unit (bukan *.00), kecuali kode induk berikut.
  'bkk_unit_induk_boleh' => array_filter(array_map('trim', explode(',', env('SIMAKU_BKK_UNIT_INDUK_BOLEH', '00.00')))),

  'bkk_ttd' => [
    'batas_rektor' => (int) env('SIMAKU_BKK_BATAS_REKTOR', 5000000),
    'rektor' => ['Mengetahui', 'Rektor,', env('SIMAKU_BKK_REKTOR', 'Dr. H. Nyoto Suseno, M.Si'), env('SIMAKU_BKK_REKTOR_NBM', 'NBM. 1032 241')],
    'wr2' => ['Setuju', 'WR II', env('SIMAKU_BKK_WR2', 'Dr. Nedi Hendri, M.Si.,Akt'), env('SIMAKU_BKK_WR2_NBM', 'NBM.1054 999')],
    'kabag' => ['-', 'Kabag Keuangan', env('SIMAKU_BKK_KABAG', 'Pediyah Murniati, S.E.'), env('SIMAKU_BKK_KABAG_NBM', 'NBM.1125 438')],
    'kasir' => ['Lunas', 'Kasir', env('SIMAKU_KASIR_NAMA', 'Luluk Ariyani, SE'), env('SIMAKU_KASIR_NBM', 'NBM.115 5246')],
  ],

  /*
  | Penanda tangan laporan keuangan akuntansi (cetak Laporan Posisi Keuangan, dsb.): [keterangan, jabatan, nama, NBM].
  | Nama & NBM memakai env yang sama dengan BKK; ubah lewat .env bila pejabat berganti.
  */
  'laporan_ttd' => [
    ['Mengetahui,', 'Rektor', env('SIMAKU_BKK_REKTOR', 'Dr. H. Nyoto Suseno, M.Si'), env('SIMAKU_BKK_REKTOR_NBM', 'NBM. 1032 241')],
    ['Menyetujui,', 'Wakil Rektor II', env('SIMAKU_BKK_WR2', 'Dr. Nedi Hendri, M.Si.,Akt'), env('SIMAKU_BKK_WR2_NBM', 'NBM.1054 999')],
    ['Dibuat oleh,', 'Kabag Keuangan', env('SIMAKU_BKK_KABAG', 'Pediyah Murniati, S.E.'), env('SIMAKU_BKK_KABAG_NBM', 'NBM.1125 438')],
  ],

  'spp_unit_tambah' => array_filter(array_map('trim', explode(',', env('SPP_UNIT_TAMBAH', '11.01')))),
];
