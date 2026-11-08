<?php

namespace App\Http\Controllers\unit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pengaturan > Visi & Misi untuk unit, padanan Unit::profile dan
 * Unit::profile_simpan di v1. Unit hanya dapat mengubah profilnya sendiri.
 */
class ProfilController extends Controller
{
  public const BAGIAN = [
    'visi' => ['Visi', 'ti-eye', 'Gambaran cita-cita unit di masa depan.'],
    'misi' => ['Misi', 'ti-flag', 'Langkah-langkah untuk mewujudkan visi.'],
    'tu' => ['Tujuan Umum', 'ti-target', 'Hasil umum yang ingin dicapai unit.'],
    'tk' => ['Tujuan Khusus', 'ti-target-arrow', 'Hasil terukur yang lebih spesifik.'],
  ];

  public function edit()
  {
    $unit = Auth::user()->unit;
    abort_unless($unit, 404, 'Akun ini tidak terhubung ke unit kerja.');

    return view('content.unit.profil', ['unit' => $unit, 'bagian' => self::BAGIAN]);
  }

  public function update(Request $request)
  {
    $unit = Auth::user()->unit;
    abort_unless($unit, 404, 'Akun ini tidak terhubung ke unit kerja.');

    $aturan = array_map(fn () => ['nullable', 'string', 'max:20000'], self::BAGIAN);
    $label = array_map(fn ($b) => strtolower($b[0]), self::BAGIAN);
    $data = $request->validate($aturan, [], $label);

    // Kolom lama NOT NULL: simpan teks kosong, bukan NULL. Rapikan akhir baris.
    $unit->update(array_map(
      fn ($v) => trim(preg_replace("/[ \t]+(\r?\n)/", '$1', str_replace("\r\n", "\n", (string) $v))),
      $data
    ));

    return redirect()->route('unit.profil')->with('status', 'Visi, misi, dan tujuan unit berhasil disimpan.');
  }
}
