<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kode sumber dana penerimaan, memakai tabel lama `ueu_tbl_sumberdana`.
 *
 * Dipakai jurnal: ueu_tbl_jurnal_spm.id_sumber dan ueu_tbl_jurnal_saldo.id_sumber
 * (beserta tabel arsip jurnal per tahun) menunjuk ke kolom `id`.
 * Kelompok menentukan pos aset neto di laporan keuangan:
 * 'Pembatasan' = dengan pembatasan dari pemberi sumber daya.
 *
 * Catatan: ueu_tbl_kegiatan.idsumberdana (P3, P12, ...) BUKAN tabel ini,
 * melainkan kode pagu (ueu_tbl_pagu).
 */
class Sumberdana extends Model
{
  public const KELOMPOK = [
    'Pembatasan' => 'Dengan Pembatasan',
    'Tanpa Pembatasan' => 'Tanpa Pembatasan',
  ];

  protected $table = 'ueu_tbl_sumberdana';
  public $timestamps = false;

  protected $fillable = ['kode', 'nama_penerimaan', 'kelompok'];

  public function getLabelKelompokAttribute(): string
  {
    return self::KELOMPOK[$this->kelompok] ?? (string) $this->kelompok;
  }
}
