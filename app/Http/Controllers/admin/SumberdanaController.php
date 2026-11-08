<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Sumberdana;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Daftar kode sumber dana (ueu_tbl_sumberdana), padanan Admin::sumberdana* di v1.
 */
class SumberdanaController extends Controller
{
  public function index()
  {
    $daftar = Sumberdana::orderBy('kode')->get();
    $pakai = $this->jumlahPemakaian();

    $daftar->each(fn ($s) => $s->dipakai = (int) ($pakai[$s->id] ?? 0));

    return view('content.admin.sumberdana.index', [
      'daftar' => $daftar,
      'kodeBerikut' => $this->kodeBerikut($daftar),
      'statistik' => [
        'total' => $daftar->count(),
        'pembatasan' => $daftar->where('kelompok', 'Pembatasan')->count(),
        'tanpa' => $daftar->where('kelompok', 'Tanpa Pembatasan')->count(),
        'dipakai' => $daftar->where('dipakai', '>', 0)->count(),
      ],
    ]);
  }

  public function store(Request $request)
  {
    $data = $this->validasi($request);
    Sumberdana::create($data);

    return back()->with('status', "Sumber dana {$data['kode']} - {$data['nama_penerimaan']} berhasil ditambahkan.");
  }

  public function update(Request $request, Sumberdana $sumberdana)
  {
    $data = $this->validasi($request, $sumberdana);

    $pesan = "Sumber dana {$data['kode']} berhasil diperbarui.";
    if ($data['kelompok'] !== $sumberdana->kelompok && ($n = $this->jumlahPemakaian()[$sumberdana->id] ?? 0) > 0) {
      $pesan .= ' Perhatian: kelompok diubah padahal sudah dipakai ' . number_format($n, 0, ',', '.')
        . ' baris jurnal; laporan aset neto ikut berubah.';
    }

    $sumberdana->update($data);

    return back()->with('status', $pesan);
  }

  public function destroy(Sumberdana $sumberdana)
  {
    // v1 menghapus tanpa cek; di sini dicegah bila sudah dipakai jurnal.
    foreach ($this->tabelPemakai() as $tabel) {
      $n = DB::table($tabel)->where('id_sumber', $sumberdana->id)->count();
      if ($n > 0) {
        return back()->with('error', "{$sumberdana->kode} - {$sumberdana->nama_penerimaan}: sudah dipakai "
          . number_format($n, 0, ',', '.') . " baris di {$tabel}, sehingga tidak dapat dihapus.");
      }
    }

    $sumberdana->delete();

    return back()->with('status', "Sumber dana {$sumberdana->kode} - {$sumberdana->nama_penerimaan} berhasil dihapus.");
  }

  private function validasi(Request $request, ?Sumberdana $sd = null): array
  {
    return $request->validate([
      'kode' => ['required', 'regex:/^[0-9A-Za-z.\-]+$/', 'max:20', Rule::unique('ueu_tbl_sumberdana', 'kode')->ignore($sd?->id)],
      'nama_penerimaan' => ['required', 'string', 'max:255'],
      'kelompok' => ['required', Rule::in(array_keys(Sumberdana::KELOMPOK))],
    ], [
      'kode.unique' => 'Kode sudah dipakai sumber dana lain.',
      'kode.regex' => 'Kode hanya boleh berisi huruf, angka, titik, atau tanda hubung.',
    ], [
      'kode' => 'kode', 'nama_penerimaan' => 'nama penerimaan', 'kelompok' => 'kelompok',
    ]);
  }

  /** Jumlah baris jurnal (aktif + saldo) per id sumber dana. */
  private function jumlahPemakaian(): array
  {
    $jumlah = [];
    foreach (['ueu_tbl_jurnal_spm', 'ueu_tbl_jurnal_saldo'] as $tabel) {
      DB::table($tabel)->whereNotNull('id_sumber')->groupBy('id_sumber')
        ->selectRaw('id_sumber, COUNT(*) as n')->get()
        ->each(function ($r) use (&$jumlah) {
          $jumlah[$r->id_sumber] = ($jumlah[$r->id_sumber] ?? 0) + $r->n;
        });
    }

    return $jumlah;
  }

  /** Usulan kode berikutnya: angka terbesar + 1, dengan lebar 4 digit seperti data lama. */
  private function kodeBerikut($daftar): string
  {
    $maks = $daftar->pluck('kode')->filter(fn ($k) => ctype_digit((string) $k))->map(fn ($k) => (int) $k)->max() ?? 0;

    return str_pad((string) ($maks + 1), 4, '0', STR_PAD_LEFT);
  }

  /** Semua tabel ber-kolom id_sumber (termasuk arsip jurnal per tahun). */
  private function tabelPemakai(): array
  {
    return Cache::remember('sumberdana.tabel_pemakai', now()->addDay(), function () {
      return DB::table('information_schema.columns')
        ->where('table_schema', DB::getDatabaseName())
        ->where('column_name', 'id_sumber')
        ->where('table_name', 'not regexp', 'tmp|_tes|temp')
        ->orderByRaw("FIELD(table_name, 'ueu_tbl_jurnal_saldo', 'ueu_tbl_jurnal_spm') DESC") // tabel utama dulu
        ->selectRaw('table_name as t')
        ->pluck('t')
        ->all();
    });
  }
}
