<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Informasi tahun anggaran, memakai tabel lama `ueu_tbl_info`.
 * aktif = 'enable'     -> tahun anggaran berjalan (penganggaran)
 * penjurnalan = 'open' -> tahun yang dibuka untuk penjurnalan (periode berjalan, penanda v1)
 * jurnal_dibuka = 'ya' -> (v2) penjurnalan dibuka admin untuk T.A di luar periode berjalan
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

  /** Periode berjalan untuk penjurnalan = T.A dengan penjurnalan = 'open' (penanda v1, jangan diubah dari v2). */
  public static function penjurnalanTerbuka(int|string $tahun): bool
  {
    return static::where('th_angg', (string) $tahun)->where('penjurnalan', 'open')->exists();
  }

  /**
   * Jurnal boleh diinput untuk T.A ini: periode berjalan, atau T.A lain yang dibuka admin
   * (jurnal_dibuka = 'ya', Setting > Periode). Sebelum kolom itu ditambahkan
   * (database/sql/2026_11_09_ueu_tbl_info_jurnal_dibuka.sql) hanya periode berjalan.
   */
  public static function jurnalTerbuka(int|string $tahun): bool
  {
    return static::where('th_angg', (string) $tahun)
      ->where(function ($q) {
        $q->where('penjurnalan', 'open');
        if (static::adaKolomJurnalDibuka()) {
          $q->orWhere('jurnal_dibuka', 'ya');
        }
      })
      ->exists();
  }

  /** Kolom jurnal_dibuka sudah ditambahkan ke tabel lama? (sekali per request) */
  public static function adaKolomJurnalDibuka(): bool
  {
    static $ada = null;

    return $ada ??= Schema::hasColumn('ueu_tbl_info', 'jurnal_dibuka');
  }

  /** T.A yang penjurnalannya terbuka (terbaru bila lebih dari satu), atau null. */
  public static function tahunPenjurnalan(): ?int
  {
    $th = static::where('penjurnalan', 'open')->orderByDesc('th_angg')->value('th_angg');

    return $th === null ? null : (int) $th;
  }
}
