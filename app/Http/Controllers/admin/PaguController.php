<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Pagu;
use App\Models\PaguUnit;
use App\Models\Unit;
use App\Services\RincianAnggaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Setting > Pagu: padanan Admin::pagu* dan Admin::pagu_unit* di v1.
 * Tahun yang ditampilkan = tahun anggaran di session (Setting > Periode).
 */
class PaguController extends Controller
{
  public function __construct(private RincianAnggaran $rinci)
  {
  }

  public function index()
  {
    $tahun = $this->tahun();

    $sumber = Pagu::where('tahun', $tahun)->orderBy('id_pagu')->get();
    $belanja = $this->rinci->belanjaPerUnitPagu($tahun);
    $total = $this->rinci->totalTahun($tahun);

    $paguUnit = PaguUnit::with('unitKerja:id_unit,kode,nama', 'pagu:id_pagu,tahun,sumber')
      ->where('tahun', $tahun)
      ->get()
      ->each(fn ($p) => $p->pakai = $belanja[(int) $p->idunit][(string) $p->idpagu] ?? 0)
      ->sortBy(fn ($p) => ($p->unitKerja->kode ?? 'zz') . ' ' . $p->idpagu)
      ->values();

    // Penggunaan per sumber = belanja kegiatan (semua unit) yang memilih pagu tsb.
    $sumber->each(function ($s) use ($belanja, $paguUnit) {
      $s->pakai = collect($belanja)->sum(fn ($perPagu) => $perPagu[$s->id_pagu] ?? 0);
      $s->teralokasi = $paguUnit->where('idpagu', $s->id_pagu)->sum('nominal');
    });

    $totalPagu = (int) $sumber->sum('jumlah');
    $ajuan = $total['belanja'] - $total['pendapatan'];   // v1: -(pendapatan - belanja)

    // Unit yang belum punya baris pagu unit untuk sumber tertentu (untuk "Tambah unit").
    $sudah = $paguUnit->map(fn ($p) => $p->idunit . '|' . $p->idpagu)->flip();

    return view('content.admin.pagu.index', [
      'tahun' => (int) $tahun,
      'sumber' => $sumber,
      'paguUnit' => $paguUnit,
      'units' => Unit::orderBy('kode')->get(['id_unit', 'kode', 'nama']),
      'sudah' => $sudah,
      'kodeBerikut' => Pagu::kodeBerikut(),
      'ringkasan' => [
        'pagu' => $totalPagu,
        'pendapatan' => $total['pendapatan'],
        'belanja' => $total['belanja'],
        'ajuan' => $ajuan,
        'sisa' => $totalPagu - $ajuan,
      ],
      // Baris pagu unit yang menunjuk pagu milik tahun lain (data lama janggal).
      'janggal' => $paguUnit->filter(fn ($p) => $p->pagu && (string) $p->pagu->tahun !== (string) $tahun)->count(),
    ]);
  }

  public function storeSumber(Request $request)
  {
    $kode = Pagu::kodeBerikut();
    abort_unless($kode, 422, 'Kode pagu sudah mencapai batas P99.');

    $data = $this->validasiSumber($request);
    Pagu::create(['id_pagu' => $kode, 'tahun' => $this->tahun()] + $data);

    return back()->with('status', "Sumber pagu {$kode} - {$data['sumber']} berhasil ditambahkan.");
  }

  public function updateSumber(Request $request, Pagu $pagu)
  {
    $pagu->update($this->validasiSumber($request));

    return back()->with('status', "Sumber pagu {$pagu->id_pagu} berhasil diperbarui.");
  }

  /** Tambahkan unit ke sebuah sumber pagu tahun ini. */
  public function storeUnit(Request $request)
  {
    $tahun = $this->tahun();
    $this->rupiah($request, 'nominal');

    $data = $request->validate([
      'idpagu' => ['required', Rule::exists('ueu_tbl_pagu', 'id_pagu')->where('tahun', $tahun)],
      'idunit' => ['required', 'integer', 'exists:ueu_tbl_unit,id_unit',
        Rule::unique('ueu_tbl_pagu_unit', 'idunit')->where(fn ($q) => $q->where('tahun', $tahun)->where('idpagu', $request->input('idpagu')))],
      'nominal' => ['required', 'integer', 'min:0'],
    ], [
      'idunit.unique' => 'Unit ini sudah memiliki pagu dari sumber tersebut.',
    ], ['idpagu' => 'sumber pagu', 'idunit' => 'unit', 'nominal' => 'nominal']);

    $p = PaguUnit::create(['tahun' => $tahun] + $data);

    return back()->with('status', 'Pagu unit ' . ($p->unitKerja->kode ?? '') . ' berhasil ditambahkan.');
  }

  /** Ubah nominal pagu unit (padanan pagu_unit_simpan di v1). */
  public function updateUnit(Request $request, PaguUnit $paguUnit)
  {
    $this->rupiah($request, 'nominal');
    $data = $request->validate(['nominal' => ['required', 'integer', 'min:0']], [], ['nominal' => 'nominal']);

    $paguUnit->update($data);

    return back()->with('status', 'Pagu unit ' . ($paguUnit->unitKerja->kode ?? '') . ' - ' . ($paguUnit->unitKerja->nama ?? '') . ' berhasil diperbarui.');
  }

  private function validasiSumber(Request $request): array
  {
    $this->rupiah($request, 'jumlah');

    return $request->validate([
      'sumber' => ['required', 'string', 'max:50'],
      'jumlah' => ['required', 'integer', 'min:0'],
    ], [], ['sumber' => 'sumber pagu', 'jumlah' => 'jumlah pagu']);
  }

  /** Terima "1.500.000" maupun "1500000". */
  private function rupiah(Request $request, string $kolom): void
  {
    if ($request->has($kolom)) {
      $request->merge([$kolom => preg_replace('/\D/', '', (string) $request->input($kolom))]);
    }
  }

  private function tahun(): string
  {
    return (string) (session('periode') ?: Info::tahun(['aktif' => 'enable']));
  }
}
