<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Laporan perubahan aset neto (/akuntansi/perubahan-aset-neto), hanya GET.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiPerubahanAsetNetoTest
 */
class AkuntansiPerubahanAsetNetoTest extends TestCase
{
    private const TAHUN = 2025;

    private function akuntansi(int $tahun = self::TAHUN): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => $tahun]);
    }

    public function test_bersambung_dengan_posisi_dan_penghasilan(): void
    {
        $r = $this->akuntansi()->get('/akuntansi/perubahan-aset-neto')->assertOk()
            ->assertSee('Laporan Perubahan Aset Neto')->assertSee('TOTAL ASET NETO')->assertViewHas('seimbang', true);
        $ini = $r->viewData('ini');
        $lalu = $r->viewData('lalu');

        // Saldo akhir = aset neto Posisi Keuangan (3.1.01 / 3.2.01).
        $posisi = $this->akuntansi()->get('/akuntansi/posisi-keuangan')->viewData('bagian')['3']['baris']->where('lv', 3)->keyBy('kode');
        $this->assertEqualsWithDelta($posisi['3.1.01']->saldo, $ini['tp']['saldo_akhir'], 0.5);
        $this->assertEqualsWithDelta($posisi['3.2.01']->saldo, $ini['dp']['saldo_akhir'], 0.5);

        // Perubahan total = penghasilan komprehensif periode berjalan + penyesuaian langsung.
        $pk = $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif')->viewData('komprehensif');
        $this->assertEqualsWithDelta($pk->jml + $ini['total']['langsung'], $ini['total']['saldo_akhir'] - $ini['total']['saldo_awal'], 0.5);

        // Kesinambungan: saldo akhir T.A lalu = saldo awal T.A berjalan; T.A lalu = laporan T.A sebelumnya.
        $this->assertEqualsWithDelta($lalu['total']['saldo_akhir'], $ini['total']['saldo_awal'], 0.5);
        $sebelumnya = $this->akuntansi(self::TAHUN - 1)->get('/akuntansi/perubahan-aset-neto')->viewData('ini');
        $this->assertEqualsWithDelta($sebelumnya['tp']['saldo_akhir'], $lalu['tp']['saldo_akhir'], 0.5);
        $this->assertEqualsWithDelta($sebelumnya['tp']['langsung'], $lalu['tp']['langsung'], 0.5);
    }

    public function test_report_tanggal(): void
    {
        $awal = (self::TAHUN + 1) . '-02-01';
        $akhir = (self::TAHUN + 1) . '-02-28';
        $r = $this->akuntansi()->get("/akuntansi/perubahan-aset-neto?mode=tanggal&awal={$awal}&akhir={$akhir}")->assertOk()
            ->assertSee('Report Tahunan')->assertSee('Sebelum periode')
            ->assertViewHas('mode', 'tanggal')->assertViewHas('sebelumPeriode', true)->assertViewHas('akhirLalu', (self::TAHUN + 1) . '-01-31')
            ->assertViewHas('seimbang', true);
        $ini = $r->viewData('ini');
        $lalu = $r->viewData('lalu');
        // Kolom kanan: saldo awal = saldo awal T.A, saldo akhir = saldo awal kolom kiri.
        $ta = $this->akuntansi()->get('/akuntansi/perubahan-aset-neto')->viewData('ini');
        foreach (['tp', 'dp'] as $k) {
            $this->assertEqualsWithDelta($ta[$k]['saldo_awal'], $lalu[$k]['saldo_awal'], 0.5);
            $this->assertEqualsWithDelta($ini[$k]['saldo_awal'], $lalu[$k]['saldo_akhir'], 0.5);
        }
        // Awal = awal T.A → kolom kanan = T.A lalu; tanpa tanggal → bulan berjalan; cetak membawa mode.
        $this->akuntansi()->get('/akuntansi/perubahan-aset-neto?mode=tanggal&awal=' . self::TAHUN . "-09-01&akhir={$akhir}")
            ->assertViewHas('sebelumPeriode', false);
        $this->akuntansi()->get('/akuntansi/perubahan-aset-neto?mode=tanggal')->assertOk()->assertViewHas('mode', 'tanggal');
        $this->akuntansi()->get("/akuntansi/perubahan-aset-neto?mode=tanggal&awal={$awal}&akhir={$akhir}&cetak=1")->assertOk()->assertSee('Sebelum periode');
        $this->akuntansi()->get('/akuntansi/perubahan-aset-neto')->assertSee('Report Tanggal')
            ->assertSee(route('akuntansi.perubahan-aset-neto.index', ['mode' => 'tanggal']), false);
    }

    public function test_filter_tanggal_dan_cetak(): void
    {
        $penuh = $this->akuntansi()->get('/akuntansi/perubahan-aset-neto')->viewData('lalu');
        $r = $this->akuntansi()->get('/akuntansi/perubahan-aset-neto?awal=' . (self::TAHUN + 1) . '-02-01&akhir=' . (self::TAHUN + 1) . '-02-28')
            ->assertOk()->assertViewHas('seimbang', true)->assertViewHas('seluruhTa', false);
        // Kolom T.A lalu tidak ikut filter.
        $this->assertEqualsWithDelta($penuh['total']['saldo_akhir'], $r->viewData('lalu')['total']['saldo_akhir'], 0.5);

        $this->akuntansi()->get('/akuntansi/perubahan-aset-neto?cetak=1')->assertOk()
            ->assertSee('<h2>Laporan Perubahan Aset Neto</h2>', false)->assertSee(config('simaku.laporan_ttd')[0][2]);
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/perubahan-aset-neto')->assertStatus(401);
    }
}
