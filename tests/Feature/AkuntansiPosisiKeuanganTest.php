<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NeracaPercobaan;
use Tests\TestCase;

/**
 * Laporan posisi keuangan (/akuntansi/posisi-keuangan), hanya GET.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiPosisiKeuanganTest
 */
class AkuntansiPosisiKeuanganTest extends TestCase
{
    private const TAHUN = 2025;

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    public function test_seimbang_dan_cocok_neraca_percobaan(): void
    {
        $np = app(NeracaPercobaan::class);
        $lv1 = $np->hitung(self::TAHUN)['baris']->where('lv', 1)->keyBy('kode');
        $surplus = -($lv1['4']->akhir + $lv1['5']->akhir);

        $r = $this->akuntansi()->get('/akuntansi/posisi-keuangan')->assertOk()->assertSee('Laporan Posisi Keuangan');
        $bagian = $r->viewData('bagian');

        // Aset = saldo akhir rek 1 (D − K); kewajiban & aset neto = −(D − K); aset neto + surplus berjalan.
        $this->assertEqualsWithDelta($lv1['1']->akhir, $bagian['1']['total'], 0.5);
        $this->assertEqualsWithDelta(-$lv1['2']->akhir, $bagian['2']['total'], 0.5);
        $this->assertEqualsWithDelta(-$lv1['3']->akhir + $surplus, $bagian['3']['total'], 0.5);
        $surplusView = $r->viewData('surplus');
        $this->assertEqualsWithDelta($surplus, $surplusView['tp'] + $surplusView['dp'], 0.5);
        $this->assertTrue($r->viewData('seimbang'));
    }

    public function test_rentang_awal_akhir(): void
    {
        $awal = (self::TAHUN + 1) . '-02-01';
        $akhir = (self::TAHUN + 1) . '-03-31';
        $r = $this->akuntansi()->get("/akuntansi/posisi-keuangan?awal={$awal}&akhir={$akhir}")->assertOk()
            ->assertViewHas('awal', $awal)->assertViewHas('akhir', $akhir)->assertViewHas('seluruhTa', false);
        $this->assertTrue($r->viewData('seimbang'));

        // Saldo Awal per tanggal awal = Saldo Akhir s/d sehari sebelumnya.
        $sebelum = $this->akuntansi()->get('/akuntansi/posisi-keuangan?awal=' . self::TAHUN . '-09-01&akhir=' . (self::TAHUN + 1) . '-01-31')->viewData('bagian');
        foreach (['1', '2', '3'] as $k) {
            $this->assertEqualsWithDelta($sebelum[$k]['total'], $r->viewData('bagian')[$k]['total_awal'], 0.5, "rek {$k}");
        }

        // Seluruh T.A: Saldo Awal aset neto tanpa surplus berjalan; tanggal di luar periode dijepit.
        $penuh = $this->akuntansi()->get('/akuntansi/posisi-keuangan')->assertViewHas('seluruhTa', true);
        $this->assertSame(['tp' => 0.0, 'dp' => 0.0], $penuh->viewData('surplus_awal'));
        $this->akuntansi()->get('/akuntansi/posisi-keuangan?awal=2000-01-01&akhir=2099-01-01')->assertViewHas('seluruhTa', true);
    }

    public function test_cetak(): void
    {
        $q = '?awal=' . self::TAHUN . '-09-01&akhir=' . (self::TAHUN + 1) . '-08-31';
        $ringkas = $this->akuntansi()->get('/akuntansi/posisi-keuangan/cetak' . $q)->assertOk()
            ->assertSee('<h2>Laporan Posisi Keuangan</h2>', false)->assertSee('TOTAL LIABILITAS + ASET NETO')->assertDontSee('SEIMBANG')
            ->assertSee(config('simaku.laporan_ttd')[0][2])->assertDontSee('class="lv6"', false);
        $this->akuntansi()->get('/akuntansi/posisi-keuangan/cetak' . $q . '&rinci=1')->assertOk()->assertSee('class="lv6"', false);
        $this->akuntansi()->get('/akuntansi/posisi-keuangan' . $q)->assertOk()
            ->assertSee(route('akuntansi.posisi-keuangan.cetak', ['awal' => self::TAHUN . '-09-01', 'akhir' => (self::TAHUN + 1) . '-08-31', 'rinci' => 1]));
        $this->assertNotNull($ringkas);
    }

    public function test_komparasi(): void
    {
        $tunggal = $this->akuntansi()->get('/akuntansi/posisi-keuangan')->assertOk()
            ->assertSee(route('akuntansi.posisi-keuangan.komparasi', ['akhir' => (self::TAHUN + 1) . '-08-31']), false);
        $r = $this->akuntansi()->get('/akuntansi/posisi-keuangan/komparasi')->assertOk()
            ->assertSee('Komparasi')->assertSee('T.A ' . self::TAHUN . '/' . (self::TAHUN + 1))->assertSee('T.A ' . (self::TAHUN - 1) . '/' . self::TAHUN)
            ->assertSee('Kenaikan / (Penurunan)');
        $b = $r->viewData('bagian');
        // Kolom berjalan = halaman tunggal seluruh T.A; kolom lalu = saldo awal T.A (posisi 31-08-T).
        foreach (['1', '2', '3'] as $k) {
            $this->assertEqualsWithDelta($tunggal->viewData('bagian')[$k]['total'], $b[$k]['total'], 0.5);
            $this->assertEqualsWithDelta($tunggal->viewData('bagian')[$k]['total_awal'], $b[$k]['total_awal'], 0.5);
        }
        $this->assertTrue($r->viewData('seimbang'));

        $this->akuntansi()->get('/akuntansi/posisi-keuangan/komparasi?akhir=' . (self::TAHUN + 1) . '-01-31&cetak=1&rinci=1')->assertOk()
            ->assertSee('Laporan Posisi Keuangan (Komparasi)')->assertSee('class="lv6"', false)->assertSee('Kenaikan / (Penurunan)');
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/posisi-keuangan')->assertStatus(401);
    }
}
