<?php

namespace App\Http\Controllers\pages;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\PaguUnit;
use App\Models\Unit;
use App\Services\NeracaPercobaan;
use App\Services\RealisasiSpm;
use App\Services\RincianAnggaran;
use App\Services\SppAnggaran;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Halaman awal tiap area wewenang. Area unit sudah punya dashboard sendiri
 * (padanan Unit::index di v1); area lain masih halaman sementara sampai
 * modulnya dipindahkan.
 */
class DashboardController extends Controller
{
  public function index(RincianAnggaran $rinci)
  {
    $user = Auth::user();

    if ($user->hasAnyRole('unit') && $user->unit) {
      return $this->unit($user->unit, $rinci);
    }

    if ($user->hasAnyRole('operatorspm')) {
      return $this->operatorSpm($user, app(SppAnggaran::class));
    }

    if ($user->hasAnyRole(['otorisator1', 'otorisator2'])) {
      return $this->otorisator($user, app(SppAnggaran::class));
    }

    if ($user->hasAnyRole('akuntansi')) {
      return $this->akuntansi($user, app(SppAnggaran::class));
    }

    if ($user->hasAnyRole('kasir')) {
      return $this->kasir($user, app(RealisasiSpm::class));
    }

    return view('content.dashboard.dashboard-simaku', [
      'user' => $user,
      'unit' => $user->unit,
      'periode' => session('periode'),
    ]);
  }

  /**
   * Dashboard unit: profil (visi, misi, tujuan) seperti v1, ditambah
   * informasi tahun anggaran yang sedang dikerjakan dan pagu unit.
   * Unit induk (/adminunit) melihat daftar sub unit sebagai ganti profil.
   */
  private function unit(Unit $unit, RincianAnggaran $rinci)
  {
    $tahun = (int) (session('periode') ?: Info::tahun(['aktif' => 'enable']));
    $info = Info::where('th_angg', $tahun)->first();
    $hariIni = Carbon::today();

    $tgl = function (?string $teks): ?Carbon {
      try {
        return $teks ? Carbon::parse($teks)->startOfDay() : null;
      } catch (\Throwable) {
        return null;
      }
    };

    $komite = app(\App\Services\KomiteAnggaran::class)->status($unit, $tahun);

    $jadwal = null;
    if ($info) {
      $awal = $tgl($info->awal_angg);
      $akhir = $tgl($info->akhir_angg);
      $akhirKomite = $tgl($info->akhir_komite);

      $jadwal = [
        'awal_angg' => $awal,
        'akhir_angg' => $akhir,
        'awal_komite' => $tgl($info->awal_komite),
        'akhir_komite' => $akhirKomite,
        // Persentase masa anggaran yang sudah berjalan.
        'berjalan' => ($awal && $akhir && $akhir->gt($awal))
          ? max(0, min(100, $awal->diffInDays($hariIni, false) / $awal->diffInDays($akhir) * 100))
          : null,
        // Aturan v1 (Simaku::aktive), sama dengan halaman kegiatan: lihat KomiteAnggaran.
        'komite_buka' => ($komite['sisa_hari'] ?? 0) > 0,
        'sisa_hari_komite' => $komite['sisa_hari'],
        'aktif' => $info->aktif === 'enable',
        'jurnal' => $info->penjurnalan === 'open',
      ];
    }

    $belanja = $rinci->belanjaPerUnitPagu((string) $tahun, $unit->id_unit)[$unit->id_unit] ?? [];
    $pagu = PaguUnit::with('pagu:id_pagu,sumber')
      ->where('tahun', $tahun)->where('idunit', $unit->id_unit)
      ->get()
      ->each(fn ($p) => $p->pakai = $belanja[$p->idpagu] ?? 0);

    // Unit induk (FF.00, area /adminunit): tampilkan sub unit, tanpa visi & misi.
    $subUnit = $unit->is_induk ? $this->subUnit($unit, $tahun, $rinci) : null;

    return view('content.dashboard.dashboard-unit', [
      'unit' => $unit,
      'tahun' => $tahun,
      'jadwal' => $jadwal,
      'klinik' => $unit->klinik_aktif,
      'pagu' => $pagu,
      'jumlahKegiatan' => DB::table('ueu_tbl_kegiatan')->where('idunit', $unit->id_unit)->where('tahun', (string) $tahun)->count(),
      'subUnit' => $subUnit,
      'profil' => $subUnit ? null : [
        'visi' => ['Visi', 'ti-eye', $unit->visi],
        'misi' => ['Misi', 'ti-flag', $unit->misi],
        'tu' => ['Tujuan Umum', 'ti-target', $unit->tu],
        'tk' => ['Tujuan Khusus', 'ti-target-arrow', $unit->tk],
      ],
    ]);
  }

  /**
   * Dashboard Operator SPM (area /adminspm). v1 (Adminspm::index) hanya sambutan + profil;
   * di sini ditambah antrian kerja, memakai kriteria yang sama dengan v1:
   *   menunggu verifikasi = otorisasi Sudah, status_spp ≠ Ditolak, no_spm kosong (Adminspm::verifikasi qry2)
   *   SPM belum diproses  = no_spm terisi, status_spm Belum diproses, status_spp ≠ Ditolak (qry3)
   *   SPM disetujui       = no_spm terisi, status_spm Disetujui, status_spp ≠ Ditolak (Adminspm::list_spm)
   *   SPP ditolak         = status_spp Ditolak
   * Semua per T.A periode login (filter tahun pada SPP, seperti v1).
   */
  private function operatorSpm($user, SppAnggaran $spp)
  {
    $tahun = (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
    $dasar = fn () => $spp->query()->where('s.tahun', (string) $tahun)->where('s.create_spp', '1');

    $verifikasi = $spp->kelompokkan($dasar()->where('s.otorisasi', 'Sudah')->where('s.status_spp', '!=', 'Ditolak')->where('s.no_spm', '')->get())
      ->sortBy('tgl_saji')->values();
    $spmProses = $dasar()->where('s.no_spm', '!=', '')->where('s.status_spm', 'Belum diproses')->where('s.status_spp', '!=', 'Ditolak')->get()
      ->groupBy('no_spm')->map(fn ($b) => (object) [
        'no_spm' => $b->first()->no_spm, 'tgl_spm' => $b->first()->tgl_spm, 'kode_unit' => $b->first()->kode_unit,
        'nama_unit' => $b->first()->nama_unit, 'spp' => $b->pluck('no_spp')->unique()->count(), 'nominal' => (float) $b->sum('ajuan'),
      ])->sortBy('tgl_spm')->values();
    $spmSetuju = $dasar()->where('s.no_spm', '!=', '')->where('s.status_spm', 'Disetujui')->where('s.status_spp', '!=', 'Ditolak')
      ->get(['s.no_spm', 's.ajuan']);
    $ditolak = $spp->kelompokkan($dasar()->where('s.status_spp', 'Ditolak')->get());

    return view('content.dashboard.dashboard-spm', [
      'user' => $user,
      'unit' => $user->unit,
      'tahun' => $tahun,
      'ringkas' => [
        'verifikasi' => ['n' => $verifikasi->count(), 'nominal' => $verifikasi->sum('nominal')],
        'spm_proses' => ['n' => $spmProses->count(), 'nominal' => $spmProses->sum('nominal')],
        'spm_setuju' => ['n' => $spmSetuju->pluck('no_spm')->unique()->count(), 'nominal' => (float) $spmSetuju->sum('ajuan')],
        'ditolak' => ['n' => $ditolak->count(), 'nominal' => $ditolak->sum('nominal')],
      ],
      'antrianVerifikasi' => $verifikasi->take(8),
      'antrianSpm' => $spmProses->take(8),
    ]);
  }

  /**
   * Dashboard otorisator SPM (area /dashboard): otorisator1 = BAK (Kepala Bagian Keuangan),
   * otorisator2 = WR II (Wakil Rektor 2). v1 (Dashboard::index)
   * hanya sambutan + profil; di sini ditambah antrian persetujuan SPM, kriteria sama dengan v1:
   *   SPM proses        = no_spm terisi, status_spm Belum diproses, status_spp ≠ Ditolak (otorisasi_one)
   *   menunggu BAK      = SPM proses & verifikasi1 kosong     (BAK: verifikasi1 Disetujui/Ditolak)
   *   menunggu WR II    = SPM proses & verifikasi1 Disetujui & verifikasi2 kosong (otorisasi_two)
   *   ditolak           = SPM proses & verifikasi1/2 Ditolak (menunggu dibatalkan Operator SPM)
   *   disetujui         = status_spm Disetujui (setelah WR II menyetujui)
   */
  private function otorisator($user, SppAnggaran $spp)
  {
    $peran = $user->role() === 'otorisator2' ? 'wr2' : 'bak';
    $tahun = (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
    $dasar = fn () => $spp->query()->where('s.tahun', (string) $tahun)->where('s.no_spm', '!=', '')->where('s.status_spp', '!=', 'Ditolak');

    // Satu SPM = beberapa baris SPP; tanggal terbit diambil dari nomor "N/SPM/<kode>/dd/mm/YYYY".
    $kelompok = fn ($baris) => $baris->groupBy('no_spm')->map(function ($b) {
      $s = clone $b->first();
      $s->nominal = (float) $b->sum('ajuan');
      $s->kegiatan = $b->count();
      $p = explode('/', (string) $s->no_spm);
      try {
        $s->tgl_terbit = count($p) >= 6 ? Carbon::createFromFormat('d/m/Y', "{$p[3]}/{$p[4]}/{$p[5]}")->startOfDay() : null;
      } catch (\Throwable) {
        $s->tgl_terbit = null;
      }

      return $s;
    })->values();

    $proses = $kelompok($dasar()->where('s.status_spm', 'Belum diproses')->get());
    $menungguBak = $proses->filter(fn ($s) => trim((string) $s->verifikasi1) === '')
      ->sortBy(fn ($s) => $s->tgl_terbit?->timestamp ?? PHP_INT_MAX)->values();
    $menungguWr2 = $proses->filter(fn ($s) => $s->verifikasi1 === 'Disetujui' && trim((string) $s->verifikasi2) === '')
      ->sortBy(fn ($s) => $s->tgl_terbit?->timestamp ?? PHP_INT_MAX)->values();
    $ditolak = $proses->filter(fn ($s) => $s->verifikasi1 === 'Ditolak' || $s->verifikasi2 === 'Ditolak');
    $disetujui = $kelompok($dasar()->where('s.status_spm', 'Disetujui')->get());

    $ringkas = fn ($c) => ['n' => $c->count(), 'nominal' => $c->sum('nominal')];

    return view('content.dashboard.dashboard-otorisator', [
      'user' => $user,
      'unit' => $user->unit,
      'tahun' => $tahun,
      'ringkas' => [
        'bak' => $ringkas($menungguBak),
        'wr2' => $ringkas($menungguWr2),
        'ditolak' => $ringkas($ditolak),
        'disetujui' => $ringkas($disetujui),
      ],
      // Antrian = SPM yang menunggu keputusan otorisator yang login.
      'peran' => $peran,
      'antrian' => ($peran === 'wr2' ? $menungguWr2 : $menungguBak)->take(8),
      // Halaman otorisasi SPM (route otorisator.spm.*) tersedia untuk BAK & WR II.
      'bisaBuka' => true,
    ]);
  }

  /**
   * Dashboard Akuntansi (area /akuntansi). v1 (Akuntansi::index) hanya sambutan + T.A;
   * di sini ditambah antrian penjurnalan dengan kriteria yang sama dengan menu v1:
   *   SPM belum dijurnal  = ueu_tbl_spp status_spm Disetujui & jurnalspm kosong (spm_real)
   *   BKK belum dijurnal  = ueu_tbl_bkk status_bkk Tunai tanpa jurnal ber-bukti no_bkk (jurnalbkk_tabel)
   *   BKM belum dijurnal  = ueu_tbl_bkm status_bkm Tunai tanpa jurnal bukti no_bkm & kredit = nominal (jurnalbkm_tabel)
   *   LPJ menunggu        = ueu_tbl_lpj status_lpj Belum diverifikasi (lpjunit)
   * Ringkasan jurnal dari ueu_tbl_jurnal_spm; saldo kas dari Neraca Percobaan (saldo awal + mutasi, tanpa TB):
   * Kas Universitas (569), Kas di Unit Kerja (570), bank & deposito, total kas & setara kas (= Posisi Keuangan).
   */
  private function akuntansi($user, SppAnggaran $spp)
  {
    $tahun = (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
    $th = (string) $tahun;
    $info = Info::where('th_angg', $th)->first();

    // SPM disetujui yang belum dijurnal, satu SPM = beberapa baris SPP.
    $spm = $spp->query()->where('s.tahun', $th)->where('s.status_spm', 'Disetujui')->where('s.jurnalspm', '')
      ->where('s.no_spm', '!=', '')->get()
      ->groupBy('no_spm')->map(fn ($b) => (object) [
        'no_spm' => $b->first()->no_spm, 'kode_unit' => $b->first()->kode_unit, 'nama_unit' => $b->first()->nama_unit,
        'tgl' => $b->max('tgl_spm'), 'kegiatan' => $b->count(), 'nominal' => (float) $b->sum('ajuan'),
      ])->sortBy('tgl')->values();

    // Bukti yang sudah dijurnal: cek per nomor lewat indeks (tahun, bukti) — tabel jurnal ±800 rb baris.
    $sudahJurnal = function (Collection $bukti) use ($th) {
      return $bukti->chunk(1000)->flatMap(fn ($c) => DB::table('ueu_tbl_jurnal_spm')->where('tahun', $th)
        ->whereIn('bukti', $c->values()->all())->distinct()->pluck('bukti'))->flip();
    };

    $bkkTunai = DB::table('ueu_tbl_bkk')->where('tahun', $th)->where('status_bkk', 'Tunai')
      ->get(['id_bkk', 'no_bkk', 'uraian', 'penerima', 'nominal', 'tgl_tunai']);
    $bkkJurnal = $sudahJurnal($bkkTunai->pluck('no_bkk'));
    $bkk = $bkkTunai->reject(fn ($b) => isset($bkkJurnal[$b->no_bkk]))->sortBy('tgl_tunai')->values();

    $bkmTunai = DB::table('ueu_tbl_bkm')->where('tahun', $th)->where('status_bkm', 'Tunai')->get(['no_bkm', 'nominal']);
    $bkmJurnal = $bkmTunai->isEmpty() ? collect() : DB::table('ueu_tbl_jurnal_spm')->where('tahun', $th)
      ->whereIn('bukti', $bkmTunai->pluck('no_bkm')->all())->get(['bukti', 'kredit'])
      ->map(fn ($j) => $j->bukti . '|' . (float) $j->kredit)->flip();
    $bkm = $bkmTunai->reject(fn ($b) => isset($bkmJurnal[$b->no_bkm . '|' . (float) $b->nominal]));

    $lpj = DB::table('ueu_tbl_lpj')->where('tahun', $th)->where('status_lpj', 'Belum diverifikasi')
      ->selectRaw('COUNT(*) as n, COALESCE(SUM(ajuan), 0) as nominal')->first();

    $statistik = $this->statistikJurnal($tahun);

    $ringkas = fn ($c, $kolom = 'nominal') => ['n' => $c->count(), 'nominal' => (float) $c->sum($kolom)];

    return view('content.dashboard.dashboard-akuntansi', [
      'user' => $user,
      'tahun' => $tahun,
      'penjurnalan' => $info?->penjurnalan === 'open',
      'ringkas' => [
        'spm' => $ringkas($spm),
        'bkk' => $ringkas($bkk),
        'bkm' => $ringkas($bkm),
        'lpj' => ['n' => (int) $lpj->n, 'nominal' => (float) $lpj->nominal],
      ],
      'jurnal' => $statistik['jurnal'],
      'perBulan' => $statistik['perBulan'],
      'kas' => $statistik['kas'],
      'statistikPada' => $statistik['dihitung'],
      'antrianSpm' => $spm->take(8),
      'antrianBkk' => $bkk->take(8),
    ]);
  }

  /**
   * Ringkasan jurnal T.A, per bulan, dan saldo kas untuk dashboard akuntansi, di-cache (Redis).
   *
   * Tanpa cache, bagian ini menjalankan Neraca Percobaan penuh + dua agregasi atas ueu_tbl_jurnal_spm
   * (±800 rb baris) setiap kali dashboard dibuka: ±400 ms untuk T.A penuh.
   *
   * Kunci cache memuat MAX(id_trans) jurnal & saldo awal (±4 ms lewat indeks PK), sehingga jurnal baru —
   * dari v2 maupun v1 yang memakai DB yang sama — langsung membuat cache lama tidak terpakai. Perubahan
   * atau penghapusan baris lama tidak terdeteksi oleh sidik ini; itu dibatasi TTL, dan pengguna bisa
   * memaksa hitung ulang dengan ?segarkan=1 (tombol di dashboard).
   *
   * @return array{jurnal: object, perBulan: Collection, kas: array<string, float>, dihitung: Carbon}
   */
  private function statistikJurnal(int $tahun): array
  {
    $th = (string) $tahun;
    $sidik = DB::table('ueu_tbl_jurnal_spm')->max('id_trans') . '-' . DB::table('ueu_tbl_jurnal_saldo')->max('id_trans');
    $kunci = "dashboard.akuntansi.{$th}.{$sidik}";

    if (request()->boolean('segarkan')) {
      Cache::forget($kunci);
    }

    return Cache::remember($kunci, now()->addMinutes(10), function () use ($tahun, $th) {
      $jurnal = DB::table('ueu_tbl_jurnal_spm')->where('tahun', $th)
        ->selectRaw('COUNT(*) as n, COALESCE(SUM(debet), 0) as debet, COALESCE(SUM(kredit), 0) as kredit, MAX(tgl_trk) as terakhir')->first();
      $perBulan = DB::table('ueu_tbl_jurnal_spm')->where('tahun', $th)
        ->selectRaw("DATE_FORMAT(tgl_trk, '%Y-%m') as bln, COUNT(*) as n, SUM(debet) as debet, SUM(kredit) as kredit")
        ->groupBy('bln')->orderBy('bln')->get();
      // Saldo kas = saldo akhir di Neraca Percobaan (saldo awal T.A + mutasi tanpa jurnal TB), sama dengan
      // Laporan Posisi Keuangan & Arus Kas. Kas & setara kas = 1.1.01 Kas, 1.1.02 Bank, 1.1.03 Surat Berharga.
      $akunKas = app(NeracaPercobaan::class)->hitung($tahun)['baris']->where('lv', 6)
        ->filter(fn ($r) => in_array(substr($r->kode, 0, 6), ['1.1.01', '1.1.02', '1.1.03'], true));
      $saldoKas = fn ($cocok) => (float) $akunKas->filter($cocok)->sum('akhir');

      return [
        'jurnal' => $jurnal,
        'perBulan' => $perBulan,
        'kas' => [
          'univ' => $saldoKas(fn ($r) => $r->id === 569),
          'unit' => $saldoKas(fn ($r) => $r->id === 570),
          'bank' => $saldoKas(fn ($r) => !in_array($r->id, [569, 570], true)),
          'total' => $saldoKas(fn ($r) => true),
          'awal' => (float) $akunKas->sum('awal'),
        ],
        'dihitung' => now(),
      ];
    });
  }

  /**
   * Dashboard Kasir (area /kasir). v1 (Kasir::index) langsung menampilkan daftar SPM disetujui;
   * di sini diringkas. Kriteria sudah/belum dibayar & metode: lihat App\Services\RealisasiSpm.
   */
  private function kasir($user, RealisasiSpm $realisasi)
  {
    $tahun = (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
    $spm = $realisasi->daftar($tahun);

    [$dibayar, $belum] = $spm->partition(fn ($s) => $s->bayar !== null);
    $tglSetuju = fn ($s) => $s->tgl_setuju ? Carbon::parse($s->tgl_setuju)->timestamp : PHP_INT_MAX;
    $ringkas = fn ($c) => ['n' => $c->count(), 'nominal' => $c->sum('nominal')];
    $bulanIni = $dibayar->filter(fn ($s) => $s->bayar->tgl_kasir && Carbon::parse($s->bayar->tgl_kasir)->isSameMonth(today()));

    return view('content.dashboard.dashboard-kasir', [
      'user' => $user,
      'unit' => $user->unit,
      'tahun' => $tahun,
      'ringkas' => [
        'belum' => $ringkas($belum),
        'dibayar' => $ringkas($dibayar),
        'tunai' => $ringkas($dibayar->where('metode', 'tunai')),
        'transfer' => $ringkas($dibayar->where('metode', 'transfer')),
        'bulan_ini' => $ringkas($bulanIni),
      ],
      // Antrian pembayaran: terlama disetujui lebih dulu.
      'antrian' => $belum->sortBy($tglSetuju)->values()->take(8),
      'terakhir' => $dibayar->sortByDesc(fn ($s) => $s->bayar->tgl_kasir . '|' . str_pad((string) $s->bayar->id_kasir, 10, '0', STR_PAD_LEFT))->values()->take(6),
    ]);
  }

  /**
   * Sub unit dari unit induk (kode "FF.xx" selain "FF.00", padanan Munit::get_sub_unit v1)
   * beserta jumlah kegiatan, pagu, belanja yang direncanakan, dan nominal yang sudah di-SPP-kan pada T.A.
   */
  private function subUnit(Unit $induk, int $tahun, RincianAnggaran $rinci)
  {
    $sub = Unit::where('kode', 'like', $induk->kode_induk . '.%')->where('kode', '!=', $induk->kode)
      ->orderBy('kode')->get(['id_unit', 'kode', 'nama', 'nama_pimp']);
    $id = $sub->pluck('id_unit')->all();

    $kegiatan = DB::table('ueu_tbl_kegiatan')->whereIn('idunit', $id ?: [0])->where('tahun', (string) $tahun)
      ->groupBy('idunit')->selectRaw('idunit, COUNT(*) as n')->pluck('n', 'idunit');
    $pagu = PaguUnit::whereIn('idunit', $id ?: [0])->where('tahun', $tahun)
      ->groupBy('idunit')->selectRaw('idunit, SUM(nominal) as total')->pluck('total', 'idunit');
    $belanja = $rinci->belanjaPerUnitPagu((string) $tahun);
    // Terpakai = nominal yang sudah di-SPP-kan: SPP terkirim (bukan draf), tidak dibatalkan
    // pimpinan (otorisasi Batal) dan tidak ditolak verifikator (status_spp Ditolak).
    $spp = DB::table('ueu_tbl_spp as s')->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 's.idkeg')
      ->whereIn('k.idunit', $id ?: [0])->where('k.tahun', (string) $tahun)
      ->where('s.create_spp', '1')->where('s.otorisasi', '!=', 'Batal')->where('s.status_spp', '!=', 'Ditolak')
      ->groupBy('k.idunit')->selectRaw('k.idunit, SUM(s.ajuan) as total')->pluck('total', 'idunit');

    return $sub->each(function ($u) use ($kegiatan, $pagu, $belanja, $spp) {
      $u->kegiatan = (int) ($kegiatan[$u->id_unit] ?? 0);
      $u->pagu = (float) ($pagu[$u->id_unit] ?? 0);
      $u->belanja = array_sum($belanja[$u->id_unit] ?? []);
      $u->spp = (float) ($spp[$u->id_unit] ?? 0);
    });
  }
}
