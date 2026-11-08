<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Login SIMAKU, padanan controller Login di v1 (CodeIgniter).
 *
 * Halaman login memakai tema Neon v1 dan mengirim form lewat AJAX ke
 * `login/validation` (neon-login.js). Respons JSON-nya sama dengan v1:
 *   { login_status: 'success'|'invalid', redirect_url: '...' }
 */
class AuthController extends Controller
{
  public function index()
  {
    if (Auth::check()) {
      return redirect(Auth::user()->homeUrl());
    }

    return view('content.authentications.auth-login', [
      'sim' => 'SIMAKU',
      'simlong' => 'Sistem Informasi Manajemen dan Akuntansi Keuangan',
    ]);
  }

  public function validation(Request $request): JsonResponse
  {
    $gagal = ['submitted_data' => [], 'login_status' => 'invalid'];

    $validator = Validator::make($request->all(), [
      'username' => 'required|string|max:32',
      'password' => 'required|string|max:32',
    ]);

    if ($validator->fails()) {
      return response()->json($gagal);
    }

    // Sama seperti v1: cocokkan username + password langsung di tabel,
    // dan hanya diterima bila tepat satu baris yang cocok.
    $users = User::where('username', $request->input('username'))
      ->where('password', $request->input('password'))
      ->limit(2)
      ->get();

    if ($users->count() !== 1) {
      return response()->json($gagal);
    }

    $user = $users->first();
    $akses = $this->tentukanAkses($user);

    if ($akses === null) {
      // Wewenang yang tidak punya area di SIMAKU (mis. kosong, tufakultas, rektor).
      return response()->json($gagal);
    }

    Auth::login($user);
    $request->session()->regenerate();

    session(array_merge([
      'id_login' => $user->idlog,
      'id_unit_masthead' => $user->id_unit,
      'username' => $user->username,
    ], $akses['session'], [
      'home_url' => url($akses['url']),
    ]));

    return response()->json([
      'submitted_data' => [],
      'login_status' => 'success',
      'redirect_url' => url($akses['url']),
    ]);
  }

  public function logout(Request $request)
  {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
  }

  /**
   * Turunkan role efektif, tahun periode, dan URL tujuan dari akun.
   * Logika mengikuti Login::validation() di v1.
   *
   * @return array{url: string, session: array}|null
   */
  private function tentukanAkses(User $user): ?array
  {
    $periodeAktif = fn () => Info::tahun(['aktif' => 'enable']);
    $periodeJurnal = fn () => Info::tahun(['penjurnalan' => 'open']);

    $wewenang = $user->wewenang;

    // Di v1, akun admin dengan password khusus menjadi role tersendiri.
    if ($wewenang === 'admin' && in_array($user->password, ['akuntansi', 'penerimaan'], true)) {
      return $this->akses($user->password, $user->password, $periodeJurnal());
    }

    switch ($wewenang) {
      case 'kasir':
        return $this->akses('kasir', 'kasir', $periodeJurnal());

      case 'op1':
      case 'op2':
        return $this->akses($wewenang, 'penggajian', $periodeAktif());

      case 'admin':
        return $this->akses('admin', 'admin', $periodeAktif());

      case 'review':
        return $this->akses('review', 'reviewer', $periodeAktif());

      case 'operatorspm':
        return $this->akses('operatorspm', 'adminspm', $periodeJurnal());

      case 'otorisator1':
      case 'otorisator2':
        return $this->akses($wewenang, 'dashboard', $periodeJurnal());

      case 'operator':
        $unit = Unit::find($user->id_unit);

        return $this->akses('operator', 'adminfakultas', $periodeAktif(), [
          'unitkode' => explode('.', (string) $unit?->kode),
        ]);

      case 'unit':
        $unit = Unit::find($user->id_unit);
        $kode = (string) $unit?->kode;
        $unitkode = explode('.', $kode);
        $induk = ($unitkode[1] ?? null) === '00';

        $tambahan = ['unitkode' => $unitkode];
        if (!$induk && $kode === '11.01') {
          $tambahan['pengeluaran'] = true;
        }
        if ($user->username === '0000') {
          $tambahan['rektor'] = true;
        }

        return $this->akses('unit', $induk ? 'adminunit' : 'unit', $periodeAktif(), $tambahan);
    }

    return null;
  }

  private function akses(string $wewenang, string $url, int $periode, array $tambahan = []): array
  {
    return [
      'url' => $url,
      'session' => array_merge(['wewenang' => $wewenang, 'periode' => $periode], $tambahan),
    ];
  }
}
