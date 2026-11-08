<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Periode anggaran kasir (/kasir/periode). POST hanya mengubah session 'periode' (tanpa tulis DB).
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=KasirPeriodeTest
 */
class KasirPeriodeTest extends TestCase
{
    private function kasir(): static
    {
        return $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())
            ->withSession(['wewenang' => 'kasir', 'periode' => 2026]);
    }

    public function test_halaman_dan_ganti_periode(): void
    {
        $tahun = DB::table('ueu_tbl_info')->orderBy('th_angg')->value('th_angg');

        $this->kasir()->get('/kasir/periode')->assertOk()
            ->assertSee('Periode Anggaran')->assertSee('action="' . url('/kasir/periode') . '"', false)
            ->assertSee('href="' . url('kasir') . '"', false);

        $this->kasir()->post('/kasir/periode', ['tahun' => $tahun])
            ->assertRedirect('/kasir/periode')->assertSessionHas('periode', (int) $tahun);

        $this->kasir()->from('/kasir/periode')->post('/kasir/periode', ['tahun' => 1900])
            ->assertRedirect('/kasir/periode')->assertSessionHasErrors('tahun')->assertSessionHas('periode', 2026);
    }

    /** Halaman periode unit memakai view yang sama — pastikan tetap mengarah ke route unit. */
    public function test_periode_unit_tetap(): void
    {
        $unit = User::where('wewenang', 'unit')->firstOrFail();
        $this->actingAs($unit)->withSession(['wewenang' => 'unit', 'periode' => 2026])->get('/unit/periode')->assertOk()
            ->assertSee('action="' . url('/unit/periode') . '"', false)->assertSee('href="' . url('unit') . '"', false);
    }

    public function test_periode_akuntansi(): void
    {
        $tahun = DB::table('ueu_tbl_info')->orderBy('th_angg')->value('th_angg');
        $ak = fn () => $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => 2026]);

        $ak()->get('/akuntansi/periode')->assertOk()
            ->assertSee('action="' . url('/akuntansi/periode') . '"', false)->assertSee('href="' . url('akuntansi') . '"', false);
        $ak()->post('/akuntansi/periode', ['tahun' => $tahun])
            ->assertRedirect('/akuntansi/periode')->assertSessionHas('periode', (int) $tahun);
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/periode')->assertStatus(401);
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi'])->get('/kasir/periode')->assertStatus(401);
    }
}
