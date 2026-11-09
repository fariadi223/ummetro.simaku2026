<?php

namespace App\Http\Controllers\adminspm;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\SppAnggaran;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Daftar & cetak SPM untuk Operator SPM (Pembuat SPM, /adminspm/spm) dan otorisator BAK/WR II
 * (/dashboard/spm), padanan Adminspm::list_spm, Dashboard::spm dan Dashboard::spm_cetak v1.
 *
 *   Daftar : no_spm terisi, status_spm Disetujui, status_spp ≠ Ditolak, T.A login
 *            (Mspp::LikeGroupSPM), dikelompokkan per nomor, nominal = Σ ajuan.
 *   Cetak  : lembar SPP + SPM: kegiatan & ajuan, pernyataan unit, tanda tangan pimpinan
 *            unit induk FF.00, nominal + terbilang, persetujuan BAK (otorisator1) & WR II
 *            (otorisator2; "An." bila an_nama_pimp terisi). Spesimen tanda tangan diambil dari
 *            storage/app/public/<simaku.specimen_dir>/{kode induk|otorisator1|otorisator2}.png bila berkasnya ada.
 */
class SpmController extends Controller
{
  public function __construct(private SppAnggaran $spp)
  {
  }

  /** ?cari= (no. SPM / SPP / kode / nama unit) & ?page=, 15 SPM per halaman. */
  public function index(Request $request)
  {
    $tahun = $this->tahun();
    $cari = trim((string) $request->query('cari', ''));

    $spm = $this->spp->query()->where('s.tahun', (string) $tahun)
      ->where('s.no_spm', '!=', '')->where('s.status_spm', 'Disetujui')->where('s.status_spp', '!=', 'Ditolak')
      ->get()->groupBy('no_spp')
      ->map(function (Collection $b) {
        $s = clone $b->first();
        $s->nominal = (float) $b->sum('ajuan');
        $s->kegiatan = $b->count();

        return $s;
      })
      ->sortByDesc(fn ($s) => (int) $s->no_spm)->values();

    $semua = $spm->count();
    $totalSemua = $spm->sum('nominal');
    if ($cari !== '') {
      $q = mb_strtolower($cari);
      $spm = $spm->filter(fn ($s) => str_contains(mb_strtolower("{$s->no_spm} {$s->no_spp} {$s->kode_unit} {$s->nama_unit}"), $q))->values();
    }

    $perHalaman = 15;
    $halaman = LengthAwarePaginator::resolveCurrentPage();
    $daftar = new LengthAwarePaginator($spm->forPage($halaman, $perHalaman)->values(), $spm->count(), $perHalaman, $halaman,
      ['path' => $request->url(), 'query' => $request->query()]);

    return view('content.adminspm.spm.index', [
      'rute' => $this->rute(),
      'tahun' => $tahun,
      'daftar' => $daftar,
      'cari' => $cari,
      'jumlah' => $spm->count(),
      'total' => $spm->sum('nominal'),
      'semua' => $semua,
      'totalSemua' => $totalSemua,
    ]);
  }

  /** Lembar SPM siap cetak (spm_cetak v1). {id} = id_spp salah satu baris SPM. */
  public function cetak(int $id)
  {
    $tahun = $this->tahun();
    $noSpm = $this->spp->query()->where('s.tahun', (string) $tahun)->where('s.id_spp', $id)->where('s.no_spm', '!=', '')
      ->first()?->no_spm ?? abort(404);

    return view('content.adminspm.spm.cetak', $this->spp->dataCetakSpm($tahun, $noSpm));
  }

  /** Prefix nama route sesuai area: Operator SPM (/adminspm/spm) atau otorisator BAK/WR II (/dashboard/spm). */
  private function rute(): string
  {
    return in_array(auth()->user()?->role(), ['otorisator1', 'otorisator2'], true) ? 'otorisator.daftar-spm' : 'adminspm.spm';
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
