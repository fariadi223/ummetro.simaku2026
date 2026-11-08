<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saldo anggaran unit (/akuntansi/saldo-unit), hanya GET.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiSaldoUnitTest
 */
class AkuntansiSaldoUnitTest extends TestCase
{
    private const TAHUN = '2026';

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    public function test_saldo_sama_dengan_rumus_v1(): void
    {
        // v1: Σ debet − Σ kredit idrek6 570 per kodeunit pada T.A.
        $v1 = DB::table('ueu_tbl_jurnal_spm')->where('tahun', self::TAHUN)->where('idrek6', 570)->groupBy('kodeunit')
            ->selectRaw('kodeunit, SUM(debet) - SUM(kredit) as saldo')->pluck('saldo', 'kodeunit');

        $this->akuntansi()->get('/akuntansi/saldo-unit')->assertOk()
            ->assertSee('Saldo Anggaran Unit')
            ->assertViewHas('daftar', fn ($d) => $d->isNotEmpty() && $d->every(fn ($r) => abs($r->saldo - (float) $v1[$r->kodeunit]) < 0.01
                && abs($r->diterima - $r->realisasi - $r->disetor - $r->lainnya - $r->saldo) < 0.01));
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/saldo-unit')->assertStatus(401);
    }
}
