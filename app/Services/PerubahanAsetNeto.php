<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Laporan perubahan aset neto, padanan report/Asetneto::view v1 (view aset_neto_v2), dihitung ulang agar
 * bersambung dengan Posisi Keuangan & Penghasilan Komprehensif v2.
 *
 * Per kelompok (Tanpa Pembatasan = 3.1.01, Dengan Pembatasan = 3.2.01; kelompok sumber dana menentukan pembagian):
 *   Surplus/(Defisit) Akumulasian
 *     Saldo awal                              = aset neto di Posisi Keuangan pada tanggal awal
 *     Surplus (defisit) tahun berjalan        = surplus Penghasilan Komprehensif (penerimaan − biaya, tanpa 4.3 & 5.4)
 *     Koreksi aset neto periode lalu          = 4.3.04
 *     Aset neto dibebaskan dari pembatasan    = 4.3.01, 4.3.02, 4.3.03
 *     Penyesuaian langsung aset neto          = mutasi akun rek 3 (kredit − debet) pada periode
 *   Penghasilan Komprehensif Lain bersih      = 4.3 lainnya (4.3.05, 4.3.99) − biaya lain-lain 5.4
 *   Saldo akhir                               = jumlah di atas = aset neto di Posisi Keuangan pada tanggal akhir
 *
 * Kolom T.A berjalan mengikuti filter [awal, akhir]; kolom T.A lalu selalu seluruh T.A lalu (dari jurnal T.A lalu,
 * sehingga saldo akhirnya = saldo awal T.A berjalan).
 *
 * Perbedaan dari v1: v1 menghitung saldo awal mundur dari komponen tahun-tahun sebelumnya (dengan nilai T.A 2022
 * tertulis di kode) dan baris "dibebaskan dari pembatasan" selalu 0; di sini saldo awal/akhir diambil langsung dari
 * posisi keuangan, ditambah baris penyesuaian langsung & pemeriksaan selisih.
 */
class PerubahanAsetNeto
{
  /** Komponen baris (urutan tampil). */
  public const BARIS = [
    'saldo_awal' => 'Saldo Awal',
    'surplus' => 'Surplus (Defisit) Tahun Berjalan',
    'koreksi' => 'Koreksi Aset Neto Periode Lalu',
    'dibebaskan' => 'Aset Neto Yang Dibebaskan Dari Pembatasan',
    'langsung' => 'Penyesuaian Langsung Aset Neto',
    'pkl' => 'Penghasilan Komprehensif Lain Bersih',
  ];

  public function __construct(private PosisiKeuangan $posisi, private PenghasilanKomprehensif $pk)
  {
  }

  /**
   * $mode 'tunggal' (default): kolom kanan = seluruh T.A lalu.
   * $mode 'tanggal' (Report Tanggal v1, aset_neto_tanggal): kolom kanan = perubahan sebelum periode,
   *       01-09-T s/d (awal − 1), sehingga saldo akhirnya = saldo awal kolom kiri; bila awal = 01-09-T → T.A lalu.
   */
  public function hitung(int $tahun, ?string $awal = null, ?string $akhir = null, string $mode = 'tunggal'): array
  {
    $mode = $mode === 'tanggal' ? 'tanggal' : 'tunggal';
    [$pAwal] = NeracaPercobaan::periode($tahun);
    $ini = $this->kolom($tahun, $awal, $akhir);
    $sebelum = $mode === 'tanggal' && $ini['awal'] > $pAwal;
    $lalu = $sebelum ? $this->kolom($tahun, $pAwal, date('Y-m-d', strtotime($ini['awal'] . ' -1 day'))) : $this->kolom($tahun - 1);

    $tgl = fn ($t) => date('d-m-Y', strtotime($t));
    $label = $mode === 'tanggal'
      ? ['ini' => 'Periode ' . $tgl($ini['awal']) . ' s/d ' . $tgl($ini['akhir']),
        'lalu' => $sebelum ? 'Sebelum periode: ' . $tgl($lalu['awal']) . ' s/d ' . $tgl($lalu['akhir']) : 'T.A ' . ($tahun - 1) . '/' . $tahun]
      : ['ini' => 'T.A ' . $tahun . '/' . ($tahun + 1), 'lalu' => 'T.A ' . ($tahun - 1) . '/' . $tahun];

    return ['mode' => $mode, 'label' => $label, 'sebelumPeriode' => $sebelum,
      'awal' => $ini['awal'], 'akhir' => $ini['akhir'], 'seluruhTa' => $ini['seluruhTa'],
      'awalLalu' => $lalu['awal'], 'akhirLalu' => $lalu['akhir'], 'ini' => $ini['nilai'], 'lalu' => $lalu['nilai'],
      'seimbang' => $ini['seimbang'] && $lalu['seimbang']];
  }

  /** Nilai satu kolom: ['tp' => [komponen => nilai, 'saldo_akhir', 'akumulasian', 'selisih'], 'dp' => [...], 'total' => [...]]. */
  private function kolom(int $tahun, ?string $awal = null, ?string $akhir = null): array
  {
    $pos = $this->posisi->hitung($tahun, $awal, $akhir);
    $pk = $this->pk->hitung($tahun, $pos['awal'], $pos['akhir']);
    [$pAwal, $pAkhir] = NeracaPercobaan::periode($tahun);

    // Saldo aset neto per kelompok dari Posisi Keuangan (akun level 3 rek 3).
    $neto = $pos['bagian']['3']['baris']->where('lv', 3)->keyBy('kode');
    $saldo = fn ($kode, $kol) => (float) ($neto[$kode]->$kol ?? 0);

    // Komponen penghasilan komprehensif lain per akun level 3 (kolom periode berjalan).
    $lain = $pk['bagian']['pendapatan_lain']['baris']->where('lv', 3)->keyBy('kode');
    $biayaLain = $pk['bagian']['biaya_lain']['total'];
    $ambil = fn (array $kode, string $k) => (float) collect($kode)->sum(fn ($c) => $lain[$c]->$k ?? 0);

    $langsung = $this->mutasiLangsung($tahun, $pos['awal'], $pos['akhir']);

    $nilai = [];
    foreach (['tp' => '3.1.01', 'dp' => '3.2.01'] as $k => $kode) {
      $n = [
        'saldo_awal' => $saldo($kode, 'saldo_awal'),
        'surplus' => (float) $pk['surplus']->$k,
        'koreksi' => $ambil(['4.3.04'], $k),
        'dibebaskan' => $ambil(['4.3.01', '4.3.02', '4.3.03'], $k),
        'langsung' => (float) ($langsung[$k] ?? 0),
      ];
      $n['pkl'] = (float) $pk['lain']->$k - $n['koreksi'] - $n['dibebaskan'];
      $n['akumulasian'] = $n['saldo_awal'] + $n['surplus'] + $n['koreksi'] + $n['dibebaskan'] + $n['langsung'];
      $n['saldo_akhir'] = $n['akumulasian'] + $n['pkl'];
      // Pemeriksaan: harus sama dengan aset neto di Posisi Keuangan pada tanggal akhir.
      $n['posisi_akhir'] = $saldo($kode, 'saldo');
      $n['selisih'] = $n['saldo_akhir'] - $n['posisi_akhir'];
      $nilai[$k] = $n;
    }
    $nilai['total'] = collect(array_keys($nilai['tp']))->mapWithKeys(fn ($c) => [$c => $nilai['tp'][$c] + $nilai['dp'][$c]])->all();

    return ['awal' => $pos['awal'], 'akhir' => $pos['akhir'], 'seluruhTa' => $pos['awal'] === $pAwal && $pos['akhir'] === $pAkhir,
      'nilai' => $nilai, 'seimbang' => abs($nilai['tp']['selisih']) < 0.5 && abs($nilai['dp']['selisih']) < 0.5];
  }

  /** Mutasi langsung akun aset neto (rek 3) pada periode, kredit − debet: 3.1 → tanpa, 3.2 → dengan pembatasan. */
  private function mutasiLangsung(int $tahun, string $awal, string $akhir): array
  {
    return DB::table('ueu_tbl_jurnal_spm as j')
      ->join('ueu_tbl_rek6 as r6', 'r6.idrek6', '=', 'j.idrek6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->where('j.tahun', (string) $tahun)->where('j.ket', '!=', 'TB')->whereBetween('j.tgl_trk', [$awal, $akhir])
      ->where('r1.koderek1', '3')
      ->groupBy('r2.koderek2')
      ->selectRaw('r2.koderek2, COALESCE(SUM(j.kredit), 0) - COALESCE(SUM(j.debet), 0) as net')
      ->pluck('net', 'koderek2')
      ->mapWithKeys(fn ($v, $k2) => [(string) $k2 === '2' ? 'dp' : 'tp' => (float) $v])->all();
  }
}
