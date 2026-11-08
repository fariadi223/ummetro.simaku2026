<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Saldo anggaran unit (/akuntansi/saldo-unit), padanan Akuntansi::daftarsaldo_unit v1.
 *
 *   Saldo = Σ debet − Σ kredit ueu_tbl_jurnal_spm, idrek6 570 (Kas di Unit Kerja), per kodeunit
 *           (= kode unit induk "FF" sebagai angka) pada T.A login — sama dengan v1.
 *   Rincian kredit: realisasi anggaran (idrinci > 0), setoran ke kas universitas
 *           (uraian "setor kas dari unit …"), dan kredit lainnya.
 *
 * Catatan data lama: jurnal umum v1 tidak menyimpan unit, sehingga barisnya ber-kodeunit 0 dan
 * ikut terhitung ke unit "00" (Rektorat) — seperti di v1. Jumlahnya ditampilkan terpisah sebagai peringatan.
 */
class SaldoUnitController extends Controller
{
  public function index()
  {
    $tahun = $this->tahun();
    $induk = Unit::where('kode', 'like', '%.00')->get(['id_unit', 'kode', 'nama'])
      ->keyBy(fn ($u) => explode('.', $u->kode)[0]);

    $setor = "uraian LIKE 'setor kas dari unit%'";
    $daftar = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->where('idrek6', PenyetoranController::KAS_UNIT)
      ->groupBy('kodeunit')
      ->selectRaw("kodeunit,
        COALESCE(SUM(debet), 0) as diterima,
        COALESCE(SUM(CASE WHEN idrinci > 0 THEN kredit ELSE 0 END), 0) as realisasi,
        COALESCE(SUM(CASE WHEN idrinci = 0 AND $setor THEN kredit ELSE 0 END), 0) as disetor,
        COALESCE(SUM(CASE WHEN idrinci = 0 AND NOT ($setor) THEN kredit ELSE 0 END), 0) as lainnya,
        COALESCE(SUM(CASE WHEN id_uk = 0 AND NOT ($setor) THEN debet - kredit ELSE 0 END), 0) as tanpa_unit")
      ->get()
      ->map(function ($r) use ($induk) {
        $r->ff = str_pad((string) $r->kodeunit, 2, '0', STR_PAD_LEFT);
        $r->unit = $induk[$r->ff] ?? null;
        foreach (['diterima', 'realisasi', 'disetor', 'lainnya', 'tanpa_unit'] as $k) {
          $r->$k = (float) $r->$k;
        }
        $r->saldo = $r->diterima - $r->realisasi - $r->disetor - $r->lainnya;

        return $r;
      })
      ->filter(fn ($r) => $r->unit)
      ->sortBy('ff')->values();

    return view('content.akuntansi.saldo-unit.index', [
      'tahun' => $tahun,
      'daftar' => $daftar,
      'total' => collect(['diterima', 'realisasi', 'disetor', 'lainnya', 'saldo'])->mapWithKeys(fn ($k) => [$k => $daftar->sum($k)])->all(),
    ]);
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
