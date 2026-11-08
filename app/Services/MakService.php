<?php

namespace App\Services;

use App\Services\Hierarki\HierarkiService;

/**
 * Mata Anggaran Kegiatan (MAK): 4 level di tabel lama ueu_tbl_mak1 .. mak4.
 *
 *   mak1 (1 Akademik, ...) > mak2 (1.1 Rutin) > mak3 (1.1.1 Proses Pembelajaran)
 *   > mak4 (1.1.1.01 Herregistrasi)
 *
 * Kegiatan anggaran (ueu_tbl_kegiatan) menunjuk ke idmak1 dan idmak4.
 *
 * mak4 punya kolom `borang` dan `blj` (INT NOT NULL tanpa default). Keduanya
 * tidak dipakai di kode v1, isinya angka kecil 0-9 (kemungkinan nomor standar
 * borang akreditasi). Di sini dipertahankan apa adanya, bawaan 0.
 */
class MakService extends HierarkiService
{
  public const LEVEL_MAX = 4;

  protected string $awalanTabel = 'ueu_tbl_mak';
  protected string $awalanKolom = 'mak';
  protected int $levelMax = self::LEVEL_MAX;

  protected array $panjang = [
    1 => ['kode' => 3, 'nama' => 255, 'kode_int' => true],
    2 => ['kode' => 3, 'nama' => 1000, 'kode_int' => true],
    3 => ['kode' => 3, 'nama' => 1000, 'kode_int' => true],
    4 => ['kode' => 5, 'nama' => 1000],
  ];

  protected array $kolomTambahan = [
    4 => [
      'borang' => ['label' => 'Borang', 'aturan' => ['nullable', 'integer', 'min:0', 'max:99'], 'bawaan' => 0],
      'blj' => ['label' => 'BLJ', 'aturan' => ['nullable', 'integer', 'min:0', 'max:99'], 'bawaan' => 0],
    ],
  ];
}
