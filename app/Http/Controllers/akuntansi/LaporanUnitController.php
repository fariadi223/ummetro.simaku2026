<?php

namespace App\Http\Controllers\akuntansi;

use App\Exports\BukuBesarExport;
use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\BukuBesar;
use App\Services\LaporanUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Laporan Keuangan > Laporan Keuangan Unit (/akuntansi/laporan-unit).
 * Unit di ueu_map_unit_prodi dikelompokkan ke induk FF.00 (01.02, 01.03 -> 01.00).
 *   index : rekap per unit + subtotal induk; klik baris untuk rincian rekening (AJAX).
 *   rinci : JSON neraca saldo per rekening untuk satu unit (?id_unit=) atau satu induk (?ff=).
 *   transaksi : JSON transaksi satu rekening (?idrek6=) dari rinci di atas, dimuat saat diklik.
 *   excel : rekap (dan rincian rekening per induk) ke .xlsx.
 *   cetak : HTML siap cetak / simpan PDF.
 */
class LaporanUnitController extends Controller
{
  /** Mode tampilan => kepala rekening yang dihitung (null = semua). */
  public const MODE = [
    'semua' => null,       // semua rekening: pendapatan, beban, surplus/(defisit)
    'neraca' => '1,2,3',   // hanya rekening neraca: 1 aset (kas/bank), 2 kewajiban, 3 aset neto
  ];

  /** Batas baris rincian transaksi per rekening (selebihnya lihat Buku Besar). */
  private const BATAS_TRANSAKSI = 1000;

  public function __construct(private LaporanUnit $lu)
  {
  }

  public function index(Request $request)
  {
    if (!$this->lu->siap()) {
      return view('content.akuntansi.laporan-unit.belum-siap');
    }
    $f = $this->filter($request);

    return view('content.akuntansi.laporan-unit.index', $f + [
      'grup' => $this->kelompok($this->lu->rekap($f['tahun'], $f['awal'], $f['akhir'], $f['kepala'])),
      'daftarTahun' => $this->lu->daftarTahun(),
      'rekNonaktif' => $this->lu->rekeningNonaktif(),
    ]);
  }

  /** JSON rincian rekening; tepat satu dari id_unit atau ff. */
  public function rinci(Request $request)
  {
    abort_unless($this->lu->siap(), 404);
    $v = $request->validate([
      'id_unit' => ['nullable', 'integer', 'min:1', 'required_without:ff'],
      'ff' => ['nullable', 'regex:/^\d{2}$/', 'required_without:id_unit'],
    ]);
    $f = $this->filter($request);
    $idUnit = isset($v['id_unit']) ? (int) $v['id_unit'] : null;
    $baris = $this->lu->rekening($f['tahun'], $f['awal'], $f['akhir'], $idUnit ? null : $v['ff'], $idUnit, $f['kepala']);

    return response()->json(['baris' => array_map(fn ($b) => [
      'idrek6' => (int) $b->idrek6,
      'kode_rek' => $b->kode_rek,
      'nama_rek' => $b->nama_rek,
      'saldo_normal' => $b->saldo_normal,
      'jml_transaksi' => (int) $b->jml_transaksi,
      'saldo_awal' => (float) $b->saldo_awal,
      'total_debet' => (float) $b->total_debet,
      'total_kredit' => (float) $b->total_kredit,
      'saldo_akhir' => (float) $b->saldo_akhir,
    ], $baris)]);
  }

  /**
   * JSON rincian transaksi satu rekening (dimuat saat baris rekening diklik).
   * Tepat satu dari id_unit atau ff, ditambah idrek6. Maksimal BATAS_TRANSAKSI baris.
   */
  public function transaksi(Request $request)
  {
    abort_unless($this->lu->siap(), 404);
    $v = $request->validate([
      'id_unit' => ['nullable', 'integer', 'min:1', 'required_without:ff'],
      'ff' => ['nullable', 'regex:/^\d{2}$/', 'required_without:id_unit'],
      'idrek6' => ['required', 'integer', 'min:1'],
    ]);
    $f = $this->filter($request);
    $idUnit = isset($v['id_unit']) ? (int) $v['id_unit'] : null;
    $baris = $this->lu->transaksi($f['tahun'], $f['awal'], $f['akhir'], $idUnit ? null : $v['ff'], $idUnit,
      (int) $v['idrek6'], self::BATAS_TRANSAKSI + 1, $f['kepala']);
    $terpotong = count($baris) > self::BATAS_TRANSAKSI;

    return response()->json([
      'terpotong' => $terpotong,
      'batas' => self::BATAS_TRANSAKSI,
      'baris' => array_map(fn ($b) => [
        'jenis' => (int) $b->jenis,
        'tanggal' => $b->tanggal,
        'bukti' => (string) $b->bukti,
        'uraian' => (string) $b->uraian,
        'kode_unit' => (string) $b->kode_unit,
        'debet' => $b->debet === null ? null : (float) $b->debet,
        'kredit' => $b->kredit === null ? null : (float) $b->kredit,
        'saldo' => (float) $b->saldo,
      ], array_slice($baris, 0, self::BATAS_TRANSAKSI)),
    ]);
  }

  public function excel(Request $request)
  {
    abort_unless($this->lu->siap(), 404);
    $f = $this->filter($request);
    $grup = $this->kelompok($this->lu->rekap($f['tahun'], $f['awal'], $f['akhir'], $f['kepala']));
    $neraca = $f['mode'] === 'neraca';
    // Kolom nilai: mode neraca = debet, kredit, mutasi bersih; mode semua = + pendapatan, beban, surplus.
    $nilai = fn ($d, $k, $p, $b) => $neraca ? [$d, $k, $d - $k] : [$d, $k, $p, $b, $p - $b];

    $isi = [];
    $tot = ['n' => 0, 'd' => 0.0, 'k' => 0.0, 'p' => 0.0, 'b' => 0.0];
    foreach ($grup as $g) {
      $isi[] = [$g['kode_induk'], $g['nama_induk'], '', '', $g['n'], ...$nilai($g['d'], $g['k'], $g['p'], $g['b'])];
      foreach ($g['unit'] as $u) {
        $isi[] = ['   ' . $u->kode, '   ' . $u->nama, ($u->jenis === 'fakultas' ? ($u->nama_fakultas ?: 'Fakultas') : trim($u->kode_prodi . ' ' . $u->jenjang_didik . ' ' . $u->nama_prodi)), $u->kode_npm,
          (int) $u->jml_transaksi, ...$nilai((float) $u->total_debet, (float) $u->total_kredit, (float) $u->pendapatan, (float) $u->beban)];
      }
      foreach (['n', 'd', 'k', 'p', 'b'] as $k) {
        $tot[$k] += $g[$k];
      }
    }
    $isi[] = ['TOTAL', '', '', '', $tot['n'], ...$nilai($tot['d'], $tot['k'], $tot['p'], $tot['b'])];

    $kepala = ['KODE', 'UNIT', 'PRODI', 'KODE NPM', 'TRX', 'DEBET', 'KREDIT',
      ...($neraca ? ['MUTASI BERSIH (D-K)'] : ['PENDAPATAN', 'BEBAN', 'SURPLUS/(DEFISIT)'])];

    return Excel::download(new BukuBesarExport($this->judul($f), $kepala,
      $isi, $neraca ? ['F', 'G', 'H'] : ['F', 'G', 'H', 'I', 'J'], 'Laporan Unit'),
      'LAP-KEUANGAN-UNIT-' . ($neraca ? 'NERACA-' : '') . str_replace('-', '', $f['awal']) . '-' . str_replace('-', '', $f['akhir']) . '.xlsx');
  }

  public function cetak(Request $request)
  {
    abort_unless($this->lu->siap(), 404);
    $f = $this->filter($request);
    $grup = $this->kelompok($this->lu->rekap($f['tahun'], $f['awal'], $f['akhir'], $f['kepala']));
    // Rincian rekening per induk ikut dicetak bila ?rinci=1.
    $rinci = [];
    if ($request->boolean('rinci')) {
      foreach ($grup as $ff => $g) {
        $rinci[$ff] = $this->lu->rekening($f['tahun'], $f['awal'], $f['akhir'], sprintf('%02d', (int) $ff), null, $f['kepala']);
      }
    }

    return view('content.akuntansi.laporan-unit.cetak', $f + [
      'grup' => $grup,
      'rinci' => $rinci,
      'judul' => $this->judul($f),
      'ttd' => config('simaku.laporan_ttd', []),
    ]);
  }

  /**
   * Kelompokkan baris rekap per FF: [ff => [kode_induk, nama_induk, unit[], n, d, k, p, b]].
   * Urutan dari SQL (FF, kode) dipertahankan.
   */
  private function kelompok(array $baris): array
  {
    $g = [];
    foreach ($baris as $b) {
      $ff = (string) $b->ff;
      $g[$ff] ??= ['kode_induk' => $b->kode_induk, 'nama_induk' => $b->nama_induk, 'unit' => [], 'n' => 0, 'd' => 0.0, 'k' => 0.0, 'p' => 0.0, 'b' => 0.0];
      $g[$ff]['unit'][] = $b;
      $g[$ff]['n'] += (int) $b->jml_transaksi;
      $g[$ff]['d'] += (float) $b->total_debet;
      $g[$ff]['k'] += (float) $b->total_kredit;
      $g[$ff]['p'] += (float) $b->pendapatan;
      $g[$ff]['b'] += (float) $b->beban;
    }

    return $g;
  }

  /** tahun, awal, akhir, mode & kepala (divalidasi; default T.A aktif, mode semua). */
  private function filter(Request $request): array
  {
    $v = $request->validate([
      'tahun' => ['nullable', 'integer', 'between:2000,2100'],
      'awal' => ['nullable', 'date_format:Y-m-d'],
      'akhir' => ['nullable', 'date_format:Y-m-d'],
      'mode' => ['nullable', 'in:' . implode(',', array_keys(self::MODE))],
    ]);
    $mode = $v['mode'] ?? 'semua';
    $tahun = (int) ($v['tahun'] ?? (session('periode') ?: Info::tahun(['penjurnalan' => 'open'])));
    [$pAwal, $pAkhir] = BukuBesar::periode($tahun);
    $awal = $v['awal'] ?? $pAwal;
    $akhir = $v['akhir'] ?? $pAkhir;
    if ($awal > $akhir) {
      [$awal, $akhir] = [$akhir, $awal];
    }

    return ['tahun' => $tahun, 'awal' => $awal, 'akhir' => $akhir, 'pAwal' => $pAwal, 'pAkhir' => $pAkhir,
      'mode' => $mode, 'kepala' => self::MODE[$mode]];
  }

  private function judul(array $f): array
  {
    $tgl = fn ($t) => Carbon::parse($t)->format('d-m-Y');

    return [
      'UNIVERSITAS MUHAMMADIYAH METRO',
      'LAPORAN KEUANGAN UNIT' . ($f['mode'] === 'neraca' ? ' (REKENING NERACA: KEPALA 1, 2, 3)' : '') . ' · T.A ' . $f['tahun'] . '/' . ($f['tahun'] + 1),
      'Periode ' . $tgl($f['awal']) . ' s/d ' . $tgl($f['akhir']) . ' · unit terpetakan, dikelompokkan ke unit induk FF.00',
    ];
  }
}
