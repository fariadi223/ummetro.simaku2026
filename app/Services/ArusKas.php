<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Laporan arus kas metode tidak langsung, padanan report/Aruskas::view v1 (helper arus_kas_hitung, view arus_v2),
 * dihitung ulang dari Posisi Keuangan & Penghasilan Komprehensif v2.
 *
 *   Kas & setara kas = 1.1.01 Kas, 1.1.02 Bank, 1.1.03 Surat Berharga (seperti v1).
 *   A. Operasi   : penghasilan komprehensif periode berjalan
 *                  + penyesuaian non-kas (format v1): − 4.3.04 koreksi aset neto, + 5.1.11 penyusutan, + 5.1.14 amortisasi,
 *                    − 4.3.05 laba penghapusan aset, + 5.4.01 kerugian penghapusan aset (saldo dari Penghasilan Komprehensif)
 *                  + penurunan / (kenaikan) aset lancar selain kas (1.1 lainnya)        [v1 O_7]
 *                  + kenaikan / (penurunan) liabilitas jangka pendek (2.1)              [v1 O_8]
 *   B. Investasi : penurunan / (kenaikan) aset tidak lancar (1.2 kecuali 1.2.02, 1.2.04)  [v1 I_2]
 *                  + penyesuaian akumulasi penyusutan = perubahan akumulasi 1.2.02/1.2.04 − penyesuaian non-kas
 *                    (pelepasan/koreksi aset), satu baris, hanya tampil bila tidak 0.
 *   C. Pendanaan : kenaikan / (penurunan) liabilitas jangka panjang (2.2.01 … 2.2.99, selalu tampil)  [v1 P_2]
 *                  + penyesuaian langsung aset neto (perubahan aset neto di luar penghasilan komprehensif)
 *   Kenaikan bersih kas = A + B + C; kas awal = kas per tanggal awal di neraca [v1 P_4]; kas akhir = kas awal + kenaikan
 *   bersih = kas per tanggal akhir di neraca (semua kolom & periode, sehingga kas akhir T.A lalu = kas awal T.A berjalan).
 *   Perubahan akun = saldo per tanggal akhir − saldo per tanggal awal (Posisi Keuangan, sisi normal akun).
 *
 * Perbedaan dari v1: tidak ada nilai T.A 2022/2023 yang ditulis di kode; penghasilan non-kas dikurangkan (v1 menjumlah
 * kelima penyesuaian dengan tanda sama).
 * Kolom T.A berjalan mengikuti filter [awal, akhir]; kolom T.A lalu = seluruh T.A lalu.
 */
class ArusKas
{
  public const KAS = ['1.1.01', '1.1.02', '1.1.03'];
  public const AKUMULASI = ['1.2.02', '1.2.04'];
  /** Kode baris selisih akumulasi di aktivitas investasi (bukan akun). */
  public const BARIS_AKUMULASI = '1.2.02/04';

  /**
   * Penyesuaian non-kas di aktivitas operasi, format v1 (o_rek_aktiv): [kode level 3 => [nama, tanda]].
   * Tanda +1 = beban non-kas ditambahkan kembali, −1 = penghasilan non-kas dikurangkan.
   */
  public const PENYESUAIAN = [
    '4.3.04' => ['Koreksi Aset Neto Periode Lalu', -1],
    '5.1.11' => ['Biaya Penyusutan', 1],
    '5.1.14' => ['Biaya Amortisasi Aset Tidak Berwujud', 1],
    '4.3.05' => ['Laba penghapusan aset tetap', -1],
    '5.4.01' => ['Kerugian Penghapusan Aset Tetap', 1],
  ];

  public function __construct(private PosisiKeuangan $posisi, private PenghasilanKomprehensif $pk)
  {
  }

  public function hitung(int $tahun, ?string $awal = null, ?string $akhir = null): array
  {
    $ini = $this->kolom($tahun, $awal, $akhir);
    $lalu = $this->kolom($tahun - 1);


    // Gabungkan baris per kode dari kedua kolom (akun yang hanya bernilai di salah satu tahun tetap tampil).
    // Aset lancar selain kas, liabilitas jangka pendek & aset tidak lancar selalu ditampilkan lengkap walau 0.
    $lengkap = [
      'operasi_aset' => $this->akunLevel3('1', '1', self::KAS),
      'operasi_liabilitas' => $this->akunLevel3('2', '1'),
      // Aset tidak lancar (1.2.xx) kecuali akumulasi penyusutan/amortisasi (sudah di penyesuaian operasi).
      'investasi' => $this->akunLevel3('1', '2', self::AKUMULASI),
      'pendanaan' => $this->akunLevel3('2', '2'),
    ];
    $bagian = [];
    foreach (['operasi_aset', 'operasi_liabilitas', 'penyusutan', 'investasi', 'pendanaan'] as $k) {
      $kode = collect($ini['rinci'][$k])->keys()->merge(collect($lalu['rinci'][$k])->keys())
        ->merge(isset($lengkap[$k]) ? $lengkap[$k]->keys() : [])->unique();
      // Penyesuaian non-kas mengikuti urutan format v1; lainnya urut kode.
      $kode = ($k === 'penyusutan' ? $kode : $kode->sort())->values();
      $bagian[$k] = $kode->map(fn ($c) => (object) [
        'kode' => $c,
        'nama' => $ini['rinci'][$k][$c]['nama'] ?? $lalu['rinci'][$k][$c]['nama'] ?? $lengkap[$k][$c] ?? $c,
        'ini' => (float) ($ini['rinci'][$k][$c]['nilai'] ?? 0),
        'lalu' => (float) ($lalu['rinci'][$k][$c]['nilai'] ?? 0),
      ])->filter(fn ($r) => $k === 'penyusutan' || isset($lengkap[$k]) || abs($r->ini) >= 0.5 || abs($r->lalu) >= 0.5)->values();
    }

    return ['awal' => $ini['awal'], 'akhir' => $ini['akhir'], 'seluruhTa' => $ini['seluruhTa'],
      'awalLalu' => $lalu['awal'], 'akhirLalu' => $lalu['akhir'], 'bagian' => $bagian,
      'ini' => $ini['nilai'], 'lalu' => $lalu['nilai']];
  }

  /** Akun level 3 di bawah rek1.rek2 (kecuali kode tertentu): [kode => nama], urut kode. */
  private function akunLevel3(string $k1, string $k2, array $kecuali = [])
  {
    return DB::table('ueu_tbl_rek3 as r3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->where('r1.koderek1', $k1)->where('r2.koderek2', $k2)
      ->orderBy('r3.koderek3')
      ->get([DB::raw("CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3) as kode"), 'r3.namarek3'])
      ->reject(fn ($r) => in_array($r->kode, $kecuali, true))
      ->pluck('namarek3', 'kode');
  }

  private function kolom(int $tahun, ?string $awal = null, ?string $akhir = null): array
  {
    [$pAwal, $pAkhir] = NeracaPercobaan::periode($tahun);
    $pos = $this->posisi->hitung($tahun, $awal, $akhir);
    $pk = $this->pk->hitung($tahun, $pos['awal'], $pos['akhir']);
    $komprehensif = (float) $pk['komprehensif']->jml;
    $akunPk = collect($pk['bagian'])->flatMap(fn ($b) => $b['baris'])->where('lv', 3)->keyBy('kode');

    $rinci = ['operasi_aset' => [], 'operasi_liabilitas' => [], 'penyusutan' => [], 'investasi' => [], 'pendanaan' => []];
    foreach (self::PENYESUAIAN as $kode => [$nama, $tanda]) {
      // 4.3.04 & 4.3.05 sudah termasuk penghasilan komprehensif → dikurangkan agar tidak terhitung dua kali.
      $rinci['penyusutan'][$kode] = ['nama' => $nama, 'nilai' => $tanda * (float) ($akunPk[$kode]->jml ?? 0)];
    }
    $akumulasi = ['1.2.02' => 0.0, '1.2.04' => 0.0];
    $kasAwal = $kasAkhir = 0.0;
    foreach (['1', '2'] as $k1) {
      foreach ($pos['bagian'][$k1]['baris']->where('lv', 3) as $r) {
        $delta = $r->saldo - $r->saldo_awal;
        $kode2 = substr($r->kode, 0, 3);
        if (in_array($r->kode, self::KAS, true)) {
          $kasAwal += $r->saldo_awal;
          $kasAkhir += $r->saldo;
          continue;
        }
        // Aset: kenaikan = kas keluar (−Δ). Akumulasi penyusutan bersaldo negatif di sisi aset → −Δ = beban penyusutan.
        [$grup, $nilai] = match (true) {
          in_array($r->kode, self::AKUMULASI, true) => [null, -$delta],
          $kode2 === '1.1' => ['operasi_aset', -$delta],
          $kode2 === '1.2' => ['investasi', -$delta],
          $kode2 === '2.1' => ['operasi_liabilitas', $delta],
          default => ['pendanaan', $delta], // 2.2 dan liabilitas lain
        };
        if ($grup === null) {
          $akumulasi[$r->kode] += $nilai;
          continue;
        }
        $rinci[$grup][$r->kode] = ['nama' => $r->nama, 'nilai' => $nilai];
      }
    }
    // Perubahan akumulasi 1.2.02/1.2.04 yang tidak dijelaskan penyesuaian non-kas (pelepasan/koreksi aset) ditampilkan
    // sebagai satu baris di investasi, sehingga kas akhir = kas neraca di semua kolom & periode.
    $n_sisaAkumulasi = array_sum($akumulasi) - (float) collect($rinci['penyusutan'])->sum('nilai');
    if (abs($n_sisaAkumulasi) >= 0.5) {
      $rinci['investasi'][self::BARIS_AKUMULASI] = ['nama' => 'Penyesuaian akumulasi penyusutan (pelepasan / koreksi aset)', 'nilai' => $n_sisaAkumulasi];
    }

    $asetNetoAwal = (float) $pos['bagian']['3']['total_awal'];
    $asetNetoAkhir = (float) $pos['bagian']['3']['total'];
    $langsung = ($asetNetoAkhir - $asetNetoAwal) - $komprehensif;

    $jumlah = fn ($g) => (float) collect($rinci[$g])->sum('nilai');
    $n = ['komprehensif' => $komprehensif, 'penyusutan' => $jumlah('penyusutan'),
      'operasi_aset' => $jumlah('operasi_aset'), 'operasi_liabilitas' => $jumlah('operasi_liabilitas')];
    $n['operasi'] = $n['komprehensif'] + $n['penyusutan'] + $n['operasi_aset'] + $n['operasi_liabilitas'];
    $n['investasi'] = $jumlah('investasi');
    $n['liabilitas_panjang'] = $jumlah('pendanaan');
    $n['langsung'] = abs($langsung) >= 0.5 ? $langsung : 0.0;
    $n['pendanaan'] = $n['liabilitas_panjang'] + $n['langsung'];
    $n['kenaikan'] = $n['operasi'] + $n['investasi'] + $n['pendanaan'];
    $n['kas_awal'] = $kasAwal;
    $n['kas_akhir'] = $kasAwal + $n['kenaikan'];
    $n['kas_neraca'] = $kasAkhir;
    $n['selisih'] = $n['kas_akhir'] - $kasAkhir;
    $n['sisa_akumulasi'] = $n_sisaAkumulasi; // informasi: perubahan akumulasi yang tidak dijelaskan penyesuaian non-kas

    return ['awal' => $pos['awal'], 'akhir' => $pos['akhir'], 'seluruhTa' => $pos['awal'] === $pAwal && $pos['akhir'] === $pAkhir,
      'rinci' => $rinci, 'nilai' => $n];
  }
}
