<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Biaya;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Daftar master (standar) biaya (tbl_biaya), padanan Admin::biaya* di v1.
 */
class BiayaController extends Controller
{
  public function index()
  {
    $daftar = Biaya::orderBy('item')->get();
    $terpetakan = DB::table('tbl_biaya_rek')->pluck('idbiaya')->flip();

    return view('content.admin.biaya.index', [
      'daftar' => $daftar,
      'terpetakan' => $terpetakan,
      'jenis' => Biaya::JENIS,
      'perJenis' => $daftar->countBy(fn ($b) => $b->kode_jenis),
      'statistik' => [
        'total' => $daftar->count(),
        'jenis' => $daftar->countBy(fn ($b) => $b->kode_jenis)->count(),
        'tanpa_nilai' => $daftar->filter(fn ($b) => !(int) $b->stmin && !(int) $b->stmak)->count(),
        'janggal' => $daftar->filter(fn ($b) => $b->tidak_konsisten)->count(),
      ],
    ]);
  }

  public function store(Request $request)
  {
    $data = $this->validasi($request);
    Biaya::create($data);

    return back()->with('status', "Item biaya \"{$data['item']}\" berhasil ditambahkan.");
  }

  public function update(Request $request, Biaya $biaya)
  {
    $biaya->update($this->validasi($request));

    return back()->with('status', "Item biaya \"{$biaya->item}\" berhasil diperbarui.");
  }

  public function destroy(Biaya $biaya)
  {
    if (DB::table('tbl_biaya_rek')->where('idbiaya', $biaya->id)->exists()) {
      return back()->with('error', "\"{$biaya->item}\" masih dipetakan ke rekening (tbl_biaya_rek), sehingga tidak dapat dihapus.");
    }

    $biaya->delete();

    return back()->with('status', "Item biaya \"{$biaya->item}\" berhasil dihapus.");
  }

  public function cetak(Request $request)
  {
    $jenis = $request->query('jenis');

    $daftar = Biaya::orderBy('item')->get()
      ->when($jenis !== null && $jenis !== '', fn ($c) => $c->filter(fn ($b) => $b->kode_jenis === $jenis))
      ->sortBy([fn ($a, $b) => strcmp($a->kode_jenis, $b->kode_jenis), fn ($a, $b) => strnatcasecmp($a->item, $b->item)])
      ->groupBy(fn ($b) => $b->kode_jenis);

    return view('content.admin.biaya.cetak', ['grup' => $daftar, 'jenis' => Biaya::JENIS]);
  }

  private function validasi(Request $request): array
  {
    // Terima format "1.500.000" atau "1500000".
    foreach (['stmin', 'stmak'] as $k) {
      $request->merge([$k => preg_replace('/[^\d]/', '', (string) $request->input($k)) ?: 0]);
    }

    $data = $request->validate([
      'item' => ['required', 'string', 'max:262'],
      'jenis' => ['required', Rule::in(array_keys(Biaya::JENIS))],
      'stmin' => ['required', 'integer', 'min:0', 'max:2147483647'],
      'stmak' => ['required', 'integer', 'min:0', 'max:2147483647', function ($attr, $nilai, $gagal) use ($request) {
        if ((int) $nilai > 0 && (int) $nilai < (int) $request->input('stmin')) {
          $gagal('Standar maksimum tidak boleh lebih kecil dari minimum (isi 0 bila tanpa batas atas).');
        }
      }],
      'ket' => ['nullable', 'string', 'max:104'],
    ], [], [
      'item' => 'uraian item', 'jenis' => 'jenis', 'stmin' => 'standar minimum',
      'stmak' => 'standar maksimum', 'ket' => 'satuan / keterangan',
    ]);

    $data['item'] = trim($data['item']);
    $data['ket'] = trim((string) ($data['ket'] ?? ''));

    return $data;
  }
}
