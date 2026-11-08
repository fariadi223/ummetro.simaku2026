<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buku besar per akun level 6, padanan Akuntansi::bukubesar_datatable & bukubesar_detail_datatable v1
 * (dan Download::bukubesar untuk Excel — barisnya sama).
 *
 *   Periode T.A  = 01-09-T s/d 31-08-(T+1); transaksi dari ueu_tbl_jurnal_spm tahun T.
 *   Saldo awal   = ueu_tbl_jurnal_saldo tahun T (saldo_awal_lv6 v1), hanya untuk akun selain rek 4 & 5:
 *                    rek 1 (kecuali 1.2.02/1.2.04) & 5 → D = Σ debet − Σ kredit, selain itu K = Σ kredit − Σ debet.
 *   Saldo jalan  = rek 1 & 5: saldo += debet − kredit; lainnya: saldo += kredit − debet.
 *   Rentang tanggal [awal, akhir] (dipilih di modal Lihat):
 *                  "Saldo Awal per <awal>"  = saldo awal T.A + mutasi 01-09-T s/d (awal − 1)  (D/K = mutasi itu),
 *                  transaksi awal..akhir, lalu "Saldo Akhir per <akhir>" (D/K = jumlah transaksi rentang).
 *
 * Perbedaan dari v1 (perbaikan): filter sumber dana berlaku untuk saldo awal, mutasi sebelum periode & transaksi
 * (v1 hanya pada mutasi sebelum periode); bila dari = 01-09-T transaksi tanggal itu tidak terhitung dua kali;
 * baris "Saldo s/d" & "Saldo Akhir" selalu tampil saat filter tanggal; kolom No. Bukti diisi (v1 selalu kosong).
 */
class BukuBesar
{
  private const KONTRA_ASET = ['1.2.02', '1.2.04'];

  /** [awal, akhir] periode T.A (Y-m-d). */
  public static function periode(int $tahun): array
  {
    return [sprintf('%04d-09-01', $tahun), sprintf('%04d-08-31', $tahun + 1)];
  }

  /** Akun level 6 yang punya jurnal pada T.A (group_jurnal_rek6 v1). */
  public function akun(int $tahun): Collection
  {
    $id = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->distinct()->pluck('idrek6')->all();

    return $this->rekening()->whereIn('r6.idrek6', $id ?: [0])->get();
  }

  /** Satu akun berkode lengkap, atau null. */
  public function satuAkun(int $idrek6): ?object
  {
    return $this->rekening()->where('r6.idrek6', $idrek6)->first();
  }

  /**
   * Baris buku besar satu akun.
   *
   * @return Collection<int, object{jenis: string, tgl: string, bukti: string, uraian: string, debet: ?float, kredit: ?float, saldo: float}>
   */
  public function detail(int $tahun, object $akun, ?int $idSumber = null, ?string $dari = null, ?string $sampai = null): Collection
  {
    [$pAwal, $pAkhir] = self::periode($tahun);
    $normalDebet = in_array((string) $akun->koderek1, ['1', '5'], true);
    $tambah = fn (float $saldo, float $d, float $k) => $saldo + ($normalDebet ? $d - $k : $k - $d);
    $pakaiTanggal = $dari !== null || $sampai !== null;
    $dari ??= $pAwal;
    $sampai ??= $pAkhir;
    if ($dari > $sampai) {
      [$dari, $sampai] = [$sampai, $dari];
    }

    $baris = collect();
    $saldo = 0.0;

    // Saldo awal (bukan akun pendapatan/biaya).
    $awal = $this->saldoAwal($tahun, $akun, $idSumber);
    if ($awal) {
      $saldo = $tambah($saldo, $awal['debet'], $awal['kredit']);
      if (!$pakaiTanggal) {
        $baris->push((object) ['jenis' => 'awal', 'tgl' => $pAwal, 'bukti' => '', 'uraian' => 'Saldo Awal',
          'debet' => $awal['debet'], 'kredit' => $awal['kredit'], 'saldo' => $saldo]);
      }
    }

    if ($pakaiTanggal) {
      // Mutasi 01-09-T s/d (dari − 1) dirangkum satu baris.
      $sebelum = Carbon::parse($dari)->subDay()->toDateString();
      $mutasi = $dari > $pAwal
        ? $this->transaksi($tahun, $akun->idrek6, $idSumber)->whereBetween('j.tgl_trk', [$pAwal, $sebelum])
          ->selectRaw('COALESCE(SUM(j.debet), 0) as d, COALESCE(SUM(j.kredit), 0) as k')->first()
        : (object) ['d' => 0, 'k' => 0];
      $saldo = $tambah($saldo, (float) $mutasi->d, (float) $mutasi->k);
      // Saldo awal per tanggal awal = saldo awal T.A + mutasi s/d sehari sebelumnya; debet/kredit = mutasi tersebut.
      $baris->push((object) ['jenis' => 'sd', 'tgl' => $dari, 'bukti' => '', 'uraian' => 'Saldo Awal per ' . self::tglId($dari),
        'debet' => (float) $mutasi->d, 'kredit' => (float) $mutasi->k, 'saldo' => $saldo]);
    }

    $this->transaksi($tahun, $akun->idrek6, $idSumber)->whereBetween('j.tgl_trk', [$dari, $sampai])
      ->orderBy('j.tgl_trk')->orderBy('j.id_trans')
      ->get(['j.tgl_trk', 'j.bukti', 'j.uraian', 'j.debet', 'j.kredit'])
      ->each(function ($r) use (&$saldo, $tambah, $baris) {
        $saldo = $tambah($saldo, (float) $r->debet, (float) $r->kredit);
        $baris->push((object) ['jenis' => 'trx', 'tgl' => $r->tgl_trk, 'bukti' => (string) $r->bukti, 'uraian' => (string) $r->uraian,
          'debet' => (float) $r->debet, 'kredit' => (float) $r->kredit, 'saldo' => $saldo]);
      });

    if ($pakaiTanggal) {
      $periodeIni = $baris->where('jenis', 'trx');
      $baris->push((object) ['jenis' => 'akhir', 'tgl' => $sampai, 'bukti' => '', 'uraian' => 'Saldo Akhir per ' . self::tglId($sampai),
        'debet' => (float) $periodeIni->sum('debet'), 'kredit' => (float) $periodeIni->sum('kredit'), 'saldo' => $saldo]);
    }

    return $baris;
  }

  private static function tglId(string $ymd): string
  {
    return Carbon::parse($ymd)->format('d-m-Y');
  }

  /** Saldo awal akun (saldo_awal_lv6 v1), atau null untuk rek 4/5 atau bila saldonya 0. */
  public function saldoAwal(int $tahun, object $akun, ?int $idSumber = null): ?array
  {
    if (in_array((string) $akun->koderek1, ['4', '5'], true)) {
      return null;
    }
    $j = DB::table('ueu_tbl_jurnal_saldo')->where('tahun', (string) $tahun)->where('idrek6', $akun->idrek6)
      ->when($idSumber, fn ($q) => $q->where('id_sumber', $idSumber))
      ->selectRaw('COALESCE(SUM(debet), 0) as d, COALESCE(SUM(kredit), 0) as k')->first();
    $k3 = $akun->koderek1 . '.' . $akun->koderek2 . '.' . $akun->koderek3;
    $normalDebet = ((string) $akun->koderek1 === '1' && !in_array($k3, self::KONTRA_ASET, true)) || (string) $akun->koderek1 === '5';
    $saldo = $normalDebet ? (float) $j->d - (float) $j->k : (float) $j->k - (float) $j->d;
    if ($saldo == 0) {
      return null;
    }

    return $normalDebet ? ['debet' => $saldo, 'kredit' => 0.0] : ['debet' => 0.0, 'kredit' => $saldo];
  }

  /** Jurnal satu akun pada T.A (get_buku_besar v1). */
  public function transaksi(int $tahun, int $idrek6, ?int $idSumber = null): Builder
  {
    return DB::table('ueu_tbl_jurnal_spm as j')->where('j.tahun', (string) $tahun)->where('j.idrek6', $idrek6)
      ->when($idSumber, fn ($q) => $q->where('j.id_sumber', $idSumber));
  }

  /** Rekening level 6 + kode level 1–6 (get_rekening v1), urut kode. */
  public function rekening(): Builder
  {
    return DB::table('ueu_tbl_rek6 as r6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')
      ->orderBy('r4.koderek4')->orderBy('r5.koderek5')->orderBy('r6.koderek6')
      ->select('r6.idrek6', 'r6.namarek6', 'r1.koderek1', 'r1.namarek1', 'r2.koderek2', 'r3.koderek3', 'r4.koderek4', 'r5.koderek5', 'r6.koderek6',
        DB::raw("CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode"));
  }
}
