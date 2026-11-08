<?php

namespace App\Http\Controllers\unit;

use App\Http\Controllers\Controller;
use App\Models\Info;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Pengaturan > Periode Anggaran untuk unit: mengganti tahun anggaran yang
 * sedang dikerjakan (session 'periode'), padanan Unit::periode di v1.
 * Jadwal hanya ditampilkan; pengaturannya oleh admin (Setting > Periode).
 */
class PeriodeController extends Controller
{
  /** Nama route halaman ini & URL beranda peran (diganti oleh subclass, mis. kasir). */
  protected string $rute = 'unit.periode';
  protected string $beranda = 'unit';

  public function index()
  {
    $hariIni = Carbon::today();
    $tgl = function (?string $teks): ?Carbon {
      try {
        return $teks ? Carbon::parse($teks)->startOfDay() : null;
      } catch (\Throwable) {
        return null;
      }
    };

    $daftar = Info::orderByDesc('th_angg')->get()->each(function ($i) use ($tgl, $hariIni) {
      $i->awal = $tgl($i->awal_angg);
      $i->akhir = $tgl($i->akhir_angg);
      $i->akhir_komite_tgl = $tgl($i->akhir_komite);
      $i->komite_buka = $i->akhir_komite_tgl && $hariIni->lte($i->akhir_komite_tgl);
    });

    return view('content.unit.periode', [
      'daftar' => $daftar,
      'periode' => (int) session('periode'),
      'aksi' => route($this->rute . '.pilih'),
      'batal' => url($this->beranda),
    ]);
  }

  public function pilih(Request $request)
  {
    $data = $request->validate(['tahun' => ['required', 'integer', 'exists:ueu_tbl_info,th_angg']], [
      'tahun.required' => 'Pilih tahun anggaran terlebih dahulu.',
      'tahun.integer' => 'Tahun anggaran yang dipilih tidak tersedia.',
      'tahun.exists' => 'Tahun anggaran yang dipilih tidak tersedia.',
    ]);
    $tahun = (int) $data['tahun'];

    session(['periode' => $tahun]);

    return redirect()->route($this->rute)->with('status', "Tahun anggaran yang dipilih {$tahun}/" . ($tahun + 1) . '.');
  }
}
