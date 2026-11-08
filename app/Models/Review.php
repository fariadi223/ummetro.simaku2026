<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penetapan reviewer ke unit per tahun anggaran, tabel lama `tbl_review`.
 * Reviewer = akun ueu_tbl_user ber-wewenang 'review'. Hasil review per
 * kegiatan tersimpan di tbl_review_kegiatan (user_id, unit_id, kegiatan_id).
 */
class Review extends Model
{
  protected $table = 'tbl_review';
  public $timestamps = false;

  protected $fillable = ['user_id', 'unit_id', 'tahun'];

  public function reviewer(): BelongsTo
  {
    return $this->belongsTo(User::class, 'user_id', 'idlog');
  }

  public function unit(): BelongsTo
  {
    return $this->belongsTo(Unit::class, 'unit_id', 'id_unit');
  }
}
