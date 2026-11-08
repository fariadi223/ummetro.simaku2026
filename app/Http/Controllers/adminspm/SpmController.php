<?php

namespace App\Http\Controllers\adminspm;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Unit;
use App\Services\SppAnggaran;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daftar & cetak SPM untuk Operator SPM (Pembuat SPM, /adminspm/spm) dan otorisator BAK/WR II
 * (/dashboard/spm), padanan Adminspm::list_spm, Dashboard::spm dan Dashboard::spm_cetak v1.
 *
 *   Daftar : no_spm terisi, status_spm Disetujui, status_spp ≠ Ditolak, T.A login
 *            (Mspp::LikeGroupSPM), dikelompokkan per nomor, nominal = Σ ajuan.
 *   Cetak  : lembar SPP + SPM: kegiatan & ajuan, pernyataan unit, tanda tangan pimpinan
 *            unit induk FF.00, nominal + terbilang, persetujuan BAK (otorisator1) & WR II
 *            (otorisator2; "An." bila an_nama_pimp terisi). Spesimen tanda tangan diambil dari
 *            public/<simaku.specimen_dir>/{kode induk|otorisator1|otorisator2}.png bila berkasnya ada.
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
    $baris = $this->spp->query()->where('s.tahun', (string) $tahun)->where('s.no_spm', $noSpm)->get();
    $ringkas = $baris->first();

    // v1: kode unit dari nomor SPM ("N/SPM/<kode>/..."), penanda tangan = unit induk FF.00.
    $kodeUnit = (string) $ringkas->kode_unit;
    $ff = explode('.', $kodeUnit)[0];
    $induk = Unit::where('kode', $ff . '.00')->first();

    $ttd = fn (string $w) => DB::table('ueu_tbl_user as t1')->join('ueu_tbl_unit as t0', 't0.id_unit', '=', 't1.id_unit')
      ->where('t1.wewenang', $w)->orderBy('t1.id_unit')->first(['t0.kode', 't0.nama', 't0.nama_pimp', 't0.an_nama_pimp']);

    return view('content.adminspm.spm.cetak', [
      'tahun' => $tahun,
      'noSpm' => $noSpm,
      'ringkas' => $ringkas,
      'baris' => $baris,
      'unit' => Unit::where('kode', $kodeUnit)->first(),
      'induk' => $induk,
      'ttd1' => $ttd('otorisator1'),
      'ttd2' => $ttd('otorisator2'),
      'spesimen' => [
        'induk' => $induk ? $this->spesimen($induk->kode) : null,
        'bak' => $ringkas->verifikasi1 === 'Disetujui' ? $this->spesimen('otorisator1') : null,
        'wr2' => $ringkas->verifikasi2 === 'Disetujui' ? $this->spesimen('otorisator2') : null,
      ],
    ]);
  }

  /** URL gambar spesimen tanda tangan bila berkasnya ada (v1: images/specimen/<nama>.png). */
  private function spesimen(string $nama): ?string
  {
    $rel = trim((string) config('simaku.specimen_dir', 'images/specimen'), '/') . '/' . $nama . '.png';

    return is_file(public_path($rel)) ? asset($rel) : null;
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
