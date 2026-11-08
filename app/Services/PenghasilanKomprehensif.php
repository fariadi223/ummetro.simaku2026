<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan penghasilan komprehensif (aktivitas), padanan report/Aktivitas::view v1 (view aktivitas_v2).
 *
 *   Kolom: T.A berjalan & T.A lalu, masing-masing Tanpa Pembatasan / Dengan Pembatasan / Jumlah
 *   (kelompok dari ueu_tbl_sumberdana.kelompok: 'Pembatasan' → dengan pembatasan, lainnya → tanpa).
 *   Saldo akun: rek 4 (penerimaan) = kredit − debet, rek 5 (biaya) = debet − kredit.
 *   T.A berjalan = mutasi ueu_tbl_jurnal_spm tahun T, ket ≠ TB, tanggal dalam [awal, akhir].
 *   T.A lalu     = selalu seluruh T.A lalu, tidak ikut filter tanggal (seperti v1): ueu_tbl_jurnal_saldo tahun T−1
 *                  (snapshot tutup buku); bila snapshot belum ada (belum tutup buku), mutasi seluruh T.A lalu.
 *   Susunan (v1): Penerimaan (rek 4 kecuali 4.3) − Biaya (rek 5 kecuali 5.4) = Surplus/Defisit;
 *                 Penghasilan komprehensif lain = Penerimaan lain-lain (4.3) − Biaya lain-lain (5.4);
 *                 Penghasilan komprehensif periode berjalan = surplus/defisit + komprehensif lain.
 *
 * Perbedaan dari v1: baris level 3 bisa dirinci ke akun level 6.
 */
class PenghasilanKomprehensif
{
  /** [kunci, judul, koderek1, filter level 2: true = hanya kode2 ini, false = kecuali] */
  public const BAGIAN = [
    'pendapatan' => ['Penerimaan', '4', '3', false],
    'biaya' => ['Biaya', '5', '4', false],
    'pendapatan_lain' => ['Penerimaan Lain-lain', '4', '3', true],
    'biaya_lain' => ['Biaya Lain-lain', '5', '4', true],
  ];

  /**
   * $mode 'tunggal' (default): kolom kanan = seluruh T.A lalu.
   * $mode 'tanggal' (Report Tanggal v1, aktivitas_tanggal): kolom kanan = mutasi T.A berjalan 01-09-T s/d (awal − 1);
   *       bila awal = 01-09-T, kolom kanan = seluruh T.A lalu (seperti v1).
   */
  public function hitung(int $tahun, ?string $awal = null, ?string $akhir = null, string $mode = 'tunggal'): array
  {
    [$pAwal, $pAkhir] = NeracaPercobaan::periode($tahun);
    $jepit = fn ($t) => $t < $pAwal ? $pAwal : ($t > $pAkhir ? $pAkhir : $t);
    $awal = $jepit($awal ?? $pAwal);
    $akhir = $jepit($akhir ?? $pAkhir);
    if ($awal > $akhir) {
      [$awal, $akhir] = [$akhir, $awal];
    }
    $seluruhTa = $awal === $pAwal && $akhir === $pAkhir;

    $ini = $this->mutasi($tahun, $awal, $akhir);
    $mode = $mode === 'tanggal' ? 'tanggal' : 'tunggal';
    if ($mode === 'tanggal' && $awal > $pAwal) {
      // Report Tanggal: kolom kanan = akumulasi T.A berjalan sebelum periode.
      [$awalLalu, $akhirLalu] = [$pAwal, date('Y-m-d', strtotime($awal . ' -1 day'))];
      $lalu = $this->mutasi($tahun, $awalLalu, $akhirLalu);
      $sumberLalu = 'sebelum';
    } else {
      // T.A lalu selalu seluruh periode (tidak ikut filter): snapshot tutup buku; bila belum ada, mutasi T.A lalu.
      [$awalLalu, $akhirLalu] = NeracaPercobaan::periode($tahun - 1);
      $lalu = $this->snapshot($tahun - 1);
      $sumberLalu = 'snapshot';
      if ($lalu->isEmpty()) {
        $lalu = $this->mutasi($tahun - 1, $awalLalu, $akhirLalu);
        $sumberLalu = 'mutasi';
      }
    }
    $tgl = fn ($t) => date('d-m-Y', strtotime($t));
    $label = $mode === 'tanggal'
      ? ['ini' => 'Periode ' . $tgl($awal) . ' s/d ' . $tgl($akhir),
        'lalu' => $sumberLalu === 'sebelum' ? 'Sebelum periode: ' . $tgl($awalLalu) . ' s/d ' . $tgl($akhirLalu) : 'T.A ' . ($tahun - 1) . '/' . $tahun]
      : ['ini' => 'T.A ' . $tahun . '/' . ($tahun + 1), 'lalu' => 'T.A ' . ($tahun - 1) . '/' . $tahun];

    $akun = $this->akun();
    $nol = ['tp' => 0.0, 'dp' => 0.0, 'tp_lalu' => 0.0, 'dp_lalu' => 0.0];
    $nilai6 = [];
    foreach ([[$ini, ''], [$lalu, '_lalu']] as [$rows, $sfx]) {
      foreach ($rows as $r) {
        $a = $akun[$r->idrek6] ?? null;
        if (!$a) {
          continue;
        }
        $saldo = $a->koderek1 === '4' ? (float) $r->k - (float) $r->d : (float) $r->d - (float) $r->k;
        $nilai6[$r->idrek6] ??= $nol;
        $nilai6[$r->idrek6][($r->kelompok === 'Pembatasan' ? 'dp' : 'tp') . $sfx] += $saldo;
      }
    }

    $bagian = [];
    foreach (self::BAGIAN as $kunci => [$judul, $k1, $k2, $hanya]) {
      $rows = collect();
      $l3 = [];
      foreach ($akun as $a) {
        if ($a->koderek1 !== $k1 || ($a->koderek2 === $k2) !== $hanya || !isset($nilai6[$a->idrek6])) {
          continue;
        }
        $n = $nilai6[$a->idrek6];
        if (max(array_map('abs', $n)) < 0.005) {
          continue;
        }
        if (!isset($l3[$a->idrek3])) {
          $l3[$a->idrek3] = (object) (['lv' => 3, 'id' => $a->idrek3, 'induk' => null, 'kode' => $a->kode3, 'nama' => $a->namarek3] + (array) $this->kosong());
          $rows->push($l3[$a->idrek3]);
          $l3[$a->idrek3]->anak = collect();
        }
        $r6 = (object) (['lv' => 6, 'id' => $a->idrek6, 'induk' => $a->idrek3, 'kode' => $a->kode6, 'nama' => $a->namarek6] + $this->dari($n));
        $l3[$a->idrek3]->anak->push($r6);
        foreach ($this->kolom() as $c) {
          $l3[$a->idrek3]->$c += $r6->$c;
        }
      }
      $total = $this->kosong();
      foreach ($rows as $r) {
        foreach ($this->kolom() as $c) {
          $total->$c += $r->$c;
        }
      }
      $bagian[$kunci] = ['judul' => $judul, 'baris' => $rows->flatMap(fn ($r) => collect([$r])->merge($r->anak))->values(), 'total' => $total];
    }

    $selisih = fn ($a, $b) => (object) collect($this->kolom())->mapWithKeys(fn ($c) => [$c => $a->$c - $b->$c])->all();
    $surplus = $selisih($bagian['pendapatan']['total'], $bagian['biaya']['total']);
    $lain = $selisih($bagian['pendapatan_lain']['total'], $bagian['biaya_lain']['total']);
    $komprehensif = (object) collect($this->kolom())->mapWithKeys(fn ($c) => [$c => $surplus->$c + $lain->$c])->all();

    return compact('mode', 'label', 'awal', 'akhir', 'seluruhTa', 'awalLalu', 'akhirLalu', 'sumberLalu', 'bagian', 'surplus', 'lain', 'komprehensif');
  }

  /** Nama kolom angka per baris. */
  public function kolom(): array
  {
    return ['tp', 'dp', 'jml', 'tp_lalu', 'dp_lalu', 'jml_lalu'];
  }

  private function kosong(): object
  {
    return (object) array_fill_keys($this->kolom(), 0.0);
  }

  private function dari(array $n): array
  {
    return ['tp' => $n['tp'], 'dp' => $n['dp'], 'jml' => $n['tp'] + $n['dp'],
      'tp_lalu' => $n['tp_lalu'], 'dp_lalu' => $n['dp_lalu'], 'jml_lalu' => $n['tp_lalu'] + $n['dp_lalu']];
  }

  /** Σ debet/kredit jurnal rek 4/5 per akun & kelompok sumber dana (tanpa TB). */
  private function mutasi(int $tahun, string $dari, string $sampai): Collection
  {
    return $this->dasar(DB::table('ueu_tbl_jurnal_spm as j'))
      ->where('j.tahun', (string) $tahun)->where('j.ket', '!=', 'TB')->whereBetween('j.tgl_trk', [$dari, $sampai])->get();
  }

  /** Snapshot tutup buku rek 4/5 di ueu_tbl_jurnal_saldo tahun T (group_jurnal_rek3_sumber + saldo_awal v1). */
  private function snapshot(int $tahun): Collection
  {
    return $this->dasar(DB::table('ueu_tbl_jurnal_saldo as j'))->where('j.tahun', (string) $tahun)->get();
  }

  private function dasar($q)
  {
    return $q->join('ueu_tbl_sumberdana as d', 'd.id', '=', 'j.id_sumber')
      ->join('ueu_tbl_rek6 as r6', 'r6.idrek6', '=', 'j.idrek6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->whereIn('r1.koderek1', ['4', '5'])
      ->groupBy('j.idrek6', 'd.kelompok')
      ->selectRaw('j.idrek6, d.kelompok, COALESCE(SUM(j.debet), 0) as d, COALESCE(SUM(j.kredit), 0) as k');
  }

  /** Akun level 6 rek 4/5 urut kode, dengan kode & nama level 3. */
  private function akun(): Collection
  {
    return DB::table('ueu_tbl_rek6 as r6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->whereIn('r1.koderek1', ['4', '5'])
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')
      ->orderBy('r4.koderek4')->orderBy('r5.koderek5')->orderBy('r6.koderek6')
      ->get(['r6.idrek6', 'r6.namarek6', 'r3.idrek3', 'r3.namarek3', 'r1.koderek1', 'r2.koderek2',
        DB::raw("CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3) as kode3"),
        DB::raw("CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode6")])
      ->map(function ($a) {
        $a->koderek1 = (string) $a->koderek1;
        $a->koderek2 = (string) $a->koderek2;

        return $a;
      })->keyBy('idrek6');
  }
}
