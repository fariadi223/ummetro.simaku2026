<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Info;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Setting > Periode: padanan Admin::periode (pilih tahun anggaran yang
 * dikerjakan, disimpan di session) dan Admin::jadwal* (jadwal penganggaran
 * & komite di ueu_tbl_info) di v1.
 *
 * Tambahan v2: buka/tutup penjurnalan T.A di luar periode berjalan (kolom jurnal_dibuka, lihat
 * database/sql/2026_11_09_ueu_tbl_info_jurnal_dibuka.sql). Periode berjalan (penjurnalan = 'open')
 * tidak diubah di sini karena kolom itu dipakai v1 (login, laporan, tutup buku).
 */
class PeriodeController extends Controller
{
  public function index()
  {
    $daftar = Info::orderByDesc('th_angg')->get();
    $sekarang = Carbon::today();

    $daftar->each(function ($i) use ($sekarang) {
      $i->tgl = [
        'awal_angg' => $this->tanggal($i->awal_angg),
        'akhir_angg' => $this->tanggal($i->akhir_angg),
        'awal_komite' => $this->tanggal($i->awal_komite),
        'akhir_komite' => $this->tanggal($i->akhir_komite),
      ];
      // Sama dengan Simaku::aktive di v1: komite masih buka selama akhir_komite belum lewat.
      $i->komite_buka = $i->tgl['akhir_komite'] && $sekarang->lte($i->tgl['akhir_komite']);
    });

    return view('content.admin.periode.index', [
      'daftar' => $daftar,
      'periode' => (int) session('periode'),
      'adaJurnalDibuka' => Info::adaKolomJurnalDibuka(),
    ]);
  }

  /** Ganti tahun anggaran yang sedang dikerjakan (session), seperti v1. */
  public function pilih(Request $request)
  {
    $data = $request->validate(['tahun' => ['required', 'integer', 'exists:ueu_tbl_info,th_angg']], [
      'tahun.required' => 'Pilih tahun anggaran terlebih dahulu.',
      'tahun.integer' => 'Tahun anggaran yang dipilih tidak tersedia.',
      'tahun.exists' => 'Tahun anggaran yang dipilih tidak tersedia.',
    ]);
    $tahun = (int) $data['tahun'];

    session(['periode' => $tahun]);

    return back()->with('status', "Tahun anggaran yang dipilih {$tahun}/" . ($tahun + 1) . '.');
  }

  /** Ubah jadwal penganggaran & komite (padanan jadwal_proses di v1). */
  public function jadwal(Request $request, Info $info)
  {
    $data = $request->validate([
      'awal_angg' => ['required', 'date'],
      'akhir_angg' => ['required', 'date', 'after_or_equal:awal_angg'],
      'awal_komite' => ['required', 'date'],
      'akhir_komite' => ['required', 'date', 'after_or_equal:awal_komite'],
    ], [], [
      'awal_angg' => 'tanggal awal anggaran', 'akhir_angg' => 'tanggal akhir anggaran',
      'awal_komite' => 'tanggal awal komite', 'akhir_komite' => 'tanggal akhir komite',
    ]);

    // Format penyimpanan mengikuti v1: awal "d F Y" (mis. 14 August 2026), akhir "d-m-Y".
    $info->update([
      'awal_angg' => Carbon::parse($data['awal_angg'])->format('d F Y'),
      'akhir_angg' => Carbon::parse($data['akhir_angg'])->format('d-m-Y'),
      'awal_komite' => Carbon::parse($data['awal_komite'])->format('d F Y'),
      'akhir_komite' => Carbon::parse($data['akhir_komite'])->format('d-m-Y'),
    ]);

    return back()->with('status', "Jadwal T.A {$info->th_angg}/" . ($info->th_angg + 1) . ' berhasil diperbarui.');
  }

  /** Buka (dibuka=1) / tutup (dibuka=0) penjurnalan T.A di luar periode berjalan. */
  public function jurnal(Request $request, Info $info)
  {
    $ta = "T.A {$info->th_angg}/" . ($info->th_angg + 1);

    if (!Info::adaKolomJurnalDibuka()) {
      return back()->with('error', 'Kolom jurnal_dibuka belum ada di ueu_tbl_info. Jalankan database/sql/2026_11_09_ueu_tbl_info_jurnal_dibuka.sql terlebih dahulu.');
    }
    if ($info->penjurnalan === 'open') {
      return back()->with('error', "{$ta} adalah periode berjalan; penjurnalannya selalu dibuka.");
    }

    $dibuka = $request->validate(['dibuka' => ['required', 'boolean']])['dibuka'];
    $info->update(['jurnal_dibuka' => $dibuka ? 'ya' : 'tidak']);

    return back()->with('status', $dibuka
      ? "Penjurnalan {$ta} dibuka. Akuntansi dapat menginput jurnal umum & setoran untuk T.A ini."
      : "Penjurnalan {$ta} ditutup kembali.");
  }

  /** Tanggal lama tersimpan sebagai teks ("14 August 2026" atau "15-09-2027"). */
  private function tanggal(?string $teks): ?Carbon
  {
    if (!$teks) {
      return null;
    }

    try {
      return Carbon::parse($teks)->startOfDay();
    } catch (\Throwable) {
      return null;
    }
  }
}
