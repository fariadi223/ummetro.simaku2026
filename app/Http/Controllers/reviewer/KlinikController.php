<?php

namespace App\Http\Controllers\reviewer;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Rinci;
use App\Services\RincianAnggaran;
use App\Services\ReviewKegiatan;
use App\Services\StatusKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Klinik Anggaran Unit oleh reviewer, padanan Reviewer::unit, unit_tabel, kegiatan, review_simpan v1.
 *
 *   /reviewer/unit             daftar unit yang ditetapkan ke reviewer pada T.A sesi
 *   /reviewer/kegiatan/{kode}  kegiatan unit per bidang (MAK 1), rincian, komentar + Setujui / Revisi
 */
class KlinikController extends Controller
{
  public function __construct(private ReviewKegiatan $review, private StatusKegiatan $status, private RincianAnggaran $rinci)
  {
  }

  public function index()
  {
    $tahun = $this->tahun();
    $unit = $this->review->daftarUnit((int) Auth::id(), $tahun);

    return view('content.reviewer.unit', [
      'tahun' => $tahun,
      'unit' => $unit,
      'total' => [
        'kegiatan' => $unit->sum('kegiatan'),
        'disetujui' => $unit->sum('disetujui'),
        'direvisi' => $unit->sum('direvisi'),
        'ajuan_revisi' => $unit->sum('ajuan_revisi'),
        'belum' => $unit->sum('belum'),
      ],
    ]);
  }

  /** Review kegiatan satu unit; ?mak=kode MAK 1 memilih bidang (tab). */
  public function kegiatan(Request $request)
  {
    [$unit, $tahun] = $this->konteks($request);
    $idUser = (int) Auth::id();

    $bidang = DB::table('ueu_tbl_mak1')->orderBy('kodemak1')->get(['idmak1', 'kodemak1', 'namamak1']);
    abort_if($bidang->isEmpty(), 404, 'Data MAK belum tersedia.');
    $aktif = $bidang->firstWhere('kodemak1', (int) $request->query('mak')) ?? $bidang->first();

    $kegiatan = DB::table('ueu_tbl_kegiatan as k')
      ->join('ueu_tbl_mak4 as m4', 'm4.idmak4', '=', 'k.idmak4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->leftJoin('tbl_kebijakan as kb', 'kb.id', '=', 'k.kebijakan_id')
      ->leftJoin('ueu_tbl_pagu as p', 'p.id_pagu', '=', 'k.idsumberdana')
      ->where('k.idunit', $unit->id_unit)->where('k.tahun', (string) $tahun)->where('m1.idmak1', $aktif->idmak1)
      ->orderBy('m2.kodemak2')->orderBy('m3.kodemak3')->orderBy('m4.kodemak4')->orderBy('k.idkeg')
      ->get(['k.*', 'm4.namamak4', 'kb.rujukan_nama', 'p.sumber as nama_pagu',
        DB::raw("CONCAT_WS('.', m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode_mak")]);
    $idKeg = $kegiatan->pluck('idkeg')->all();

    $rinci = $this->rinci->query()->whereIn('r.idkeg', $idKeg ?: [0])->whereIn('r1.koderek1', ['4', '5'])
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')->orderBy('r4.koderek4')
      ->orderBy('r5.koderek5')->orderBy('r6.koderek6')->orderBy('r.idrinci')
      ->get(['r.*', 'r1.koderek1', 'r6.namarek6',
        DB::raw("CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode_rek")])
      ->each(function ($r) {
        $r->jumlah = Rinci::nilai($r);
        $r->volume = Rinci::volume($r);
      })->groupBy('idkeg');

    $milikSaya = $this->review->penilaian($idUser, $idKeg);
    $semua = $this->status->review($unit, $tahun, $idKeg);
    $catatanLain = DB::table('tbl_review_kegiatan as rk')
      ->leftJoin('ueu_tbl_user as u', 'u.idlog', '=', 'rk.user_id')
      ->whereIn('rk.kegiatan_id', $idKeg ?: [0])->where('rk.user_id', '!=', $idUser)
      ->whereNotNull('rk.keterangan')->where('rk.keterangan', '!=', '')
      ->orderBy('rk.id')
      ->get(['rk.kegiatan_id', 'rk.status', 'rk.diperbaiki', 'rk.keterangan', 'u.nama_lengkap', 'u.username'])
      ->groupBy('kegiatan_id');

    $kegiatan->each(function ($k) use ($rinci, $milikSaya, $semua, $catatanLain) {
      $r = $rinci[$k->idkeg] ?? collect();
      $k->pendapatan = $r->where('koderek1', '4')->values();
      $k->belanja = $r->where('koderek1', '5')->values();
      $k->total_pendapatan = (float) $k->pendapatan->sum('jumlah');
      $k->total_belanja = (float) $k->belanja->sum('jumlah');
      $k->saya = $milikSaya[$k->idkeg] ?? null;
      $k->review = $semua[$k->idkeg];
      $k->catatan_lain = $catatanLain[$k->idkeg] ?? collect();
    });

    // Penilaian reviewer ini untuk semua bidang unit (badge tab & ringkasan).
    $perBidang = DB::table('ueu_tbl_kegiatan as k')
      ->leftJoin('tbl_review_kegiatan as rk', fn ($j) => $j->on('rk.kegiatan_id', '=', 'k.idkeg')->where('rk.user_id', $idUser))
      ->where('k.idunit', $unit->id_unit)->where('k.tahun', (string) $tahun)
      ->groupBy('k.idmak1')
      ->selectRaw("k.idmak1, COUNT(DISTINCT k.idkeg) as n,
        COUNT(DISTINCT IF(rk.id IS NULL OR (rk.status = 'ditolak' AND rk.diperbaiki = 'Ya'), k.idkeg, NULL)) as perlu")
      ->get()->keyBy('idmak1');

    return view('content.reviewer.kegiatan', [
      'unit' => $unit,
      'tahun' => $tahun,
      'bidang' => $bidang,
      'aktif' => $aktif,
      'kegiatan' => $kegiatan,
      'perBidang' => $perBidang,
      'ringkasan' => $this->review->ringkasan($unit, $tahun, $bidang),
      'jumlahReviewer' => $this->status->jumlahReviewer($unit, $tahun),
    ]);
  }

  /**
   * Simpan penilaian satu kegiatan (review_simpan v1). status: disetujui | ditolak (Revisi).
   * Komentar wajib untuk Revisi agar unit tahu apa yang harus diperbaiki.
   */
  public function simpan(Request $request, int $idkeg)
  {
    [$unit, $tahun] = $this->konteks($request);

    $keg = DB::table('ueu_tbl_kegiatan')->where('idkeg', $idkeg)->where('idunit', $unit->id_unit)
      ->where('tahun', (string) $tahun)->first(['idkeg', 'idmak1', 'uraiankeg']);
    abort_unless($keg, 404, 'Kegiatan tidak ditemukan pada unit & T.A ini.');

    $data = $request->validate([
      'status' => ['required', Rule::in(['disetujui', 'ditolak'])],
      'keterangan' => ['nullable', 'string', 'max:5000', 'required_if:status,ditolak'],
    ], [
      'keterangan.required_if' => 'Tuliskan komentar: apa yang harus direvisi oleh unit.',
    ], ['keterangan' => 'komentar']);

    $keterangan = trim((string) ($data['keterangan'] ?? ''));
    $this->review->simpan((int) Auth::id(), (int) $unit->id_unit, $idkeg, $data['status'], $keterangan === '' ? null : $keterangan);

    $pesan = $data['status'] === 'disetujui'
      ? "Kegiatan \"{$keg->uraiankeg}\" disetujui."
      : "Kegiatan \"{$keg->uraiankeg}\" dikembalikan ke unit untuk direvisi.";

    if ($request->expectsJson()) {
      return response()->json(['pesan' => $pesan, 'status' => $data['status'], 'keterangan' => $keterangan]);
    }

    $mak = DB::table('ueu_tbl_mak1')->where('idmak1', $keg->idmak1)->value('kodemak1');

    return redirect()->to(route('reviewer.kegiatan.index', ['mak' => $mak]) . '#keg-' . $idkeg)->with('status', $pesan);
  }

  // ---------------------------------------------------------------------------

  /** Unit & T.A dari middleware ReviewerUnit. */
  private function konteks(Request $request): array
  {
    return [$request->attributes->get('unit_review'), (int) $request->attributes->get('tahun_review')];
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['aktif' => 'enable']));
  }
}
