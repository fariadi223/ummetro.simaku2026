<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\RekeningLaporanUnit;
use Illuminate\Http\Request;

/**
 * Admin > Rekening Laporan Unit (/admin/rekening-laporan-unit):
 * aktif/nonaktifkan rekening yang tampil di Laporan Keuangan Unit (mis. Kas Universitas disembunyikan).
 */
class RekeningLaporanUnitController extends Controller
{
  public function __construct(private RekeningLaporanUnit $rl)
  {
  }

  public function index()
  {
    if (!$this->rl->siap()) {
      return view('content.admin.rekening-laporan-unit.belum-siap');
    }
    $daftar = $this->rl->daftar();

    return view('content.admin.rekening-laporan-unit.index', [
      'daftar' => $daftar,
      'jmlNonaktif' => count(array_filter($daftar, fn ($r) => !$r->aktif)),
    ]);
  }

  /** Ubah status satu rekening: aktif=1 tampil, aktif=0 disembunyikan. */
  public function atur(Request $request, int $idrek6)
  {
    abort_unless($this->rl->siap(), 404);
    $v = $request->validate([
      'aktif' => ['required', 'boolean'],
      'keterangan' => ['nullable', 'string', 'max:255'],
    ]);
    $akun = app(\App\Services\BukuBesar::class)->satuAkun($idrek6) ?? abort(404);
    $aktif = (bool) $v['aktif'];

    $this->rl->atur($idrek6, $aktif, $v['keterangan'] ?? null, $request->user()?->username);

    return back()->with('status', "{$akun->kode} {$akun->namarek6} " . ($aktif ? 'ditampilkan kembali' : 'disembunyikan') . ' di Laporan Keuangan Unit.');
  }
}
