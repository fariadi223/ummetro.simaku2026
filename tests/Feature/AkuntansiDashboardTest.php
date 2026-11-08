<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Dashboard Akuntansi (/akuntansi): hanya GET, tidak menulis ke database.
 * Akun akuntansi = wewenang admin dengan password 'akuntansi' (aturan v1).
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiDashboardTest
 */
class AkuntansiDashboardTest extends TestCase
{
    private function akuntansi(): User
    {
        return User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail();
    }

    public function test_dashboard_akuntansi_render(): void
    {
        $this->actingAs($this->akuntansi())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => '2026'])
            ->get('/akuntansi')
            ->assertOk()
            ->assertSee('Halaman Akuntansi')
            ->assertSee('SPM Belum Dijurnal')
            ->assertSee('BKK Belum Dijurnal')
            ->assertSee('Saldo Kas')
            ->assertSee('2026/2027');
    }

    public function test_saldo_kas_sama_dengan_posisi_keuangan(): void
    {
        $r = $this->actingAs($this->akuntansi())->withSession(['wewenang' => 'akuntansi', 'periode' => '2026'])->get('/akuntansi')
            ->assertOk()->assertSee('Total Kas dan Setara Kas')->assertSee('Bank &amp; Deposito', false);
        $kas = $r->viewData('kas');

        $pos = $this->actingAs($this->akuntansi())->withSession(['wewenang' => 'akuntansi', 'periode' => '2026'])
            ->get('/akuntansi/posisi-keuangan')->viewData('bagian')['1']['baris'];
        $lv3 = $pos->where('lv', 3)->whereIn('kode', ['1.1.01', '1.1.02', '1.1.03']);
        $this->assertEqualsWithDelta($lv3->sum('saldo'), $kas['total'], 0.5);
        $this->assertEqualsWithDelta($lv3->sum('saldo_awal'), $kas['awal'], 0.5);
        $this->assertEqualsWithDelta($kas['univ'] + $kas['unit'] + $kas['bank'], $kas['total'], 0.5);
        $lv6 = $pos->where('lv', 6)->keyBy('id');
        $this->assertEqualsWithDelta((float) ($lv6[569]->saldo ?? 0), $kas['univ'], 0.5);
        $this->assertEqualsWithDelta((float) ($lv6[570]->saldo ?? 0), $kas['unit'], 0.5);

        // Sama dengan kas akhir Laporan Arus Kas.
        $ak = $this->actingAs($this->akuntansi())->withSession(['wewenang' => 'akuntansi', 'periode' => '2026'])
            ->get('/akuntansi/arus-kas')->viewData('ini');
        $this->assertEqualsWithDelta($ak['kas_akhir'], $kas['total'], 0.5);
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())
            ->withSession(['wewenang' => 'kasir'])->get('/akuntansi')->assertStatus(401);
    }
}
