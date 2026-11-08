<?php

namespace App\Http\Controllers\unit;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\PaguUnit;
use App\Models\Unit;
use App\Services\RincianAnggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Menu Keluaran unit, padanan Unit::infopagu, rekap_bebasis_fungsi,
 * rekap_bebasis_sifat, grafik_* dan lap_* di v1.
 *
 * Semua nilai = Σ hargasat × qty1 × qty2..4 (kosong/0 = 1) dari rincian kegiatan
 * unit pada T.A yang dipilih (filter tahun pada kegiatan, seperti v1).
 */
class KeluaranController extends Controller
{
  public function __construct(private RincianAnggaran $rinci)
  {
  }

  /**
   * Informasi pagu (infopagu v1), per sumber pagu unit:
   *   ajuan = belanja − pendapatan (kegiatan yang memakai sumber pagu tsb.)
   *   sisa  = pagu − ajuan
   * v1 hanya menampilkan baris pagu pertama; di sini semua sumber + total.
   * Unit induk (FF.00, /adminunit) melihat semua sub unit FF.* (tanpa FF.00 sendiri).
   */
  public function infoPagu()
  {
    [$unit, $tahun] = $this->konteks();

    $idUnit = $this->cakupanUnit($unit);
    $semuaUnit = $unit->is_induk ? Unit::whereIn('id_unit', $idUnit)->orderBy('kode')->get(['id_unit', 'kode', 'nama']) : null;

    $paguPerUnit = $this->paguUnit($idUnit, $tahun);
    $pagu = $paguPerUnit[$unit->id_unit] ?? collect();

    // Unit induk: setiap unit beserta pagu-nya (unit tanpa pagu tetap tampil).
    $semuaUnit?->each(fn ($u) => $u->pagu = $paguPerUnit[$u->id_unit] ?? collect());

    // Penggunaan per bidang (MAK 1) untuk konteks; unit induk = gabungan semua unit.
    $perBidang = $this->rinci->query()
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'k.idmak1')
      ->whereIn('k.idunit', $idUnit)->where('k.tahun', (string) $tahun)->whereIn('r1.koderek1', ['4', '5'])
      ->groupBy('m1.kodemak1', 'm1.namamak1')
      ->orderBy('m1.kodemak1')
      ->selectRaw("m1.kodemak1, m1.namamak1,
        SUM(IF(r1.koderek1 = '4', " . RincianAnggaran::SQL_NILAI . ", 0)) as pendapatan,
        SUM(IF(r1.koderek1 = '5', " . RincianAnggaran::SQL_NILAI . ", 0)) as belanja,
        COUNT(DISTINCT k.idkeg) as kegiatan")
      ->get();

    return view('content.unit.keluaran.info-pagu', compact('unit', 'tahun', 'pagu', 'perBidang', 'semuaUnit'));
  }

  /** Pagu per sumber untuk beberapa unit: [idunit => Collection<object>]. */
  private function paguUnit(array $idUnit, int $tahun): array
  {
    $nilai = $this->rinci->query()
      ->whereIn('k.idunit', $idUnit ?: [0])->where('k.tahun', (string) $tahun)->whereIn('r1.koderek1', ['4', '5'])
      ->groupBy('k.idunit', 'k.idsumberdana', 'r1.koderek1')
      ->selectRaw('k.idunit, k.idsumberdana as pagu, r1.koderek1 as kode, SUM(' . RincianAnggaran::SQL_NILAI . ') as total')
      ->get()->groupBy(fn ($r) => $r->idunit . '|' . $r->pagu);

    return PaguUnit::with('pagu:id_pagu,sumber')
      ->where('tahun', (string) $tahun)->whereIn('idunit', $idUnit ?: [0])->orderBy('idpagu')->get()
      ->map(function ($p) use ($nilai) {
        $baris = $nilai[$p->idunit . '|' . $p->idpagu] ?? collect();
        $pendapatan = (float) ($baris->firstWhere('kode', '4')->total ?? 0);
        $belanja = (float) ($baris->firstWhere('kode', '5')->total ?? 0);
        $ajuan = $belanja - $pendapatan;

        return (object) [
          'idunit' => (int) $p->idunit,
          'kode' => $p->idpagu,
          'sumber' => $p->pagu->sumber ?? $p->idpagu,
          'nominal' => (float) $p->nominal,
          'pendapatan' => $pendapatan,
          'belanja' => $belanja,
          'ajuan' => $ajuan,
          'sisa' => (float) $p->nominal - $ajuan,
        ];
      })
      ->groupBy('idunit')->all();
  }

  /** ?unit=<kode> (khusus unit induk) memfilter ke satu unit di cakupannya; kosong = semua unit. */
  public function rekapFungsi(Request $request)
  {
    [$unit, $tahun] = $this->konteks();

    return view('content.unit.keluaran.rekap-fungsi', ['unit' => $unit, 'tahun' => $tahun] + $this->dataFungsi($unit, $tahun, $request->query('unit')));
  }

  public function cetakFungsi(Request $request)
  {
    [$unit, $tahun] = $this->konteks();

    return view('content.unit.keluaran.cetak-fungsi', ['unit' => $unit, 'tahun' => $tahun] + $this->dataFungsi($unit, $tahun, $request->query('unit')));
  }

  /** ?unit=<kode> (khusus unit induk) memfilter ke satu unit di cakupannya; kosong = semua unit. */
  public function rekapSifat(Request $request)
  {
    [$unit, $tahun] = $this->konteks();

    return view('content.unit.keluaran.rekap-sifat', ['unit' => $unit, 'tahun' => $tahun] + $this->dataSifat($unit, $tahun, $request->query('unit')));
  }

  public function cetakSifat(Request $request)
  {
    [$unit, $tahun] = $this->konteks();

    return view('content.unit.keluaran.cetak-sifat', ['unit' => $unit, 'tahun' => $tahun] + $this->dataSifat($unit, $tahun, $request->query('unit')));
  }

  // ---------------------------------------------------------------------------

  /**
   * Rekap berbasis fungsi (rekap_bebasis_fungsi v1): kegiatan per bidang (MAK 1)
   * dengan pendapatan & belanja masing-masing, subtotal per bidang, dan total.
   */
  private function dataFungsi($unit, int $tahun, ?string $pilih = null): array
  {
    $bidang = DB::table('ueu_tbl_mak1')->orderBy('kodemak1')->get(['idmak1', 'kodemak1', 'namamak1']);
    $tab = $this->pilihUnit($unit, $pilih);
    $idUnit = $tab['idUnit'];

    $kegiatan = DB::table('ueu_tbl_kegiatan as k')
      ->join('ueu_tbl_mak4 as m4', 'm4.idmak4', '=', 'k.idmak4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->leftJoin('ueu_tbl_pagu as p', 'p.id_pagu', '=', 'k.idsumberdana')
      ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'k.idunit')
      ->whereIn('k.idunit', $idUnit)->where('k.tahun', (string) $tahun)
      ->orderBy('m1.kodemak1')->orderBy('m2.kodemak2')->orderBy('m3.kodemak3')->orderBy('m4.kodemak4')->orderBy('u.kode')->orderBy('k.idkeg')
      ->get(['k.idkeg', 'k.idmak1', 'k.uraiankeg', 'k.latblk', 'k.tujuan', 'k.lingkup', 'k.jadwal',
        'm2.namamak2', 'm4.namamak4', 'p.sumber as dana', 'u.kode as kode_unit', 'u.nama as nama_unit',
        DB::raw("CONCAT_WS('.', m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode_mak")]);

    $nilai = $this->rinci->query()
      ->whereIn('r.idkeg', $kegiatan->pluck('idkeg')->all() ?: [0])->whereIn('r1.koderek1', ['4', '5'])
      ->groupBy('r.idkeg', 'r1.koderek1')
      ->selectRaw('r.idkeg, r1.koderek1 as kode, SUM(' . RincianAnggaran::SQL_NILAI . ') as total')
      ->get()->groupBy('idkeg');

    $kegiatan->each(function ($k) use ($nilai) {
      $n = $nilai[$k->idkeg] ?? collect();
      $k->pendapatan = (float) ($n->firstWhere('kode', '4')->total ?? 0);
      $k->belanja = (float) ($n->firstWhere('kode', '5')->total ?? 0);
    });

    $grup = $bidang->map(fn ($b) => (object) [
      'kode' => $b->kodemak1,
      'nama' => trim($b->namamak1),
      'kegiatan' => $kegiatan->where('idmak1', $b->idmak1)->values(),
    ])->each(function ($g) {
      $g->pendapatan = $g->kegiatan->sum('pendapatan');
      $g->belanja = $g->kegiatan->sum('belanja');
    });

    return [
      'grup' => $grup,
      'total' => ['pendapatan' => $grup->sum('pendapatan'), 'belanja' => $grup->sum('belanja'), 'kegiatan' => $kegiatan->count()],
      'grafik' => $this->grafikFungsi($idUnit, $tahun, $bidang),
    ] + $tab;
  }

  /**
   * Cakupan unit + tab unit untuk halaman rekap. Unit induk: daftar semua sub unit (tab),
   * ?unit=<kode> memilih satu sub unit (kode di luar cakupan diabaikan = semua unit).
   *
   * @return array{idUnit: int[], jumlahUnit: ?int, daftarUnit: ?\Illuminate\Support\Collection, unitAktif: ?Unit}
   */
  private function pilihUnit($unit, ?string $pilih): array
  {
    $idUnit = $this->cakupanUnit($unit);
    $daftarUnit = $unit->is_induk ? Unit::whereIn('id_unit', $idUnit)->orderBy('kode')->get(['id_unit', 'kode', 'nama']) : null;
    $unitAktif = $daftarUnit?->firstWhere('kode', $pilih);

    return [
      'idUnit' => $unitAktif ? [(int) $unitAktif->id_unit] : $idUnit,
      // Unit induk: jumlah unit yang tercakup (null = unit sendiri saja).
      'jumlahUnit' => $daftarUnit ? count($idUnit) : null,
      'daftarUnit' => $daftarUnit,
      'unitAktif' => $unitAktif,
    ];
  }

  /**
   * Unit yang datanya ditampilkan: unit induk (FF.00, /adminunit) = semua sub unit berkode
   * FF.* kecuali FF.00 sendiri (tidak dihitung maupun ditampilkan); unit biasa = unit itu sendiri.
   */
  private function cakupanUnit($unit): array
  {
    return $unit->is_induk
      ? Unit::where('kode', 'like', $unit->kode_induk . '.%')->where('kode', 'not like', '%.00')
        ->pluck('id_unit')->map(fn ($id) => (int) $id)->all()
      : [(int) $unit->id_unit];
  }

  /**
   * Grafik rekap berbasis fungsi, algoritma grafik_bebasis_fungsi v1:
   *   untuk setiap MAK 1 (urut kode, termasuk yang kosong):
   *     pendapatan = Σ rinci rek 4 dari kegiatan dgn kegiatan.idmak1 = MAK1, unit & T.A ini
   *     belanja    = Σ rinci rek 5 (filter sama)
   *     selisih    = belanja − pendapatan
   * Dikelompokkan langsung dari kegiatan.idmak1 (tanpa rantai MAK 4), sama seperti v1.
   */
  private function grafikFungsi(array $idUnit, int $tahun, $bidang): array
  {
    $nilai = $this->rinci->query()
      ->whereIn('k.idunit', $idUnit)->where('k.tahun', (string) $tahun)->whereIn('r1.koderek1', ['4', '5'])
      ->groupBy('k.idmak1', 'r1.koderek1')
      ->selectRaw('k.idmak1, r1.koderek1 as kode, SUM(' . RincianAnggaran::SQL_NILAI . ') as total')
      ->get();

    $ambil = fn ($idmak1, $kode) => (float) ($nilai->first(fn ($n) => (int) $n->idmak1 === (int) $idmak1 && (string) $n->kode === $kode)->total ?? 0);

    $data = ['kategori' => [], 'pendapatan' => [], 'belanja' => [], 'selisih' => []];
    foreach ($bidang as $b) {
      $pendapatan = $ambil($b->idmak1, '4');
      $belanja = $ambil($b->idmak1, '5');

      $data['kategori'][] = trim($b->namamak1);
      $data['pendapatan'][] = round($pendapatan);
      $data['belanja'][] = round($belanja);
      $data['selisih'][] = round($belanja - $pendapatan);
    }

    return $data;
  }

  /**
   * Rekap berbasis sifat (rekap_bebasis_sifat v1): jumlah per rekening level 6,
   * dikelompokkan Pendapatan (4), Belanja (5), Penerimaan (6.1) & Pengeluaran (6.2) pembiayaan.
   */
  private function dataSifat($unit, int $tahun, ?string $pilih = null): array
  {
    $tab = $this->pilihUnit($unit, $pilih);

    $baris = $this->rinci->query()
      ->whereIn('k.idunit', $tab['idUnit'])->where('k.tahun', (string) $tahun)
      ->groupBy('r.idrek6', 'r1.koderek1', 'r2.koderek2', 'r2.namarek2', 'r6.namarek6', 'r3.koderek3', 'r4.koderek4', 'r5.koderek5', 'r6.koderek6')
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')->orderBy('r4.koderek4')->orderBy('r5.koderek5')->orderBy('r6.koderek6')
      ->selectRaw("r1.koderek1, r2.koderek2, r2.namarek2, r6.namarek6,
        CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode,
        SUM(" . RincianAnggaran::SQL_NILAI . ') as jumlah, COUNT(*) as item')
      ->get();

    $kelompok = [
      'pendapatan' => ['Pendapatan', $baris->where('koderek1', 4)->values()],
      'belanja' => ['Belanja', $baris->where('koderek1', 5)->values()],
      'penerimaan' => ['Penerimaan Pembiayaan', $baris->where('koderek1', 6)->where('koderek2', 1)->values()],
      'pengeluaran' => ['Pengeluaran Pembiayaan', $baris->where('koderek1', 6)->where('koderek2', 2)->values()],
    ];

    return ['kelompok' => $kelompok] + $this->grafikSifat($baris, $tab['idUnit'], $tahun) + $tab;
  }

  /**
   * Grafik rekap berbasis sifat, algoritma grafik_bebasis_sifat v1:
   *   totbl = Σ belanja (rek 5) unit pada T.A ini
   *   persen 5.1 / 5.2 / 5.3 = blj5.x / totbl × 100   (0 semua bila totbl = 0)
   * Hanya 5.1–5.3 seperti v1. Label dari nama COA (v1 menulis langsung
   * "Belanja Pegawai / Barang Jasa / Aset" dari COA lama). Belanja rek 5.4 ke atas
   * tidak masuk grafik v1; porsinya dikembalikan terpisah sebagai catatan.
   */
  private function grafikSifat($baris, array $idUnit, int $tahun): array
  {
    $belanja = $baris->where('koderek1', 5);
    $totbl = (float) $belanja->sum('jumlah');

    $nama = DB::table('ueu_tbl_rek2 as r2')->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->where('r1.koderek1', 5)->pluck('r2.namarek2', 'r2.koderek2');

    $komposisi = collect([1, 2, 3])->map(function ($k2) use ($belanja, $totbl, $nama) {
      $jumlah = (float) $belanja->where('koderek2', $k2)->sum('jumlah');

      return (object) [
        'kode' => '5.' . $k2,
        'nama' => $nama[$k2] ?? "Belanja 5.{$k2}",
        'jumlah' => $jumlah,
        'persen' => $totbl > 0 ? $jumlah / $totbl * 100 : 0,
      ];
    });

    $lain = (float) $belanja->filter(fn ($r) => (int) $r->koderek2 > 3)->sum('jumlah');

    // Tambahan v2: belanja per jenis biaya (rekening level 3), terbesar dulu, hanya yang bernilai.
    $jenis = $this->rinci->query()
      ->whereIn('k.idunit', $idUnit)->where('k.tahun', (string) $tahun)->where('r1.koderek1', '5')
      ->groupBy('r1.koderek1', 'r2.koderek2', 'r3.koderek3', 'r3.namarek3')
      ->selectRaw("CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3) as kode, r3.namarek3 as nama, SUM(" . RincianAnggaran::SQL_NILAI . ') as jumlah')
      ->get()
      ->filter(fn ($r) => (float) $r->jumlah > 0)
      ->sortByDesc(fn ($r) => (float) $r->jumlah)
      ->map(fn ($r) => (object) [
        'kode' => $r->kode,
        'nama' => $r->nama,
        'jumlah' => (float) $r->jumlah,
        'persen' => $totbl > 0 ? (float) $r->jumlah / $totbl * 100 : 0,
      ])->values();

    return [
      'komposisi' => $komposisi,
      'totalBelanja' => $totbl,
      'belanjaLain' => ['jumlah' => $lain, 'persen' => $totbl > 0 ? $lain / $totbl * 100 : 0],
      'jenisBiaya' => $jenis,
    ];
  }

  private function konteks(): array
  {
    $unit = Auth::user()->unit;
    abort_unless($unit, 404, 'Akun ini tidak terhubung ke unit kerja.');

    return [$unit, (int) (session('periode') ?: Info::tahun(['aktif' => 'enable']))];
  }
}
