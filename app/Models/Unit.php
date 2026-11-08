<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit kerja, memakai tabel lama `ueu_tbl_unit`.
 *
 * Kode berformat "FF.UU": FF = fakultas/biro, UU = "00" berarti unit induk.
 * Arti kolom (dari v1):
 *   unit            : kelompok, 1 = Core Unit, 2 (atau 0) = Supporting Unit
 *   jml_spp         : batas maksimal pengajuan SPP
 *   insert_anggaran : "klinik" anggaran, 'enable' = unit boleh input anggaran
 *                     walau jadwal komite sudah lewat (Simaku::aktive)
 *   an_nama_pimp    : nama "a.n." pimpinan untuk tanda tangan SPP
 *   kode_fak        : kode fakultas di PDPT (honor mengajar), hanya unit induk
 *   visi .. latblk4 : profil yang diisi unit sendiri
 *   namauser, pass, pass_opr, pagu1..3 : kolom lama, tidak dipakai lagi
 *                     (login di ueu_tbl_user, pagu di ueu_tbl_pagu_unit)
 */
class Unit extends Model
{
  public const KELOMPOK = [1 => 'Core Unit', 2 => 'Supporting Unit'];

  protected $table = 'ueu_tbl_unit';
  protected $primaryKey = 'id_unit';
  public $timestamps = false;

  protected $guarded = ['id_unit'];

  protected $hidden = ['namauser', 'pass', 'pass_opr'];

  public function users(): HasMany
  {
    return $this->hasMany(User::class, 'id_unit', 'id_unit');
  }

  /** Dua digit pertama kode, mis. "01" dari "01.03". */
  public function getKodeIndukAttribute(): string
  {
    return explode('.', (string) $this->kode)[0];
  }

  public function getIsIndukAttribute(): bool
  {
    return (explode('.', (string) $this->kode)[1] ?? null) === '00';
  }

  public function getKelompokAttribute(): string
  {
    return (int) $this->unit === 1 ? self::KELOMPOK[1] : self::KELOMPOK[2];
  }

  public function getKlinikAktifAttribute(): bool
  {
    return $this->insert_anggaran === 'enable';
  }
}
