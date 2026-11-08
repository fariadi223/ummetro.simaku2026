<?php

namespace App\Http\Controllers\unit;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Unit;
use App\Services\RincianAnggaran;
use App\Services\SppAnggaran;
use App\Services\StatusKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pengajuan SPP (Surat Permintaan Pembayaran) unit, padanan Spp::proses1, ajukan,
 * ajukan_proses, max_ajuan, batal_kegiatan, batal_spp, ajuan_finis, detail_spp di v1.
 * Tabel lama `ueu_tbl_spp`: satu baris = satu kegiatan dalam satu nomor SPP.
 *
 * Alur (sama dengan v1):
 *   1. Unit memilih kegiatan & nominal -> baris SPP "draf" (create_spp = 0).
 *      Semua draf memakai nomor SPP yang sama.
 *   2. "Proses SPP" -> create_spp = 1, SPP dikirim ke otorisasi pimpinan
 *      (otorisasi Belum/Sudah/Batal) lalu verifikasi (status_spp) dan SPM (status_spm).
 *   3. Selama ada SPP terkirim yang belum diproses menjadi SPM (status_spm
 *      "Belum diproses"), unit tidak bisa menyusun SPP baru, kecuali unit pada
 *      config simaku.spp_unit_tambah (v1: 11.01, tombol "Tambah").
 *
 * Kuota: jml_spp unit − jumlah baris SPP yang belum di-SPJ-kan (jurnalspm ≠ OK).
 * Pembuatan SPP ditutup bila tanggal akhir anggaran (ueu_tbl_info.akhir_angg) lewat.
 *
 * Kegiatan dapat di-SPP-kan bila (v1):
 *   - anggaran belanja (rek 5) > 0 dan sisa (belanja − SPP berstatus SPM Disetujui) > 0,
 *   - tidak punya SPP yang belum diotorisasi (termasuk draf),
 *   - minimal satu reviewer menyetujui,
 *   - tidak punya SPP yang belum di-SPJ-kan (jurnalspm kosong),
 *   - kuota unit masih ada.
 * v2 memeriksa ulang semua syarat ini saat menyimpan (v1 hanya batas nominal).
 */
class SppController extends Controller
{
  public function __construct(private RincianAnggaran $rinci, private StatusKegiatan $status, private SppAnggaran $spp)
  {
  }

  public function index(Request $request)
  {
    [$unit, $tahun] = $this->konteks();
    $jadwal = $this->jadwal($tahun);
    $kuota = $this->kuota($unit, $tahun);

    $draf = $this->querySpp($unit, $tahun)->where('s.create_spp', '0')->get();
    $daftar = $this->daftarSpp($unit, $tahun);
    $tertunda = $daftar->where('status_spm', 'Belum diproses')->isNotEmpty();
    $bolehTambah = $this->bolehTambahSaatTertunda($unit);
    $susun = $draf->isNotEmpty() || !$tertunda || ($bolehTambah && $request->boolean('baru'));

    return view('content.unit.spp.index', [
      'unit' => $unit,
      'tahun' => $tahun,
      'jadwal' => $jadwal,
      'kuota' => $kuota,
      'draf' => $draf,
      'nomorDraf' => $draf->first()->no_spp ?? null,
      'daftar' => $daftar,
      'susun' => $susun,
      'tertunda' => $tertunda,
      'bolehTambah' => $bolehTambah,
      'kegiatan' => $susun ? $this->kegiatan($unit, $tahun, $kuota) : collect(),
    ]);
  }

  public function create(int $idkeg)
  {
    [$unit, $tahun] = $this->konteks();
    [$keg, $alasan] = $this->periksaKegiatan($unit, $tahun, $idkeg);

    if ($alasan) {
      return redirect()->route('unit.spp.index')->with('error', $alasan);
    }

    return view('content.unit.spp.form', [
      'unit' => $unit,
      'tahun' => $tahun,
      'keg' => $keg,
      'nomorDraf' => $this->querySpp($unit, $tahun)->where('s.create_spp', '0')->first()?->no_spp,
    ]);
  }

  public function store(Request $request, int $idkeg)
  {
    [$unit, $tahun] = $this->konteks();
    [$keg, $alasan] = $this->periksaKegiatan($unit, $tahun, $idkeg);

    if ($alasan) {
      return redirect()->route('unit.spp.index')->with('error', $alasan);
    }

    // Boleh diketik dengan pemisah ribuan ("1.500.000" / "Rp 1.500.000"); desimal tidak diterima.
    $request->merge(['ajuan' => preg_replace('/^rp|[.\s]/i', '', trim((string) $request->input('ajuan')))]);
    $request->validate(
      ['ajuan' => ['required', 'integer', 'min:1', 'max:' . max(0, (int) floor($keg->sisa))]],
      ['ajuan.max' => 'Maaf, ajuan terlalu besar. Batas yang diizinkan maksimal Rp ' . number_format($keg->sisa, 0, ',', '.') . '.'],
      ['ajuan' => 'jumlah anggaran yang diajukan'],
    );

    DB::transaction(function () use ($unit, $tahun, $keg, $request) {
      $nomor = $this->querySpp($unit, $tahun)->where('s.create_spp', '0')->lockForUpdate()->first()?->no_spp
        ?? $this->nomorBaru($unit, $tahun);

      // Kolom & nilai awal sama dengan Spp::ajukan_proses v1 (status_spm memakai default tabel).
      DB::table('ueu_tbl_spp')->insert([
        'no_spp' => $nomor,
        'tahun' => $tahun,
        'idkeg' => $keg->idkeg,
        'idta' => 2,
        'ajuan' => (int) $request->input('ajuan'),
        'tgl_saji' => now()->toDateString(),
        'no_spm' => '',
        'alasan1' => '',
        'alasan2' => '',
        'verifikasi1' => '',
        'alasan3' => '',
        'verifikasi2' => '',
        'alasan4' => '',
        'jurnalspm' => '',
        'tgl_spm' => now()->toDateString(),
        'otorisasi' => 'Belum',
        'status_spp' => 'Belum diverifikasi',
        // Kolom NOT NULL tanpa default. v1 (MySQL non-strict) otomatis mengisi ''; Laravel
        // memakai strict mode jadi harus eksplisit. Paraf diisi saat SPM dibuat (create_spm / Kasir::paraf_spm).
        'paraf_jabatan' => '',
        'paraf_nama' => '',
      ]);
    });

    return redirect()->route('unit.spp.index')->with('status', "Kegiatan \"{$keg->uraiankeg}\" ditambahkan ke SPP. Klik Proses SPP bila semua kegiatan sudah dimasukkan.");
  }

  /** Kirim draf (ajuan_finis v1). */
  public function proses()
  {
    [$unit, $tahun] = $this->konteks();
    $nomor = $this->querySpp($unit, $tahun)->where('s.create_spp', '0')->first()?->no_spp;

    if (!$nomor) {
      return back()->with('error', 'Belum ada kegiatan yang akan di-SPP-kan.');
    }
    if (!$this->jadwal($tahun)['aktif']) {
      return back()->with('error', 'Pembuatan SPP tahun anggaran ini sudah ditutup.');
    }

    $this->barisMilikUnit($unit, $tahun)->where('no_spp', $nomor)->where('create_spp', '0')->update(['create_spp' => DB::raw('1')]); // BIT(1): string '1' ditolak strict mode

    return redirect()->route('unit.spp.index')->with('status', "SPP {$nomor} dikirim untuk otorisasi pimpinan.");
  }

  /** Hapus satu kegiatan dari draf (batal_kegiatan v1). */
  public function batalKegiatan(int $id)
  {
    [$unit, $tahun] = $this->konteks();
    $baris = $this->querySpp($unit, $tahun)->where('s.id_spp', $id)->where('s.create_spp', '0')->first();
    abort_unless($baris, 404);

    DB::table('ueu_tbl_spp')->where('id_spp', $id)->delete();

    return back()->with('status', "Kegiatan \"{$baris->uraiankeg}\" dikeluarkan dari SPP.");
  }

  /** Hapus satu SPP terkirim beserta semua kegiatannya (batal_spp v1). */
  public function batalSpp(int $id)
  {
    [$unit, $tahun] = $this->konteks();
    $spp = $this->daftarSpp($unit, $tahun)->firstWhere('id_spp', $id) ?? abort(404);

    if (!$spp->boleh_hapus) {
      return back()->with('error', "SPP {$spp->no_spp} sudah diotorisasi pimpinan sehingga tidak dapat dihapus.");
    }

    $this->barisMilikUnit($unit, $tahun)->where('no_spp', $spp->no_spp)->delete();

    return back()->with('status', "SPP {$spp->no_spp} dihapus.");
  }

  /** Lembar SPP untuk dicetak (detail_spp v1). */
  public function cetak(int $id)
  {
    [$unit, $tahun] = $this->konteks();
    $nomor = $this->querySpp($unit, $tahun)->where('s.id_spp', $id)->first()?->no_spp ?? abort(404);
    $baris = $this->querySpp($unit, $tahun)->where('s.no_spp', $nomor)->get();
    $nilai = $this->nilaiKegiatan($baris->pluck('idkeg')->all(), $tahun, $nomor);

    $baris->each(function ($b) use ($nilai) {
      $n = $nilai[$b->idkeg];
      $b->anggaran = $n->anggaran;
      // Lebih tepat dari v1: "pernah diterima" tidak menghitung SPP ini sendiri.
      $b->diterima = $n->dicairkan;
      $b->tersedia = $n->anggaran - $n->dicairkan;
    });

    $induk = Unit::where('kode', $unit->kode_induk . '.00')->first() ?? $unit;

    return view('content.unit.spp.cetak', compact('unit', 'tahun', 'nomor', 'baris', 'induk'));
  }

  // ---------------------------------------------------------------------------

  /** Batas waktu pembuatan SPP (Simaku::tutup_spp v1), lihat SppAnggaran::jadwal. */
  private function jadwal(int $tahun): array
  {
    return $this->spp->jadwal($tahun);
  }

  private function kuota(Unit $unit, int $tahun): array
  {
    $belumSpj = $this->querySpp($unit, $tahun)->where('s.jurnalspm', '!=', 'OK')->count();
    $maks = (int) $unit->jml_spp;

    return ['maks' => $maks, 'belum_spj' => $belumSpj, 'sisa' => $maks - $belumSpj];
  }

  private function bolehTambahSaatTertunda(Unit $unit): bool
  {
    return in_array($unit->kode, (array) config('simaku.spp_unit_tambah', ['11.01']), true);
  }

  /** ueu_tbl_spp + kegiatan + MAK milik unit pada T.A (get_keg_spp v1). */
  private function querySpp(Unit $unit, int $tahun)
  {
    return DB::table('ueu_tbl_spp as s')
      ->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 's.idkeg')
      ->join('ueu_tbl_mak4 as m4', 'm4.idmak4', '=', 'k.idmak4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->where('k.idunit', $unit->id_unit)->where('k.tahun', (string) $tahun)
      ->orderBy('m1.kodemak1')->orderBy('m2.kodemak2')->orderBy('m3.kodemak3')->orderBy('m4.kodemak4')
      ->select('s.*', 'k.uraiankeg', 'm4.namamak4',
        DB::raw("CONCAT_WS('.', m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode_mak"));
  }

  /** Baris ueu_tbl_spp milik unit pada T.A, untuk update/delete. */
  private function barisMilikUnit(Unit $unit, int $tahun)
  {
    return DB::table('ueu_tbl_spp')->whereIn('idkeg',
      DB::table('ueu_tbl_kegiatan')->where('idunit', $unit->id_unit)->where('tahun', (string) $tahun)->select('idkeg'));
  }

  /** SPP terkirim yang belum menjadi SPM disetujui, per nomor (get_group_nospp v1). */
  private function daftarSpp(Unit $unit, int $tahun): Collection
  {
    return $this->querySpp($unit, $tahun)
      ->where('s.create_spp', '1')->where('s.status_spm', '!=', 'Disetujui')
      ->get()
      ->groupBy('no_spp')
      ->map(function (Collection $baris) {
        $s = clone $baris->first();
        $s->kegiatan = $baris->count();
        $s->nominal = (float) $baris->sum('ajuan');
        // Alasan penolakan/pembatalan: alasan1..4 pertama yang terisi (Mspp::alasan_batal v1).
        $s->alasan = $baris->flatMap(fn ($b) => [$b->alasan1, $b->alasan2, $b->alasan3, $b->alasan4])
          ->map(fn ($a) => trim((string) $a))->first(fn ($a) => $a !== '');
        $s->boleh_hapus = $s->otorisasi !== 'Sudah' || $s->status_spp === 'Ditolak';

        return $s;
      })
      ->sortByDesc(fn ($s) => (int) $s->no_spp)
      ->values();
  }

  /** Anggaran belanja & yang sudah dicairkan per kegiatan, lihat SppAnggaran::nilaiKegiatan. */
  private function nilaiKegiatan(array $idKeg, int $tahun, ?string $kecuali = null): array
  {
    return $this->spp->nilaiKegiatan($idKeg, $tahun, $kecuali);
  }

  /** Semua kegiatan unit beserta nilai dan status pengajuan SPP-nya. */
  private function kegiatan(Unit $unit, int $tahun, array $kuota): Collection
  {
    $kegiatan = DB::table('ueu_tbl_kegiatan as k')
      ->join('ueu_tbl_mak4 as m4', 'm4.idmak4', '=', 'k.idmak4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->where('k.idunit', $unit->id_unit)->where('k.tahun', (string) $tahun)
      ->orderBy('m1.kodemak1')->orderBy('m2.kodemak2')->orderBy('m3.kodemak3')->orderBy('m4.kodemak4')
      ->get(['k.idkeg', 'k.uraiankeg', 'm4.namamak4',
        DB::raw("CONCAT_WS('.', m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode_mak")]);

    $id = $kegiatan->pluck('idkeg')->all();
    $nilai = $this->nilaiKegiatan($id, $tahun);
    $review = $this->status->review($unit, $tahun, $id);
    $spp = DB::table('ueu_tbl_spp')->whereIn('idkeg', $id ?: [0])
      ->groupBy('idkeg')
      ->selectRaw("idkeg, SUM(otorisasi != 'Sudah') as belum_otorisasi, SUM(jurnalspm = '') as belum_spj")
      ->get()->keyBy('idkeg');

    return $kegiatan->map(function ($k) use ($nilai, $review, $spp, $kuota) {
      $k->anggaran = $nilai[$k->idkeg]->anggaran;
      $k->dicairkan = $nilai[$k->idkeg]->dicairkan;
      $k->sisa = $k->anggaran - $k->dicairkan;
      $s = $spp[$k->idkeg] ?? null;

      [$k->status, $k->keterangan] = match (true) {
        $k->anggaran <= 0 => ['kosong', 'Tidak ada anggaran belanja'],
        $k->sisa <= 0 => ['lunas', 'Anggaran sudah dicairkan seluruhnya'],
        $s && $s->belum_otorisasi > 0 => ['diajukan', 'Sedang dalam pengajuan SPP'],
        ($review[$k->idkeg]->disetujui ?? 0) < 1 => ['review', 'Belum disetujui reviewer'],
        $s && $s->belum_spj > 0 => ['spj', 'Belum SPJ'],
        $kuota['sisa'] < 1 => ['kuota', 'Batas pengajuan SPP sudah maksimal'],
        default => ['boleh', null],
      };

      return $k;
    });
  }

  /** @return array{0: ?object, 1: ?string} kegiatan & alasan tidak dapat diajukan */
  private function periksaKegiatan(Unit $unit, int $tahun, int $idkeg): array
  {
    if (!$this->jadwal($tahun)['aktif']) {
      return [null, 'Pembuatan SPP tahun anggaran ini sudah ditutup.'];
    }

    $daftar = $this->daftarSpp($unit, $tahun);
    $adaDraf = $this->querySpp($unit, $tahun)->where('s.create_spp', '0')->exists();
    // Unit pengecualian (v1: 11.01) boleh menyusun SPP baru walau masih ada yang diproses.
    if (!$adaDraf && $daftar->where('status_spm', 'Belum diproses')->isNotEmpty() && !$this->bolehTambahSaatTertunda($unit)) {
      return [null, 'Masih ada SPP yang sedang diproses. SPP baru dapat dibuat setelah SPP tersebut selesai.'];
    }

    $keg = $this->kegiatan($unit, $tahun, $this->kuota($unit, $tahun))->firstWhere('idkeg', $idkeg);
    abort_unless($keg, 404);

    return $keg->status === 'boleh' ? [$keg, null] : [null, "Kegiatan \"{$keg->uraiankeg}\" tidak dapat di-SPP-kan: {$keg->keterangan}."];
  }

  /**
   * Nomor SPP baru "N/SPP/<kode unit>/dd/mm/YYYY" (ajukan_proses v1):
   * N = max(jumlah SPP ber-SPM Disetujui, nomor terbesar) + 1. v2 mengambil nomor
   * terbesar dari semua SPP unit pada T.A (v1: hanya yang Disetujui, kecuali
   * jalur "Tambah") agar tidak bentrok dengan SPP yang masih diproses.
   */
  private function nomorBaru(Unit $unit, int $tahun): string
  {
    $semua = $this->barisMilikUnit($unit, $tahun)->where('tahun', (string) $tahun);
    $disetujui = (clone $semua)->where('status_spm', 'Disetujui')->distinct()->count('no_spp');
    $terbesar = (int) (clone $semua)->selectRaw('MAX(CAST(no_spp AS UNSIGNED)) as n')->value('n');

    return (max($disetujui, $terbesar) + 1) . '/SPP/' . $unit->kode . '/' . now()->format('d/m/Y');
  }

  private function konteks(): array
  {
    $unit = Auth::user()->unit;
    abort_unless($unit, 404, 'Akun ini tidak terhubung ke unit kerja.');
    // Pengajuan SPP hanya untuk sub unit; unit induk (FF.00, /adminunit) tidak boleh mengakses.
    abort_if($unit->is_induk, 403, 'Pengajuan SPP hanya dapat dilakukan oleh sub unit.');

    return [$unit, (int) (session('periode') ?: Info::tahun(['aktif' => 'enable']))];
  }
}
