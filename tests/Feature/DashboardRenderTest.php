<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DashboardRenderTest extends TestCase
{
    public function test_dashboard_admin_render(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Program Studi Aktif');
    }

    public function test_dashboard_prodi_render(): void
    {
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->actingAs($prodi)
            ->get('/prodi/dashboard')
            ->assertOk();
    }

    public function test_login_admin_sampai_dashboard(): void
    {
        $this->post('/operator/login', [
            'username' => 'admin',
            'password' => 'Tracer@2026',
        ])->assertRedirect('/admin/dashboard');

        $this->assertAuthenticated();
    }

    public function test_login_prodi_diarahkan_ke_area_prodi(): void
    {
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->post('/operator/login', [
            'username' => $prodi->username,
            'password' => 'Prodi@2026',
        ])->assertRedirect('/prodi/dashboard');

        $this->assertAuthenticated();
    }

    public function test_password_salah_ditolak(): void
    {
        $this->post('/operator/login', [
            'username' => 'admin',
            'password' => 'salah-sekali',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_prodi_tidak_boleh_masuk_area_admin(): void
    {
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->actingAs($prodi)
            ->get('/admin/dashboard')
            ->assertStatus(401);
    }

    public function test_admin_tidak_boleh_masuk_area_prodi(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/prodi/dashboard')
            ->assertStatus(401);
    }

    public function test_logout_mengembalikan_ke_login_operator(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->post('/logout')
            ->assertRedirect('/operator/login');

        $this->assertGuest();
    }
}
