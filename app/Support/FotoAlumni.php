<?php

namespace App\Support;

/**
 * Pencarian foto alumni.
 *
 * Foto disimpan di storage/app/public/images dengan nama <npm>.jpg, dan
 * diakses browser lewat symlink public/storage.
 *
 * Diletakkan terpisah karena dipakai di dua tempat: tabel Data Alumni
 * (admin) dan dashboard alumni. Supaya aturan penamaan dan lokasinya hanya
 * ditulis sekali.
 */
class FotoAlumni
{
  /** Folder foto, relatif terhadap disk 'public'. */
  public const FOLDER = 'images';

  /** Ekstensi berkas foto. */
  public const EKSTENSI = 'jpg';

  /**
   * URL foto alumni, atau null bila berkasnya tidak ada.
   *
   * Pengecekan dilakukan di sisi server supaya halaman tidak meminta gambar
   * yang berujung 404 untuk alumni yang belum punya foto -- pemanggil bisa
   * langsung memilih tampilan inisial.
   */
  public static function url(?string $npm): ?string
  {
    $berkas = self::berkas($npm);

    if ($berkas === null) {
      return null;
    }

    return file_exists(storage_path('app/public/' . $berkas))
      ? asset('storage/' . $berkas)
      : null;
  }

  /** Path berkas relatif terhadap disk 'public', atau null bila NPM kosong. */
  private static function berkas(?string $npm): ?string
  {
    $npm = trim((string) $npm);

    if ($npm === '') {
      return null;
    }

    // basename(): NPM berasal dari basis data, tapi tetap dijaga agar nilai
    // seperti '../../.env' tidak bisa dipakai menyusun path di luar folder.
    return self::FOLDER . '/' . basename($npm) . '.' . self::EKSTENSI;
  }
}
