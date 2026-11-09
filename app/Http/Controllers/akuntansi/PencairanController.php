<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Unit;
use App\Services\RealisasiSpm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pencairan anggaran belanja (/akuntansi/pencairan), padanan Akuntansi::spm_real & spm_jurnal v1.
 *
 *   Daftar  = baris ueu_tbl_spp (satu kegiatan) dengan status_spm Disetujui, jurnalspm kosong,
 *             pada T.A login, yang SPM-nya sudah dibayar kasir (ada STTK di ueu_tbl_kasir).
 *   Jurnal  = dua baris ueu_tbl_jurnal_spm, uraian "transfer ke unit <nama unit induk>":
 *               debet  570 Kas di Unit Kerja   (id_uk = unit kegiatan, kodeunit = FF induk)
 *               kredit 569 Kas Universitas
 *             lalu ueu_tbl_spp.jurnalspm = 'CLK'.
 *
 * Perbedaan dari v1: dikirim lewat POST ber-CSRF (v1 GET dengan parameter di URL), nilai diambil
 * dari database bukan dari URL, tidak bisa dijurnal dua kali, dan insert + update dalam satu transaksi.
 * Jurnal hanya pada periode berjalan atau T.A yang penjurnalannya dibuka admin (Info::jurnalTerbuka):
 * selain itu tombol Jurnalkan dinonaktifkan & simpan ditolak.
 */
class PencairanController extends Controller
{
  public const KAS_UNIV = PenyetoranController::KAS_UNIV;
  public const KAS_UNIT = PenyetoranController::KAS_UNIT;

  public function __construct(private RealisasiSpm $realisasi)
  {
  }

  public function index()
  {
    $tahun = $this->tahun();
    $daftar = $this->belumDijurnal($tahun);

    return view('content.akuntansi.pencairan.index', [
      'tahun' => $tahun,
      'terbuka' => Info::jurnalTerbuka($tahun),
      'tahunBerjalan' => Info::tahunPenjurnalan(),
      'daftar' => $daftar,
      'total' => $daftar->sum('ajuan'),
      'kasUniv' => $this->saldoKasUniv($tahun),
    ]);
  }

  /** Jurnalkan satu baris SPP (spm_jurnal v1). */
  public function jurnal(int $id)
  {
    $tahun = $this->tahun();
    if (!Info::jurnalTerbuka($tahun)) {
      return redirect()->route('akuntansi.pencairan.index')
        ->with('error', "Periode T.A {$tahun}/" . ($tahun + 1) . ' bukan periode berjalan dan penjurnalannya tidak dibuka. Jurnal tidak disimpan.');
    }
    $baris = $this->belumDijurnal($tahun)->firstWhere('id_spp', $id);
    if (!$baris) {
      return redirect()->route('akuntansi.pencairan.index')
        ->with('error', 'Data tidak ditemukan, belum dibayar kasir, atau sudah dijurnal.');
    }

    $ajuan = (int) $baris->ajuan;
    $jurnal = fn (int $idrek6, int $debet, int $kredit) => [
      'tahun' => (string) $tahun,
      'id_uk' => (int) $baris->idunit,
      'kodeunit' => (int) $baris->ff,
      'idkeg' => (int) $baris->idkeg,
      'idtama' => 0,
      'idrinci' => 0,
      'idrinci_na' => 0,
      'idrek6' => $idrek6,
      'tgl_trk' => today()->toDateString(),
      'uraian' => 'transfer ke unit ' . $baris->induk->nama,
      'bukti' => '',
      'debet' => $debet,
      'kredit' => $kredit,
      'ket' => '',
      'tgl_trans' => '',
    ];

    $ok = DB::transaction(function () use ($id, $jurnal, $ajuan) {
      // Update bersyarat (jurnalspm masih kosong) agar klik ganda / dua pengguna tidak menjurnal dua kali.
      $diubah = DB::table('ueu_tbl_spp')->where('id_spp', $id)->where('jurnalspm', '')->update(['jurnalspm' => 'CLK']);
      if ($diubah !== 1) {
        return false;
      }
      DB::table('ueu_tbl_jurnal_spm')->insert([
        $jurnal(self::KAS_UNIT, $ajuan, 0),
        $jurnal(self::KAS_UNIV, 0, $ajuan),
      ]);

      return true;
    });

    return $ok
      ? redirect()->route('akuntansi.pencairan.index')->with('status',
        'Pencairan ' . $baris->no_spm . ' ke ' . $baris->induk->nama . ' sebesar Rp ' . number_format($ajuan, 0, ',', '.') . ' dijurnal.')
      : redirect()->route('akuntansi.pencairan.index')->with('error', 'Data ini sudah dijurnal sebelumnya.');
  }

  /** Baris SPP ber-SPM disetujui & sudah dibayar kasir yang belum dijurnal, urut no_spm (get_spm_all v1). */
  private function belumDijurnal(int $tahun): Collection
  {
    $baris = $this->realisasi->daftar($tahun)
      ->filter(fn ($s) => $s->bayar)
      ->flatMap(fn ($s) => $s->baris->map(function ($b) use ($s) {
        $b = clone $b;
        $b->no_kasir = $s->bayar->no_kasir;
        $b->no_bkk = RealisasiSpm::nomorBkk($s);
        $b->urut = $s->urut;

        return $b;
      }))
      ->filter(fn ($b) => trim((string) $b->jurnalspm) === '')
      ->values();

    // Unit induk dari kode unit kegiatan ("FF.xx" → "FF.00"), seperti v1.
    $induk = Unit::whereIn('kode', $baris->map(fn ($b) => explode('.', (string) $b->kode_unit)[0] . '.00')->unique()->all() ?: [''])
      ->get(['id_unit', 'kode', 'nama'])->keyBy('kode');

    return $baris->map(function ($b) use ($induk) {
      $b->ff = explode('.', (string) $b->kode_unit)[0];
      $b->induk = $induk[$b->ff . '.00'] ?? null;

      return $b;
    })->filter(fn ($b) => $b->induk && ctype_digit($b->ff))
      ->sortBy(fn ($b) => $b->no_spm . '|' . $b->kode_mak)->values();
  }

  private function saldoKasUniv(int $tahun): float
  {
    $r = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->where('idrek6', self::KAS_UNIV)
      ->selectRaw('COALESCE(SUM(debet), 0) - COALESCE(SUM(kredit), 0) as saldo')->first();

    return (float) ($r->saldo ?? 0);
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
