<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\NeracaPercobaan;
use App\Services\PosisiKeuangan;
use Illuminate\Http\Request;

/**
 * Laporan Keuangan > Laporan Posisi Keuangan (/akuntansi/posisi-keuangan), padanan report/neraca/view v1.
 * Perhitungan di App\Services\PosisiKeuangan. ?awal=&akhir= (Y-m-d) = rentang; default seluruh T.A.
 */
class PosisiKeuanganController extends Controller
{
  public function __construct(private PosisiKeuangan $pk)
  {
  }

  public function index(Request $request)
  {
    $tahun = $this->tahun();
    [$pAwal, $pAkhir] = NeracaPercobaan::periode($tahun);
    $hasil = $this->pk->hitung($tahun, $this->tgl($request->query('awal')), $this->tgl($request->query('akhir')));

    return view('content.akuntansi.posisi-keuangan.index', $hasil + [
      'tahun' => $tahun,
      'pAwal' => $pAwal,
      'pAkhir' => $pAkhir,
      'seluruhTa' => $hasil['awal'] === $pAwal && $hasil['akhir'] === $pAkhir,
    ]);
  }

  /**
   * Komparasi (report/neraca/komparasi v1): posisi per ?akhir (default akhir T.A) dibanding tahun lalu
   * = saldo awal T.A (posisi 31-08-T). ?cetak=1 → halaman cetak, &rinci=1 dengan akun level 6.
   */
  public function komparasi(Request $request)
  {
    $tahun = $this->tahun();
    [$pAwal, $pAkhir] = NeracaPercobaan::periode($tahun);
    $hasil = $this->pk->hitung($tahun, $pAwal, $this->tgl($request->query('akhir')));
    $data = $hasil + [
      'tahun' => $tahun,
      'pAwal' => $pAwal,
      'pAkhir' => $pAkhir,
      'tglLalu' => $tahun . '-08-31',
      'rinci' => $request->boolean('rinci'),
      'ttd' => config('simaku.laporan_ttd', []),
    ];

    return view($request->boolean('cetak') ? 'content.akuntansi.posisi-keuangan.komparasi-cetak' : 'content.akuntansi.posisi-keuangan.komparasi', $data);
  }

  /** Halaman cetak (HTML siap print / simpan PDF). ?rinci=1 menyertakan akun level 6. */
  public function cetak(Request $request)
  {
    $tahun = $this->tahun();
    [$pAwal, $pAkhir] = NeracaPercobaan::periode($tahun);
    $hasil = $this->pk->hitung($tahun, $this->tgl($request->query('awal')), $this->tgl($request->query('akhir')));

    return view('content.akuntansi.posisi-keuangan.cetak', $hasil + [
      'tahun' => $tahun,
      'pAwal' => $pAwal,
      'rinci' => $request->boolean('rinci'),
      'ttd' => config('simaku.laporan_ttd', []),
    ]);
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
