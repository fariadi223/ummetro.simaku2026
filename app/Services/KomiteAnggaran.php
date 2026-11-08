<?php

namespace App\Services;

use App\Models\Info;
use App\Models\Unit;
use Carbon\Carbon;

/**
 * Status input anggaran unit, padanan Simaku::aktive() di v1:
 *
 *   sisa_hari = akhir_komite - hari ini (dalam hari)
 *   dibuka    = sisa_hari > 0  ATAU  klinik anggaran unit 'enable'
 *
 * Catatan: seperti v1, pada hari terakhir komite (sisa 0) input sudah ditutup.
 */
class KomiteAnggaran
{
  /** @return array{dibuka: bool, sisa_hari: ?int, klinik: bool, akhir_komite: ?Carbon} */
  public function status(Unit $unit, int|string $tahun): array
  {
    $info = Info::where('th_angg', $tahun)->first();
    $akhir = null;

    try {
      $akhir = $info?->akhir_komite ? Carbon::parse($info->akhir_komite)->startOfDay() : null;
    } catch (\Throwable) {
      $akhir = null;
    }

    $sisa = $akhir ? Carbon::today()->diffInDays($akhir, false) : null;
    $klinik = $unit->insert_anggaran === 'enable';

    return [
      'dibuka' => ($sisa !== null && $sisa > 0) || $klinik,
      'sisa_hari' => $sisa,
      'klinik' => $klinik,
      'akhir_komite' => $akhir,
    ];
  }
}
