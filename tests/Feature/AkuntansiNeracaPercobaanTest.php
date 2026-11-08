<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Neraca percobaan (/akuntansi/neraca-percobaan), hanya GET.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiNeracaPercobaanTest
 */
class AkuntansiNeracaPercobaanTest extends TestCase
{
    private const TAHUN = 2025;

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    /** Per level 3 menurut helper v1 (saldo_awal + mutasi), tanpa filter tanggal: [idrek3 => [awalNet, mutD, mutK]]. */
    private function v1(): array
    {
        $rek = fn ($t) => DB::table($t . ' as j')
            ->join('ueu_tbl_rek6 as r6', 'r6.idrek6', '=', 'j.idrek6')->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
            ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
            ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
            ->where('j.tahun', (string) self::TAHUN)->groupBy('r3.idrek3');
        $awal = $rek('ueu_tbl_jurnal_saldo')->whereIn('r1.koderek1', ['1', '2', '3'])
            ->selectRaw('r3.idrek3, SUM(j.debet) - SUM(j.kredit) net')->pluck('net', 'idrek3');
        $mut = $rek('ueu_tbl_jurnal_spm')->where('j.ket', '!=', 'TB')
            ->whereBetween('j.tgl_trk', [self::TAHUN . '-09-01', (self::TAHUN + 1) . '-08-31'])
            ->selectRaw('r3.idrek3, SUM(j.debet) d, SUM(j.kredit) k')->get()->keyBy('idrek3');
        $hasil = [];
        foreach ($awal->keys()->merge($mut->keys())->unique() as $id) {
            $hasil[$id] = [(float) ($awal[$id] ?? 0), (float) ($mut[$id]->d ?? 0), (float) ($mut[$id]->k ?? 0)];
        }

        return $hasil;
    }

    public function test_sesuai_v1_seluruh_ta(): void
    {
        $v1 = $this->v1();
        $this->akuntansi()->get('/akuntansi/neraca-percobaan')->assertOk()
            ->assertSee('Neraca Percobaan')->assertViewHas('seluruhTa', true)
            ->assertViewHas('baris', function ($b) use ($v1) {
                $lv3 = $b->where('lv', 3)->keyBy('id');
                foreach ($v1 as $id => [$awal, $d, $k]) {
                    if (abs($awal) + abs($d) + abs($k) < 0.5) {
                        continue;
                    }
                    $r = $lv3[$id] ?? null;
                    if (!$r || abs($r->awal - $awal) > 0.5 || abs($r->mut_d - $d) > 0.5 || abs($r->mut_k - $k) > 0.5) {
                        return false;
                    }
                }

                return $lv3->isNotEmpty();
            });
    }

    public function test_rentang_tanggal_konsisten(): void
    {
        $tengah = (self::TAHUN + 1) . '-02-01';
        $akhir = (self::TAHUN + 1) . '-08-31';
        $penuh = $this->akuntansi()->get('/akuntansi/neraca-percobaan')->viewData('baris')->where('lv', 3)->keyBy('id');
        $sebagian = $this->akuntansi()->get("/akuntansi/neraca-percobaan?awal={$tengah}&akhir={$akhir}")
            ->assertOk()->assertViewHas('seluruhTa', false)->viewData('baris')->where('lv', 3)->keyBy('id');

        // Saldo akhir per 31-08 sama, apa pun tanggal awalnya; saldo awal per tanggal = saldo akhir penuh − mutasi rentang.
        foreach ($penuh as $id => $r) {
            $s = $sebagian[$id] ?? null;
            $this->assertNotNull($s);
            $this->assertEqualsWithDelta($r->akhir, $s->akhir, 0.5);
            $this->assertEqualsWithDelta($r->akhir - $s->mut_d + $s->mut_k, $s->awal, 0.5);
        }

        // Di luar periode dijepit; urutan terbalik ditukar.
        $this->akuntansi()->get('/akuntansi/neraca-percobaan?awal=2000-01-01&akhir=2099-01-01')->assertViewHas('seluruhTa', true);
        $this->akuntansi()->get("/akuntansi/neraca-percobaan?awal={$akhir}&akhir={$tengah}")
            ->assertViewHas('awal', $tengah)->assertViewHas('akhir', $akhir);
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/neraca-percobaan')->assertStatus(401);
    }
}
