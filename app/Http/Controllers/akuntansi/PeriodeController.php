<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\unit\PeriodeController as PeriodeUnit;

/**
 * Periode anggaran untuk akuntansi (/akuntansi/periode), padanan Akuntansi::periode v1: mengganti tahun
 * anggaran yang dikerjakan (session 'periode'). Tampilan & logika sama dengan periode unit/kasir.
 */
class PeriodeController extends PeriodeUnit
{
  protected string $rute = 'akuntansi.periode';
  protected string $beranda = 'akuntansi';
}
