<?php

namespace App\Http\Controllers\adminspm;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Unit;
use App\Services\SppAnggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi SPP & pembuatan SPM oleh Operator SPM (wewenang operatorspm, area /adminspm),
 * padanan Adminspm::verifikasi, Spp::verifikasi, create_spm, verifikasi_tolak, Adminspm::spm_approve v1.
 *
 * Alur (v1):
 *   1. SPP perlu verifikasi : otorisasi Sudah, status_spp ≠ Ditolak, no_spm kosong.
 *   2. Setujui (create_spm) : status_spp = Disetujui, no_spm = "N/SPM/<kode unit>/dd/mm/YYYY"
 *                             (N = nomor SPM terbesar T.A + 1), paraf = unit induk FF.00
 *                             (nama, an_nama_pimp atau nama_pimp); unit 11.* tanpa paraf.
 *   3. Tolak                : status_spp = Ditolak, no_spm = '', alasan2 = komentar.
 *   4. SPM siap di-approve  : no_spm terisi, status_spm Belum diproses, status_spp ≠ Ditolak;
 *                             disetujui BAK (verifikasi1, otorisator1) & WR II (verifikasi2, otorisator2).
 *                             Bila salah satu menolak, operator dapat membatalkan (seperti langkah 3, alasan2 = '').
 * Lembar SPM dicetak lewat SpmController::cetak (route adminspm.verifikasi.spm).
 * Lebih ketat dari v1: setiap aksi memeriksa status SPP saat itu & dibatasi ke T.A login.
 */
class VerifikasiController extends Controller
{
  public function __construct(private SppAnggaran $spp)
  {
  }

  public function index()
  {
    $tahun = $this->tahun();

    $perluVerifikasi = $this->spp->kelompokkan($this->perluVerifikasi($tahun)->get())
      ->sortBy([fn ($a, $b) => strcmp((string) $a->tgl_saji, (string) $b->tgl_saji)])->values();

    $spm = $this->siapApprove($tahun)->get()->groupBy('no_spm')
      ->map(function (Collection $b) {
        $s = clone $b->first();
        $s->nominal = (float) $b->sum('ajuan');
        $s->kegiatan = $b->count();
        $s->ditolak = $b->contains(fn ($x) => $x->verifikasi1 === 'Ditolak' || $x->verifikasi2 === 'Ditolak');
        $s->alasan = $b->flatMap(fn ($x) => [$x->alasan3, $x->alasan4, $x->alasan2, $x->alasan1])
          ->map(fn ($a) => trim((string) $a))->first(fn ($a) => $a !== '');

        return $s;
      })
      ->sortByDesc(fn ($s) => (int) $s->no_spm)->values();

    return view('content.adminspm.verifikasi.index', compact('tahun', 'perluVerifikasi', 'spm'));
  }

  /** Lembar SPP untuk diverifikasi (Spp::verifikasi v1). */
  public function show(int $id)
  {
    $tahun = $this->tahun();
    [$nomor, $baris] = $this->sppNomor($tahun, $id);

    return view('content.adminspm.verifikasi.show', [
      'tahun' => $tahun,
      'id' => $id,
      'nomor' => $nomor,
      'baris' => $this->spp->lengkapiNilai($baris, $tahun, $nomor),
      'ringkas' => $this->spp->kelompokkan($baris)->first(),
      'induk' => $this->induk($baris->first()->kode_unit),
      'bolehVerifikasi' => $this->perluVerifikasi($tahun)->where('s.no_spp', $nomor)->exists(),
    ]);
  }

  /** Setujui SPP & terbitkan nomor SPM (Spp::create_spm v1). */
  public function setujui(int $id)
  {
    $tahun = $this->tahun();
    [$nomor, $baris] = $this->sppNomor($tahun, $id);

    if (!$this->perluVerifikasi($tahun)->where('s.no_spp', $nomor)->exists()) {
      return redirect()->route('adminspm.verifikasi.index')->with('error', "SPP {$nomor} sudah diverifikasi sebelumnya.");
    }

    $kodeUnit = (string) $baris->first()->kode_unit;
    [$jabatan, $nama] = $this->paraf($kodeUnit);

    $noSpm = DB::transaction(function () use ($tahun, $nomor, $kodeUnit, $jabatan, $nama) {
      // GetLastGroupSPM v1: nomor SPM terbesar pada T.A (no_spm kosong = 0).
      $terakhir = (int) DB::table('ueu_tbl_spp')->where('tahun', (string) $tahun)->lockForUpdate()
        ->selectRaw('MAX(CAST(no_spm AS UNSIGNED)) as n')->value('n');
      $noSpm = ($terakhir + 1) . '/SPM/' . $kodeUnit . '/' . now()->format('d/m/Y');

      $this->barisNomor($tahun, $nomor)->where('status_spp', '!=', 'Ditolak')->where('no_spm', '')->update([
        'status_spp' => 'Disetujui',
        'no_spm' => $noSpm,
        'paraf_jabatan' => $jabatan,
        'paraf_nama' => $nama,
      ]);

      return $noSpm;
    });

    return redirect()->route('adminspm.verifikasi.index')->with('status', "SPP {$nomor} disetujui, SPM {$noSpm} diterbitkan.");
  }

  /** Tolak SPP (Spp::verifikasi_tolak v1, dari lembar SPP). */
  public function tolak(Request $request, int $id)
  {
    $tahun = $this->tahun();
    [$nomor] = $this->sppNomor($tahun, $id);

    if (!$this->perluVerifikasi($tahun)->where('s.no_spp', $nomor)->exists()) {
      return redirect()->route('adminspm.verifikasi.index')->with('error', "SPP {$nomor} sudah diverifikasi sebelumnya.");
    }

    // v1 tidak mewajibkan komentar; di sini wajib agar unit tahu alasannya.
    $data = validator($request->all(), ['alasan' => ['required', 'string', 'max:1000']], [], ['alasan' => 'alasan penolakan'])
      ->validateWithBag('tolak');

    $this->barisNomor($tahun, $nomor)->update(['status_spp' => 'Ditolak', 'no_spm' => '', 'alasan2' => trim($data['alasan'])]);

    return redirect()->route('adminspm.verifikasi.index')->with('status', "SPP {$nomor} ditolak.");
  }

  /** Batalkan SPM yang ditolak BAK / WR II (link "Batal" v1 -> verifikasi_tolak tanpa komentar). */
  public function batal(int $id)
  {
    $tahun = $this->tahun();
    $baris = $this->siapApprove($tahun)->where('s.id_spp', $id)->first() ?? abort(404);
    $semua = $this->siapApprove($tahun)->where('s.no_spm', $baris->no_spm)->get();

    if (!$semua->contains(fn ($x) => $x->verifikasi1 === 'Ditolak' || $x->verifikasi2 === 'Ditolak')) {
      return back()->with('error', "SPM {$baris->no_spm} tidak ditolak BAK / WR II, sehingga tidak dapat dibatalkan.");
    }

    // Seperti v1: alasan2 dikosongkan; alasan penolakan BAK/WR II tetap di alasan3/alasan4.
    $this->barisNomor($tahun, $baris->no_spp)->update(['status_spp' => 'Ditolak', 'no_spm' => '', 'alasan2' => '']);

    return back()->with('status', "SPM {$baris->no_spm} dibatalkan; SPP {$baris->no_spp} berstatus ditolak.");
  }

  // ---------------------------------------------------------------------------

  /** SPP menunggu verifikasi (Adminspm::verifikasi qry2 v1). */
  private function perluVerifikasi(int $tahun)
  {
    return $this->spp->query()->where('s.tahun', (string) $tahun)
      ->where('s.otorisasi', 'Sudah')->where('s.status_spp', '!=', 'Ditolak')->where('s.no_spm', '');
  }

  /** SPM siap di-approve BAK / WR II (Adminspm::verifikasi qry3 v1). */
  private function siapApprove(int $tahun)
  {
    return $this->spp->query()->where('s.tahun', (string) $tahun)
      ->where('s.status_spp', '!=', 'Ditolak')->where('s.status_spm', 'Belum diproses')->where('s.no_spm', '!=', '');
  }

  /** @return array{0: string, 1: Collection} nomor SPP & baris-barisnya (yang sudah diotorisasi). */
  private function sppNomor(int $tahun, int $id): array
  {
    $nomor = $this->spp->query()->where('s.tahun', (string) $tahun)->where('s.otorisasi', 'Sudah')->where('s.id_spp', $id)->first()?->no_spp
      ?? abort(404);

    return [$nomor, $this->spp->query()->where('s.tahun', (string) $tahun)->where('s.no_spp', $nomor)->get()];
  }

  private function barisNomor(int $tahun, string $nomor)
  {
    return DB::table('ueu_tbl_spp')->where('tahun', (string) $tahun)->where('no_spp', $nomor);
  }

  /** Unit induk sebuah unit: "00.*" = unit itu sendiri, selain itu FF.00 (create_spm v1). */
  private function induk(string $kodeUnit): ?Unit
  {
    $ff = explode('.', $kodeUnit)[0];

    return Unit::where('kode', $ff === '00' ? $kodeUnit : $ff . '.00')->first();
  }

  /** @return array{0: string, 1: string} paraf jabatan & nama untuk SPM (create_spm v1). */
  private function paraf(string $kodeUnit): array
  {
    if (explode('.', $kodeUnit)[0] === '11') {
      return ['', ''];
    }

    $induk = $this->induk($kodeUnit);

    return $induk ? [(string) $induk->nama, (string) ($induk->an_nama_pimp ?: $induk->nama_pimp)] : ['', ''];
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
