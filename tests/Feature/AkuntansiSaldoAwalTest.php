<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Saldo awal (/akuntansi/saldo-awal). Hanya GET dan POST yang ditolak validasi —
 * tidak menulis ke ueu_tbl_jurnal_saldo.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiSaldoAwalTest
 */
class AkuntansiSaldoAwalTest extends TestCase
{
    private const TAHUN = '2025'; // T.A yang masih punya saldo awal (2026 bisa kosong setelah saldo:reset)

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    /** Saldo konsolidasi satu akun menurut aturan saldo_awal_lv6 v1. */
    private function saldoV1(int $idrek6): array
    {
        $a = DB::table('ueu_tbl_rek6 as r6')->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
            ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
            ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
            ->where('r6.idrek6', $idrek6)->first(['r1.koderek1', 'r2.koderek2', 'r3.koderek3']);
        $k3 = "{$a->koderek1}.{$a->koderek2}.{$a->koderek3}";
        $normalDebet = ((string) $a->koderek1 === '1' && !in_array($k3, ['1.2.02', '1.2.04'])) || (string) $a->koderek1 === '5';
        $j = DB::table('ueu_tbl_jurnal_saldo')->where('tahun', self::TAHUN)->where('idrek6', $idrek6)->whereNotNull('id_sumber')
            ->selectRaw('COALESCE(SUM(debet),0) d, COALESCE(SUM(kredit),0) k')->first();

        return $normalDebet ? [(float) $j->d - (float) $j->k, 0.0] : [0.0, (float) $j->k - (float) $j->d];
    }

    public function test_tampil_dan_saldo_sesuai_v1(): void
    {
        $contoh = DB::table('ueu_tbl_jurnal_saldo as s')
            ->join('ueu_tbl_rek6 as r6', 'r6.idrek6', '=', 's.idrek6')->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
            ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
            ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
            ->where('s.tahun', self::TAHUN)->whereNotIn('r1.koderek1', ['4', '5'])->distinct()->limit(15)->pluck('s.idrek6');

        $this->akuntansi()->get('/akuntansi/saldo-awal')->assertOk()
            ->assertSee('Saldo Awal')->assertSee('01 September 2025')->assertSee('id="modalSaldo"', false)
            ->assertViewHas('baris', function ($b) use ($contoh) {
                $akun = $b->where('lv', 6)->keyBy('id');
                $tanpa45 = $b->where('lv', 1)->every(fn ($r) => !in_array($r->kode, ['4', '5']));

                return $tanpa45 && $contoh->isNotEmpty() && $contoh->every(function ($id) use ($akun) {
                    [$d, $k] = $this->saldoV1($id);

                    return abs($akun[$id]->nilai['d'] - $d) < 0.01 && abs($akun[$id]->nilai['k'] - $k) < 0.01;
                });
            });

        $this->akuntansi()->get('/akuntansi/saldo-awal?saldo=all&isi=1')->assertOk()
            ->assertViewHas('baris', fn ($b) => $b->where('lv', 6)->every(fn ($r) => $r->ada));

        // Tahun lain: hanya lihat, tanpa modal input.
        $this->akuntansi()->get('/akuntansi/saldo-awal?tahun=2024')->assertOk()
            ->assertViewHas('tahun', 2024)->assertDontSee('id="modalSaldo"', false);
    }

    public function test_simpan_tidak_valid_ditolak(): void
    {
        $sebelum = DB::table('ueu_tbl_jurnal_saldo')->count();

        $this->akuntansi()->post('/akuntansi/saldo-awal', ['idrek6' => 99999999, 'pencatatan' => 'X', 'nominal' => '0', 'id_sumber' => '', 'f_isi' => '1'])
            ->assertRedirect('/akuntansi/saldo-awal?isi=1')
            ->assertSessionHasErrors(['idrek6', 'pencatatan', 'nominal', 'id_sumber']);

        $this->assertSame($sebelum, DB::table('ueu_tbl_jurnal_saldo')->count());
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/saldo-awal')->assertStatus(401);
    }
}
