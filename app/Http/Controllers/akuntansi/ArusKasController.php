<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\ArusKas;
use App\Services\NeracaPercobaan;
use Illuminate\Http\Request;

/**
 * Laporan Keuangan > Arus Kas (/akuntansi/arus-kas), padanan report/aruskas/view v1.
 * Perhitungan di App\Services\ArusKas. ?awal=&akhir= (Y-m-d), default seluruh T.A; ?cetak=1 → halaman cetak.
 */
class ArusKasController extends Controller
{
  public function __construct(private ArusKas $ak)
  {
  }

  public function index(Request $request)
  {
    $tahun = $this->tahun();
    [$pAwal, $pAkhir] = NeracaPercobaan::periode($tahun);
    $data = $this->ak->hitung($tahun, $this->tgl($request->query('awal')), $this->tgl($request->query('akhir'))) + [
      'tahun' => $tahun,
      'pAwal' => $pAwal,
      'pAkhir' => $pAkhir,
      'ttd' => config('simaku.laporan_ttd', []),
    ];

    return view($request->boolean('cetak') ? 'content.akuntansi.arus-kas.cetak' : 'content.akuntansi.arus-kas.index', $data);
  }

  private function tgl($v): ?string
  {
    return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)
      && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
