<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\PemetaanUnit;
use Illuminate\Http\Request;

/**
 * Kertas Kerja > Pemetaan Unit (/akuntansi/pemetaan-unit): unit kerja -> prodi atau fakultas (ueu_map_unit_prodi.jenis).
 * Unit pimpinan (Dekan / Wakil Dekan / Direktur, mis. 01.01) diusulkan ke fakultas induknya.
 * Satu form (modal) untuk tambah & ubah karena id_unit unik. Dipakai Laporan Keuangan Unit.
 */
class PemetaanUnitController extends Controller
{
  public function __construct(private PemetaanUnit $pu)
  {
  }

  public function index(Request $request)
  {
    if (!$this->pu->siap()) {
      return view('content.akuntansi.laporan-unit.belum-siap');
    }
    $semua = $request->query('tahun') === 'semua';
    $tahun = $semua ? null : ($request->integer('tahun') ?: (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open'])));

    return view('content.akuntansi.pemetaan-unit.index', [
      'tahun' => $tahun,
      'daftarTahun' => $this->pu->daftarTahun(),
      'belum' => $this->pu->belumDipetakan($tahun),
      'pemetaan' => $this->pu->daftar(),
      'daftarUnit' => $this->pu->daftarUnit(),
      'daftarProdi' => $this->pu->daftarProdi(),
      'daftarFakultas' => $this->pu->daftarFakultas(),
      'adaFakultas' => $this->pu->adaFakultas(),
      'jmlProdi' => $this->pu->jumlahProdi(),
      'adaKodeNpm' => $this->pu->adaKodeNpm(),
    ]);
  }

  public function simpan(Request $request)
  {
    abort_unless($this->pu->siap(), 404);
    $fakultas = $this->pu->adaFakultas();
    $v = $request->validate([
      'id_unit' => ['required', 'integer', 'exists:ueu_tbl_unit,id_unit'],
      'jenis' => ['nullable', 'in:' . ($fakultas ? 'prodi,fakultas' : 'prodi')],
      'id_sms' => ['nullable', 'required_unless:jenis,fakultas', 'string', 'max:36', 'exists:ueu_ref_prodi,id_sms'],
      'kode_fak' => array_merge(['nullable', 'required_if:jenis,fakultas', 'string', 'max:4'], $fakultas ? ['exists:ueu_ref_fakultas,kode_fak'] : []),
      'kode_npm' => ['nullable', 'string', 'max:10', 'regex:/^[0-9A-Za-z]+$/'],
      'berlaku_dari' => ['nullable', 'date_format:Y-m-d'],
    ], [
      'id_unit.required' => 'Pilih unit kerja.',
      'id_unit.exists' => 'Unit kerja tidak ditemukan.',
      'id_sms.required_unless' => 'Pilih prodi.',
      'kode_fak.required_if' => 'Pilih fakultas.',
      'kode_fak.exists' => 'Fakultas tidak ada di referensi (jalankan php artisan simaku:sinkron-prodi).',
      'jenis.in' => 'Jenis pemetaan tidak dikenal.',
      'id_sms.exists' => 'Prodi tidak ada di referensi (jalankan php artisan simaku:sinkron-prodi).',
      'kode_npm.regex' => 'Kode NPM hanya boleh berisi huruf dan angka.',
      'berlaku_dari.date_format' => 'Format tanggal berlaku harus YYYY-MM-DD.',
    ]);

    $jenis = $v['jenis'] ?? 'prodi';
    $baru = $this->pu->simpan((int) $v['id_unit'], $jenis, $v['id_sms'] ?? null, $v['kode_fak'] ?? null,
      $v['kode_npm'] ?? null, $v['berlaku_dari'] ?? null);

    return back()->with('status', 'Pemetaan unit ' . $v['id_unit'] . ' ke ' . $jenis . ($baru ? ' ditambahkan.' : ' diperbarui.'));
  }

  public function hapus(int $id)
  {
    abort_unless($this->pu->siap(), 404);
    abort_unless($this->pu->hapus($id) > 0, 404);

    return back()->with('status', 'Pemetaan dihapus; unit tersebut tidak lagi masuk Laporan Keuangan Unit.');
  }
}
