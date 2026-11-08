<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembagian pagu ke unit kerja per tahun, tabel lama `ueu_tbl_pagu_unit`.
 * Penggunaannya = belanja (rekening 5) kegiatan unit tsb. yang memilih pagu ini.
 */
class PaguUnit extends Model
{
  protected $table = 'ueu_tbl_pagu_unit';
  protected $primaryKey = 'id_paguunit';
  public $timestamps = false;

  protected $fillable = ['tahun', 'idunit', 'idpagu', 'nominal'];

  public function unitKerja(): BelongsTo
  {
    return $this->belongsTo(Unit::class, 'idunit', 'id_unit');
  }

  public function pagu(): BelongsTo
  {
    return $this->belongsTo(Pagu::class, 'idpagu', 'id_pagu');
  }
}
