<?php

namespace App\Http\Controllers\reviewer;

use App\Http\Controllers\unit\KegiatanController as KegiatanUnit;
use App\Models\Unit;

/**
 * Tambah kegiatan unit oleh reviewer (/reviewer/kegiatan/{kode}/tambah), padanan Unit::add_kegiatan
 * & process_kegiatan v1 dengan wewenang review: form & validasi sama dengan unit, tetapi tidak dibatasi
 * jadwal komite. Unit diambil dari middleware ReviewerUnit (unit yang ditetapkan ke reviewer).
 */
class KegiatanController extends KegiatanUnit
{
  protected string $ruteAwal = 'reviewer.';

  protected function konteks(): array
  {
    return [request()->attributes->get('unit_review'), (int) request()->attributes->get('tahun_review')];
  }

  protected function bolehTambah(Unit $unit, int $tahun): bool
  {
    return true;
  }
}
