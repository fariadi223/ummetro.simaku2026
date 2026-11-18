<?php

namespace App\Services;

use App\Models\PaguUnit;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Klinik anggaran oleh reviewer (wewenang 'review'), padanan controller Reviewer v1
 * (unit_tabel, kegiatan, review_simpan) dan Lib_config::jml_kegiatan.
 *
 *   tbl_review           : penetapan reviewer → unit per T.A (diatur admin, Setting > Penetapan Review)
 *   tbl_review_kegiatan  : hasil review satu reviewer untuk satu kegiatan
 *                          status 'disetujui' | 'ditolak' (= minta revisi), keterangan = komentar,
 *                          diperbaiki 'Ya' = unit sudah mengirim perbaikan (Selesai diperbaiki)
 *
 * Berbeda dengan v1, reviewer hanya bisa membuka unit yang ditetapkan kepadanya pada T.A tsb.
 */
class ReviewKegiatan
{
  public function __construct(private RincianAnggaran $rinci)
  {
  }

  /** Unit yang ditetapkan ke reviewer pada T.A, atau null bila tidak ditetapkan. */
  public function unitDitugaskan(int $idUser, int $tahun, string $kode): ?Unit
  {
    return Unit::where('kode', $kode)
      ->whereIn('id_unit', DB::table('tbl_review')->where('user_id', $idUser)->where('tahun', (string) $tahun)->select('unit_id'))
      ->first();
  }

  /**
   * Daftar unit klinik reviewer pada T.A beserta ringkasan (unit_tabel v1). v1 menampilkan satu baris per
   * pagu unit; di sini satu baris per unit dengan pagu dijumlahkan.
   *
   *   pagu      = Σ ueu_tbl_pagu_unit.nominal unit T.A
   *   belanja   = Σ rinci rek 5 kegiatan unit T.A
   *   kegiatan  = jumlah kegiatan unit T.A
   *   disetujui / direvisi = penilaian reviewer ini; ajuan_revisi = direvisi yang sudah diperbaiki unit
   *   belum     = kegiatan yang belum dinilai reviewer ini
   */
  public function daftarUnit(int $idUser, int $tahun): Collection
  {
    $th = (string) $tahun;
    $unit = DB::table('tbl_review as rv')
      ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'rv.unit_id')
      ->where('rv.user_id', $idUser)->where('rv.tahun', $th)
      ->orderBy('u.kode')->distinct()
      ->get(['u.id_unit', 'u.kode', 'u.nama']);
    $id = $unit->pluck('id_unit')->all() ?: [0];

    $pagu = PaguUnit::whereIn('idunit', $id)->where('tahun', $th)->groupBy('idunit')
      ->selectRaw('idunit, SUM(nominal) as total')->pluck('total', 'idunit');
    $belanja = $this->rinci->query()->whereIn('k.idunit', $id)->where('k.tahun', $th)->where('r1.koderek1', '5')
      ->groupBy('k.idunit')->selectRaw('k.idunit, SUM(' . RincianAnggaran::SQL_NILAI . ') as total')->pluck('total', 'idunit');
    $kegiatan = DB::table('ueu_tbl_kegiatan')->whereIn('idunit', $id)->where('tahun', $th)
      ->groupBy('idunit')->selectRaw('idunit, COUNT(*) as n')->pluck('n', 'idunit');
    $nilai = DB::table('tbl_review_kegiatan as rk')
      ->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 'rk.kegiatan_id')
      ->where('rk.user_id', $idUser)->whereIn('k.idunit', $id)->where('k.tahun', $th)
      ->groupBy('k.idunit')
      ->selectRaw("k.idunit,
        SUM(rk.status = 'disetujui') as disetujui,
        SUM(rk.status = 'ditolak') as direvisi,
        SUM(rk.status = 'ditolak' AND rk.diperbaiki = 'Ya') as ajuan_revisi")
      ->get()->keyBy('idunit');

    return $unit->each(function ($u) use ($pagu, $belanja, $kegiatan, $nilai) {
      $n = $nilai[$u->id_unit] ?? null;
      $u->pagu = (float) ($pagu[$u->id_unit] ?? 0);
      $u->belanja = abs((float) ($belanja[$u->id_unit] ?? 0));
      $u->persen = $u->pagu > 0 ? $u->belanja / $u->pagu * 100 : 0;
      $u->kegiatan = (int) ($kegiatan[$u->id_unit] ?? 0);
      $u->disetujui = (int) ($n->disetujui ?? 0);
      $u->direvisi = (int) ($n->direvisi ?? 0);
      $u->ajuan_revisi = (int) ($n->ajuan_revisi ?? 0);
      $u->belum = max(0, $u->kegiatan - $u->disetujui - $u->direvisi);
    });
  }

  /** Penilaian reviewer ini per idkeg: [idkeg => object{status, diperbaiki, keterangan}]. */
  public function penilaian(int $idUser, array $idKeg): Collection
  {
    return DB::table('tbl_review_kegiatan')->where('user_id', $idUser)->whereIn('kegiatan_id', $idKeg ?: [0])
      ->orderBy('id')->get(['id', 'kegiatan_id', 'status', 'diperbaiki', 'keterangan'])
      ->keyBy('kegiatan_id');
  }

  /**
   * Simpan penilaian (review_simpan v1): satu baris per (reviewer, kegiatan), diperbarui bila sudah ada.
   * Setiap penilaian baru mengosongkan tanda "diperbaiki", sehingga unit harus mengirim perbaikan lagi
   * bila diminta revisi kembali.
   */
  public function simpan(int $idUser, int $idUnit, int $idKeg, string $status, ?string $keterangan): void
  {
    $data = [
      'unit_id' => $idUnit,
      'status' => $status,
      'keterangan' => $keterangan,
      'diperbaiki' => null,
    ];

    DB::transaction(function () use ($idUser, $idKeg, $data) {
      $ada = DB::table('tbl_review_kegiatan')->where('user_id', $idUser)->where('kegiatan_id', $idKeg)
        ->lockForUpdate()->orderBy('id')->value('id');

      $ada
        ? DB::table('tbl_review_kegiatan')->where('id', $ada)->update($data)
        : DB::table('tbl_review_kegiatan')->insert($data + ['user_id' => $idUser, 'kegiatan_id' => $idKeg]);
    });
  }

  /**
   * Ringkasan anggaran unit untuk halaman review (grafik kegiatan v1):
   *   fungsi = pendapatan & belanja per bidang (MAK 1, kegiatan.idmak1)
   *   sifat  = porsi belanja pegawai (5.1), barang & jasa (5.2), aset (5.3) terhadap total belanja
   */
  public function ringkasan(Unit $unit, int $tahun, Collection $bidang): array
  {
    $dasar = fn () => $this->rinci->query()->where('k.idunit', $unit->id_unit)->where('k.tahun', (string) $tahun);

    $fungsi = $dasar()->whereIn('r1.koderek1', ['4', '5'])->groupBy('k.idmak1', 'r1.koderek1')
      ->selectRaw('k.idmak1, r1.koderek1 as kode, SUM(' . RincianAnggaran::SQL_NILAI . ') as total')->get();
    $ambil = fn ($idmak1, $kode) => (float) ($fungsi->first(fn ($n) => (int) $n->idmak1 === (int) $idmak1 && (string) $n->kode === $kode)->total ?? 0);

    $perBidang = $bidang->map(fn ($b) => (object) [
      'kode' => $b->kodemak1,
      'nama' => trim($b->namamak1),
      'pendapatan' => $ambil($b->idmak1, '4'),
      'belanja' => $ambil($b->idmak1, '5'),
    ]);

    $sifat = $dasar()->where('r1.koderek1', '5')->whereIn('r2.koderek2', ['1', '2', '3'])->groupBy('r2.koderek2')
      ->selectRaw('r2.koderek2 as kode, SUM(' . RincianAnggaran::SQL_NILAI . ') as total')->pluck('total', 'kode');
    $totalBelanja = (float) $dasar()->where('r1.koderek1', '5')->selectRaw('SUM(' . RincianAnggaran::SQL_NILAI . ') as total')->value('total');

    $porsi = collect(['1' => 'Belanja Pegawai', '2' => 'Belanja Barang & Jasa', '3' => 'Belanja Aset'])
      ->map(fn ($nama, $kode) => (object) [
        'nama' => $nama,
        'nilai' => (float) ($sifat[$kode] ?? 0),
        'persen' => $totalBelanja > 0 ? (float) ($sifat[$kode] ?? 0) / $totalBelanja * 100 : 0,
      ])->values();

    return [
      'perBidang' => $perBidang,
      'sifat' => $porsi,
      'pendapatan' => $perBidang->sum('pendapatan'),
      'belanja' => $totalBelanja,
    ];
  }
}
