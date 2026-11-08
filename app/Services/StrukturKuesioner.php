<?php

namespace App\Services;

/**
 * Aturan penyusunan tampilan kuesioner, dipakai bersama oleh pratinjau
 * administrator dan halaman pengisian alumni supaya keduanya tidak pernah
 * menampilkan struktur yang berbeda.
 */
class StrukturKuesioner
{
  /** Awalan kode pertanyaan kompetensi yang ditampilkan sebagai matriks A/B. */
  public const PREFIKS_MATRIKS = 'f17';

  /**
   * Nilai opsi -> nilai kolom ringkasan di tabel respon.
   *
   * Daftar nilainya dibatasi CHECK constraint chk_respon_status_saat_ini dan
   * chk_respon_kesesuaian_bidang, jadi tidak boleh diisi sembarang teks.
   */
  public const PETA_RINGKASAN = [
    'status_saat_ini' => [
      '1' => 'bekerja',
      '2' => 'belum_memungkinkan_bekerja',
      '3' => 'wiraswasta',
      '4' => 'studi_lanjut',
      '5' => 'mencari_kerja',
    ],
    'kesesuaian_bidang' => [
      '1' => 'sangat_erat',
      '2' => 'erat',
      '3' => 'cukup_erat',
      '4' => 'kurang_erat',
      '5' => 'tidak_sama_sekali',
    ],
  ];

  /**
   * Pertanyaan bertipe 'text' yang isinya punya bentuk baku, dikenali dari
   * kodenya.
   *
   * Tipe pertanyaan di database tidak mengenal 'email' atau 'telepon'
   * (dibatasi chk_pertanyaan_tipe), jadi pembedanya kode pertanyaan. Dipakai
   * halaman pengisian untuk atribut kontrolnya, dan penyimpanan untuk
   * memeriksa isinya.
   */
  public const ISI_EMAIL = 'email';
  public const ISI_TELEPON = 'telepon';

  private const ISI_KHUSUS = [
    'emailmsmh' => self::ISI_EMAIL,
    'telpomsmh' => self::ISI_TELEPON,
  ];

  /** Panjang maksimal, menyamakan dengan kolom email/telepon tabel alumni. */
  private const PANJANG_EMAIL = 165;

  /** Bentuk baku isi pertanyaan ini, atau null bila teks bebas. */
  public static function jenisIsi(string $kode): ?string
  {
    return self::ISI_KHUSUS[$kode] ?? null;
  }

  /**
   * Rapikan isi sebelum disimpan.
   *
   * Nomor telepon dibuang pemisahnya ("0812-3456-7890" -> "081234567890")
   * supaya yang tersimpan -- dan ikut berkas pelaporan -- selalu berupa
   * deretan angka, bukan bergantung cara alumni mengetiknya.
   */
  public static function rapikanIsi(string $kode, string $nilai): string
  {
    if (self::jenisIsi($kode) !== self::ISI_TELEPON) {
      return $nilai;
    }

    return preg_replace('/[\s().\-]/u', '', $nilai) ?? $nilai;
  }

  /**
   * Pesan kesalahan bila isinya tidak sesuai bentuk bakunya, atau null bila
   * sah. Nilai kosong dianggap sah: "wajib diisi" diperiksa terpisah.
   */
  public static function pesanIsiTidakSah(string $kode, string $nilai): ?string
  {
    if ($nilai === '') {
      return null;
    }

    return match (self::jenisIsi($kode)) {
      self::ISI_EMAIL => filter_var($nilai, FILTER_VALIDATE_EMAIL) !== false
        && mb_strlen($nilai) <= self::PANJANG_EMAIL
          ? null
          : 'Alamat email tidak valid. Contoh: nama@contoh.com',

      // Pemisah sudah dibuang rapikanIsi(), jadi yang tersisa harus angka
      // dengan kode negara opsional.
      self::ISI_TELEPON => preg_match('/^\+?[0-9]{8,15}$/', $nilai) === 1
        ? null
        : 'Nomor telepon tidak valid. Isi 8-15 angka, contoh: 081234567890',

      default => null,
    };
  }

  /**
   * Susun pertanyaan satu bagian menjadi daftar blok tampilan.
   *
   * Pertanyaan berkode f17xx datang berpasangan untuk tiap kompetensi: satu
   * menanyakan tingkat yang dikuasai saat lulus (A) dan satu tingkat yang
   * diperlukan dalam pekerjaan (B). Teks keduanya nyaris sama dan sangat
   * panjang, sehingga bila dicetak satu per satu akan tampil sebagai belasan
   * pertanyaan berulang. Pasangan itu digabung menjadi satu matriks dua
   * kolom, seperti bentuk aslinya pada borang tracer study.
   *
   * Pertanyaan lain -- termasuk f17xx yang pola teksnya tidak dikenali --
   * tetap dicetak satu per satu, supaya tidak ada yang hilang.
   *
   * @return array<int, array<string, mixed>>
   */
  public static function susunBlok($pertanyaan): array
  {
    $blok = [];
    $posisiMatriks = null;
    $baris = [];
    $kolom = [];
    $introKandidat = [];

    foreach ($pertanyaan as $soal) {
      $urai = self::uraiKompetensi($soal);

      if (!$urai) {
        $blok[] = ['jenis' => 'soal', 'soal' => $soal];
        continue;
      }

      // Matriks menempati posisi pertanyaan f17 yang pertama muncul.
      if ($posisiMatriks === null) {
        $posisiMatriks = count($blok);
        $blok[] = null;
      }

      $baris[$urai['label']][$urai['sisi']] = $soal;
      $kolom[$urai['sisi']] = $kolom[$urai['sisi']] ?? $urai['keterangan'];
      $introKandidat[] = $urai['intro'];
    }

    if ($posisiMatriks !== null) {
      $blok[$posisiMatriks] = [
        'jenis' => 'matriks',
        'intro' => self::introTersering($introKandidat),
        'kolom' => [
          'A' => $kolom['A'] ?? 'saat lulus',
          'B' => $kolom['B'] ?? 'diperlukan dalam pekerjaan',
        ],
        'baris' => $baris,
      ];
    }

    return $blok;
  }

  /**
   * Pecah teks pertanyaan kompetensi menjadi intro, nama kompetensi, dan
   * sisi A/B. Mengembalikan null bila pertanyaan ini bukan bagian matriks.
   *
   * Contoh teks: "Pada saat lulus ... (A) Pada saat ini ... (B) - Etika (A: saat lulus)"
   */
  private static function uraiKompetensi($soal): ?array
  {
    if (!str_starts_with((string) $soal->kode, self::PREFIKS_MATRIKS) || $soal->tipe !== 'scale') {
      return null;
    }

    // Pertanyaan bersyarat punya aturan tampil/sembunyi sendiri yang tidak
    // bisa diwakili oleh satu sel matriks.
    if ($soal->tampil_jika_pertanyaan_id) {
      return null;
    }

    if (!preg_match('/^(.*?)\s*-\s*([^-]+?)\s*\((A|B):\s*([^)]*)\)\s*$/us', (string) $soal->teks, $m)) {
      return null;
    }

    return [
      'intro' => trim(preg_replace('/\s+/u', ' ', $m[1])),
      'label' => trim($m[2]),
      'sisi' => $m[3],
      'keterangan' => trim($m[4]),
    ];
  }

  /**
   * Intro yang paling sering muncul di antara pasangan kompetensi.
   *
   * Hasil impor kadang menyisipkan judul kelompok ("ETIKA") di awal teks
   * pertanyaan pertama; mengambil yang tersering membuat judul matriks
   * memakai kalimat yang bersih.
   */
  private static function introTersering(array $kandidat): string
  {
    $kandidat = array_filter($kandidat, fn ($t) => $t !== '');

    if ($kandidat === []) {
      return '';
    }

    $hitung = array_count_values($kandidat);
    arsort($hitung);

    return (string) array_key_first($hitung);
  }
}
