<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Laporan posisi keuangan (neraca), padanan report/Neraca::view v1 (view neraca_v2, helper saldo_awal, mutasi, hitung_neto).
 *
 *   Rentang [awal, akhir] (default seluruh T.A): dua kolom posisi — Saldo Awal (posisi pada tanggal awal, sebelum
 *   transaksinya) dan Saldo Akhir (posisi pada tanggal akhir). Posisi per tanggal X, T.A T:
 *     Aset (rek 1)      = saldo awal T.A + mutasi 01-09-T..X, saldo D − K
 *     Kewajiban (rek 2) = idem, saldo K − D
 *     Aset neto (rek 3) = idem, saldo K − D, ditambah surplus berjalan rek 4 & 5 01-09-T..X:
 *                         sumber dana Tanpa Pembatasan → 3.1.01, Pembatasan → 3.2.01 (neto_tp / neto_dp v1)
 *   Mutasi tanpa jurnal TB. Nilai per akun level 3 diambil dari NeracaPercobaan (kolom saldo akhir), sehingga
 *   kedua laporan selalu cocok; baris level 3 bisa dirinci ke akun level 6.
 *
 * Perbedaan dari v1: surplus dibagi per kelompok sumber dana dengan benar (pengelompokan di helper mutasi v1 menjumlah
 * kredit ke debet & per rek3 sehingga neto_tp/neto_dp bisa salah bagi), bisa dilihat per tanggal, dan menampilkan
 * pemeriksaan keseimbangan Aset = Kewajiban + Aset Neto.
 */
class PosisiKeuangan
{
  public function __construct(private NeracaPercobaan $np)
  {
  }

  /**
   * @return array{awal: string, akhir: string, bagian: array<string, array{kode: string, nama: string, baris: Collection, total_awal: float, total: float}>,
   *               surplus: array{tp: float, dp: float}, surplus_awal: array{tp: float, dp: float}, seimbang: bool, selisih: float, selisih_awal: float}
   *   Setiap baris: saldo_awal (posisi pada tanggal awal) & saldo (posisi pada tanggal akhir).
   */
  public function hitung(int $tahun, ?string $awal = null, ?string $akhir = null): array
  {
    [$pAwal] = NeracaPercobaan::periode($tahun);
    $hasil = $this->np->hitung($tahun, $awal, $akhir);
    $baris = $hasil['baris'];

    $netoDari = fn ($rows) => [
      'tp' => (float) $rows->where('idrek6', NeracaPercobaan::ASET_NETO_TANPA_PEMBATASAN)->sum('surplus'),
      'dp' => (float) $rows->where('idrek6', NeracaPercobaan::ASET_NETO_DENGAN_PEMBATASAN)->sum('surplus'),
    ];
    $neto = $netoDari($this->np->surplusPerSumber($tahun, $hasil['akhir']));
    // Surplus s/d sehari sebelum tanggal awal (0 bila awal = awal T.A).
    $netoAwal = $hasil['awal'] > $pAwal
      ? $netoDari($this->np->surplusPerSumber($tahun, date('Y-m-d', strtotime($hasil['awal'] . ' -1 day'))))
      : ['tp' => 0.0, 'dp' => 0.0];

    $lv1 = $baris->where('lv', 1)->keyBy('kode');
    $bagian = [];
    foreach (['1' => 1, '2' => -1, '3' => -1] as $k1 => $tanda) {
      $k1 = (string) $k1; // key array '1' menjadi int di PHP
      // Baris level 3 (+ akun level 6) rek1 ini; saldo ditampilkan menurut sisi normal.
      $rows = collect();
      $aktif = false;
      foreach ($baris as $b) {
        if ($b->lv === 1) {
          $aktif = $b->kode === $k1;
          continue;
        }
        if (!$aktif || !in_array($b->lv, [3, 6], true)) {
          continue;
        }
        $rows->push((object) ['lv' => $b->lv, 'id' => $b->id, 'induk' => $b->induk ?? null, 'kode' => $b->kode, 'nama' => $b->nama,
          'saldo_awal' => $tanda * $b->awal, 'saldo' => $tanda * $b->akhir, 'surplus' => 0.0]);
      }

      if ($k1 === '3') {
        // Surplus berjalan ke 3.1.01 (tanpa pembatasan) & 3.2.01 (dengan pembatasan); buat barisnya bila belum ada.
        foreach (['3.1.01' => ['tp', 'Aset Neto Tanpa Pembatasan'], '3.2.01' => ['dp', 'Aset Neto Dengan Pembatasan']] as $kode => [$k, $nama]) {
          if (abs($neto[$k]) < 0.005 && abs($netoAwal[$k]) < 0.005) {
            continue;
          }
          $r = $rows->first(fn ($x) => $x->lv === 3 && $x->kode === $kode);
          if (!$r) {
            $r = (object) ['lv' => 3, 'id' => null, 'induk' => null, 'kode' => $kode, 'nama' => $nama, 'saldo_awal' => 0.0, 'saldo' => 0.0, 'surplus' => 0.0];
            $rows->push($r);
          }
          $r->surplus = $neto[$k];
          $r->saldo += $neto[$k];
          $r->saldo_awal += $netoAwal[$k];
        }
        $rows = $this->urut($rows);
      }

      $bagian[$k1] = [
        'kode' => $k1,
        'nama' => $lv1[$k1]->nama ?? ['1' => 'Aset', '2' => 'Kewajiban', '3' => 'Aset Neto'][$k1],
        'baris' => $rows,
        'total_awal' => (float) $rows->where('lv', 3)->sum('saldo_awal'),
        'total' => (float) $rows->where('lv', 3)->sum('saldo'),
      ];
    }

    $selisih = $bagian['1']['total'] - ($bagian['2']['total'] + $bagian['3']['total']);
    $selisihAwal = $bagian['1']['total_awal'] - ($bagian['2']['total_awal'] + $bagian['3']['total_awal']);

    return ['awal' => $hasil['awal'], 'akhir' => $hasil['akhir'], 'bagian' => $bagian, 'surplus' => $neto, 'surplus_awal' => $netoAwal,
      'seimbang' => abs($selisih) < 0.5 && abs($selisihAwal) < 0.5, 'selisih' => $selisih, 'selisih_awal' => $selisihAwal];
  }

  /** Urutkan level 3 menurut kode, akun level 6 tetap di bawah induknya. */
  private function urut(Collection $rows): Collection
  {
    $anak = $rows->where('lv', 6)->groupBy('induk');

    return $rows->where('lv', 3)->sortBy('kode')->flatMap(fn ($r) => collect([$r])->merge($r->id ? ($anak[$r->id] ?? []) : []))->values();
  }
}
