<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan nilai rincian anggaran (ueu_tbl_rinci), padanan
 * Lib_config::jml_rinci / jml_rinci_v1 di v1, tetapi dihitung di SQL.
 *
 * Nilai satu rinci = hargasat × qty1 × qty2 × qty3 × qty4, dengan qty2..4
 * yang kosong/0 dianggap 1 (aturan v1).
 */
class RincianAnggaran
{
  public const SQL_NILAI = 'r.hargasat * r.qty1'
    . ' * IF(IFNULL(r.qty2, 0) = 0, 1, r.qty2)'
    . ' * IF(IFNULL(r.qty3, 0) = 0, 1, r.qty3)'
    . ' * IF(IFNULL(r.qty4, 0) = 0, 1, r.qty4)';

  /** Rinci + kegiatan + rantai rekening sampai level 1 (alias r, k, r1). */
  public function query(): Builder
  {
    return DB::table('ueu_tbl_rinci as r')
      ->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 'r.idkeg')
      ->join('ueu_tbl_rek6 as r6', 'r6.idrek6', '=', 'r.idrek6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1');
  }

  /**
   * Total pendapatan (rek 4) & belanja (rek 5) satu tahun, seperti ringkasan
   * Admin::pagu di v1 (filter tahun pada kegiatan dan rinci).
   *
   * @return array{pendapatan: int, belanja: int}
   */
  public function totalTahun(string $tahun): array
  {
    $hasil = $this->query()
      ->where('k.tahun', $tahun)
      ->where('r.tahun', $tahun)
      ->whereIn('r1.koderek1', ['4', '5'])
      ->groupBy('r1.koderek1')
      ->selectRaw('r1.koderek1 as kode, SUM(' . self::SQL_NILAI . ') as total')
      ->pluck('total', 'kode');

    return [
      'pendapatan' => (int) round($hasil['4'] ?? 0),
      'belanja' => (int) round($hasil['5'] ?? 0),
    ];
  }

  /**
   * Belanja per [idunit][idpagu] satu tahun, seperti pagu_unit_tabel di v1
   * (filter tahun hanya pada kegiatan). Bisa dibatasi ke satu unit.
   */
  public function belanjaPerUnitPagu(string $tahun, ?int $idUnit = null): array
  {
    $hasil = [];

    $this->query()
      ->where('k.tahun', $tahun)
      ->when($idUnit, fn ($q) => $q->where('k.idunit', $idUnit))
      ->where('r1.koderek1', '5')
      ->groupBy('k.idunit', 'k.idsumberdana')
      ->selectRaw('k.idunit, k.idsumberdana, SUM(' . self::SQL_NILAI . ') as total')
      ->get()
      ->each(function ($r) use (&$hasil) {
        $hasil[(int) $r->idunit][(string) $r->idsumberdana] = (int) round(abs($r->total));
      });

    return $hasil;
  }
}
