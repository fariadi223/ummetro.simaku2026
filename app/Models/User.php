<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Akun pengguna SIMAKU, memakai tabel lama `ueu_tbl_user` (warisan v1).
 *
 * Catatan: password di tabel lama masih tersimpan polos (varchar 32).
 * Role efektif (wewenang) dihitung saat login dan disimpan di session,
 * karena di v1 role "akuntansi"/"penerimaan" diturunkan dari kombinasi
 * wewenang admin + nilai password, bukan dari kolom tersendiri.
 */
class User extends Authenticatable
{
  protected $table = 'ueu_tbl_user';
  protected $primaryKey = 'idlog';
  public $timestamps = false;

  protected $fillable = ['id_unit', 'nama_lengkap', 'username', 'password', 'wewenang'];

  protected $hidden = ['password'];

  // Tabel lama tidak punya kolom remember_token.
  protected $rememberTokenName = '';

  public function unit(): BelongsTo
  {
    return $this->belongsTo(Unit::class, 'id_unit', 'id_unit');
  }

  /** Nama tampilan, dipakai navbar template. */
  public function getNameAttribute(): ?string
  {
    return $this->nama_lengkap ?: $this->username;
  }

  /** Role efektif hasil login (lihat AuthController::tentukanAkses). */
  public function role(): ?string
  {
    return session('wewenang');
  }

  public function hasAnyRole(array|string $roles): bool
  {
    return in_array($this->role(), (array) $roles, true);
  }

  /** Login sebagai unit induk (kode "FF.00", area /adminunit), lihat AuthController::tentukanAkses. */
  public function isAdminUnit(): bool
  {
    return $this->role() === 'unit' && (bool) $this->unit?->is_induk;
  }

  /**
   * Item menu disembunyikan bila role tidak cocok, atau ditandai
   * "sembunyiAdminUnit" (sembunyi untuk unit induk) / "hanyaAdminUnit" (hanya unit induk).
   */
  public function menuTersembunyi(object $item): bool
  {
    return (isset($item->role) && !$this->hasAnyRole($item->role))
      || (!empty($item->sembunyiAdminUnit) && $this->isAdminUnit())
      || (!empty($item->hanyaAdminUnit) && !$this->isAdminUnit());
  }

  public function homeUrl(): string
  {
    return session('home_url', url('/login'));
  }

  /** Label tiap role efektif. */
  public const LABEL_ROLE = [
    'admin' => 'Administrator',
    'akuntansi' => 'Akuntansi',
    'penerimaan' => 'Penerimaan',
    'kasir' => 'Kasir',
    'op1' => 'Operator Penggajian 1',
    'op2' => 'Operator Penggajian 2',
    'review' => 'Reviewer',
    'operatorspm' => 'Operator SPM',
    'otorisator1' => 'Otorisator 1',
    'otorisator2' => 'Otorisator 2',
    'operator' => 'Operator Fakultas',
    'unit' => 'Unit',
  ];

  /**
   * Nilai kolom `wewenang` yang bisa dipilih saat membuat/mengubah akun.
   * 'tufakultas' & 'rektor' ada di enum tabel tetapi tidak punya area di
   * SIMAKU (login ditolak), sehingga tidak ditawarkan.
   */
  public const WEWENANG_TERSEDIA = [
    'admin', 'unit', 'operator', 'otorisator1', 'otorisator2', 'kasir', 'review', 'operatorspm', 'op1', 'op2',
  ];

  /**
   * Password khusus akun admin yang mengubahnya menjadi role lain (warisan v1).
   * Mengganti password akun seperti ini berarti mengubah role-nya.
   */
  public const PASSWORD_ROLE = ['akuntansi', 'penerimaan'];

  /** Label role untuk ditampilkan di UI. */
  public function roleLabel(): string
  {
    return self::LABEL_ROLE[$this->role()] ?? (string) $this->role();
  }

  /** Role efektif akun ini (tanpa perlu login), mengikuti aturan v1. */
  public function wewenangEfektif(): ?string
  {
    if ($this->wewenang === 'admin' && in_array($this->password, self::PASSWORD_ROLE, true)) {
      return $this->password;
    }

    return $this->wewenang ?: null;
  }

  public function isAkunRole(): bool
  {
    return $this->wewenang === 'admin' && in_array($this->password, self::PASSWORD_ROLE, true);
  }
}
