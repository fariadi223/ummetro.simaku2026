<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NeracaPercobaan;
use Tests\TestCase;

/**
 * Laporan penghasilan komprehensif (/akuntansi/penghasilan-komprehensif), hanya GET.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiPenghasilanKomprehensifTest
 */
class AkuntansiPenghasilanKomprehensifTest extends TestCase
{
    private const TAHUN = 2025;

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    public function test_cocok_dengan_surplus_posisi_keuangan(): void
    {
        $np = app(NeracaPercobaan::class);
        $surplus = $np->surplusPerSumber(self::TAHUN);
        $r = $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif')->assertOk()
            ->assertSee('Laporan Penghasilan Komprehensif')->assertSee('PENGHASILAN KOMPREHENSIF PERIODE BERJALAN')
            ->assertViewHas('sumberLalu', 'snapshot');
        $k = $r->viewData('komprehensif');

        // Total = surplus yang ditutup ke aset neto; TP/DP = aset neto 262/274.
        $this->assertEqualsWithDelta($surplus->sum('surplus'), $k->jml, 0.5);
        $this->assertEqualsWithDelta($surplus->where('idrek6', 262)->sum('surplus'), $k->tp, 0.5);
        $this->assertEqualsWithDelta($surplus->where('idrek6', 274)->sum('surplus'), $k->dp, 0.5);
        $this->assertEqualsWithDelta($k->tp + $k->dp, $k->jml, 0.5);

        // Bagian: penerimaan & biaya tidak memuat 4.3 / 5.4, bagian lain-lain hanya 4.3 / 5.4.
        $b = $r->viewData('bagian');
        $this->assertTrue($b['pendapatan']['baris']->where('lv', 3)->every(fn ($x) => str_starts_with($x->kode, '4.') && !str_starts_with($x->kode, '4.3.')));
        $this->assertTrue($b['biaya_lain']['baris']->where('lv', 3)->every(fn ($x) => str_starts_with($x->kode, '5.4.')));
    }

    public function test_kolom_tahun_lalu(): void
    {
        // T.A lalu (snapshot) = penghasilan komprehensif T.A sebelumnya.
        $lalu = $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN - 1])
            ->get('/akuntansi/penghasilan-komprehensif')->viewData('komprehensif');
        $ini = $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif')->viewData('komprehensif');
        $this->assertEqualsWithDelta($lalu->jml, $ini->jml_lalu, 1000000); // snapshot tutup buku bisa beda sedikit dari jurnal terkini

        // Filter tanggal hanya mengubah kolom T.A berjalan; kolom T.A lalu tetap seluruh T.A lalu.
        $r = $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif?awal=' . self::TAHUN . '-10-01&akhir=' . self::TAHUN . '-10-31')
            ->assertOk()->assertViewHas('sumberLalu', 'snapshot')->assertViewHas('awalLalu', (self::TAHUN - 1) . '-09-01');
        $this->assertFalse($r->viewData('seluruhTa'));
        $sebagian = $r->viewData('komprehensif');
        $this->assertEqualsWithDelta($ini->jml_lalu, $sebagian->jml_lalu, 0.5);
        $this->assertEqualsWithDelta($ini->tp_lalu, $sebagian->tp_lalu, 0.5);
        $pendLalu = fn ($v) => $v->viewData('bagian')['pendapatan']['total']->jml_lalu;
        $this->assertEqualsWithDelta($pendLalu($this->akuntansi()->get('/akuntansi/penghasilan-komprehensif')), $pendLalu($r), 0.5);
        $this->assertNotEqualsWithDelta($ini->jml, $sebagian->jml, 0.5); // kolom berjalan memang ikut filter
    }

    public function test_report_tanggal(): void
    {
        $awal = (self::TAHUN + 1) . '-02-01';
        $akhir = (self::TAHUN + 1) . '-02-28';
        $r = $this->akuntansi()->get("/akuntansi/penghasilan-komprehensif?mode=tanggal&awal={$awal}&akhir={$akhir}")->assertOk()
            ->assertSee('Report Tanggal')->assertSee('Report Tahunan')->assertSee('Sebelum periode')
            ->assertViewHas('mode', 'tanggal')->assertViewHas('sumberLalu', 'sebelum')->assertViewHas('akhirLalu', (self::TAHUN + 1) . '-01-31');
        $k = $r->viewData('komprehensif');

        // Kiri + kanan = akumulasi 01-09-T s/d tanggal akhir (laporan tunggal dengan rentang itu).
        $kumulatif = $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif?awal=' . self::TAHUN . "-09-01&akhir={$akhir}")->viewData('komprehensif');
        $this->assertEqualsWithDelta($kumulatif->jml, $k->jml + $k->jml_lalu, 0.5);
        $this->assertEqualsWithDelta($kumulatif->tp, $k->tp + $k->tp_lalu, 0.5);

        // Tanggal awal = awal T.A → kolom kanan = T.A lalu (seperti v1); tanpa tanggal → bulan berjalan (dijepit ke periode).
        $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif?mode=tanggal&awal=' . self::TAHUN . "-09-01&akhir={$akhir}")
            ->assertViewHas('sumberLalu', 'snapshot');
        $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif?mode=tanggal')->assertOk()->assertViewHas('mode', 'tanggal');
        $this->akuntansi()->get("/akuntansi/penghasilan-komprehensif?mode=tanggal&awal={$awal}&akhir={$akhir}&cetak=1")->assertOk()
            ->assertSee('Sebelum periode');
        // Laporan biasa punya tombol Report Tanggal.
        $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif')->assertSee('Report Tanggal')
            ->assertSee(route('akuntansi.penghasilan-komprehensif.index', ['mode' => 'tanggal']), false);
    }

    public function test_cetak_dan_role(): void
    {
        $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif?cetak=1&rinci=1')->assertOk()
            ->assertSee('<h2>Laporan Penghasilan Komprehensif</h2>', false)->assertSee('class="lv6', false)
            ->assertSee(config('simaku.laporan_ttd')[0][2]);
        $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif?cetak=1')->assertOk()->assertDontSee('class="lv6', false);
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/penghasilan-komprehensif')->assertStatus(401);
    }
}
