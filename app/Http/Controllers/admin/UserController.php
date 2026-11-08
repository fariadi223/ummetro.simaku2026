<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan akun pengguna (ueu_tbl_user), padanan Admin::password* di v1.
 *
 * Aturan tabel lama yang harus dijaga:
 * - username TIDAK unik: satu unit memakai username yang sama untuk akun
 *   'unit' dan 'operator', dibedakan oleh password. Login mencari pasangan
 *   username + password yang cocok tepat satu baris, jadi yang wajib unik
 *   adalah KOMBINASI username + password.
 * - Akun admin berpassword 'akuntansi'/'penerimaan' adalah role tersendiri.
 * - Password tersimpan polos (varchar 32), tabel MyISAM (tanpa transaksi).
 */
class UserController extends Controller
{
  public function index()
  {
    $users = User::query()->with('unit:id_unit,kode,nama')->orderBy('idlog')->get();

    // Pasangan username + password yang dipakai lebih dari satu akun:
    // login akun-akun ini selalu gagal (v1 & v2 menuntut tepat satu baris).
    $bentrok = DB::table('ueu_tbl_user')
      ->select('username', 'password')
      ->groupBy('username', 'password')
      ->havingRaw('COUNT(*) > 1')
      ->get()
      ->map(fn ($r) => mb_strtolower($r->username . "\0" . $r->password))
      ->flip();

    $baris = $users->map(function (User $u) use ($bentrok) {
      $peringatan = [];
      $efektif = $u->wewenangEfektif();

      if (isset($bentrok[mb_strtolower($u->username . "\0" . $u->password)])) {
        $peringatan[] = ['danger', 'Login bentrok: ada akun lain dengan username & password yang sama, sehingga tidak bisa login.'];
      }
      if (!$efektif || !isset(User::LABEL_ROLE[$efektif])) {
        $peringatan[] = ['danger', 'Wewenang "' . ($u->wewenang ?: 'kosong') . '" tidak punya area di SIMAKU, sehingga tidak bisa login.'];
      }
      if ((int) $u->id_unit !== 0 && !$u->unit) {
        $peringatan[] = ['warning', "Unit dengan id {$u->id_unit} tidak ditemukan."];
      }
      if (!$u->isAkunRole() && (mb_strlen($u->password) < 6 || strcasecmp($u->password, $u->username) === 0)) {
        $peringatan[] = ['warning', 'Password lemah (kurang dari 6 karakter atau sama dengan username).'];
      }

      return (object) [
        'user' => $u,
        'efektif' => $efektif,
        'label' => User::LABEL_ROLE[$efektif] ?? ($u->wewenang ?: '(kosong)'),
        'peringatan' => $peringatan,
      ];
    });

    $units = Unit::orderBy('kode')->get(['id_unit', 'kode', 'nama']);

    return view('content.admin.user.index', [
      'baris' => $baris,
      ...$this->pohon($baris, $units),
      'units' => $units,
      'labelRole' => User::LABEL_ROLE,
      'wewenangTersedia' => User::WEWENANG_TERSEDIA,
      'statistik' => [
        'total' => $users->count(),
        'peran' => $baris->countBy('efektif')->count(),
        'bermasalah' => $baris->filter(fn ($b) => collect($b->peringatan)->contains(fn ($p) => $p[0] === 'danger'))->count(),
        'lemah' => $baris->filter(fn ($b) => collect($b->peringatan)->contains(fn ($p) => str_starts_with($p[1], 'Password lemah')))->count(),
      ],
      'idSaya' => Auth::id(),
    ]);
  }

  /**
   * Susun akun menjadi pohon mengikuti kode unit:
   *
   *   Universitas (id_unit 0)
   *   00.00 Rektorat          <- unit induk + akunnya
   *     ├ 00.01 Rektor        <- sub-unit + akunnya
   *     └ 00.02 Wakil Rektor 1
   *   01.00 Dekan FKIP
   *     └ 01.01 ...
   *   Unit tidak ditemukan    <- id_unit yang tidak ada di ueu_tbl_unit
   */
  private function pohon($baris, $units): array
  {
    $perUnit = $baris->groupBy(fn ($b) => (int) $b->user->id_unit);
    $simpul = fn (Unit $unit) => (object) ['unit' => $unit, 'akun' => $perUnit->get($unit->id_unit, collect())];

    $grup = $units->groupBy(fn ($u) => $u->kode_induk)->map(function ($anggota, $kode) use ($simpul) {
      $induk = $anggota->firstWhere('is_induk', true);
      $anak = $anggota->reject(fn ($u) => $induk && $u->id_unit === $induk->id_unit)->map($simpul)->values();
      $indukSimpul = $induk ? $simpul($induk) : null;

      return (object) [
        'kode' => (string) $kode,
        'nama' => $induk?->nama ?? "Kelompok {$kode}",
        'induk' => $indukSimpul,
        'anak' => $anak,
        'jumlah' => ($indukSimpul?->akun->count() ?? 0) + $anak->sum(fn ($s) => $s->akun->count()),
      ];
    })->values();

    $idAda = $units->pluck('id_unit')->flip();

    return [
      'pohon' => $grup,
      'akunUniversitas' => $perUnit->get(0, collect()),
      'akunTanpaUnit' => $baris->filter(fn ($b) => (int) $b->user->id_unit !== 0 && !isset($idAda[(int) $b->user->id_unit]))->values(),
    ];
  }

  public function store(Request $request)
  {
    $data = $request->validate($this->aturan(true), [], $this->label());

    $this->cekPassword($data['wewenang'], $data['password']);
    $this->cekBentrok($data['username'], $data['password']);

    User::create([
      'id_unit' => (int) ($data['id_unit'] ?? 0),
      'nama_lengkap' => $data['nama_lengkap'] ?: null,
      'username' => $data['username'],
      'password' => $data['password'],
      'wewenang' => $data['wewenang'],
    ]);

    return back()->with('status', "Akun {$data['username']} berhasil ditambahkan.");
  }

  /** Ubah data akun (tanpa password). */
  public function update(Request $request, User $user)
  {
    $data = $request->validate($this->aturan(false), [], $this->label());

    if ($user->idlog === Auth::id() && $data['wewenang'] !== $user->wewenang) {
      throw ValidationException::withMessages(['wewenang' => 'Anda tidak dapat mengubah wewenang akun Anda sendiri.']);
    }
    // Wewenang admin ikut menentukan role akuntansi/penerimaan.
    if ($user->isAkunRole() && $data['wewenang'] !== 'admin') {
      throw ValidationException::withMessages(['wewenang' => 'Akun ini adalah akun role ' . ucfirst($user->password) . '; wewenangnya harus tetap admin.']);
    }
    if ($data['wewenang'] === 'admin' && !$user->isAkunRole()) {
      $this->cekPassword('admin', $user->password, 'wewenang');
    }

    $this->cekBentrok($data['username'], $user->password, $user->idlog);

    $user->update([
      'id_unit' => (int) ($data['id_unit'] ?? 0),
      'nama_lengkap' => $data['nama_lengkap'] ?: null,
      'username' => $data['username'],
      'wewenang' => $data['wewenang'],
    ]);

    return back()->with('status', "Akun {$user->username} berhasil diperbarui.");
  }

  public function password(Request $request, User $user)
  {
    if ($user->isAkunRole()) {
      return back()->with('error', "Password akun {$user->username} menentukan role " . ucfirst($user->password) . ' sehingga tidak dapat diganti di sini.');
    }

    $data = $request->validate([
      'password' => ['required', 'string', 'min:6', 'max:32', 'confirmed'],
    ], [], ['password' => 'password baru']);

    $this->cekPassword($user->wewenang, $data['password'], 'password');
    $this->cekBentrok($user->username, $data['password'], $user->idlog, 'password');

    $user->update(['password' => $data['password']]);

    Log::info('SIMAKU: password akun diganti', ['oleh' => Auth::user()->username, 'akun' => $user->idlog]);

    return back()->with('status', "Password akun {$user->username} berhasil diganti.");
  }

  /**
   * Tampilkan password satu akun (v1 menampilkan semua password di tabel).
   * Dicatat di log karena password masih tersimpan polos.
   */
  public function lihatPassword(User $user)
  {
    Log::info('SIMAKU: password akun dilihat', ['oleh' => Auth::user()->username, 'akun' => $user->idlog]);

    return response()->json(['password' => $user->password]);
  }

  public function destroy(User $user)
  {
    if ($user->idlog === Auth::id()) {
      return back()->with('error', 'Anda tidak dapat menghapus akun yang sedang dipakai.');
    }

    $user->delete();

    return back()->with('status', "Akun {$user->username} (" . (User::LABEL_ROLE[$user->wewenangEfektif()] ?? $user->wewenang) . ') berhasil dihapus.');
  }

  private function aturan(bool $baru): array
  {
    $aturan = [
      'id_unit' => ['nullable', 'integer', function ($attr, $nilai, $gagal) {
        if ((int) $nilai !== 0 && !Unit::whereKey($nilai)->exists()) {
          $gagal('Unit tidak ditemukan.');
        }
      }],
      'nama_lengkap' => ['nullable', 'string', 'max:140'],
      'username' => ['required', 'string', 'max:32', 'regex:/^\S+$/'],
      'wewenang' => ['required', Rule::in(User::WEWENANG_TERSEDIA)],
    ];

    if ($baru) {
      $aturan['password'] = ['required', 'string', 'min:6', 'max:32', 'confirmed'];
    }

    return $aturan;
  }

  private function label(): array
  {
    return [
      'id_unit' => 'unit', 'nama_lengkap' => 'nama lengkap', 'username' => 'username',
      'password' => 'password', 'wewenang' => 'wewenang',
    ];
  }

  /** Admin + password khusus akan berubah menjadi role lain; cegah tak sengaja. */
  private function cekPassword(string $wewenang, string $password, string $kolom = 'password'): void
  {
    if ($wewenang === 'admin' && in_array(mb_strtolower($password), User::PASSWORD_ROLE, true)) {
      throw ValidationException::withMessages([
        $kolom => "Password \"{$password}\" dicadangkan untuk role " . ucfirst(mb_strtolower($password)) . '. Gunakan password lain.',
      ]);
    }
  }

  /** Kombinasi username + password wajib unik agar login tetap bisa. */
  private function cekBentrok(string $username, string $password, ?int $kecuali = null, string $kolom = 'username'): void
  {
    // Kolom username/password ber-collation utf8_general_ci (tidak peka huruf besar),
    // sama dengan cara login mencocokkan.
    $ada = DB::table('ueu_tbl_user')
      ->where('username', $username)
      ->where('password', $password)
      ->when($kecuali, fn ($q) => $q->where('idlog', '!=', $kecuali))
      ->exists();

    if ($ada) {
      throw ValidationException::withMessages([
        $kolom => 'Sudah ada akun lain dengan username dan password yang sama; login akan bentrok. Gunakan password lain.',
      ]);
    }
  }
}
