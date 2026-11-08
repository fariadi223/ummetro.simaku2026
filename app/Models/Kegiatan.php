<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kegiatan (proses bisnis) unit per tahun anggaran, tabel lama `ueu_tbl_kegiatan`.
 *
 *   idmak1 / idmak4  : bidang (MAK level 1) & nama kegiatan baku (MAK level 4)
 *   uraiankeg        : nama / judul kegiatan
 *   idsumberdana     : kode PAGU (ueu_tbl_pagu.id_pagu, mis. "P13"), bukan ueu_tbl_sumberdana
 *   kebijakan_id     : sumber rujukan (tbl_kebijakan), kebijakan_uraian = deskripsinya
 *   indikator        : daftar "IKU1,IKT4,..." dipisah koma
 *   lingkup          : luaran / output kegiatan
 *   created_date/modified_date : teks "d-m-Y G:i:s" WIB (format v1)
 */
class Kegiatan extends Model
{
  /** Pilihan indikator, sama dengan v1: IKU1..IKU12 dan IKT1..IKT12. */
  public static function opsiIndikator(): array
  {
    return array_merge(
      array_map(fn ($i) => "IKU{$i}", range(1, 12)),
      array_map(fn ($i) => "IKT{$i}", range(1, 12)),
    );
  }

  protected $table = 'ueu_tbl_kegiatan';
  protected $primaryKey = 'idkeg';
  public $timestamps = false;

  protected $guarded = ['idkeg'];

  public function unit(): BelongsTo
  {
    return $this->belongsTo(Unit::class, 'idunit', 'id_unit');
  }

  /** Indikator sebagai array. */
  public function getDaftarIndikatorAttribute(): array
  {
    return array_values(array_filter(array_map('trim', explode(',', (string) $this->indikator))));
  }
}
