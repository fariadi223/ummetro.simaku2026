<?php

namespace App\Support;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Susunan kolom berkas impor alumni.
 *
 * Satu-satunya tempat format kolom ditulis. Dipakai bertiga oleh:
 *   - AlumniTemplateExport  -> menyusun baris judul berkas contoh
 *   - AlumniImport          -> mengenali kolom berkas yang diunggah
 *   - halaman Data Alumni   -> menampilkan panduan kolom di modal impor
 *
 * Kalau ada kolom baru, cukup tambahkan di sini: berkas contoh, pembaca
 * berkas, dan panduan di layar ikut berubah bersamaan.
 */
class FormatImporAlumni
{
  /**
   * Definisi kolom, berurutan sesuai letaknya di berkas Excel.
   *
   * - label     : judul kolom di baris pertama berkas
   * - alias     : nama lain yang tetap dikenali saat berkas dibaca, supaya
   *               berkas dari sumber lain tidak harus diubah judulnya lebih
   *               dulu. Semua dibandingkan setelah di-slug ('NO. HP' -> 'no_hp').
   * - wajib     : kolomnya harus terisi di setiap baris
   * - jenis     : cara nilainya dibaca -- 'teks', 'angka', atau 'tanggal'
   * - lebar     : lebar kolom di berkas contoh
   * - format    : format sel berkas contoh; teks dipakai untuk NPM, NIK,
   *               telepon, dan tahun supaya angka nol di depan tidak hilang
   *               dan nomor panjang tidak berubah jadi notasi ilmiah
   * - contoh    : contoh isi, ditampilkan di panduan dan di catatan sel
   * - keterangan: penjelasan singkat untuk panduan di layar
   */
  private const KOLOM = [
    'npm_mhs' => [
      'label' => 'NPM',
      'alias' => ['npm', 'nim', 'nomor_mahasiswa', 'no_mhs'],
      'wajib' => true,
      'jenis' => 'teks',
      'lebar' => 16,
      'format' => NumberFormat::FORMAT_TEXT,
      'contoh' => '19120001',
      'keterangan' => 'Wajib, maksimal 15 karakter. Menjadi kunci data: NPM yang sama tidak boleh muncul dua kali.',
    ],
    'nama_mhs' => [
      'label' => 'NAMA LENGKAP',
      'alias' => ['nama', 'nama_mahasiswa', 'nama_alumni'],
      'wajib' => true,
      'jenis' => 'teks',
      'lebar' => 32,
      'contoh' => 'Budi Santoso',
      'keterangan' => 'Wajib, maksimal 160 karakter.',
    ],
    'nik' => [
      'label' => 'NIK',
      'alias' => ['nomor_induk_kependudukan'],
      'jenis' => 'teks',
      'lebar' => 22,
      'format' => NumberFormat::FORMAT_TEXT,
      'contoh' => '1872014501990002',
      'keterangan' => 'Maksimal 25 karakter dan tidak boleh sama antar alumni. '
        . 'Pastikan selnya berformat teks, kalau tidak angka 16 digit bisa dibulatkan Excel.',
    ],
    'email' => [
      'label' => 'EMAIL',
      'alias' => ['surel', 'e_mail', 'alamat_email'],
      'jenis' => 'teks',
      'lebar' => 28,
      'contoh' => 'budi.santoso@gmail.com',
      'keterangan' => 'Maksimal 165 karakter, harus berformat email.',
    ],
    'tlpn' => [
      'label' => 'TELEPON',
      'alias' => ['no_hp', 'hp', 'nomor_hp', 'no_telepon', 'nomor_telepon', 'wa'],
      'jenis' => 'teks',
      'lebar' => 16,
      'format' => NumberFormat::FORMAT_TEXT,
      'contoh' => '081234567890',
      'keterangan' => 'Maksimal 20 karakter. Berformat teks supaya angka 0 di depan tidak hilang.',
    ],
    'alamat_asal' => [
      'label' => 'ALAMAT',
      'alias' => ['alamat_lengkap'],
      'jenis' => 'teks',
      'lebar' => 36,
      'contoh' => 'Jl. Ki Hajar Dewantara No. 116, Metro',
      'keterangan' => 'Maksimal 185 karakter.',
    ],
    'kode_prg' => [
      'label' => 'KODE PRODI',
      'alias' => ['kode_program_studi', 'kode_prodi'],
      'jenis' => 'teks',
      'lebar' => 12,
      'format' => NumberFormat::FORMAT_TEXT,
      'contoh' => '86205',
      'keterangan' => 'Maksimal 8 karakter. Dipakai filter program studi, jadi sebaiknya diisi.',
    ],
    'nama_prg' => [
      'label' => 'PROGRAM STUDI',
      'alias' => ['prodi', 'nama_program_studi', 'nama_prodi'],
      'jenis' => 'teks',
      'lebar' => 30,
      'contoh' => 'Pendidikan Matematika',
      'keterangan' => 'Maksimal 180 karakter.',
    ],
    'nama_fak' => [
      'label' => 'FAKULTAS',
      'alias' => ['nama_fakultas'],
      'jenis' => 'teks',
      'lebar' => 28,
      'contoh' => 'FKIP',
      'keterangan' => 'Maksimal 100 karakter.',
    ],
    'thnlulus' => [
      'label' => 'TAHUN LULUS',
      'alias' => ['tahun_lulus', 'thn_lulus', 'tahun_kelulusan'],
      'jenis' => 'teks',
      'lebar' => 13,
      'format' => NumberFormat::FORMAT_TEXT,
      'contoh' => '2024',
      'keterangan' => 'Empat angka, misal 2024. Dipakai filter tahun lulus.',
    ],
  ];

  /** Seluruh definisi kolom, berurutan, dengan nama kolom tabel sebagai kunci. */
  public static function kolom(): array
  {
    return self::KOLOM;
  }

  /** Judul kolom untuk baris pertama berkas; kolom wajib ditandai bintang. */
  public static function judul(): array
  {
    return array_map(
      fn (array $k) => ($k['wajib'] ?? false) ? $k['label'] . ' *' : $k['label'],
      array_values(self::KOLOM)
    );
  }

  /**
   * Peta nama judul (sudah di-slug) -> nama kolom tabel.
   *
   * Label resmi, seluruh aliasnya, dan nama kolom tabel itu sendiri semuanya
   * masuk. Yang terakhir membuat berkas hasil ekspor sistem lain yang memakai
   * nama kolom asli tetap bisa dibaca tanpa diubah judulnya.
   *
   * Tanda bintang pada kolom wajib hilang dengan sendirinya saat di-slug
   * ('NPM *' -> 'npm'), jadi berkas contoh tidak perlu diedit sebelum diisi.
   */
  public static function petaJudul(): array
  {
    $peta = [];

    foreach (self::KOLOM as $field => $k) {
      $nama = array_merge([$k['label'], $field], $k['alias'] ?? []);

      foreach ($nama as $n) {
        $peta[Str::slug($n, '_')] = $field;
      }
    }

    return $peta;
  }

  /** Nama kolom tabel yang wajib terisi di setiap baris. */
  public static function wajib(): array
  {
    return array_keys(array_filter(self::KOLOM, fn ($k) => $k['wajib'] ?? false));
  }

  /**
   * Nama kolom tabel -> judul kolomnya di berkas.
   *
   * Dipakai sebagai nama atribut validasi saat impor, supaya pesan
   * kesalahannya menyebut judul yang benar-benar dilihat pengguna di
   * Excel ("NAMA LENGKAP"), bukan nama kolom tabelnya ("nama mhs").
   */
  public static function atribut(): array
  {
    return array_map(fn (array $k) => $k['label'], self::KOLOM);
  }
}
