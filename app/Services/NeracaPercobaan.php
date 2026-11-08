<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Neraca percobaan, padanan report/Neraca::percobaan v1 (view neraca_percobaan & neraca_percobaan_tanggal,
 * helper saldo_awal & mutasi).
 *
 *   Saldo awal T.A  = Σ ueu_tbl_jurnal_saldo tahun T, hanya akun rek 1, 2, 3 (saldo_awal v1).
 *   Mutasi          = Σ ueu_tbl_jurnal_spm tahun T, ket ≠ 'TB' (jurnal tutup buku dikecualikan), tgl dalam rentang.
 *   Rentang [awal, akhir] (mode tanggal v1):
 *     Saldo awal per <awal> = saldo awal T.A + mutasi 01-09-T s/d (awal − 1);
 *     Saldo akhir          = saldo awal per <awal> + mutasi awal..akhir.
 *   Penempatan D/K   = berdasarkan tanda saldo bersih (debet − kredit): positif di D, negatif di K.
 *
 * Dihitung per akun level 6 lalu dijumlah ke level 3, 2, 1 (v1 langsung per level 3; hasilnya sama),
 * sehingga baris level 3 bisa dirinci ke akunnya.
 *
 * Perbedaan dari v1: akun dengan mutasi bersih 0 tetap menampilkan debet/kredit mutasinya (v1 menganggapnya
 * kosong sehingga total mutasi kurang); saldo negatif ditaruh di kolom lawan (v1 menampilkan angka negatif
 * di kolom normal); bila awal = 01-09-T, transaksi tanggal itu tidak terhitung dua kali (v1 mode tanggal).
 */
class NeracaPercobaan
{
  /** [awal, akhir] periode T.A (Y-m-d). */
  public static function periode(int $tahun): array
  {
    return [sprintf('%04d-09-01', $tahun), sprintf('%04d-08-31', $tahun + 1)];
  }

  /**
   * @return array{baris: Collection, total: array, awal: string, akhir: string}
   *   baris: objek {lv, kode, nama, id?, induk?, awal, mut_d, mut_k, akhir} — awal/akhir = saldo bersih (D − K).
   */
  public function hitung(int $tahun, ?string $awal = null, ?string $akhir = null): array
  {
    [$pAwal, $pAkhir] = self::periode($tahun);
    $awal = $this->jepit($awal ?? $pAwal, $pAwal, $pAkhir);
    $akhir = $this->jepit($akhir ?? $pAkhir, $pAwal, $pAkhir);
    if ($awal > $akhir) {
      [$awal, $akhir] = [$akhir, $awal];
    }

    $saldoTa = DB::table('ueu_tbl_jurnal_saldo')->where('tahun', (string) $tahun)->groupBy('idrek6')
      ->selectRaw('idrek6, COALESCE(SUM(debet), 0) - COALESCE(SUM(kredit), 0) as net')->pluck('net', 'idrek6');
    $mutasi = fn (string $dari, string $sampai) => DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)
      ->where('ket', '!=', 'TB')->whereBetween('tgl_trk', [$dari, $sampai])->groupBy('idrek6')
      ->selectRaw('idrek6, COALESCE(SUM(debet), 0) as d, COALESCE(SUM(kredit), 0) as k')->get()->keyBy('idrek6');
    $sebelum = $awal > $pAwal ? $mutasi($pAwal, Carbon::parse($awal)->subDay()->toDateString()) : collect();
    $periode = $mutasi($awal, $akhir);

    $akun = DB::table('ueu_tbl_rek6 as r6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')
      ->orderBy('r4.koderek4')->orderBy('r5.koderek5')->orderBy('r6.koderek6')
      ->get(['r6.idrek6', 'r6.namarek6', 'r1.idrek1', 'r1.koderek1', 'r1.namarek1', 'r2.idrek2', 'r2.koderek2', 'r2.namarek2',
        'r3.idrek3', 'r3.koderek3', 'r3.namarek3', 'r4.koderek4', 'r5.koderek5', 'r6.koderek6']);

    $nol = fn () => ['awal' => 0.0, 'mut_d' => 0.0, 'mut_k' => 0.0];
    $l1 = $l2 = $l3 = [];
    $l6 = [];
    foreach ($akun as $a) {
      $denganSaldoAwal = in_array((string) $a->koderek1, ['1', '2', '3'], true);
      $b = $sebelum[$a->idrek6] ?? null;
      $p = $periode[$a->idrek6] ?? null;
      $n = [
        'awal' => ($denganSaldoAwal ? (float) ($saldoTa[$a->idrek6] ?? 0) : 0.0) + ($b ? (float) $b->d - (float) $b->k : 0.0),
        'mut_d' => (float) ($p->d ?? 0),
        'mut_k' => (float) ($p->k ?? 0),
      ];
      if (!$this->adaNilai($n)) {
        continue;
      }
      $l6[$a->idrek3][] = (object) (['lv' => 6, 'id' => $a->idrek6, 'induk' => $a->idrek3, 'nama' => $a->namarek6,
        'kode' => implode('.', [$a->koderek1, $a->koderek2, $a->koderek3, $a->koderek4, $a->koderek5, $a->koderek6])] + $n);
      $l1[$a->idrek1] = $this->jumlah($l1[$a->idrek1] ?? $nol(), $n);
      $l2[$a->idrek2] = $this->jumlah($l2[$a->idrek2] ?? $nol(), $n);
      $l3[$a->idrek3] = $this->jumlah($l3[$a->idrek3] ?? $nol(), $n);
    }

    // Susun baris: semua level 1 & 2 (seperti v1), level 3 & 6 hanya yang bernilai.
    $baris = collect();
    $total = ['awal_d' => 0.0, 'awal_k' => 0.0, 'mut_d' => 0.0, 'mut_k' => 0.0, 'akhir_d' => 0.0, 'akhir_k' => 0.0];
    $lalu1 = $lalu2 = $lalu3 = null;
    foreach ($akun as $a) {
      if ($lalu1 !== $a->idrek1) {
        $baris->push($this->baris(1, (string) $a->koderek1, $a->namarek1, $l1[$a->idrek1] ?? $nol(), $a->idrek1));
        $lalu1 = $a->idrek1;
      }
      if ($lalu2 !== $a->idrek2) {
        $baris->push($this->baris(2, $a->koderek1 . '.' . $a->koderek2, $a->namarek2, $l2[$a->idrek2] ?? $nol(), $a->idrek2));
        $lalu2 = $a->idrek2;
      }
      if ($lalu3 !== $a->idrek3 && isset($l3[$a->idrek3])) {
        $r3 = $this->baris(3, $a->koderek1 . '.' . $a->koderek2 . '.' . $a->koderek3, $a->namarek3, $l3[$a->idrek3], $a->idrek3);
        $baris->push($r3);
        foreach ($l6[$a->idrek3] as $r6) {
          $baris->push($this->tempatkan($r6));
        }
        // Total dari level 3 (seperti v1).
        foreach (array_keys($total) as $k) {
          $total[$k] += $r3->$k;
        }
      }
      $lalu3 = $a->idrek3;
    }

    return ['baris' => $baris, 'total' => $total, 'awal' => $awal, 'akhir' => $akhir];
  }

  /**
   * Saldo akhir T.A per (akun level 6, sumber dana) untuk akun rek1 tertentu — dasar pemindahan ke saldo awal
   * tahun berikutnya (Tutupbuku::neraca_proses v1). Sama dengan kolom Saldo Akhir neraca percobaan seluruh T.A,
   * hanya dipecah per sumber dana. Baris bersaldo 0 tidak disertakan.
   *
   * @return Collection<int, object{idrek6: int, id_sumber: int, koderek1: string, kode: string, nama: string, net: float}>
   */
  public function saldoAkhirPerSumber(int $tahun, array $rek1 = ['1', '2', '3']): Collection
  {
    [$pAwal, $pAkhir] = self::periode($tahun);
    $akun = DB::table('ueu_tbl_rek6 as r6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->whereIn('r1.koderek1', $rek1)
      ->get(['r6.idrek6', 'r6.namarek6', 'r1.koderek1',
        DB::raw("CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode")])
      ->keyBy('idrek6');
    $id = $akun->keys()->all() ?: [0];

    // Sumber dana harus ada di ueu_tbl_sumberdana (inner join, seperti group_jurnal_rek6_sumber v1).
    $saldo = DB::table('ueu_tbl_jurnal_saldo as s')->join('ueu_tbl_sumberdana as d', 'd.id', '=', 's.id_sumber')
      ->where('s.tahun', (string) $tahun)->whereIn('s.idrek6', $id)
      ->groupBy('s.idrek6', 's.id_sumber')->selectRaw('s.idrek6, s.id_sumber, COALESCE(SUM(s.debet), 0) - COALESCE(SUM(s.kredit), 0) as net');
    $mutasi = DB::table('ueu_tbl_jurnal_spm as j')->join('ueu_tbl_sumberdana as d', 'd.id', '=', 'j.id_sumber')
      ->where('j.tahun', (string) $tahun)->where('j.ket', '!=', 'TB')->whereBetween('j.tgl_trk', [$pAwal, $pAkhir])->whereIn('j.idrek6', $id)
      ->groupBy('j.idrek6', 'j.id_sumber')->selectRaw('j.idrek6, j.id_sumber, COALESCE(SUM(j.debet), 0) - COALESCE(SUM(j.kredit), 0) as net');

    $net = [];
    foreach ([$saldo->get(), $mutasi->get()] as $rows) {
      foreach ($rows as $r) {
        $net[$r->idrek6 . '|' . $r->id_sumber] = ($net[$r->idrek6 . '|' . $r->id_sumber] ?? 0.0) + (float) $r->net;
      }
    }

    return collect($net)->filter(fn ($v) => abs($v) >= 0.005)->map(function ($v, $k) use ($akun) {
      [$idrek6, $idSumber] = array_map('intval', explode('|', $k));
      $a = $akun[$idrek6];

      return (object) ['idrek6' => $idrek6, 'id_sumber' => $idSumber, 'koderek1' => (string) $a->koderek1,
        'kode' => $a->kode, 'nama' => $a->namarek6, 'net' => round($v, 2)];
    })->sortBy(fn ($r) => $r->kode . '|' . str_pad((string) $r->id_sumber, 6, '0', STR_PAD_LEFT))->values();
  }

  /** Akun aset neto penampung surplus (Tutupbuku::surplus_proses v1). */
  public const ASET_NETO_TANPA_PEMBATASAN = 262;
  public const ASET_NETO_DENGAN_PEMBATASAN = 274;

  /**
   * Surplus/defisit T.A per sumber dana = (pendapatan K − D) − (biaya D − K) dari mutasi rek 4 & 5 (tanpa jurnal TB)
   * 01-09-T s/d $sampai (default akhir periode),
   * dipetakan ke akun aset neto sesuai kelompok sumber dana (Pembatasan → 274, lainnya → 262), seperti surplus_proses v1.
   * net = saldo bersih (D − K) baris aset neto: surplus → negatif (kredit), defisit → positif (debet).
   *
   * @return Collection<int, object{idrek6: int, id_sumber: int, kelompok: string, surplus: float, net: float}>
   */
  public function surplusPerSumber(int $tahun, ?string $sampai = null): Collection
  {
    [$pAwal, $pAkhir] = self::periode($tahun);
    $pAkhir = $sampai !== null ? $this->jepit($sampai, $pAwal, $pAkhir) : $pAkhir;

    return DB::table('ueu_tbl_jurnal_spm as j')
      ->join('ueu_tbl_sumberdana as d', 'd.id', '=', 'j.id_sumber')
      ->join('ueu_tbl_rek6 as r6', 'r6.idrek6', '=', 'j.idrek6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->where('j.tahun', (string) $tahun)->where('j.ket', '!=', 'TB')->whereBetween('j.tgl_trk', [$pAwal, $pAkhir])
      ->whereIn('r1.koderek1', ['4', '5'])
      ->groupBy('j.id_sumber', 'd.kelompok')
      ->selectRaw('j.id_sumber, d.kelompok, COALESCE(SUM(j.kredit), 0) - COALESCE(SUM(j.debet), 0) as surplus')
      ->get()
      ->filter(fn ($r) => abs((float) $r->surplus) >= 0.005)
      ->map(fn ($r) => (object) [
        'idrek6' => $r->kelompok === 'Pembatasan' ? self::ASET_NETO_DENGAN_PEMBATASAN : self::ASET_NETO_TANPA_PEMBATASAN,
        'id_sumber' => (int) $r->id_sumber,
        'kelompok' => (string) $r->kelompok,
        'surplus' => round((float) $r->surplus, 2),
        'net' => round(-(float) $r->surplus, 2),
      ])->sortBy('id_sumber')->values();
  }

  private function baris(int $lv, string $kode, string $nama, array $n, int $id): object
  {
    return $this->tempatkan((object) (['lv' => $lv, 'kode' => $kode, 'nama' => $nama, 'id' => $id] + $n));
  }

  /** Tambah kolom tampilan D/K: saldo bersih positif di D, negatif di K. */
  private function tempatkan(object $r): object
  {
    $r->akhir = $r->awal + $r->mut_d - $r->mut_k;
    $r->awal_d = max($r->awal, 0.0);
    $r->awal_k = max(-$r->awal, 0.0);
    $r->akhir_d = max($r->akhir, 0.0);
    $r->akhir_k = max(-$r->akhir, 0.0);

    return $r;
  }

  private function jumlah(array $a, array $b): array
  {
    foreach ($b as $k => $v) {
      $a[$k] += $v;
    }

    return $a;
  }

  private function adaNilai(array $n): bool
  {
    return abs($n['awal']) >= 0.005 || abs($n['mut_d']) >= 0.005 || abs($n['mut_k']) >= 0.005;
  }

  private function jepit(string $t, string $min, string $max): string
  {
    return $t < $min ? $min : ($t > $max ? $max : $t);
  }
}
