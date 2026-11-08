<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\NeracaPercobaan;
use App\Services\PerubahanAsetNeto;
use Illuminate\Http\Request;

/**
 * Laporan Keuangan > Perubahan Aset Neto (/akuntansi/perubahan-aset-neto), padanan report/asetneto/view v1.
 * Perhitungan di App\Services\PerubahanAsetNeto. ?awal=&akhir= (Y-m-d), default seluruh T.A; ?mode=tanggal = Report Tanggal
 * (default bulan berjalan); ?cetak=1 → halaman cetak.
 */
class PerubahanAsetNetoController extends Controller
{
  public function __construct(private PerubahanAsetNeto $pan)
  {
  }

  public function index(Request $request)
  {
    $tahun = $this->tahun();
    [$pAwal, $pAkhir] = NeracaPercobaan::periode($tahun);
    $mode = $request->query('mode') === 'tanggal' ? 'tanggal' : 'tunggal';
    $awal = $this->tgl($request->query('awal'));
    $akhir = $this->tgl($request->query('akhir'));
    if ($mode === 'tanggal' && !$awal && !$akhir) {
      // Report Tanggal tanpa tanggal: bulan berjalan (tautan "Report Tanggal" v1).
      [$awal, $akhir] = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
    }
    $data = $this->pan->hitung($tahun, $awal, $akhir, $mode) + [
      'tahun' => $tahun,
      'pAwal' => $pAwal,
      'pAkhir' => $pAkhir,
      'ttd' => config('simaku.laporan_ttd', []),
    ];

    return view($request->boolean('cetak') ? 'content.akuntansi.perubahan-aset-neto.cetak' : 'content.akuntansi.perubahan-aset-neto.index', $data);
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
