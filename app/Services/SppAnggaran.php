<?php

namespace App\Services;

use App\Models\Info;
use App\Models\Unit;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan bersama SPP (ueu_tbl_spp) untuk pengajuan (sub unit) dan otorisasi
 * (unit induk). Satu baris ueu_tbl_spp = satu kegiatan dalam satu nomor SPP.
 */
class SppAnggaran
{
  public function __construct(private RincianAnggaran $rinci)
  {
  }

  /** Batas waktu pembuatan SPP (Simaku::tutup_spp v1): aktif bila akhir_angg masih > hari ini. */
  public function jadwal(int $tahun): array
  {
    $akhir = Info::where('th_angg', (string) $tahun)->value('akhir_angg');

    try {
      $akhir = $akhir ? Carbon::parse($akhir)->startOfDay() : null;
    } catch (\Throwable) {
      $akhir = null;
    }

    $sisa = $akhir ? (int) today()->diffInDays($akhir, false) : null;

    return ['akhir' => $akhir, 'sisa_hari' => $sisa, 'aktif' => $sisa !== null && $sisa > 0];
  }

  /**
   * ueu_tbl_spp + kegiatan + MAK + unit (get_keg_spp / LikeSPP_Keg v1), tanpa filter unit.
   * Sudah ber-select: jangan pakai ->value()/->pluck() kolom lain (lihat first()).
   */
  public function query(): Builder
  {
    return DB::table('ueu_tbl_spp as s')
      ->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 's.idkeg')
      ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'k.idunit')
      ->join('ueu_tbl_mak4 as m4', 'm4.idmak4', '=', 'k.idmak4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->orderBy('m1.kodemak1')->orderBy('m2.kodemak2')->orderBy('m3.kodemak3')->orderBy('m4.kodemak4')
      ->select('s.*', 'k.uraiankeg', 'k.idunit', 'm4.namamak4', 'u.kode as kode_unit', 'u.nama as nama_unit',
        DB::raw("CONCAT_WS('.', m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode_mak"));
  }

  /**
   * Anggaran belanja (rek 5) & yang sudah dicairkan (SPP ber-SPM Disetujui) per kegiatan
   * (jml_rinci_v1 & jml_ajuan v1). $kecuali = nomor SPP yang tidak ikut dihitung.
   *
   * @return array<int, object{anggaran: float, dicairkan: float}>
   */
  public function nilaiKegiatan(array $idKeg, int $tahun, ?string $kecuali = null): array
  {
    $anggaran = $this->rinci->query()
      ->whereIn('k.idkeg', $idKeg ?: [0])->where('k.tahun', (string) $tahun)->where('r1.koderek1', '5')
      ->groupBy('k.idkeg')
      ->selectRaw('k.idkeg, SUM(' . RincianAnggaran::SQL_NILAI . ') as total')
      ->pluck('total', 'idkeg');

    $cair = DB::table('ueu_tbl_spp')->whereIn('idkeg', $idKeg ?: [0])->where('status_spm', 'Disetujui')
      ->when($kecuali, fn ($q) => $q->where('no_spp', '!=', $kecuali))
      ->groupBy('idkeg')->selectRaw('idkeg, SUM(ajuan) as total')->pluck('total', 'idkeg');

    $hasil = [];
    foreach ($idKeg as $id) {
      $hasil[$id] = (object) ['anggaran' => (float) ($anggaran[$id] ?? 0), 'dicairkan' => (float) ($cair[$id] ?? 0)];
    }

    return $hasil;
  }

  /** Lengkapi baris SPP satu nomor dengan anggaran / pernah diterima / tersedia (lembar SPP). */
  public function lengkapiNilai(Collection $baris, int $tahun, string $nomor): Collection
  {
    $nilai = $this->nilaiKegiatan($baris->pluck('idkeg')->all(), $tahun, $nomor);

    return $baris->each(function ($b) use ($nilai) {
      $n = $nilai[$b->idkeg];
      $b->anggaran = $n->anggaran;
      // Lebih tepat dari v1: "pernah diterima" tidak menghitung SPP ini sendiri.
      $b->diterima = $n->dicairkan;
      $b->tersedia = $n->anggaran - $n->dicairkan;
    });
  }

  /** Ringkas baris-baris SPP per nomor (get_group_nospp / LikeGroupSPP v1). */
  public function kelompokkan(Collection $baris): Collection
  {
    return $baris->groupBy('no_spp')
      ->map(function (Collection $b) {
        $s = clone $b->first();
        $s->kegiatan = $b->count();
        $s->nominal = (float) $b->sum('ajuan');
        $s->baris = $b->values();
        // Alasan penolakan/pembatalan: alasan1..4 pertama yang terisi (Mspp::alasan_batal v1).
        $s->alasan = $b->flatMap(fn ($x) => [$x->alasan1, $x->alasan2, $x->alasan3, $x->alasan4])
          ->map(fn ($a) => trim((string) $a))->first(fn ($a) => $a !== '');

        return $s;
      })
      ->sortByDesc(fn ($s) => (int) $s->no_spp)
      ->values();
  }

  /**
   * Data lembar SPM siap cetak (Dashboard::spm_cetak v1), dipakai Operator SPM, otorisator BAK/WR II,
   * dan pimpinan unit induk (view content.adminspm.spm.cetak).
   *
   *   Kegiatan & ajuan semua baris nomor SPM pada T.A, pernyataan unit, tanda tangan pimpinan unit
   *   induk FF.00, persetujuan BAK (otorisator1) & WR II (otorisator2). Spesimen tanda tangan dari
   *   storage/app/public/<simaku.specimen_dir>/{kode induk|otorisator1|otorisator2}.png bila berkasnya ada.
   */
  public function dataCetakSpm(int $tahun, string $noSpm): array
  {
    $baris = $this->query()->where('s.tahun', (string) $tahun)->where('s.no_spm', $noSpm)->get();
    $ringkas = $baris->first() ?? abort(404);

    // v1: kode unit dari nomor SPM ("N/SPM/<kode>/..."), penanda tangan = unit induk FF.00.
    $kodeUnit = (string) $ringkas->kode_unit;
    $induk = Unit::where('kode', explode('.', $kodeUnit)[0] . '.00')->first();

    $ttd = fn (string $w) => DB::table('ueu_tbl_user as t1')->join('ueu_tbl_unit as t0', 't0.id_unit', '=', 't1.id_unit')
      ->where('t1.wewenang', $w)->orderBy('t1.id_unit')->first(['t0.kode', 't0.nama', 't0.nama_pimp', 't0.an_nama_pimp']);

    return [
      'tahun' => $tahun,
      'noSpm' => $noSpm,
      'ringkas' => $ringkas,
      'baris' => $baris,
      'unit' => Unit::where('kode', $kodeUnit)->first(),
      'induk' => $induk,
      'ttd1' => $ttd('otorisator1'),
      'ttd2' => $ttd('otorisator2'),
      'spesimen' => [
        'induk' => $induk ? $this->spesimen($induk->kode) : null,
        'bak' => $ringkas->verifikasi1 === 'Disetujui' ? $this->spesimen('otorisator1') : null,
        'wr2' => $ringkas->verifikasi2 === 'Disetujui' ? $this->spesimen('otorisator2') : null,
      ],
    ];
  }

  /**
   * URL gambar spesimen tanda tangan bila berkasnya ada: storage/app/public/<specimen_dir>/<nama>.png,
   * disajikan lewat symlink public/storage (v1: images/specimen/<nama>.png).
   */
  private function spesimen(string $nama): ?string
  {
    $rel = trim((string) config('simaku.specimen_dir', 'images/specimen'), '/') . '/' . $nama . '.png';

    return is_file(storage_path('app/public/' . $rel)) ? asset('storage/' . $rel) : null;
  }
}
