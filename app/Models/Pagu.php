<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sumber pagu anggaran per tahun, tabel lama `ueu_tbl_pagu`.
 *
 *   id_pagu : kode "P1", "P2", ... (varchar 3, bukan auto increment)
 *   tahun   : tahun anggaran
 *   sumber  : mis. "SPP Mahasiswa", "Surplus Tahun Lalu"
 *   jumlah  : nominal pagu
 *
 * Kegiatan memilih pagu lewat ueu_tbl_kegiatan.idsumberdana = id_pagu.
 */
class Pagu extends Model
{
  protected $table = 'ueu_tbl_pagu';
  protected $primaryKey = 'id_pagu';
  protected $keyType = 'string';
  public $incrementing = false;
  public $timestamps = false;

  protected $fillable = ['id_pagu', 'tahun', 'sumber', 'jumlah'];

  public function unit(): HasMany
  {
    return $this->hasMany(PaguUnit::class, 'idpagu', 'id_pagu');
  }

  /** Kode berikutnya: "P" + (angka terbesar + 1). Batas kolom 3 karakter -> P99. */
  public static function kodeBerikut(): ?string
  {
    $maks = static::pluck('id_pagu')
      ->map(fn ($k) => (int) preg_replace('/\D/', '', (string) $k))
      ->max() ?? 0;

    return $maks + 1 <= 99 ? 'P' . ($maks + 1) : null;
  }
}
