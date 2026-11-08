<?php

namespace App\Http\Controllers\akuntansi;

use App\Exports\BukuBesarExport;
use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\BukuBesar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan Keuangan > Buku Besar (/akuntansi/buku-besar), padanan Akuntansi::bukubesar, bukubesar_datatable,
 * bukubesar_detail(_datatable) dan Download::bukubesar, bukubesarPembatasan, bukubesarAll v1.
 * Perhitungan ada di App\Services\BukuBesar.
 *
 *   index   : daftar akun ber-jurnal + panel detail (dimuat lewat AJAX seperti Lihat() v1; ?rek6=&sumber= membuka langsung).
 *   detail  : JSON baris buku besar satu akun (filter sumber & tanggal).
 *   excel   : .xlsx baris yang sama dengan detail.
 *   kelompok: .xlsx jurnal akun per kelompok sumber dana (Pembatasan / Tanpa Pembatasan), saldo jalan dari 0 seperti v1.
 *   semua   : CSV semua jurnal T.A (v1 .xlsx; di sini CSV ber-stream agar ±75 ribu baris tidak menghabiskan memori),
 *             saldo jalan per akun (v1 menjumlah lintas akun sehingga kolom saldonya tidak bermakna).
 */
class BukuBesarController extends Controller
{
  public function __construct(private BukuBesar $bb)
  {
  }

  public function index(Request $request)
  {
    $tahun = $this->tahun();
    [$pAwal, $pAkhir] = BukuBesar::periode($tahun);
    $pilih = $request->integer('rek6') ?: null;

    return view('content.akuntansi.buku-besar.index', [
      'tahun' => $tahun,
      'pAwal' => $pAwal,
      'pAkhir' => $pAkhir,
      'akun' => $this->bb->akun($tahun),
      'sumber' => DB::table('ueu_tbl_sumberdana')->whereNotNull('kode')->orderBy('kode')->get(['id', 'kode', 'nama_penerimaan', 'kelompok']),
      'awal' => [
        'rek6' => $pilih && $this->bb->satuAkun($pilih) ? $pilih : null,
        'sumber' => $request->integer('sumber') ?: null,
      ],
    ]);
  }

  /** JSON detail buku besar (bukubesar_detail_datatable v1). */
  public function detail(Request $request, int $idrek6)
  {
    $tahun = $this->tahun();
    $akun = $this->bb->satuAkun($idrek6) ?? abort(404);
    [$sumber, $dari, $sampai] = $this->filter($request);
    $baris = $this->bb->detail($tahun, $akun, $sumber, $dari, $sampai);
    $trx = $baris->where('jenis', 'trx');

    return response()->json([
      'akun' => ['id' => $akun->idrek6, 'kode' => $akun->kode, 'nama' => $akun->namarek6],
      'baris' => $baris->values(),
      'ringkas' => [
        'transaksi' => $trx->count(),
        'debet' => $trx->sum('debet'),
        'kredit' => $trx->sum('kredit'),
        'saldo' => (float) ($baris->last()->saldo ?? 0),
      ],
    ]);
  }

  /** Excel buku besar satu akun (Download::bukubesar v1). */
  public function excel(Request $request, int $idrek6)
  {
    $tahun = $this->tahun();
    $akun = $this->bb->satuAkun($idrek6) ?? abort(404);
    [$sumber, $dari, $sampai] = $this->filter($request);
    $no = 0;
    $baris = $this->bb->detail($tahun, $akun, $sumber, $dari, $sampai)
      ->map(fn ($b) => [++$no, $b->tgl, $b->bukti, $b->uraian, $b->debet, $b->kredit, $b->saldo])->all();

    return Excel::download(new BukuBesarExport(
      $this->judul($tahun, $akun, $sumber, $dari, $sampai),
      ['NO', 'TANGGAL', 'NO. BUKTI', 'URAIAN', 'DEBET', 'KREDIT', 'SALDO'],
      $baris, ['E', 'F', 'G'],
    ), 'LAP-BUKUBESAR-' . str_replace('.', '', $akun->kode) . '-' . $tahun . '.xlsx');
  }

  /** Excel per kelompok sumber dana (Download::bukubesarPembatasan v1). */
  public function kelompok(Request $request, int $idrek6)
  {
    $tahun = $this->tahun();
    $akun = $this->bb->satuAkun($idrek6) ?? abort(404);
    $kelompok = $request->validate(['kelompok' => ['required', Rule::in(['Pembatasan', 'Tanpa Pembatasan'])]])['kelompok'];

    $saldo = 0.0;
    $no = 0;
    $normalDebet = in_array((string) $akun->koderek1, ['1', '5'], true);
    $baris = $this->bb->transaksi($tahun, $idrek6)->join('ueu_tbl_sumberdana as d', 'd.id', '=', 'j.id_sumber')
      ->where('d.kelompok', $kelompok)->orderBy('j.tgl_trk')->orderBy('j.id_trans')
      ->get(['j.tgl_trk', 'j.bukti', 'j.uraian', 'd.nama_penerimaan', 'j.debet', 'j.kredit'])
      ->map(function ($r) use (&$saldo, &$no, $normalDebet) {
        $saldo += $normalDebet ? (float) $r->debet - (float) $r->kredit : (float) $r->kredit - (float) $r->debet;

        return [++$no, $r->tgl_trk, $r->bukti, $r->uraian, $r->nama_penerimaan, (float) $r->debet, (float) $r->kredit, $saldo];
      })->all();

    return Excel::download(new BukuBesarExport(
      [...$this->judul($tahun, $akun), 'KELOMPOK: ' . strtoupper($kelompok)],
      ['NO', 'TANGGAL', 'NO. BUKTI', 'URAIAN', 'SUMBER DANA', 'DEBET', 'KREDIT', 'SALDO'],
      $baris, ['F', 'G', 'H'],
    ), 'LAP-BUKUBESAR-' . str_replace('.', '', $akun->kode) . '-' . str_replace(' ', '', $kelompok) . '-' . $tahun . '.xlsx');
  }

  /** CSV semua jurnal T.A (Download::bukubesarAll v1), di-stream per potongan. */
  public function semua(): StreamedResponse
  {
    $tahun = $this->tahun();
    $nama = 'BUKUBESAR-SEMUA-' . $tahun . '-' . ($tahun + 1) . '.csv';

    return response()->streamDownload(function () use ($tahun) {
      $out = fopen('php://output', 'w');
      fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
      $tulis = fn (array $r) => fputcsv($out, $r, ';');
      $tulis(['UNIVERSITAS MUHAMMADIYAH METRO']);
      $tulis(['BUKU BESAR TAHUN ' . $tahun . '/' . ($tahun + 1)]);
      $tulis([]);
      $tulis(['NO', 'TANGGAL', 'KODE AKUN', 'NAMA AKUN', 'NO. BUKTI', 'URAIAN', 'DEBET', 'KREDIT', 'SALDO']);

      $no = 0;
      $saldo = 0.0;
      $akunLalu = null;
      $akun = $this->bb->rekening()->get()->keyBy('idrek6');
      $urut = $akun->keys()->flip();
      // Urut per akun (kode) lalu tanggal; saldo jalan diulang dari 0 di setiap akun.
      $idAkun = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->distinct()->pluck('idrek6')
        ->sortBy(fn ($id) => $urut[$id] ?? PHP_INT_MAX)->values();
      foreach ($idAkun as $id) {
        $a = $akun[$id] ?? null;
        $normalDebet = $a && in_array((string) $a->koderek1, ['1', '5'], true);
        $this->bb->transaksi($tahun, (int) $id)->orderBy('j.tgl_trk')->orderBy('j.id_trans')
          ->select('j.id_trans', 'j.tgl_trk', 'j.bukti', 'j.uraian', 'j.debet', 'j.kredit')
          ->chunk(2000, function ($rows) use (&$no, &$saldo, &$akunLalu, $id, $a, $normalDebet, $tulis) {
            foreach ($rows as $r) {
              if ($akunLalu !== $id) {
                $saldo = 0.0;
                $akunLalu = $id;
              }
              $saldo += $normalDebet ? (float) $r->debet - (float) $r->kredit : (float) $r->kredit - (float) $r->debet;
              $tulis([++$no, $r->tgl_trk, $a->kode ?? $id, $a->namarek6 ?? '', $r->bukti, $r->uraian, (float) $r->debet, (float) $r->kredit, $saldo]);
            }
          });
      }
      fclose($out);
    }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
  }

  /** [id_sumber|null, dari|null, sampai|null] dari query string; tanggal tidak valid diabaikan. */
  private function filter(Request $request): array
  {
    $tgl = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)
      && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
    $sumber = $request->integer('sumber') ?: null;
    if ($sumber && !DB::table('ueu_tbl_sumberdana')->where('id', $sumber)->exists()) {
      $sumber = null;
    }

    return [$sumber, $tgl($request->query('dari')), $tgl($request->query('sampai'))];
  }

  private function judul(int $tahun, object $akun, ?int $sumber = null, ?string $dari = null, ?string $sampai = null): array
  {
    $j = ['UNIVERSITAS MUHAMMADIYAH METRO', 'BUKU BESAR TAHUN ' . $tahun . '/' . ($tahun + 1), $akun->kode . ' - ' . strtoupper($akun->namarek6)];
    $ket = array_filter([
      $sumber ? 'Sumber dana: ' . DB::table('ueu_tbl_sumberdana')->where('id', $sumber)->value('nama_penerimaan') : null,
      ($dari || $sampai) ? 'Periode: ' . ($dari ?? '…') . ' s/d ' . ($sampai ?? '…') : null,
    ]);
    if ($ket) {
      $j[2] .= ' (' . implode(', ', $ket) . ')';
    }

    return $j;
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
