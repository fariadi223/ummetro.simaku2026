<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rekening yang ditampilkan / disembunyikan di Laporan Keuangan Unit.
 * Disembunyikan = ada baris di ueu_lap_unit_rek_nonaktif (sql/laporan_unit/00_rekening_nonaktif.sql).
 * Hanya menulis ke ueu_lap_unit_rek_nonaktif.
 */
class RekeningLaporanUnit
{
  public function __construct(private BukuBesar $bb)
  {
  }

  public function siap(): bool
  {
    return Schema::hasTable('ueu_lap_unit_rek_nonaktif') && Schema::hasTable('ueu_map_unit_prodi');
  }

  /**
   * Rekening yang pernah dipakai transaksi unit terpetakan (semua tahun) + rekening yang sedang nonaktif.
   * Tiap baris: idrek6, kode, namarek6, jml_trx, aktif, keterangan, diubah_oleh, created_at.
   */
  public function daftar(): array
  {
    $jml = DB::table('ueu_tbl_jurnal_spm as j')->join('ueu_map_unit_prodi as m', 'm.id_unit', '=', 'j.id_uk')
      ->groupBy('j.idrek6')->selectRaw('j.idrek6, COUNT(*) as n')->pluck('n', 'idrek6');
    $nonaktif = DB::table('ueu_lap_unit_rek_nonaktif')->get()->keyBy('idrek6');
    $id = array_unique(array_merge($jml->keys()->all(), $nonaktif->keys()->all()));

    return $this->bb->rekening()->whereIn('r6.idrek6', $id ?: [0])->get()
      ->map(function ($r) use ($jml, $nonaktif) {
        $n = $nonaktif[$r->idrek6] ?? null;

        return (object) [
          'idrek6' => (int) $r->idrek6,
          'kode' => $r->kode,
          'namarek6' => $r->namarek6,
          'jml_trx' => (int) ($jml[$r->idrek6] ?? 0),
          'aktif' => $n === null,
          'keterangan' => $n->keterangan ?? null,
          'diubah_oleh' => $n->diubah_oleh ?? null,
          'created_at' => $n->created_at ?? null,
        ];
      })->all();
  }

  /** Aktifkan (hapus dari daftar nonaktif) atau nonaktifkan satu rekening. */
  public function atur(int $idrek6, bool $aktif, ?string $keterangan, ?string $oleh): void
  {
    if ($aktif) {
      DB::table('ueu_lap_unit_rek_nonaktif')->where('idrek6', $idrek6)->delete();

      return;
    }
    DB::table('ueu_lap_unit_rek_nonaktif')->updateOrInsert(['idrek6' => $idrek6], [
      'keterangan' => $keterangan,
      'diubah_oleh' => $oleh,
      'created_at' => now(),
    ]);
  }

  /** Jumlah rekening nonaktif (untuk keterangan di laporan). */
  public function jumlahNonaktif(): int
  {
    return Schema::hasTable('ueu_lap_unit_rek_nonaktif') ? DB::table('ueu_lap_unit_rek_nonaktif')->count() : 0;
  }
}
