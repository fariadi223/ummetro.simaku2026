<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Sumberdana;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Penyetoran kas unit ke kas universitas (/akuntansi/penyetoran), padanan Akuntansi::penyetoran,
 * penyetoran_input, penyetoran_proses, penyetoran_simpan, jurnalsetoran v1.
 *
 *   Saldo kas unit = Σ debet − Σ kredit ueu_tbl_jurnal_spm, idrek6 570 (Kas di Unit Kerja),
 *                    per kodeunit (= kode unit induk "FF" sebagai angka) pada T.A login.
 *   Setor          = dua baris jurnal, uraian "setor kas dari unit <nama>":
 *                      debet  rekening pilihan (default 569 Kas Universitas)
 *                      kredit 570 Kas di Unit Kerja
 *
 * Perbedaan dari v1: input bukti & isian digabung dalam satu form, rekening debet tidak boleh
 * Kas di Unit Kerja itu sendiri, dan kedua baris jurnal disimpan dalam satu transaksi.
 * Setoran hanya pada periode berjalan atau T.A yang penjurnalannya dibuka admin (Info::jurnalTerbuka); lainnya: form dikunci
 * & simpan ditolak.
 */
class PenyetoranController extends Controller
{
  public const KAS_UNIV = 569;
  public const KAS_UNIT = 570;

  public function index()
  {
    $tahun = $this->tahun();
    $induk = $this->indukPerKode();

    $saldo = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->where('idrek6', self::KAS_UNIT)
      ->groupBy('kodeunit')->selectRaw('kodeunit, COALESCE(SUM(debet), 0) as debet, COALESCE(SUM(kredit), 0) as kredit')
      ->get()
      ->map(function ($r) use ($induk) {
        $ff = str_pad((string) $r->kodeunit, 2, '0', STR_PAD_LEFT);
        $r->ff = $ff;
        $r->unit = $induk[$ff] ?? null;
        $r->saldo = (float) $r->debet - (float) $r->kredit;

        return $r;
      })
      ->filter(fn ($r) => $r->unit)
      ->sortBy('ff')->values();

    return view('content.akuntansi.penyetoran.index', [
      'tahun' => $tahun,
      'daftar' => $saldo,
      'total' => [
        'debet' => $saldo->sum('debet'),
        'kredit' => $saldo->sum('kredit'),
        'saldo' => $saldo->sum('saldo'),
      ],
    ]);
  }

  /** Form setor untuk unit induk {ff} (penyetoran_input + penyetoran_proses v1). */
  public function create(string $ff)
  {
    $tahun = $this->tahun();
    $unit = $this->unitAtau404($ff);

    return view('content.akuntansi.penyetoran.form', [
      'tahun' => $tahun,
      'unit' => $unit,
      'ff' => $ff,
      'terbuka' => Info::jurnalTerbuka($tahun),
      'tahunBerjalan' => Info::tahunPenjurnalan(),
      'saldo' => $this->saldo($tahun, $ff),
      'rekening' => $this->rekening(),
      'sumber' => Sumberdana::whereNotNull('kode')->orderBy('kode')->get(['id', 'kode', 'nama_penerimaan']),
      'riwayat' => DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->where('idrek6', self::KAS_UNIT)
        ->where('kodeunit', (int) $ff)->where('kredit', '>', 0)->where('uraian', 'like', 'setor kas dari unit%')
        ->orderByDesc('tgl_trk')->orderByDesc('id_trans')->limit(10)->get(['id_trans', 'tgl_trk', 'bukti', 'kredit']),
    ]);
  }

  /** Simpan setoran (penyetoran_simpan + jurnalsetoran v1). */
  public function store(Request $request, string $ff)
  {
    $tahun = $this->tahun();
    $unit = $this->unitAtau404($ff);

    if (!Info::jurnalTerbuka($tahun)) {
      return redirect()->route('akuntansi.penyetoran.create', $ff)
        ->with('error', "Periode T.A {$tahun}/" . ($tahun + 1) . ' bukan periode berjalan dan penjurnalannya tidak dibuka. Setoran tidak disimpan.');
    }

    $request->merge(['nominal' => ($v = preg_replace('/\D/', '', (string) $request->input('nominal'))) === '' ? null : (int) $v]);
    $data = $request->validate([
      'bukti' => ['required', 'string', 'max:100'],
      'tgl_trk' => ['required', 'date_format:Y-m-d'],
      'nominal' => ['required', 'integer', 'min:1', 'max:999999999999'],
      'debet' => ['required', 'integer', 'not_in:' . self::KAS_UNIT, Rule::exists('ueu_tbl_rek6', 'idrek6')],
      'id_sumber' => ['required', 'integer', Rule::exists('ueu_tbl_sumberdana', 'id')],
    ], [
      'debet.not_in' => 'Rekening yang di-debet tidak boleh Kas di Unit Kerja.',
    ], [
      'bukti' => 'No. Bukti Transaksi', 'tgl_trk' => 'Tanggal', 'nominal' => 'Besar uang',
      'debet' => 'Rekening yang di-debet', 'id_sumber' => 'Sumber Dana',
    ]);

    // Kolom NOT NULL tanpa default pada tabel lama (v1 berjalan non-strict) diisi eksplisit.
    $baris = fn (int $idrek6, int $debet, int $kredit) => [
      'tahun' => (string) $tahun,
      'id_uk' => 0,
      'kodeunit' => (int) $ff,
      'idkeg' => 0,
      'idtama' => 0,
      'id_sumber' => (int) $data['id_sumber'],
      'idrek6' => $idrek6,
      'idrinci' => 0,
      'idrinci_na' => 0,
      'tgl_trk' => $data['tgl_trk'],
      'uraian' => 'setor kas dari unit ' . $unit->nama,
      'bukti' => trim($data['bukti']),
      'debet' => $debet,
      'kredit' => $kredit,
      'ket' => '',
      'tgl_trans' => '',
    ];

    DB::transaction(function () use ($baris, $data) {
      DB::table('ueu_tbl_jurnal_spm')->insert([
        $baris((int) $data['debet'], $data['nominal'], 0),
        $baris(self::KAS_UNIT, 0, $data['nominal']),
      ]);
    });

    return redirect()->route('akuntansi.penyetoran.index')->with('status',
      'Setoran kas ' . $unit->nama . ' sebesar Rp ' . number_format($data['nominal'], 0, ',', '.') . ' dijurnal (bukti ' . trim($data['bukti']) . ').');
  }

  /** Saldo Kas di Unit Kerja untuk kode induk {ff} pada T.A. */
  private function saldo(int $tahun, string $ff): float
  {
    $r = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->where('idrek6', self::KAS_UNIT)
      ->where('kodeunit', (int) $ff)->selectRaw('COALESCE(SUM(debet), 0) - COALESCE(SUM(kredit), 0) as saldo')->first();

    return (float) ($r->saldo ?? 0);
  }

  /** Semua rekening level 6 berkode lengkap (Mrek::get_rek_all v1). */
  private function rekening()
  {
    return DB::table('ueu_tbl_rek6 as r6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')
      ->orderBy('r4.koderek4')->orderBy('r5.koderek5')->orderBy('r6.koderek6')
      ->selectRaw('r6.idrek6, r6.namarek6, CONCAT(r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode, r1.namarek1')
      ->get();
  }

  /** Unit induk "FF.00" diindeks dengan "FF". */
  private function indukPerKode()
  {
    return Unit::where('kode', 'like', '%.00')->get(['id_unit', 'kode', 'nama'])
      ->keyBy(fn ($u) => explode('.', $u->kode)[0]);
  }

  private function unitAtau404(string $ff): Unit
  {
    abort_unless(preg_match('/^\d{2}$/', $ff), 404);

    return Unit::where('kode', $ff . '.00')->first() ?? abort(404);
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
