<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rincian anggaran kegiatan (pendapatan / belanja), tabel lama `ueu_tbl_rinci`.
 *
 *   jumlah = hargasat × qty1 × qty2 × qty3 × qty4, qty2..4 kosong/0 dihitung 1
 *   satuan1..4 pasangan qty1..4 (satuan2..4 maks 15 karakter)
 *   created_date/modified_date: teks "d-m-Y G:i:s" WIB (format v1)
 */
class Rinci extends Model
{
  protected $table = 'ueu_tbl_rinci';
  protected $primaryKey = 'idrinci';
  public $timestamps = false;

  protected $guarded = ['idrinci'];

  public static function nilai(object $r): float
  {
    $q = fn ($v) => ($v === null || (float) $v == 0.0) ? 1 : (float) $v;

    return (float) $r->hargasat * (float) $r->qty1 * $q($r->qty2) * $q($r->qty3) * $q($r->qty4);
  }

  /** "4 Orang × 7 hari" dari pasangan qty/satuan yang terisi. */
  public static function volume(object $r): string
  {
    $angka = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $bagian = [];
    foreach ([1, 2, 3, 4] as $i) {
      $qty = $r->{"qty{$i}"};
      if ($i > 1 && ($qty === null || (float) $qty == 0.0)) {
        continue;
      }
      $bagian[] = trim($angka($qty) . ' ' . trim((string) $r->{"satuan{$i}"}));
    }

    return implode(' × ', $bagian);
  }
}
