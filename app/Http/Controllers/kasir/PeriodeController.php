<?php

namespace App\Http\Controllers\kasir;

use App\Http\Controllers\unit\PeriodeController as PeriodeUnit;

/**
 * Periode anggaran untuk kasir (/kasir/periode), padanan Kasir::periode v1: mengganti tahun anggaran
 * yang dikerjakan (session 'periode'). Tampilan & logika sama dengan periode unit.
 */
class PeriodeController extends PeriodeUnit
{
  protected string $rute = 'kasir.periode';
  protected string $beranda = 'kasir';
}
