<?php

namespace App\Http\Controllers\unit;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Kegiatan;
use App\Models\Pagu;
use App\Models\Unit;
use App\Services\KomiteAnggaran;
use App\Services\RincianAnggaran;
use App\Services\StatusKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Proses Bisnis - Kegiatan unit, padanan Unit::kegiatan, add_kegiatan,
 * edit_kegiatan, process_kegiatan, delete_kegiatan, kirim_revisi_kegiatan di v1.
 *
 * Aturan boleh ubah/hapus (sama dengan v1):
 *   - komite dibuka (atau klinik anggaran unit aktif), ATAU
 *   - kegiatan perlu revisi: semua reviewer sudah menilai, tidak semua
 *     menyetujui, dan revisinya belum ditandai "selesai".
 *   - config simaku.update_force_kegiatan memaksa selalu boleh.
 */
class KegiatanController extends Controller
{
  public function __construct(private KomiteAnggaran $komite, private RincianAnggaran $rinci, private StatusKegiatan $status)
  {
  }

  public function index(Request $request)
  {
    [$unit, $tahun] = $this->konteks();
    $bidang = $this->bidang();
    $aktif = $this->bidangAktif($bidang, $request->query('mak'));

    $kegiatan = $this->queryKegiatan($unit, $tahun)->where('k.idmak1', $aktif->idmak1)->get();
    $status = $this->status->review($unit, $tahun, $kegiatan->pluck('idkeg')->all());
    $anggaran = $this->anggaran($kegiatan->pluck('idkeg')->all());
    $komite = $this->komite->status($unit, $tahun);

    $kegiatan->each(function ($k) use ($status, $anggaran, $komite) {
      $k->review = $status[$k->idkeg];
      $k->anggaran = $anggaran[$k->idkeg] ?? 0;
      $k->boleh_ubah = $this->status->bolehUbah($komite, $k->review);
    });

    // Jumlah kegiatan per bidang (untuk badge tab).
    $perBidang = DB::table('ueu_tbl_kegiatan')->where('idunit', $unit->id_unit)->where('tahun', $tahun)
      ->groupBy('idmak1')->selectRaw('idmak1, COUNT(*) as n')->pluck('n', 'idmak1');

    return view('content.unit.kegiatan.index', [
      'unit' => $unit,
      'tahun' => $tahun,
      'bidang' => $bidang,
      'aktif' => $aktif,
      'kegiatan' => $kegiatan,
      'perBidang' => $perBidang,
      'komite' => $komite,
      'jumlahReviewer' => $this->status->jumlahReviewer($unit, $tahun),
      'totalAnggaran' => $kegiatan->sum('anggaran'),
    ]);
  }

  public function create(Request $request)
  {
    [$unit, $tahun] = $this->konteks();
    $aktif = $this->bidangAktif($this->bidang(), $request->query('mak'));

    if (!$this->komite->status($unit, $tahun)['dibuka']) {
      return redirect()->route('unit.kegiatan.index', ['mak' => $aktif->kodemak1])
        ->with('error', 'Jadwal komite sudah ditutup; kegiatan baru tidak dapat ditambahkan.');
    }

    return $this->form($unit, $tahun, $aktif, null);
  }

  public function store(Request $request)
  {
    [$unit, $tahun] = $this->konteks();
    $aktif = $this->bidangAktif($this->bidang(), null, (int) $request->input('idmak1'));
    abort_unless($this->komite->status($unit, $tahun)['dibuka'], 403, 'Jadwal komite sudah ditutup.');

    $data = $this->validasi($request, $aktif, $tahun);

    Kegiatan::create($data + [
      'tahun' => (string) $tahun,
      'idunit' => $unit->id_unit,
      'idmak1' => $aktif->idmak1,
      'targetwkt' => '',   // kolom lama NOT NULL tanpa default, tidak dipakai
      'created_by' => Auth::user()->username,
      'created_date' => now('Asia/Jakarta')->format('d-m-Y G:i:s'),
    ]);

    return redirect()->route('unit.kegiatan.index', ['mak' => $aktif->kodemak1])
      ->with('status', "Kegiatan \"{$data['uraiankeg']}\" berhasil ditambahkan.");
  }

  public function edit(int $id)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatanMilikUnit($unit, $tahun, $id);
    $aktif = $this->bidangAktif($this->bidang(), null, (int) $keg->idmak1);

    if (!$this->status->bolehUbahKegiatan($unit, $tahun, $keg)) {
      return redirect()->route('unit.kegiatan.index', ['mak' => $aktif->kodemak1])
        ->with('error', 'Kegiatan tidak dapat diubah: jadwal komite sudah ditutup dan kegiatan tidak sedang direvisi.');
    }

    return $this->form($unit, $tahun, $aktif, $keg);
  }

  public function update(Request $request, int $id)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatanMilikUnit($unit, $tahun, $id);
    $aktif = $this->bidangAktif($this->bidang(), null, (int) $keg->idmak1);
    abort_unless($this->status->bolehUbahKegiatan($unit, $tahun, $keg), 403, 'Kegiatan tidak dapat diubah.');

    $data = $this->validasi($request, $aktif, $tahun);

    $keg->update($data + [
      'modified_by' => Auth::user()->username,
      'modified_date' => now('Asia/Jakarta')->format('d-m-Y G:i:s'),
    ]);

    return redirect()->route('unit.kegiatan.index', ['mak' => $aktif->kodemak1])
      ->with('status', "Kegiatan \"{$data['uraiankeg']}\" berhasil diperbarui.");
  }

  public function destroy(int $id)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatanMilikUnit($unit, $tahun, $id);
    $kodeMak1 = $this->bidangAktif($this->bidang(), null, (int) $keg->idmak1)->kodemak1;
    $kembali = redirect()->route('unit.kegiatan.index', ['mak' => $kodeMak1]);

    if (!$this->status->bolehUbahKegiatan($unit, $tahun, $keg)) {
      return $kembali->with('error', 'Kegiatan tidak dapat dihapus: jadwal komite sudah ditutup dan kegiatan tidak sedang direvisi.');
    }

    // Lebih ketat dari v1: kegiatan yang sudah diproses keuangan tidak boleh hilang.
    foreach (['ueu_tbl_spp' => 'SPP', 'ueu_tbl_lpj' => 'LPJ', 'ueu_tbl_jurnal_spm' => 'jurnal'] as $tabel => $label) {
      if (DB::table($tabel)->where('idkeg', $keg->idkeg)->exists()) {
        return $kembali->with('error', "Kegiatan \"{$keg->uraiankeg}\" sudah memiliki {$label}, sehingga tidak dapat dihapus.");
      }
    }

    DB::transaction(function () use ($keg) {
      // Seperti v1: rincian anggaran kegiatan ikut dihapus.
      DB::table('ueu_tbl_rinci')->where('idkeg', $keg->idkeg)->delete();
      $keg->delete();
    });

    return $kembali->with('status', "Kegiatan \"{$keg->uraiankeg}\" beserta rinciannya berhasil dihapus.");
  }

  /** Tandai revisi kegiatan selesai (padanan kirim_revisi_kegiatan di v1). */
  public function selesai(int $id)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatanMilikUnit($unit, $tahun, $id);

    DB::table('tbl_review_kegiatan')->where('kegiatan_id', $keg->idkeg)->update(['diperbaiki' => 'Ya']);

    return back()->with('status', "Revisi kegiatan \"{$keg->uraiankeg}\" dikirim ke reviewer.");
  }

  // ---------------------------------------------------------------------------

  private function form(Unit $unit, int $tahun, object $aktif, ?Kegiatan $keg)
  {
    $mak4 = DB::table('ueu_tbl_mak4 as m4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->where('m1.idmak1', $aktif->idmak1)
      ->orderBy('m2.kodemak2')->orderBy('m3.kodemak3')->orderBy('m4.kodemak4')
      ->get(['m4.idmak4', 'm4.namamak4', 'm3.namamak3', 'm2.namamak2',
        DB::raw("CONCAT_WS('.', m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode")]);

    return view('content.unit.kegiatan.form', [
      'unit' => $unit,
      'tahun' => $tahun,
      'aktif' => $aktif,
      'keg' => $keg,
      'mak4' => $mak4->groupBy(fn ($m) => $m->namamak2 . ' › ' . $m->namamak3),
      'rujukan' => DB::table('tbl_kebijakan')->orderBy('id')->get(),
      'pagu' => Pagu::where('tahun', (string) $tahun)->orderBy('id_pagu')->get(),
      'opsiIndikator' => Kegiatan::opsiIndikator(),
      'komite' => $this->komite->status($unit, $tahun),
    ]);
  }

  private function validasi(Request $request, object $aktif, int $tahun): array
  {
    $data = $request->validate([
      'idmak4' => ['required', 'integer', Rule::exists('ueu_tbl_mak4', 'idmak4')->where(fn ($q) => $q->whereIn('idmak3',
        DB::table('ueu_tbl_mak3 as m3')->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
          ->where('m2.idmak1', $aktif->idmak1)->select('m3.idmak3')))],
      'uraiankeg' => ['required', 'string', 'max:200'],
      'rujukan_id' => ['required', 'integer', 'exists:tbl_kebijakan,id'],
      'rujukan_uraian' => ['required', 'string'],
      'latblk' => ['required', 'string'],
      'tujuan' => ['required', 'string'],
      'indikator' => ['nullable', 'array'],
      'indikator.*' => [Rule::in(Kegiatan::opsiIndikator())],
      'lingkup' => ['required', 'string'],
      'jadwal' => ['required', 'string', 'max:255'],
      'idsumberdana' => ['required', Rule::exists('ueu_tbl_pagu', 'id_pagu')->where('tahun', (string) $tahun)],
    ], [
      'idmak4.exists' => 'Nama kegiatan tidak sesuai dengan bidang yang dipilih.',
      'idsumberdana.exists' => 'Sumber dana harus pagu T.A ' . $tahun . '.',
    ], [
      'idmak4' => 'nama kegiatan', 'uraiankeg' => 'nama / judul kegiatan', 'rujukan_id' => 'sumber rujukan',
      'rujukan_uraian' => 'deskripsi sumber rujukan', 'latblk' => 'latar belakang', 'tujuan' => 'tujuan',
      'indikator' => 'indikator', 'lingkup' => 'luaran / output', 'jadwal' => 'jadwal kegiatan', 'idsumberdana' => 'sumber dana',
    ]);

    // Urutan indikator mengikuti daftar baku (IKU1..12, IKT1..12), tanpa duplikat.
    $indikator = array_values(array_intersect(Kegiatan::opsiIndikator(), $data['indikator'] ?? []));

    return [
      'idmak4' => (int) $data['idmak4'],
      'uraiankeg' => trim($data['uraiankeg']),
      'kebijakan_id' => (int) $data['rujukan_id'],
      'kebijakan_uraian' => trim($data['rujukan_uraian']),
      'latblk' => trim($data['latblk']),
      'tujuan' => trim($data['tujuan']),
      'indikator' => implode(',', $indikator),
      'lingkup' => trim($data['lingkup']),
      'jadwal' => trim($data['jadwal']),
      'idsumberdana' => $data['idsumberdana'],
    ];
  }

  /** Unit & tahun anggaran dari sesi login (padanan $this->idunit / $this->tahun v1). */
  private function konteks(): array
  {
    $unit = Auth::user()->unit;
    abort_unless($unit, 404, 'Akun ini tidak terhubung ke unit kerja.');

    return [$unit, (int) (session('periode') ?: Info::tahun(['aktif' => 'enable']))];
  }

  /** MAK level 1 = bidang / tab (urut kode). */
  private function bidang()
  {
    return DB::table('ueu_tbl_mak1')->orderBy('kodemak1')->get(['idmak1', 'kodemak1', 'namamak1']);
  }

  private function bidangAktif($bidang, $kode = null, ?int $id = null): object
  {
    $pilih = $id ? $bidang->firstWhere('idmak1', $id) : ($kode !== null ? $bidang->firstWhere('kodemak1', (int) $kode) : null);
    $pilih ??= $bidang->first();
    abort_unless($pilih, 404, 'Data MAK belum tersedia.');

    return $pilih;
  }

  private function queryKegiatan(Unit $unit, int $tahun)
  {
    return DB::table('ueu_tbl_kegiatan as k')
      ->join('ueu_tbl_mak4 as m4', 'm4.idmak4', '=', 'k.idmak4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->leftJoin('tbl_kebijakan as kb', 'kb.id', '=', 'k.kebijakan_id')
      ->leftJoin('ueu_tbl_pagu as p', 'p.id_pagu', '=', 'k.idsumberdana')
      ->where('k.idunit', $unit->id_unit)
      ->where('k.tahun', (string) $tahun)
      ->orderBy('m1.kodemak1')->orderBy('m2.kodemak2')->orderBy('m3.kodemak3')->orderBy('m4.kodemak4')->orderBy('k.idkeg')
      ->select('k.*', 'm4.namamak4', 'kb.rujukan_nama', 'p.sumber as nama_pagu',
        DB::raw("CONCAT_WS('.', m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode_mak"));
  }

  private function kegiatanMilikUnit(Unit $unit, int $tahun, int $id): Kegiatan
  {
    return Kegiatan::where('idkeg', $id)->where('idunit', $unit->id_unit)->where('tahun', (string) $tahun)->firstOrFail();
  }

  /** |pendapatan − belanja| per kegiatan (Lib_config::jml_anggaran di v1). */
  private function anggaran(array $idKeg): array
  {
    if (!$idKeg) {
      return [];
    }

    $hasil = [];
    $this->rinci->query()->whereIn('r.idkeg', $idKeg)->whereIn('r1.koderek1', ['4', '5'])
      ->groupBy('r.idkeg', 'r1.koderek1')
      ->selectRaw('r.idkeg, r1.koderek1 as kode, SUM(' . RincianAnggaran::SQL_NILAI . ') as total')
      ->get()
      ->each(function ($r) use (&$hasil) {
        $hasil[$r->idkeg] = ($hasil[$r->idkeg] ?? 0) + ($r->kode == '4' ? 1 : -1) * $r->total;
      });

    return array_map(fn ($v) => (int) round(abs($v)), $hasil);
  }
}
