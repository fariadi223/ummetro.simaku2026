<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Laporan keuangan unit: unit di ueu_map_unit_prodi dikelompokkan ke unit induk FF.00.
 * Query di sql/laporan_unit/*.sql; parameter lewat variabel sesi (prepared statement).
 * Hanya membaca jurnal.
 */
class LaporanUnit
{
  private const DIR = 'sql/laporan_unit';

  public function siap(): bool
  {
    return Schema::hasTable('ueu_map_unit_prodi') && Schema::hasTable('ueu_ref_prodi')
      && Schema::hasTable('ueu_lap_unit_rek_nonaktif');
  }

  /** Rekening yang disembunyikan (Admin > Rekening Laporan Unit): ["kode nama", ...]. */
  public function rekeningNonaktif(): array
  {
    $id = DB::table('ueu_lap_unit_rek_nonaktif')->pluck('idrek6')->all();

    return $id ? app(BukuBesar::class)->rekening()->whereIn('r6.idrek6', $id)->get()
      ->map(fn ($r) => $r->kode . ' ' . $r->namarek6)->all() : [];
  }

  /** Daftar tahun (T.A) yang ada di jurnal, terbaru dulu. */
  public function daftarTahun(): array
  {
    return array_map('intval', DB::table('ueu_tbl_jurnal_spm')->distinct()->orderByDesc('tahun')->pluck('tahun')->all());
  }

  /**
   * Rekap per unit terpetakan (01_rekap_unit.sql), urut FF lalu kode unit.
   * $kepala = daftar kepala rekening dipisah koma (mis. '1,2,3'), null = semua rekening.
   * $selalu = idrek6 yang tetap dihitung walau dinonaktifkan admin (mis. 569 Kas Universitas).
   */
  public function rekap(int $tahun, string $awal, string $akhir, ?string $kepala = null, ?int $selalu = null): array
  {
    return $this->jalankan('01_rekap_unit.sql', $tahun, $awal, $akhir, ['p_kepala' => $kepala, 'p_selalu' => $selalu]);
  }

  /**
   * Neraca saldo per rekening (02_rekening_unit.sql) untuk satu kelompok ($ff, 2 digit)
   * atau satu unit ($idUnit). Keduanya null = semua unit terpetakan.
   * $selalu = idrek6 yang tetap ditampilkan walau dinonaktifkan admin (mis. 569 Kas Universitas).
   */
  public function rekening(int $tahun, string $awal, string $akhir, ?string $ff = null, ?int $idUnit = null, ?string $kepala = null, ?int $selalu = null): array
  {
    return $this->jalankan('02_rekening_unit.sql', $tahun, $awal, $akhir,
      ['p_ff' => $ff, 'p_id_unit' => $idUnit, 'p_kepala' => $kepala, 'p_selalu' => $selalu]);
  }

  /**
   * Rincian transaksi satu rekening (03_transaksi_unit.sql) untuk satu kelompok / satu unit,
   * dengan baris "Saldo awal" dan saldo berjalan. $batas = jumlah baris maksimum.
   */
  public function transaksi(int $tahun, string $awal, string $akhir, ?string $ff, ?int $idUnit, int $idrek6, int $batas, ?string $kepala = null, ?int $selalu = null): array
  {
    return $this->jalankan('03_transaksi_unit.sql', $tahun, $awal, $akhir,
      ['p_ff' => $ff, 'p_id_unit' => $idUnit, 'p_idrek6' => $idrek6, 'p_kepala' => $kepala, 'p_selalu' => $selalu], $batas);
  }

  /**
   * Rekap transfer dari Kas Universitas (kredit 569) per unit terpetakan (04_transfer_univ_rekap.sql).
   * Tidak terpengaruh rekening nonaktif: 569 memang disembunyikan dari laporan utama.
   */
  public function transferRekap(int $tahun, string $awal, string $akhir): array
  {
    return $this->jalankan('04_transfer_univ_rekap.sql', $tahun, $awal, $akhir);
  }

  /** Jurnal transfer dari Kas Universitas satu kelompok / satu unit (05_transfer_univ_jurnal.sql). */
  public function transferJurnal(int $tahun, string $awal, string $akhir, ?string $ff, ?int $idUnit, int $batas): array
  {
    return $this->jalankan('05_transfer_univ_jurnal.sql', $tahun, $awal, $akhir, ['p_ff' => $ff, 'p_id_unit' => $idUnit], $batas);
  }

  /**
   * Kolom nilai per mode tampilan: [judul, merah bila negatif].
   *   semua     : debet, kredit
   *   neraca    : debet, kredit, mutasi bersih
   *   aktivitas : pendapatan, beban, surplus/(defisit), penghasilan komprehensif lain, total (ISAK 35)
   */
  public static function kolom(string $mode): array
  {
    return match ($mode) {
      'neraca' => [['Debet', false], ['Kredit', false], ['Mutasi bersih D−K', true]],
      'aktivitas' => [['Pendapatan', false], ['Beban', false], ['Surplus / (Defisit)', true],
        ['Penghasilan Kompr. Lain', true], ['Total Penghasilan Kompr.', true]],
      default => [['Debet', false], ['Kredit', false]],
    };
  }

  /** Nilai sesuai kolom(): $x = baris unit (objek SQL) atau subtotal kelompok/total (array d,k,p,b,l). */
  public static function nilai(string $mode, object|array $x): array
  {
    $x = is_object($x)
      ? ['d' => (float) $x->total_debet, 'k' => (float) $x->total_kredit, 'p' => (float) $x->pendapatan, 'b' => (float) $x->beban, 'l' => (float) $x->pkl]
      : $x;

    return match ($mode) {
      'neraca' => [$x['d'], $x['k'], $x['d'] - $x['k']],
      'aktivitas' => [$x['p'], $x['b'], $x['p'] - $x['b'], $x['l'], $x['p'] - $x['b'] + $x['l']],
      default => [$x['d'], $x['k']],
    };
  }

  /** Format rupiah tanpa desimal, negatif dalam kurung. */
  public static function rp(float|int|string|null $n, bool $kosongJikaNol = false): string
  {
    $n = (float) $n;
    if (abs($n) < 0.5) {
      return $kosongJikaNol ? '' : '0';
    }
    $t = number_format(abs($n), 0, ',', '.');

    return $n < 0 ? '(' . $t . ')' : $t;
  }

  /** Set variabel sesi (prepared) lalu jalankan file SQL di koneksi yang sama. */
  private function jalankan(string $file, int $tahun, string $awal, string $akhir, array $lain = [], ?int $batas = null): array
  {
    $path = base_path(self::DIR . '/' . $file);
    $sql = is_file($path) ? file_get_contents($path) : false;
    if ($sql === false) {
      throw new RuntimeException("File SQL tidak ditemukan: {$path}");
    }

    $param = ['p_tahun' => (string) $tahun, 'p_tgl_awal' => $awal, 'p_tgl_akhir' => $akhir] + $lain;
    $set = implode(', ', array_map(fn ($k) => "@{$k} = ?", array_keys($param))); // nama variabel tetap, bukan input

    $db = DB::connection();
    $db->statement('SET ' . $set, array_values($param));

    // useReadPdo = false: PDO yang sama dengan SET agar variabel sesi terbaca.
    $sql = rtrim(trim($sql), ';') . ($batas ? ' LIMIT ' . max(1, $batas) : ''); // $batas integer, aman disisipkan

    return $db->select($sql, [], false);
  }
}
