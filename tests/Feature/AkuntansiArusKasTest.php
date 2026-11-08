<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Laporan arus kas (/akuntansi/arus-kas), hanya GET.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiArusKasTest
 */
class AkuntansiArusKasTest extends TestCase
{
    private const TAHUN = 2025;

    private function akuntansi(int $tahun = self::TAHUN): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => $tahun]);
    }

    public function test_rekonsiliasi_dengan_kas_neraca(): void
    {
        $r = $this->akuntansi()->get('/akuntansi/arus-kas')->assertOk()
            ->assertSee('Laporan Arus Kas')->assertSee('ARUS KAS DARI AKTIVITAS OPERASI')->assertSee('KAS DAN SETARA KAS PADA AKHIR PERIODE')
            ->assertDontSee('kas di neraca')->assertSee('Kenaikan bersih');
        $ini = $r->viewData('ini');
        $lalu = $r->viewData('lalu');

        // Kas awal = saldo awal kas (1.1.01–1.1.03) di Posisi Keuangan. Kas akhir laporan berbeda dari kas neraca hanya
        // sebesar perubahan akumulasi penyusutan/amortisasi yang tidak dihitung (disembunyikan).
        $pos = $this->akuntansi()->get('/akuntansi/posisi-keuangan')->viewData('bagian')['1']['baris']->where('lv', 3)->whereIn('kode', ['1.1.01', '1.1.02', '1.1.03']);
        // Kas awal & akhir = kas neraca di kedua kolom; kas akhir T.A lalu = kas awal T.A berjalan.
        $this->assertEqualsWithDelta($pos->sum('saldo_awal'), $ini['kas_awal'], 0.5);
        $this->assertEqualsWithDelta($pos->sum('saldo'), $ini['kas_akhir'], 0.5);
        $this->assertEqualsWithDelta(0.0, $ini['selisih'], 0.5);
        $this->assertEqualsWithDelta(0.0, $lalu['selisih'], 0.5);
        $this->assertEqualsWithDelta($lalu['kas_akhir'], $ini['kas_awal'], 0.5);
        // Kas akhir T.A lalu sama, dari periode mana pun dibuka.
        $this->assertEqualsWithDelta($this->akuntansi(self::TAHUN - 1)->get('/akuntansi/arus-kas')->viewData('ini')['kas_akhir'], $lalu['kas_akhir'], 0.5);
        $this->assertEqualsWithDelta($ini['operasi'] + $ini['investasi'] + $ini['pendanaan'], $ini['kas_akhir'] - $ini['kas_awal'], 0.5);

        // Penghasilan komprehensif = laporan Penghasilan Komprehensif; T.A lalu bersambung.
        $pk = $this->akuntansi()->get('/akuntansi/penghasilan-komprehensif')->viewData('komprehensif');
        $this->assertEqualsWithDelta($pk->jml, $ini['komprehensif'], 0.5);
        $sebelumnya = $this->akuntansi(self::TAHUN - 1)->get('/akuntansi/arus-kas')->viewData('ini');
        $this->assertEqualsWithDelta($sebelumnya['operasi'], $lalu['operasi'], 0.5);
    }

    public function test_penyesuaian_format_v1(): void
    {
        $r = $this->akuntansi()->get('/akuntansi/arus-kas')->assertOk()
            ->assertSee('4.3.04')->assertSee('Koreksi Aset Neto Periode Lalu')->assertSee('5.1.11')->assertSee('Biaya Penyusutan')
            ->assertSee('5.1.14')->assertSee('4.3.05')->assertSee('Laba penghapusan aset tetap')->assertSee('5.4.01')->assertSee('Kerugian Penghapusan Aset Tetap');
        $p = $r->viewData('bagian')['penyusutan'];
        $this->assertSame(['4.3.04', '5.1.11', '5.1.14', '4.3.05', '5.4.01'], $p->pluck('kode')->all());

        // Nilai = saldo akun di Penghasilan Komprehensif; penghasilan non-kas bertanda negatif.
        $akun = collect($this->akuntansi()->get('/akuntansi/penghasilan-komprehensif')->viewData('bagian'))
            ->flatMap(fn ($b) => $b['baris'])->where('lv', 3)->keyBy('kode');
        $nilai = $p->keyBy('kode');
        // 4.3.04 sudah termasuk penghasilan komprehensif → dikurangkan (tidak terhitung dua kali).
        $this->assertEqualsWithDelta(-(float) ($akun['4.3.04']->jml ?? 0), $nilai['4.3.04']->ini, 0.5);
        $ini = $r->viewData('ini');
        $this->assertEqualsWithDelta($ini['komprehensif'] - (float) ($akun['4.3.04']->jml ?? 0) + (float) ($akun['5.1.11']->jml ?? 0)
            + (float) ($akun['5.1.14']->jml ?? 0) - (float) ($akun['4.3.05']->jml ?? 0) + (float) ($akun['5.4.01']->jml ?? 0),
            $ini['komprehensif'] + $ini['penyusutan'], 0.5);
        $this->assertEqualsWithDelta((float) ($akun['5.1.11']->jml ?? 0), $nilai['5.1.11']->ini, 0.5);
        $this->assertEqualsWithDelta($p->sum('ini'), $r->viewData('ini')['penyusutan'], 0.5);

        // Aset lancar selain kas selalu tampil lengkap (termasuk yang 0), urut kode.
        $aset = $r->viewData('bagian')['operasi_aset'];
        $this->assertSame(['1.1.04', '1.1.05', '1.1.06', '1.1.07', '1.1.08', '1.1.09', '1.1.99'], $aset->pluck('kode')->all());
        $this->assertEqualsWithDelta($aset->sum('ini'), $r->viewData('ini')['operasi_aset'], 0.5);
        $r->assertSee('Investasi Jangka Pendek')->assertSee('Persediaan')->assertSee('Aset Lancar Lain-lain');

        // Liabilitas jangka pendek (2.1.xx) juga selalu tampil lengkap, urut kode.
        $liab = $r->viewData('bagian')['operasi_liabilitas'];
        $this->assertSame(['2.1.01', '2.1.02', '2.1.03', '2.1.04', '2.1.05', '2.1.99'], $liab->pluck('kode')->all());
        $this->assertEqualsWithDelta($liab->sum('ini'), $r->viewData('ini')['operasi_liabilitas'], 0.5);
        $r->assertSee('Hutang Pajak')->assertSee('Bagian Lancar Liabilitas Jangka Panjang')->assertSee('Liabilitas Lancar Lain-lain');

        // Aset tidak lancar (1.2.xx, kecuali akumulasi 1.2.02/1.2.04) selalu tampil lengkap, tanpa baris penyesuaian akumulasi.
        $inv = $r->viewData('bagian')['investasi'];
        $this->assertSame(['1.2.01', '1.2.03', '1.2.05', '1.2.06', '1.2.07', '1.2.08', '1.2.99'],
            $inv->pluck('kode')->reject(fn ($k) => $k === \App\Services\ArusKas::BARIS_AKUMULASI)->values()->all());
        $this->assertEqualsWithDelta($inv->sum('ini'), $r->viewData('ini')['investasi'], 0.5);
        $r->assertSee('Harga Perolehan Aset Tetap Tidak Berwujud')->assertSee('Jaminan')->assertSee('Aset Tidak Lancar Lain-lain');

        // Liabilitas jangka panjang (2.2.xx) selalu tampil lengkap; pendanaan = liabilitas jangka panjang + penyesuaian langsung aset neto.
        $dana = $r->viewData('bagian')['pendanaan'];
        $this->assertSame(['2.2.01', '2.2.02', '2.2.03', '2.2.99'], $dana->pluck('kode')->all());
        $ini = $r->viewData('ini');
        $this->assertEqualsWithDelta($dana->sum('ini'), $ini['liabilitas_panjang'], 0.5);
        $this->assertEqualsWithDelta($ini['liabilitas_panjang'] + $ini['langsung'], $ini['pendanaan'], 0.5);
        $r->assertSee('Kenaikan/(Penurunan) Liabilitas Jangka Panjang:')->assertSee('Imbalan Pasca Kerja')->assertSee('Liabilitas Jangka Panjang Lain-lain');
    }

    public function test_filter_cetak_role(): void
    {
        $r = $this->akuntansi()->get('/akuntansi/arus-kas?awal=' . (self::TAHUN + 1) . '-02-01&akhir=' . (self::TAHUN + 1) . '-02-28')
            ->assertOk()->assertViewHas('seluruhTa', false);
        $penuhAll = $this->akuntansi()->get('/akuntansi/arus-kas');
        $penuh = $penuhAll->viewData('lalu');
        $this->assertEqualsWithDelta($penuh['kas_akhir'], $r->viewData('lalu')['kas_akhir'], 0.5);
        // Difilter: kas awal = kas akhir T.A lalu + kenaikan 01-09 s/d 31-01.
        $sebelum = $this->akuntansi()->get('/akuntansi/arus-kas?awal=' . self::TAHUN . '-09-01&akhir=' . (self::TAHUN + 1) . '-01-31')->viewData('ini');
        $this->assertEqualsWithDelta($sebelum['kas_akhir'], $r->viewData('ini')['kas_awal'], 0.5);

        $this->akuntansi()->get('/akuntansi/arus-kas?cetak=1')->assertOk()
            ->assertSee('<h2>Laporan Arus Kas</h2>', false)->assertSee(config('simaku.laporan_ttd')[0][2]);
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/arus-kas')->assertStatus(401);
    }
}
