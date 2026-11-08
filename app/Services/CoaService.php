<?php

namespace App\Services;

use App\Services\Hierarki\HierarkiService;

/**
 * Chart of Accounts (COA) SIMAKU: 6 level rekening di tabel lama
 * ueu_tbl_rek1 .. ueu_tbl_rek6.
 *
 *   rek1 (1 Aset) > rek2 (1.1 Aset Lancar) > rek3 (1.1.01 Kas) > ... > rek6
 *
 * PENTING soal kolom `deleted_at`: kolom ini ber-DEFAULT CURRENT_TIMESTAMP
 * dan tidak pernah dipakai v1, sehingga hampir semua rekening yang dibuat
 * sejak 2024 terisi `deleted_at` padahal masih aktif dipakai jurnal.
 * Karena itu kolom ini DIABAIKAN (jangan pakai SoftDeletes). Baris baru
 * tetap diisi NULL supaya tidak menambah data yang menyesatkan.
 */
class CoaService extends HierarkiService
{
  public const LEVEL_MAX = 6;

  protected string $awalanTabel = 'ueu_tbl_rek';
  protected string $awalanKolom = 'rek';
  protected int $levelMax = self::LEVEL_MAX;
  protected bool $adaDeletedAt = true;

  protected array $panjang = [
    1 => ['kode' => 1, 'nama' => 50, 'kode_int' => true],
    2 => ['kode' => 2, 'nama' => 200, 'kode_int' => true],
    3 => ['kode' => 2, 'nama' => 200],
    4 => ['kode' => 2, 'nama' => 200],
    5 => ['kode' => 3, 'nama' => 200],
    6 => ['kode' => 3, 'nama' => 200],
  ];
}
