<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\NeracaPercobaan;
use Illuminate\Http\Request;

/**
 * Laporan Keuangan > Neraca Percobaan (/akuntansi/neraca-percobaan), padanan report/neraca/percobaan v1
 * (mode biasa & mode=tanggal digabung: tanpa ?awal/?akhir = seluruh T.A). Perhitungan di App\Services\NeracaPercobaan.
 */
class NeracaPercobaanController extends Controller
{
  public function __construct(private NeracaPercobaan $np)
  {
  }

  public function index(Request $request)
  {
    $tahun = $this->tahun();
    [$pAwal, $pAkhir] = NeracaPercobaan::periode($tahun);
    $tgl = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)
      && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
    $hasil = $this->np->hitung($tahun, $tgl($request->query('awal')), $tgl($request->query('akhir')));

    return view('content.akuntansi.neraca-percobaan.index', $hasil + [
      'tahun' => $tahun,
      'pAwal' => $pAwal,
      'pAkhir' => $pAkhir,
      'seluruhTa' => $hasil['awal'] === $pAwal && $hasil['akhir'] === $pAkhir,
    ]);
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
