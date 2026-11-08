<?php

namespace App\Http\Controllers\kasir;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Services\RealisasiSpm;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Realisasi SPM oleh kasir (/kasir/spm), padanan Kasir::spm, tunai, transfer, transfer_proses,
 * lihat, paraf_spm, paraf_spm_proses v1.
 *
 *   Daftar   : SPM disetujui T.A login + rincian kegiatan; aksi Tunai/Transfer bila belum
 *              dibayar, Lihat (cetak BKK) bila sudah.
 *   Tunai    : catat ueu_tbl_kasir tanpa data bank; no_kasir = jumlah pembayaran T.A + 1 (v1 tunai).
 *   Transfer : catat ueu_tbl_kasir dengan bank, no. rekening, no. ref; no_kasir = jumlah
 *              pembayaran unit tsb pada T.A + 1 (v1 transfer_proses).
 *   Penerima : paraf_jabatan & paraf_nama pada semua baris SPM (nama penerima di BKK).
 *
 * Perbedaan dari v1: pembayaran ganda ditolak (v1 tunai bisa mencatat dua kali bila tautan
 * dibuka ulang), tahun diisi T.A login (v1 mengandalkan default kolom), dan aksi tulis lewat POST.
 */
class RealisasiSpmController extends Controller
{
  public function __construct(private RealisasiSpm $realisasi)
  {
  }

  /** ?cari= (no. SPM / kode / nama unit), ?status=belum|sudah, ?page= */
  public function index(Request $request)
  {
    $tahun = $this->tahun();
    $cari = trim((string) $request->query('cari', ''));
    $status = in_array($request->query('status'), ['belum', 'sudah'], true) ? $request->query('status') : '';

    $semua = $this->realisasi->daftar($tahun);
    $spm = $semua
      ->when($status === 'belum', fn ($c) => $c->filter(fn ($s) => !$s->bayar))
      ->when($status === 'sudah', fn ($c) => $c->filter(fn ($s) => $s->bayar))
      ->when($cari !== '', function ($c) use ($cari) {
        $q = mb_strtolower($cari);

        return $c->filter(fn ($s) => str_contains(mb_strtolower(
          "{$s->no_spm} {$s->kode_spm} {$s->nama_unit} " . ($s->induk->nama ?? '')
        ), $q));
      })->values();

    $perHalaman = 20;
    $halaman = LengthAwarePaginator::resolveCurrentPage();
    $daftar = new LengthAwarePaginator($spm->forPage($halaman, $perHalaman)->values(), $spm->count(), $perHalaman, $halaman,
      ['path' => $request->url(), 'query' => $request->query()]);

    $dibayar = $semua->filter(fn ($s) => $s->bayar);

    return view('content.kasir.spm.index', [
      'tahun' => $tahun,
      'daftar' => $daftar,
      'cari' => $cari,
      'status' => $status,
      'jumlah' => $spm->count(),
      'total' => $spm->sum('nominal'),
      'ringkas' => [
        'semua' => ['n' => $semua->count(), 'nominal' => $semua->sum('nominal')],
        'sudah' => ['n' => $dibayar->count(), 'nominal' => $dibayar->sum('nominal')],
        'belum' => ['n' => $semua->count() - $dibayar->count(), 'nominal' => $semua->sum('nominal') - $dibayar->sum('nominal')],
      ],
      'unitParaf' => config('simaku.kasir_unit_paraf', []),
    ]);
  }

  /** Bayar tunai (Kasir::tunai v1), lalu buka BKK. */
  public function tunai(int $id)
  {
    $tahun = $this->tahun();

    return $this->catat($tahun, $id, function ($spm) use ($tahun) {
      // v1: nomor tunai dihitung dari seluruh pembayaran T.A (id_unit != 0).
      $no = DB::table('ueu_tbl_kasir')->where('tahun', (string) $tahun)->where('id_unit', '!=', 0)->count() + 1;

      return ['no_kasir' => $no, 'namabank' => '', 'norekbank' => '', 'nobuktirek' => ''];
    }, 'tunai');
  }

  /** Form bukti transfer (Kasir::transfer v1). */
  public function transfer(int $id)
  {
    $spm = $this->spmAtau404($id);
    if ($spm->bayar) {
      return redirect()->route('kasir.spm.index')->with('error', "SPM {$spm->no_spm} sudah dibayarkan.");
    }

    return view('content.kasir.spm.transfer', ['spm' => $spm, 'tahun' => $this->tahun()]);
  }

  /** Simpan bukti transfer (Kasir::transfer_proses v1), lalu buka BKK. */
  public function simpanTransfer(Request $request, int $id)
  {
    $data = $request->validate([
      'namabank' => ['nullable', 'string', 'max:50'],
      'nomrek' => ['required', 'string', 'max:50'],
      'nomref' => ['required', 'string', 'max:50'],
    ], [], ['namabank' => 'Nama Bank Tujuan', 'nomrek' => 'Nomor Rekening', 'nomref' => 'No. Ref / No. Record']);

    $tahun = $this->tahun();

    return $this->catat($tahun, $id, function ($spm) use ($tahun, $data) {
      // v1: nomor transfer dihitung per unit pada T.A.
      $no = DB::table('ueu_tbl_kasir')->where('tahun', (string) $tahun)->where('id_unit', $spm->id_unit_spm)->count() + 1;

      return [
        'no_kasir' => $no,
        'namabank' => trim((string) ($data['namabank'] ?? '')),
        'norekbank' => trim($data['nomrek']),
        'nobuktirek' => trim($data['nomref']),
      ];
    }, 'transfer');
  }

  /** Cetak BKK (Kasir::lihat v1 / spm_ct). */
  public function bkk(int $id)
  {
    $spm = $this->spmAtau404($id);
    if (!$spm->bayar) {
      return redirect()->route('kasir.spm.index')->with('error', "SPM {$spm->no_spm} belum dibayarkan.");
    }

    $info = Info::query()->where('id', 1)->first(['namapt', 'alamat']);
    $pimpinan = $spm->induk ? ($spm->induk->an_nama_pimp ?: $spm->induk->nama_pimp) : '';

    return view('content.kasir.spm.bkk', [
      'spm' => $spm,
      'tahun' => $this->tahun(),
      'nomorBkk' => RealisasiSpm::nomorBkk($spm),
      'salinan' => !session()->pull("bkk_asli.spm.{$id}", false),
      'kota' => $info->alamat ?? 'Metro',
      'pimpinan' => $pimpinan,
      // v1: kolom "Mengetahui" hanya untuk unit induk berkode 00 / 11.
      'mengetahui' => in_array(explode('.', $spm->kode_induk)[0], ['00', '11'], true),
      'namaKasir' => config('simaku.kasir_nama'),
      'penerima' => mb_strtoupper($spm->paraf_nama !== '' ? $spm->paraf_nama : $pimpinan),
    ]);
  }

  /** Form penerima (Kasir::paraf_spm v1). */
  public function paraf(int $id)
  {
    $spm = $this->spmAtau404($id);
    $this->bolehParaf($spm);

    return view('content.kasir.spm.paraf', ['spm' => $spm, 'tahun' => $this->tahun()]);
  }

  /** Simpan penerima (Kasir::paraf_spm_proses v1). */
  public function simpanParaf(Request $request, int $id)
  {
    $spm = $this->spmAtau404($id);
    $this->bolehParaf($spm);

    $data = $request->validate([
      'nm_jbtn' => ['nullable', 'string', 'max:100'],
      'nm_pimp' => ['required', 'string', 'max:100'],
    ], [], ['nm_jbtn' => 'Nama Jabatan', 'nm_pimp' => 'Nama Penerima']);

    DB::table('ueu_tbl_spp')->where('tahun', (string) $this->tahun())->where('no_spm', $spm->no_spm)->update([
      'paraf_jabatan' => trim((string) ($data['nm_jbtn'] ?? '')),
      'paraf_nama' => trim($data['nm_pimp']),
    ]);

    return redirect()->route('kasir.spm.index', ['cari' => $spm->no_spm])
      ->with('status', "Penerima SPM {$spm->no_spm} disimpan.");
  }

  /**
   * Catat pembayaran satu kali per SPM. ueu_tbl_kasir MyISAM (tanpa transaksi/row lock),
   * jadi pencatatan diserialkan dengan cache lock dan dicek ulang di dalamnya.
   */
  private function catat(int $tahun, int $id, callable $isian, string $metode)
  {
    $hasil = Cache::lock('simaku:kasir-bayar', 10)->block(5, function () use ($tahun, $id, $isian) {
      $spm = $this->spmAtau404($id);
      if ($spm->bayar) {
        return [$spm, false];
      }
      if (!$spm->id_unit_spm) {
        abort(422, "Unit {$spm->kode_spm} pada nomor SPM tidak ditemukan.");
      }

      DB::table('ueu_tbl_kasir')->insert(array_merge([
        'tahun' => (string) $tahun,
        'no_spm' => $spm->urut,
        'id_unit' => $spm->id_unit_spm,
        'tgl_kasir' => today()->toDateString(),
      ], $isian($spm)));

      return [$spm, true];
    });

    [$spm, $baru] = $hasil;
    if (!$baru) {
      return redirect()->route('kasir.spm.index')->with('error', "SPM {$spm->no_spm} sudah dibayarkan sebelumnya.");
    }

    // Cetakan pertama setelah dibayar = asli; cetak berikutnya diberi watermark COPY.
    session()->put("bkk_asli.spm.{$id}", true);

    return redirect()->route('kasir.spm.index', ['cari' => $spm->no_spm])
      ->with('status', "SPM {$spm->no_spm} dibayar " . ($metode === 'tunai' ? 'tunai' : 'melalui transfer') . '.')
      ->with('cetak', route('kasir.spm.bkk', $id));
  }

  private function bolehParaf(object $spm): void
  {
    abort_unless(in_array($spm->kode_spm, config('simaku.kasir_unit_paraf', []), true), 404);
  }

  private function spmAtau404(int $id): object
  {
    return $this->realisasi->cari($this->tahun(), $id) ?? abort(404);
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
