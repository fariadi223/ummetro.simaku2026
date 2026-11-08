<?php

namespace App\Services;

use App\Models\Kegiatan;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Status review kegiatan & aturan boleh ubah, padanan Lib_config::status_kegiatan
 * dan logika $otoris/$update/$selsai di view v1 (kegiatan & kegiatan_rinci).
 *
 *   lengkap       = semua reviewer unit sudah menilai (jumlah == reviewer, > 0)
 *   perlu_revisi  = lengkap dan tidak semua menyetujui
 *   boleh ubah    = komite dibuka, ATAU perlu revisi dan revisi belum dikirim
 *                   ("selesai diperbaiki"), ATAU config simaku.update_force_kegiatan
 */
class StatusKegiatan
{
  public function __construct(private KomiteAnggaran $komite)
  {
  }

  public function jumlahReviewer(Unit $unit, int $tahun): int
  {
    return DB::table('tbl_review')->where('unit_id', $unit->id_unit)->where('tahun', (string) $tahun)->count();
  }

  /** @return array<int, object> status per idkeg */
  public function review(Unit $unit, int $tahun, array $idKeg): array
  {
    $reviewer = $this->jumlahReviewer($unit, $tahun);
    $baris = DB::table('tbl_review_kegiatan as rk')
      ->leftJoin('ueu_tbl_user as u', 'u.idlog', '=', 'rk.user_id')
      ->whereIn('rk.kegiatan_id', $idKeg ?: [0])
      ->get(['rk.kegiatan_id', 'rk.status', 'rk.diperbaiki', 'rk.keterangan', 'u.nama_lengkap', 'u.username'])
      ->groupBy('kegiatan_id');

    $hasil = [];
    foreach ($idKeg as $id) {
      $r = $baris[$id] ?? collect();
      $setuju = $r->where('status', 'disetujui')->count();
      $tolak = $r->where('status', 'ditolak')->count();
      $lengkap = ($setuju + $tolak) > 0 && ($setuju + $tolak) === $reviewer;

      $hasil[$id] = (object) [
        'disetujui' => $setuju,
        'ditolak' => $tolak,
        'belum_diperbaiki' => $r->filter(fn ($x) => $x->diperbaiki !== 'Ya')->count(),
        'reviewer' => $reviewer,
        'lengkap' => $lengkap,
        'perlu_revisi' => $lengkap && $setuju !== $reviewer,
        'semua_setuju' => $reviewer > 0 && $setuju === $reviewer,
        'catatan' => $r->filter(fn ($x) => trim((string) $x->keterangan) !== '')
          ->map(fn ($x) => (object) ['status' => $x->status, 'oleh' => $x->nama_lengkap ?: $x->username, 'teks' => trim($x->keterangan)])
          ->values(),
      ];
    }

    return $hasil;
  }

  public function bolehUbah(array $komite, object $review): bool
  {
    if (config('simaku.update_force_kegiatan')) {
      return true;
    }

    return $komite['dibuka'] || ($review->perlu_revisi && $review->belum_diperbaiki > 0);
  }

  public function bolehUbahKegiatan(Unit $unit, int $tahun, Kegiatan $keg): bool
  {
    return $this->bolehUbah($this->komite->status($unit, $tahun), $this->review($unit, $tahun, [$keg->idkeg])[$keg->idkeg]);
  }
}
