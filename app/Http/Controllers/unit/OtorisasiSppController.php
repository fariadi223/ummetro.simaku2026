<?php

namespace App\Http\Controllers\unit;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Unit;
use App\Services\SppAnggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Otorisasi SPP oleh pimpinan unit induk (kode FF.00, area /adminunit), padanan
 * Spp::proses2, otorisasi, ajax_post_mak, setujui, tolak di v1.
 *
 * Cakupan: SPP terkirim (create_spp = 1) T.A berjalan dari semua sub unit FF.*
 * (v1: no_spp LIKE '/SPP/FF', di sini lewat kegiatan.idunit).
 *   - Setujui : otorisasi = 'Sudah' untuk semua baris nomor SPP tsb., setelah password
 *               salah satu akun unit FF.00 dicocokkan (Munit::GetPassUnit v1).
 *   - Tolak   : otorisasi = 'Batal', alasan1 = komentar. Sub unit lalu dapat menghapus SPP-nya.
 * Lebih ketat dari v1: hanya SPP berotorisasi 'Belum' milik sub unit sendiri yang bisa
 * disetujui/ditolak (v1 menerima nomor SPP apa saja dari POST).
 */
class OtorisasiSppController extends Controller
{
  public function __construct(private SppAnggaran $spp)
  {
  }

  /** Daftar SPP & SPM (proses2 v1). ?status=semua menampilkan juga yang sudah diotorisasi. */
  public function index(Request $request)
  {
    [$unit, $tahun, $idUnit] = $this->konteks();
    $semua = $request->query('status') === 'semua';

    // v1: otorisasi <> 'Batal' AND create_spp = 1 (termasuk yang sudah diotorisasi / jadi SPM).
    $spp = $this->spp->kelompokkan($this->querySpp($idUnit, $tahun)->where('s.otorisasi', '!=', 'Batal')->get())
      ->sortBy([fn ($a, $b) => strcmp((string) $b->tgl_saji, (string) $a->tgl_saji), fn ($a, $b) => strcmp($a->kode_unit, $b->kode_unit)])
      ->values();
    $menunggu = $spp->where('otorisasi', 'Belum')->count();

    $spm = $this->spp->kelompokkan($this->querySpp($idUnit, $tahun)->where('s.status_spm', 'Disetujui')->get())
      ->sortByDesc('tgl_saji')->values();

    return view('content.unit.otorisasi-spp.index', [
      'unit' => $unit,
      'tahun' => $tahun,
      'jadwal' => $this->spp->jadwal($tahun),
      'spp' => $semua ? $spp : $spp->where('otorisasi', 'Belum')->values(),
      'semua' => $semua,
      'menunggu' => $menunggu,
      'jumlahSpp' => $spp->count(),
      'spm' => $spm,
    ]);
  }

  /** Lembar SPP + tombol setujui/tolak (otorisasi v1). */
  public function show(int $id)
  {
    [$unit, $tahun, $idUnit] = $this->konteks();
    [$nomor, $baris] = $this->spp1($idUnit, $tahun, $id);

    return view('content.unit.otorisasi-spp.show', [
      'unit' => $unit,
      'tahun' => $tahun,
      'id' => $id,
      'nomor' => $nomor,
      'baris' => $this->spp->lengkapiNilai($baris, $tahun, $nomor),
      'ringkas' => $this->spp->kelompokkan($baris)->first(),
      'bolehOtorisasi' => $baris->contains('otorisasi', 'Belum'),
    ]);
  }

  /** Cetak lembar SPP; penanda tangan = pimpinan unit induk (seperti v1). */
  public function cetak(int $id)
  {
    [$unit, $tahun, $idUnit] = $this->konteks();
    [$nomor, $baris] = $this->spp1($idUnit, $tahun, $id);
    $sub = Unit::find($baris->first()->idunit);

    return view('content.unit.spp.cetak', [
      'unit' => $sub,
      'tahun' => $tahun,
      'nomor' => $nomor,
      'baris' => $this->spp->lengkapiNilai($baris, $tahun, $nomor),
      'induk' => $unit,
    ]);
  }

  public function setujui(Request $request, int $id)
  {
    [$unit, $tahun, $idUnit] = $this->konteks();
    [$nomor, $baris] = $this->spp1($idUnit, $tahun, $id);
    $kembali = redirect()->route('unit.otorisasi.show', $id);

    if (!$baris->contains('otorisasi', 'Belum')) {
      return $kembali->with('error', "SPP {$nomor} sudah diproses sebelumnya.");
    }

    $request->validate(['password' => ['required', 'string', 'max:32']], [], ['password' => 'password']);

    // Sama dengan v1 (GetPassUnit): password salah satu akun unit FF.00, disimpan polos.
    $cocok = DB::table('ueu_tbl_user')->where('id_unit', $unit->id_unit)->where('password', $request->input('password'))->exists();
    if (!$cocok) {
      return $kembali->withErrors(['password' => 'Maaf, password yang dimasukkan SALAH.'], 'setujui');
    }

    $this->barisNomor($idUnit, $tahun, $nomor)->where('otorisasi', 'Belum')->update(['otorisasi' => 'Sudah']);

    return redirect()->route('unit.otorisasi.index')->with('status', "SPP {$nomor} disetujui dan diteruskan ke verifikasi.");
  }

  public function tolak(Request $request, int $id)
  {
    [$unit, $tahun, $idUnit] = $this->konteks();
    [$nomor, $baris] = $this->spp1($idUnit, $tahun, $id);
    $kembali = redirect()->route('unit.otorisasi.show', $id);

    if (!$baris->contains('otorisasi', 'Belum')) {
      return $kembali->with('error', "SPP {$nomor} sudah diproses sebelumnya.");
    }

    // v1 tidak mewajibkan komentar; di sini wajib agar sub unit tahu alasannya.
    $data = validator($request->all(), ['alasan' => ['required', 'string', 'max:1000']], [], ['alasan' => 'alasan penolakan'])
      ->validateWithBag('tolak');

    $this->barisNomor($idUnit, $tahun, $nomor)->where('otorisasi', 'Belum')
      ->update(['otorisasi' => 'Batal', 'alasan1' => trim($data['alasan'])]);

    return redirect()->route('unit.otorisasi.index')->with('status', "SPP {$nomor} ditolak. Sub unit akan melihat alasan penolakan.");
  }

  // ---------------------------------------------------------------------------

  /** SPP terkirim sub unit pada T.A (filter tahun pada SPP, seperti LikeGroupSPP v1). */
  private function querySpp(array $idUnit, int $tahun)
  {
    return $this->spp->query()
      ->whereIn('k.idunit', $idUnit ?: [0])->where('s.tahun', (string) $tahun)->where('s.create_spp', '1');
  }

  /** @return array{0: string, 1: \Illuminate\Support\Collection} nomor SPP & baris-barisnya */
  private function spp1(array $idUnit, int $tahun, int $id): array
  {
    $nomor = $this->querySpp($idUnit, $tahun)->where('s.id_spp', $id)->first()?->no_spp ?? abort(404);

    return [$nomor, $this->querySpp($idUnit, $tahun)->where('s.no_spp', $nomor)->get()];
  }

  /** Baris ueu_tbl_spp satu nomor milik sub unit, untuk update. */
  private function barisNomor(array $idUnit, int $tahun, string $nomor)
  {
    return DB::table('ueu_tbl_spp')->where('no_spp', $nomor)->where('tahun', (string) $tahun)->where('create_spp', '1')
      ->whereIn('idkeg', DB::table('ueu_tbl_kegiatan')->whereIn('idunit', $idUnit ?: [0])->select('idkeg'));
  }

  /** @return array{0: Unit, 1: int, 2: int[]} unit induk, T.A, id sub unit */
  private function konteks(): array
  {
    $unit = Auth::user()->unit;
    abort_unless($unit, 404, 'Akun ini tidak terhubung ke unit kerja.');
    abort_unless($unit->is_induk, 403, 'Otorisasi SPP hanya untuk pimpinan unit induk.');

    $idUnit = Unit::where('kode', 'like', $unit->kode_induk . '.%')->where('kode', 'not like', '%.00')
      ->pluck('id_unit')->map(fn ($v) => (int) $v)->all();

    return [$unit, (int) (session('periode') ?: Info::tahun(['aktif' => 'enable'])), $idUnit];
  }
}
