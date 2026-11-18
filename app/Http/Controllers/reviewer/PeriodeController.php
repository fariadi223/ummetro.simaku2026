<?php

namespace App\Http\Controllers\reviewer;

use App\Http\Controllers\unit\PeriodeController as PeriodeUnit;

/**
 * Periode anggaran untuk reviewer (/reviewer/periode), padanan Reviewer::periode v1: mengganti tahun
 * anggaran yang di-review (session 'periode'). Tampilan & logika sama dengan periode unit.
 */
class PeriodeController extends PeriodeUnit
{
  protected string $rute = 'reviewer.periode';
  protected string $beranda = 'reviewer';
}
