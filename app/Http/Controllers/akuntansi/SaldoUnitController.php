<?php

namespace App\Http\Controllers\akuntansi;

use App\Exports\BukuBesarExport;
use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\BukuBesar;
use App\Services\SaldoUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Saldo anggaran unit (/akuntansi/saldo-unit), padanan Akuntansi::daftarsaldo_unit v1.
 *
 *   Saldo = Σ debet − Σ kredit ueu_tbl_jurnal_spm, idrek6 570 (Kas di Unit Kerja), per kodeunit
 *           (= kode unit induk "FF") pada T.A — sama dengan v1 bila periode dikosongkan.
 *   v2: dikelompokkan seperti Laporan Keuangan Unit (induk FF.00 + sub-unit per id_uk), filter periode,
 *       rincian transaksi dimuat saat baris diklik (transaksi), ekspor Excel.
 *   Rincian kredit: realisasi (idrinci > 0), setoran ("setor kas dari unit …"), kredit lainnya.
 *   Jurnal lama tanpa unit (kodeunit 0) masuk 00 (Rektorat) seperti v1, sebagai sub-baris "(Jurnal tanpa unit)".
 */
class SaldoUnitController extends Controller
{
  private const BATAS_TRANSAKSI = 1000;

  public function __construct(private SaldoUnit $su)
  {
  }

  public function index(Request $request)
  {
    $f = $this->filter($request);

    return view('content.akuntansi.saldo-unit.index', $f + [
      'grup' => $this->su->rekap($f['tahun'], $f['awal'], $f['akhir']),
      'daftarTahun' => $this->su->daftarTahun(),
    ]);
  }

  /** JSON transaksi Kas di Unit Kerja untuk satu induk (?ff=) atau satu sub-unit (?ff=&id_uk=). */
  public function transaksi(Request $request)
  {
    $v = $request->validate([
      'ff' => ['required', 'regex:/^\d{2}$/'],
      'id_uk' => ['nullable', 'integer', 'min:0'],
    ]);
    $f = $this->filter($request);
    $baris = $this->su->transaksi($f['tahun'], $f['awal'], $f['akhir'], $v['ff'],
      isset($v['id_uk']) ? (int) $v['id_uk'] : null, self::BATAS_TRANSAKSI + 1);

    return response()->json([
      'terpotong' => count($baris) > self::BATAS_TRANSAKSI,
      'batas' => self::BATAS_TRANSAKSI,
      'baris' => array_map(fn ($b) => [
        'jenis' => (int) $b->jenis,
        'tanggal' => $b->tanggal,
        'bukti' => (string) $b->bukti,
        'uraian' => (string) $b->uraian,
        'jenis_kredit' => (string) $b->jenis_kredit,
        'kode_unit' => (string) $b->kode_unit,
        'debet' => $b->debet === null ? null : (float) $b->debet,
        'kredit' => $b->kredit === null ? null : (float) $b->kredit,
        'saldo' => (float) $b->saldo,
      ], array_slice($baris, 0, self::BATAS_TRANSAKSI)),
    ]);
  }

  public function excel(Request $request)
  {
    $f = $this->filter($request);
    $kol = ['saldo_awal', 'diterima', 'realisasi', 'disetor', 'lainnya', 'saldo'];
    $isi = [];
    $tot = array_fill_keys([...$kol, 'jml_transaksi'], 0.0);
    foreach ($this->su->rekap($f['tahun'], $f['awal'], $f['akhir']) as $g) {
      $isi[] = [$g['kode_induk'], $g['nama_induk'], $g['jml_transaksi'], ...array_map(fn ($k) => $g[$k], $kol)];
      foreach ($g['unit'] as $u) {
        $isi[] = ['   ' . ($u->kode ?? '-'), '   ' . $u->nama, $u->jml_transaksi, ...array_map(fn ($k) => $u->$k, $kol)];
      }
      foreach (array_keys($tot) as $k) {
        $tot[$k] += $g[$k];
      }
    }
    $isi[] = ['TOTAL', '', $tot['jml_transaksi'], ...array_map(fn ($k) => $tot[$k], $kol)];

    return Excel::download(new BukuBesarExport($this->judul($f),
      ['KODE', 'UNIT KERJA', 'TRX', 'SALDO AWAL', 'DITERIMA', 'REALISASI', 'DISETOR', 'KREDIT LAINNYA', 'SALDO'],
      $isi, ['D', 'E', 'F', 'G', 'H', 'I'], 'Saldo Unit'),
      'SALDO-ANGGARAN-UNIT-' . $f['tahun'] . ($f['awal'] || $f['akhir'] ? '-' . str_replace('-', '', $f['awal'] ?? $f['pAwal']) . '-' . str_replace('-', '', $f['akhir'] ?? $f['pAkhir']) : '') . '.xlsx');
  }

  /** tahun (default T.A login) + periode opsional (kosong = seluruh jurnal T.A, seperti v1). */
  private function filter(Request $request): array
  {
    $v = $request->validate([
      'tahun' => ['nullable', 'integer', 'between:2000,2100'],
      'awal' => ['nullable', 'date_format:Y-m-d'],
      'akhir' => ['nullable', 'date_format:Y-m-d'],
    ]);
    $tahun = (int) ($v['tahun'] ?? (session('periode') ?: Info::tahun(['penjurnalan' => 'open'])));
    [$pAwal, $pAkhir] = BukuBesar::periode($tahun);
    $awal = $v['awal'] ?? null;
    $akhir = $v['akhir'] ?? null;
    if ($awal && $akhir && $awal > $akhir) {
      [$awal, $akhir] = [$akhir, $awal];
    }

    return ['tahun' => $tahun, 'awal' => $awal, 'akhir' => $akhir, 'pAwal' => $pAwal, 'pAkhir' => $pAkhir];
  }

  private function judul(array $f): array
  {
    $tgl = fn ($t) => Carbon::parse($t)->format('d-m-Y');
    $periode = $f['awal'] || $f['akhir']
      ? 'Periode ' . ($f['awal'] ? $tgl($f['awal']) : 'awal') . ' s/d ' . ($f['akhir'] ? $tgl($f['akhir']) : 'akhir')
      : 'Seluruh jurnal T.A';

    return ['UNIVERSITAS MUHAMMADIYAH METRO', 'SALDO ANGGARAN UNIT (KAS DI UNIT KERJA) · T.A ' . $f['tahun'] . '/' . ($f['tahun'] + 1),
      $periode . ' · dikelompokkan ke unit induk FF.00'];
  }
}
