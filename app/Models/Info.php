<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Informasi tahun anggaran, memakai tabel lama `ueu_tbl_info`.
 * aktif = 'enable'     -> tahun anggaran berjalan (penganggaran)
 * penjurnalan = 'open' -> tahun yang dibuka untuk penjurnalan
 */
class Info extends Model
{
  protected $table = 'ueu_tbl_info';
  protected $primaryKey = 'id';
  public $timestamps = false;

  protected $guarded = ['id'];

  /** Tahun anggaran sesuai filter, atau tahun sekarang bila tidak ada (perilaku v1). */
  public static function tahun(array $where): int
  {
    return (int) (static::where($where)->value('th_angg') ?? date('Y'));
  }
}
