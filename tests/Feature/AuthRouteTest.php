<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthRouteTest extends TestCase
{
    public function test_halaman_login_alumni_tampil(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk Alumni')
            ->assertDontSee('operator/login');
    }

    public function test_halaman_login_operator_tampil_dan_noindex(): void
    {
        $this->get('/operator/login')
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('noindex', false);
    }

    public function test_login_alumni_wajib_isi(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors(['npm', 'kode_prg']);
    }

    public function test_login_alumni_npm_dan_prodi_tidak_cocok_ditolak(): void
    {
        $alumni = DB::table('alumni')
            ->whereNotNull('kode_prg')
            ->where('kode_prg', '<>', '')
            ->first(['npm_mhs', 'kode_prg']);

        // Kode prodi lain, sehingga pasangannya pasti tidak cocok.
        $prodiLain = DB::table('alumni')
            ->where('kode_prg', '<>', $alumni->kode_prg)
            ->whereNotNull('kode_prg')
            ->value('kode_prg');

        $this->post('/login', ['npm' => $alumni->npm_mhs, 'kode_prg' => $prodiLain])
            ->assertSessionHasErrors('npm');

        $this->assertGuest();
    }

    public function test_halaman_login_alumni_memuat_pilihan_prodi(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="kode_prg"', false)
            ->assertDontSee('name="tanggal_lahir"', false);
    }

    public function test_area_internal_menolak_tamu(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/operator/login');
        $this->get('/prodi/dashboard')->assertRedirect('/operator/login');
    }

    public function test_area_alumni_mengarahkan_tamu_ke_login_alumni(): void
    {
        $this->get('/alumni/dashboard')->assertRedirect('/login');
    }

    public function test_root_mengarah_ke_login_alumni(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
