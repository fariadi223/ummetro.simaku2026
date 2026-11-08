<?php

namespace Tests\Feature;

use App\Http\Controllers\authentications\AuthController as Auth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImpersonateAlumniTest extends TestCase
{
    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function alumni(): object
    {
        return DB::table('alumni')
            ->whereNotNull('kode_prg')
            ->where('kode_prg', '<>', '')
            ->first(['npm_mhs', 'nama_mhs']);
    }

    public function test_admin_bisa_login_as_alumni(): void
    {
        $alumni = $this->alumni();

        DB::beginTransaction();
        try {
            $this->actingAs($this->admin())
                ->post('/admin/alumni/' . $alumni->npm_mhs . '/login-as')
                ->assertRedirect('/alumni/dashboard')
                ->assertSessionHas(Auth::KUNCI_IMPERSONASI, $this->admin()->id);

            // User aktif kini akun alumni, bukan admin.
            $this->assertSame(
                $alumni->npm_mhs,
                User::find(auth()->id())->mahasiswa_npm
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_dashboard_alumni_menampilkan_pita_login_as(): void
    {
        $alumni = $this->alumni();

        DB::beginTransaction();
        try {
            $this->actingAs($this->admin())
                ->post('/admin/alumni/' . $alumni->npm_mhs . '/login-as');

            $this->get('/alumni/dashboard')
                ->assertOk()
                ->assertSee('login as', false)
                ->assertSee('Kembali ke akun saya');
        } finally {
            DB::rollBack();
        }
    }

    public function test_stop_mengembalikan_ke_akun_admin(): void
    {
        $alumni = $this->alumni();

        DB::beginTransaction();
        try {
            $admin = $this->admin();

            $this->actingAs($admin)
                ->post('/admin/alumni/' . $alumni->npm_mhs . '/login-as');

            $this->post('/impersonate/stop')
                ->assertRedirect('/admin/alumni')
                ->assertSessionMissing(Auth::KUNCI_IMPERSONASI);

            $this->assertSame($admin->id, auth()->id());
        } finally {
            DB::rollBack();
        }
    }

    public function test_impersonasi_berlapis_ditolak(): void
    {
        $alumni = $this->alumni();
        $lain = DB::table('alumni')
            ->where('npm_mhs', '<>', $alumni->npm_mhs)
            ->first(['npm_mhs']);

        DB::beginTransaction();
        try {
            $this->actingAs($this->admin())
                ->post('/admin/alumni/' . $alumni->npm_mhs . '/login-as');

            // Sedang memerankan alumni, jadi route admin pun sudah tertutup.
            $this->post('/admin/alumni/' . $lain->npm_mhs . '/login-as')
                ->assertStatus(401);
        } finally {
            DB::rollBack();
        }
    }

    public function test_prodi_tidak_boleh_login_as(): void
    {
        $alumni = $this->alumni();
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->actingAs($prodi)
            ->post('/admin/alumni/' . $alumni->npm_mhs . '/login-as')
            ->assertStatus(401);

        $this->assertSame($prodi->id, auth()->id());
    }

    public function test_tamu_tidak_boleh_login_as(): void
    {
        $alumni = $this->alumni();

        $this->post('/admin/alumni/' . $alumni->npm_mhs . '/login-as')
            ->assertRedirect('/operator/login');

        $this->assertGuest();
    }

    public function test_stop_tanpa_sesi_impersonasi_tidak_mengubah_akun(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/impersonate/stop')
            ->assertRedirect('/admin/dashboard');

        $this->assertSame($admin->id, auth()->id());
    }

    public function test_npm_tidak_ada_menghasilkan_404(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/alumni/NPM-TIDAK-ADA/login-as')
            ->assertNotFound();
    }

    public function test_aksi_login_as_tercatat_di_activity_logs(): void
    {
        $alumni = $this->alumni();

        DB::beginTransaction();
        try {
            $sebelum = DB::table('activity_logs')->where('aksi', 'impersonate.start')->count();

            $this->actingAs($this->admin())
                ->post('/admin/alumni/' . $alumni->npm_mhs . '/login-as');

            $this->assertSame(
                $sebelum + 1,
                DB::table('activity_logs')->where('aksi', 'impersonate.start')->count()
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_dropdown_navbar_menampilkan_logout_khusus_saat_login_as(): void
    {
        $alumni = $this->alumni();

        DB::beginTransaction();
        try {
            $admin = $this->admin();

            $this->actingAs($admin)
                ->post('/admin/alumni/' . $alumni->npm_mhs . '/login-as');

            $this->get('/alumni/dashboard')
                ->assertOk()
                ->assertSee('Mode login as')
                ->assertSee('Kembali ke akun saya')
                ->assertSee('Logout penuh')
                ->assertSee($admin->name)
                // Dua jalan keluar yang berbeda harus tersedia bersamaan.
                ->assertSee('id="impersonate-stop-form"', false)
                ->assertSee('id="logout-form"', false);
        } finally {
            DB::rollBack();
        }
    }

    public function test_dropdown_navbar_normal_tanpa_logout_khusus(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Logout')
            ->assertDontSee('Mode login as')
            ->assertDontSee('Logout penuh')
            ->assertDontSee('id="impersonate-stop-form"', false);
    }

    public function test_logout_saat_login_as_kembali_ke_login_operator(): void
    {
        $alumni = $this->alumni();

        DB::beginTransaction();
        try {
            $this->actingAs($this->admin())
                ->post('/admin/alumni/' . $alumni->npm_mhs . '/login-as');

            $this->post('/logout')->assertRedirect('/operator/login');
            $this->assertGuest();
        } finally {
            DB::rollBack();
        }
    }
}
