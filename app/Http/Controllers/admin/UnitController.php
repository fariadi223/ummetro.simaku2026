<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pengelolaan unit kerja (ueu_tbl_unit), padanan Admin::unit* dan
 * Admin::unit_klinik* di v1.
 */
class UnitController extends Controller
{
  public function index()
  {
    $units = Unit::query()
      ->withCount('users')
      ->orderBy('kode')
      ->get();

    // Penanda otorisator per unit, seperti kolom nama unit di v1.
    $otorisator = DB::table('ueu_tbl_user')
      ->whereIn('wewenang', ['otorisator1', 'otorisator2'])
      ->get(['id_unit', 'wewenang'])
      ->groupBy('id_unit')
      ->map(fn ($g) => $g->pluck('wewenang')->unique()->sort()->values()->all());

    // Kelompokkan per fakultas/biro (2 digit pertama kode).
    $grup = $units->groupBy(fn ($u) => $u->kode_induk)->map(function ($anggota, $kode) {
      $induk = $anggota->firstWhere('is_induk', true);

      return (object) [
        'kode' => $kode,
        'nama' => $induk?->nama ?? "Kelompok {$kode}",
        'unit' => $anggota,
      ];
    });

    return view('content.admin.unit.index', [
      'grup' => $grup,
      'otorisator' => $otorisator,
      'statistik' => [
        'total' => $units->count(),
        'core' => $units->where('unit', 1)->count(),
        'supporting' => $units->where('unit', '!=', 1)->count(),
        'klinik' => $units->where('insert_anggaran', 'enable')->count(),
        'tanpa_akun' => $units->where('users_count', 0)->count(),
      ],
    ]);
  }

  /** Detail unit (JSON) untuk panel samping: profil & akun pengguna. */
  public function show(Unit $unit)
  {
    return response()->json([
      'unit' => $unit->only([
        'id_unit', 'kode', 'nama', 'nama_pimp', 'an_nama_pimp', 'nip', 'nama_operator',
        'jabatan_operator', 'kode_fak', 'jml_spp', 'visi', 'misi', 'tu', 'tk', 'ket',
      ]) + ['kelompok' => $unit->kelompok, 'klinik' => $unit->klinik_aktif],
      'users' => $unit->users()->orderBy('wewenang')->get(['username', 'nama_lengkap', 'wewenang']),
    ]);
  }

  public function store(Request $request)
  {
    $data = $this->validasi($request);

    // Banyak kolom lama NOT NULL tanpa default: isi nilai kosong.
    Unit::create($data + [
      'pelaksana' => '', 'pagu1' => 0, 'pagu2' => 0, 'pagu3' => 0,
      'namauser' => '', 'pass' => '', 'pass_opr' => '', 'status' => 'belum terdaftar',
      'visi' => '', 'misi' => '', 'tu' => '', 'tk' => '', 'ket' => '',
      'latblk1' => '', 'latblk2' => '', 'latblk3' => '', 'latblk4' => '',
      'insert_anggaran' => 'disable',
    ]);

    return back()->with('status', "Unit {$data['kode']} - {$data['nama']} berhasil ditambahkan.");
  }

  public function update(Request $request, Unit $unit)
  {
    $data = $this->validasi($request, $unit);

    $unit->update($data);

    return back()->with('status', "Unit {$unit->kode} - {$unit->nama} berhasil diperbarui.");
  }

  /** Aktif/nonaktifkan "klinik" anggaran (padanan unit_klinik_update di v1). */
  public function klinik(Request $request, Unit $unit)
  {
    $aktif = $request->boolean('aktif');
    $unit->update(['insert_anggaran' => $aktif ? 'enable' : 'disable']);

    $pesan = "Klinik anggaran {$unit->kode} - {$unit->nama} " . ($aktif ? 'diaktifkan.' : 'dinonaktifkan.');

    return $request->expectsJson()
      ? response()->json(['aktif' => $aktif, 'pesan' => $pesan])
      : back()->with('status', $pesan);
  }

  public function destroy(Unit $unit)
  {
    if ($alasan = $this->alasanTidakBolehHapus($unit)) {
      return back()->with('error', "{$unit->kode} - {$unit->nama}: {$alasan}");
    }

    $unit->delete();

    return back()->with('status', "Unit {$unit->kode} - {$unit->nama} berhasil dihapus.");
  }

  public function cetak()
  {
    return view('content.admin.unit.cetak', [
      'units' => Unit::withCount('users')->orderBy('kode')->get(),
    ]);
  }

  private function validasi(Request $request, ?Unit $unit = null): array
  {
    $data = $request->validate([
      'kode' => ['required', 'regex:/^\d{2}\.\d{2}$/', Rule::unique('ueu_tbl_unit', 'kode')->ignore($unit?->id_unit, 'id_unit')],
      'nama' => ['required', 'string', 'max:100'],
      'unit' => ['required', Rule::in(array_keys(Unit::KELOMPOK))],
      'nama_pimp' => ['required', 'string', 'max:100'],
      'an_nama_pimp' => ['nullable', 'string', 'max:225'],
      'nip' => ['nullable', 'string', 'max:20'],
      'jml_spp' => ['required', 'integer', 'min:0', 'max:999'],
      'kode_fak' => ['nullable', 'regex:/^\d{1,2}$/'],
      'nama_operator' => ['nullable', 'string', 'max:85'],
      'jabatan_operator' => ['nullable', 'string', 'max:100'],
    ], [
      'kode.regex' => 'Format kode unit harus xx.xx, mis. 01.03.',
      'kode.unique' => 'Kode unit sudah dipakai unit lain.',
      'kode_fak.regex' => 'Kode fakultas berupa angka 1-2 digit.',
    ], [
      'kode' => 'kode unit', 'nama' => 'nama unit', 'unit' => 'kelompok unit',
      'nama_pimp' => 'nama pimpinan', 'an_nama_pimp' => 'a.n. pimpinan', 'nip' => 'NIP',
      'jml_spp' => 'maksimal SPP', 'kode_fak' => 'kode fakultas',
      'nama_operator' => 'nama operator', 'jabatan_operator' => 'jabatan operator',
    ]);

    // Kolom NOT NULL tanpa default tidak boleh NULL.
    $data['nip'] = trim((string) ($data['nip'] ?? ''));
    $data['kode_fak'] = (string) ($data['kode_fak'] ?? '');
    $data['an_nama_pimp'] = ($data['an_nama_pimp'] ?? null) ?: null;

    return $data;
  }

  /** Unit yang sudah dipakai data lain tidak boleh dihapus (v1 menghapus tanpa cek). */
  private function alasanTidakBolehHapus(Unit $unit): ?string
  {
    foreach ($this->tabelPemakai() as [$tabel, $kolom]) {
      $n = DB::table($tabel)->where($kolom, $unit->id_unit)->count();
      if ($n > 0) {
        return "masih dipakai {$n} data di tabel {$tabel}, sehingga tidak dapat dihapus.";
      }
    }

    return null;
  }

  /**
   * [tabel, kolom] yang menunjuk ke id_unit, dideteksi dari skema.
   * Tabel salinan/percobaan (…tmp…, …_tes, …temp) dilewati.
   */
  private function tabelPemakai(): array
  {
    return Cache::remember('unit.tabel_pemakai', now()->addDay(), function () {
      return DB::table('information_schema.columns')
        ->where('table_schema', DB::getDatabaseName())
        ->whereIn('column_name', ['id_unit', 'idunit', 'idunitasal', 'id_uk', 'unit_id'])
        ->where('table_name', '!=', 'ueu_tbl_unit')
        ->where('table_name', 'not regexp', 'tmp|_tes|temp')
        ->orderByRaw("FIELD(table_name, 'ueu_tbl_user') DESC") // pesan paling jelas dulu
        ->get(['table_name', 'column_name'])
        ->map(fn ($r) => [$r->table_name ?? $r->TABLE_NAME, $r->column_name ?? $r->COLUMN_NAME])
        ->all();
    });
  }
}
