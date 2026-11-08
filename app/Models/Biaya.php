<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master (standar) biaya, memakai tabel lama `tbl_biaya` (MyISAM).
 *
 *   item  : uraian barang/jasa
 *   stmin : standar biaya minimum (Rp)
 *   stmak : standar biaya maksimum (Rp); 0 = tanpa batas atas
 *   ket   : satuan atau dasar acuan (mis. "Per Kotak", "SK Rektor No. ...", "Menkeu")
 *   jenis : kategori; '0' / NULL pada data lama = umum
 *
 * Pemetaan ke rekening ada di tbl_biaya_rek (idbiaya -> idrek6), belum dipakai v1.
 */
class Biaya extends Model
{
  public const JENIS = [
    '0' => 'Umum',
    'atk' => 'ATK',
    'honor' => 'Honorarium',
    'jasa' => 'Jasa',
    'pembangunan' => 'Pembangunan',
    'perdin' => 'Perjalanan Dinas',
  ];

  protected $table = 'tbl_biaya';
  public $timestamps = false;

  protected $fillable = ['item', 'stmin', 'stmak', 'ket', 'jenis'];

  /** Kunci jenis yang dinormalkan ('0' untuk NULL/kosong). */
  public function getKodeJenisAttribute(): string
  {
    return ($this->jenis === null || $this->jenis === '') ? '0' : (string) $this->jenis;
  }

  public function getLabelJenisAttribute(): string
  {
    return self::JENIS[$this->kode_jenis] ?? ucfirst($this->kode_jenis);
  }

  /**
   * Standar biaya dalam satu teks, mis. "Rp 6.000", "Rp 10.000 – Rp 25.000",
   * "maks. Rp 17.500", "min. Rp 4.150.000", atau null bila keduanya 0.
   */
  public function rentang(bool $denganRp = true): ?string
  {
    $min = (int) $this->stmin;
    $mak = (int) $this->stmak;
    $f = fn ($n) => ($denganRp ? 'Rp ' : '') . number_format($n, 0, ',', '.');

    return match (true) {
      !$min && !$mak => null,
      $min && $mak && $min === $mak => $f($min),
      $min && $mak => $f($min) . ' – ' . $f($mak),
      (bool) $mak => 'maks. ' . $f($mak),
      default => 'min. ' . $f($min),
    };
  }

  /** Nilai untuk pengurutan: batas atas bila ada, kalau tidak batas bawah. */
  public function getNilaiUrutAttribute(): int
  {
    return (int) $this->stmak ?: (int) $this->stmin;
  }

  /** Data lama: minimum lebih besar dari maksimum yang tidak nol. */
  public function getTidakKonsistenAttribute(): bool
  {
    return (int) $this->stmak > 0 && (int) $this->stmin > (int) $this->stmak;
  }
}
