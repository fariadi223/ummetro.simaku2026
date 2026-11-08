<?php

namespace App\Http\Controllers\otorisator;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Unit;
use App\Services\SppAnggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Persetujuan SPM oleh otorisator, padanan Dashboard::otorisasi_one / otoris_one_view /
 * otoris_one_approve / otoris_one_tolak v1 (dan *_two untuk WR II).
 *
 *   Daftar : no_spm terisi, status_spm Belum diproses, status_spp ≠ Ditolak, T.A login.
 *   Level 1 (otorisator1, BAK)   : setujui -> verifikasi1 = Disetujui; tolak -> verifikasi1 = Ditolak, alasan3.
 *   Level 2 (otorisator2, WR II) : hanya SPM dengan verifikasi1 Disetujui; setujui -> verifikasi2 = Disetujui,
 *                                  status_spm = Disetujui, tgl_spm = hari ini; tolak -> verifikasi2 = Ditolak, alasan4.
 * SPM yang ditolak kembali ke Operator SPM untuk dibatalkan (Verifikasi SPP).
 * Lebih ketat dari v1: aksi hanya untuk SPM T.A login yang belum diputuskan pada level tsb.
 */
class OtorisasiSpmController extends Controller
{
  /** Konfigurasi per level otorisator. */
  private const LEVEL = [
    'otorisator1' => ['kolom' => 'verifikasi1', 'alasan' => 'alasan3', 'nama' => 'BAK', 'syarat' => null],
    'otorisator2' => ['kolom' => 'verifikasi2', 'alasan' => 'alasan4', 'nama' => 'WR II', 'syarat' => 'verifikasi1'],
  ];

  public function __construct(private SppAnggaran $spp)
  {
  }

  public function index(Request $request)
  {
    [$tahun, $lv] = $this->konteks();
    $semua = $request->query('status') === 'semua';

    $spm = $this->kelompok($this->dasar($tahun)->get())
      ->sortBy(fn ($s) => $s->tgl_terbit?->timestamp ?? PHP_INT_MAX)->values();
    // Antrian level ini: belum diputuskan (dan sudah disetujui level sebelumnya untuk WR II).
    $menunggu = $spm->filter(fn ($s) => $this->menunggu($s, $lv))->values();

    return view('content.otorisator.spm.index', [
      'tahun' => $tahun,
      'lv' => $lv,
      'spm' => $semua ? $spm : $menunggu,
      'semua' => $semua,
      'jumlahMenunggu' => $menunggu->count(),
      'jumlahSemua' => $spm->count(),
    ]);
  }

  /** Lembar SPM untuk diputuskan (spm_approve_one v1). {id} = id_spp salah satu baris SPM. */
  public function show(int $id)
  {
    [$tahun, $lv] = $this->konteks();
    [$noSpm, $baris] = $this->spm1($tahun, $id);
    $ringkas = $this->kelompok($baris)->first();
    $ff = explode('.', (string) $ringkas->kode_unit)[0];

    $ttd = fn (string $w) => DB::table('ueu_tbl_user as t1')->join('ueu_tbl_unit as t0', 't0.id_unit', '=', 't1.id_unit')
      ->where('t1.wewenang', $w)->orderBy('t1.id_unit')->first(['t0.nama', 't0.nama_pimp', 't0.an_nama_pimp']);

    return view('content.otorisator.spm.show', [
      'tahun' => $tahun,
      'lv' => $lv,
      'id' => $id,
      'noSpm' => $noSpm,
      'ringkas' => $ringkas,
      'baris' => $this->spp->lengkapiNilai($baris, $tahun, (string) $ringkas->no_spp),
      'induk' => Unit::where('kode', $ff . '.00')->first(),
      'ttd1' => $ttd('otorisator1'),
      'ttd2' => $ttd('otorisator2'),
      'bolehPutus' => $this->menunggu($ringkas, $lv),
    ]);
  }

  public function setujui(int $id)
  {
    [$tahun, $lv] = $this->konteks();
    [$noSpm, $baris] = $this->spm1($tahun, $id);

    if (!$this->menunggu($this->kelompok($baris)->first(), $lv)) {
      return redirect()->route('otorisator.spm.index')->with('error', "SPM {$noSpm} sudah diputuskan sebelumnya atau belum dapat diproses.");
    }

    $data = [$lv['kolom'] => 'Disetujui'];
    if ($lv['syarat']) {
      // WR II = persetujuan terakhir (otoris_two_approve v1).
      $data += ['status_spm' => 'Disetujui', 'tgl_spm' => now()->toDateString()];
    }
    $this->barisSpm($tahun, $noSpm)->update($data);

    return redirect()->route('otorisator.spm.index')->with('status', "SPM {$noSpm} disetujui {$lv['nama']}.");
  }

  public function tolak(Request $request, int $id)
  {
    [$tahun, $lv] = $this->konteks();
    [$noSpm, $baris] = $this->spm1($tahun, $id);

    if (!$this->menunggu($this->kelompok($baris)->first(), $lv)) {
      return redirect()->route('otorisator.spm.index')->with('error', "SPM {$noSpm} sudah diputuskan sebelumnya atau belum dapat diproses.");
    }

    // v1 tidak mewajibkan komentar; di sini wajib agar Operator SPM & unit tahu alasannya.
    $data = validator($request->all(), ['alasan' => ['required', 'string', 'max:1000']], [], ['alasan' => 'alasan penolakan'])
      ->validateWithBag('tolak');

    $this->barisSpm($tahun, $noSpm)->update([$lv['kolom'] => 'Ditolak', $lv['alasan'] => trim($data['alasan'])]);

    return redirect()->route('otorisator.spm.index')->with('status', "SPM {$noSpm} ditolak {$lv['nama']}; Operator SPM akan menindaklanjuti.");
  }

  // ---------------------------------------------------------------------------

  /** SPM dalam proses persetujuan (otorisasi_one v1). */
  private function dasar(int $tahun)
  {
    return $this->spp->query()->where('s.tahun', (string) $tahun)
      ->where('s.no_spm', '!=', '')->where('s.status_spm', 'Belum diproses')->where('s.status_spp', '!=', 'Ditolak');
  }

  /** Kelompokkan per nomor SPM; tanggal terbit dari nomor "N/SPM/<kode>/dd/mm/YYYY". */
  private function kelompok(Collection $baris): Collection
  {
    return $baris->groupBy('no_spm')->map(function (Collection $b) {
      $s = clone $b->first();
      $s->nominal = (float) $b->sum('ajuan');
      $s->kegiatan = $b->count();
      $s->alasan_tolak = $b->flatMap(fn ($x) => [$x->alasan3, $x->alasan4])->map(fn ($a) => trim((string) $a))->first(fn ($a) => $a !== '');
      $p = explode('/', (string) $s->no_spm);
      try {
        $s->tgl_terbit = count($p) >= 6 ? \Illuminate\Support\Carbon::createFromFormat('d/m/Y', "{$p[3]}/{$p[4]}/{$p[5]}")->startOfDay() : null;
      } catch (\Throwable) {
        $s->tgl_terbit = null;
      }

      return $s;
    })->values();
  }

  private function menunggu(object $s, array $lv): bool
  {
    return trim((string) $s->{$lv['kolom']}) === ''
      && ($lv['syarat'] === null || $s->{$lv['syarat']} === 'Disetujui')
      // SPM yang sudah ditolak level lain tidak diputuskan lagi.
      && $s->verifikasi1 !== 'Ditolak' && $s->verifikasi2 !== 'Ditolak';
  }

  /** @return array{0: string, 1: Collection} nomor SPM & baris-barisnya (masih dalam proses). */
  private function spm1(int $tahun, int $id): array
  {
    $noSpm = $this->dasar($tahun)->where('s.id_spp', $id)->first()?->no_spm ?? abort(404);

    return [$noSpm, $this->dasar($tahun)->where('s.no_spm', $noSpm)->get()];
  }

  private function barisSpm(int $tahun, string $noSpm)
  {
    return DB::table('ueu_tbl_spp')->where('tahun', (string) $tahun)->where('no_spm', $noSpm)
      ->where('status_spm', 'Belum diproses')->where('status_spp', '!=', 'Ditolak');
  }

  /** @return array{0: int, 1: array} T.A & konfigurasi level otorisator yang login */
  private function konteks(): array
  {
    $lv = self::LEVEL[Auth::user()->role()] ?? abort(403);

    return [(int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open'])), $lv + ['role' => Auth::user()->role()]];
  }
}
