<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/** Dashboard kasir (/kasir): hanya GET, tidak menulis ke database. */
class KasirDashboardTest extends TestCase
{
    public function test_dashboard_kasir_render(): void
    {
        $kasir = User::where('wewenang', 'kasir')->firstOrFail();

        $this->actingAs($kasir)
            ->withSession(['wewenang' => 'kasir', 'periode' => '2026'])
            ->get('/kasir')
            ->assertOk()
            ->assertSee('Antrian Pembayaran SPM')
            ->assertSee('Pembayaran Terakhir')
            ->assertSee('2026/2027');
    }

    public function test_role_lain_tidak_boleh_masuk_area_kasir(): void
    {
        $lain = User::where('wewenang', 'operatorspm')->firstOrFail();

        $this->actingAs($lain)->withSession(['wewenang' => 'operatorspm'])->get('/kasir')->assertStatus(401);
    }
}
