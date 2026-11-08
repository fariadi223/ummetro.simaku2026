<?php

namespace Tests\Feature;

use App\Services\NeracaPercobaan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Command saldo:pindah — hanya jalur yang TIDAK menulis (dry-run, validasi, batal).
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=SaldoPindahCommandTest
 */
class SaldoPindahCommandTest extends TestCase
{
    public function test_per_sumber_sama_dengan_neraca_percobaan(): void
    {
        $np = app(NeracaPercobaan::class);
        $perSumber = $np->saldoAkhirPerSumber(2025);
        $lv6 = $np->hitung(2025)['baris']->where('lv', 6)->keyBy('id');

        $this->assertTrue($perSumber->isNotEmpty());
        $this->assertTrue($perSumber->every(fn ($r) => in_array($r->koderek1, ['1', '2', '3'], true)));
        foreach ($perSumber->groupBy('idrek6') as $id => $g) {
            // Akun yang saldonya saling meniadakan antar sumber dana tidak tampil di neraca (total 0).
            $this->assertEqualsWithDelta($lv6[$id]->akhir ?? 0.0, $g->sum('net'), 0.5, "akun {$id}");
        }
    }

    public function test_surplus_menyeimbangkan(): void
    {
        $np = app(NeracaPercobaan::class);
        $rek45 = $np->hitung(2025)['baris']->where('lv', 1)->whereIn('kode', ['4', '5'])->sum('akhir');
        $surplus = $np->surplusPerSumber(2025);

        // Total surplus per sumber dana = −(saldo akhir rek 4 + rek 5) di neraca percobaan.
        $this->assertEqualsWithDelta(-$rek45, $surplus->sum('surplus'), 0.5);
        $this->assertTrue($surplus->every(fn ($r) => in_array($r->idrek6, [262, 274], true)));
        // Rek 1–3 + surplus = seimbang.
        $this->assertEqualsWithDelta(0.0, $np->saldoAkhirPerSumber(2025)->sum('net') + $surplus->sum('net'), 0.5);

        $sebelum = DB::table('ueu_tbl_jurnal_saldo')->count();
        $this->artisan('saldo:pindah', ['tahun' => '2025', '--surplus' => true, '--dry-run' => true])
            ->expectsOutputToContain('Seimbang')->assertSuccessful();
        $this->assertSame($sebelum, DB::table('ueu_tbl_jurnal_saldo')->count());
    }

    public function test_dry_run_dan_batal_tidak_menulis(): void
    {
        $sebelum = DB::table('ueu_tbl_jurnal_saldo')->count();
        $n = app(NeracaPercobaan::class)->saldoAkhirPerSumber(2025)->count();

        $this->artisan('saldo:pindah', ['tahun' => '2025', '--dry-run' => true])
            ->expectsOutputToContain("Dry run: {$n} baris akan ditulis ke tahun 2026")->assertSuccessful();
        $this->artisan('saldo:pindah', ['tahun' => '25'])->assertExitCode(2);
        $this->artisan('saldo:pindah', ['tahun' => '1990'])->expectsOutputToContain('Tidak ada saldo')->assertSuccessful();

        $tujuanAda = DB::table('ueu_tbl_jurnal_saldo')->where('tahun', '2026')->whereRaw("LOWER(uraian) LIKE 'saldo awal%'")->exists();
        $cmd = $this->artisan('saldo:pindah', ['tahun' => '2025']);
        if ($tujuanAda) {
            $cmd->expectsOutputToContain('--ganti')->assertFailed(); // menolak tanpa --ganti
        } else {
            $cmd->expectsQuestion('Ketik tahun tujuan (2026) untuk melanjutkan', 'tidak')->expectsOutput('Dibatalkan.')->assertFailed();
        }

        $this->assertSame($sebelum, DB::table('ueu_tbl_jurnal_saldo')->count());
    }
}
