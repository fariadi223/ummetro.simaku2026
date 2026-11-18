<?php

namespace App\Http\Controllers\reviewer;

use App\Http\Controllers\unit\RinciController as RinciUnit;
use App\Models\Kegiatan;
use App\Models\Unit;

/**
 * Edit rincian pendapatan/belanja kegiatan unit oleh reviewer (/reviewer/kegiatan/{kode}/rinci/{idkeg}),
 * padanan Unit::rinci, add_pendptn, add_belanja, process_belanja, delete_belanja v1 dengan wewenang review.
 * Seperti v1, reviewer tidak dibatasi jadwal komite / status revisi; cek pagu tetap berlaku.
 */
class RinciController extends RinciUnit
{
  protected string $ruteAwal = 'reviewer.';

  protected function konteks(): array
  {
    return [request()->attributes->get('unit_review'), (int) request()->attributes->get('tahun_review')];
  }

  protected function bolehUbah(Unit $unit, int $tahun, Kegiatan $keg, ?array $komite = null, ?object $review = null): bool
  {
    return true;
  }
}
