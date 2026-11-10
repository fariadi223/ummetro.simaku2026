<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Laporan Keuangan Unit (/akuntansi/laporan-unit), hanya GET (data asli, tanpa penulisan).
 * Membutuhkan tabel ueu_map_unit_prodi & ueu_ref_prodi (sql/laporan_prodi/01_buat_tabel.sql).
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiLaporanUnitTest
 */
class AkuntansiLaporanUnitTest extends TestCase
{
    private const TAHUN = 2025;
    private const AWAL = '2025-09-01';
    private const AKHIR = '2026-08-31';

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    /** Total langsung dari jurnal untuk unit terpetakan (pembanding). */
    private function totalJurnal(?string $ff = null): object
    {
        return DB::table('ueu_tbl_jurnal_spm as j')
            ->join('ueu_map_unit_prodi as m', 'm.id_unit', '=', 'j.id_uk')
            ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'j.id_uk')
            ->where('j.tahun', (string) self::TAHUN)->whereBetween('j.tgl_trk', [self::AWAL, self::AKHIR])
            ->where(fn ($q) => $q->whereNull('m.berlaku_dari')->orWhereColumn('j.tgl_trk', '>=', 'm.berlaku_dari'))
            ->when($ff, fn ($q) => $q->whereRaw('LEFT(u.kode, 2) = ?', [$ff]))
            ->whereNotIn('j.idrek6', DB::table('ueu_lap_unit_rek_nonaktif')->select('idrek6'))   // rekening disembunyikan admin
            ->selectRaw('COUNT(*) n, COALESCE(SUM(j.debet), 0) d, COALESCE(SUM(j.kredit), 0) k')->first();
    }

    public function test_rekap_dikelompokkan_ke_induk_00_dan_total_sama_dengan_jurnal(): void
    {
        $this->skipJikaBelumSiap();
        $r = $this->akuntansi()->get('/akuntansi/laporan-unit')->assertOk()->assertSee('Laporan Keuangan Unit');
        $grup = $r->viewData('grup');
        $this->assertNotEmpty($grup);

        $n = 0;
        $d = 0.0;
        $k = 0.0;
        foreach ($grup as $ff => $g) {
            // Induk berakhiran .00 dengan 2 digit yang sama; semua sub-unit berawalan FF.
            $this->assertSame(sprintf('%02d', (int) $ff) . '.00', $g['kode_induk']);
            foreach ($g['unit'] as $u) {
                $this->assertStringStartsWith(sprintf('%02d', (int) $ff) . '.', $u->kode);
                $this->assertTrue(DB::table('ueu_map_unit_prodi')->where('id_unit', $u->id_unit)->exists(), "unit {$u->kode} tidak ada di mapping");
            }
            $n += $g['n'];
            $d += $g['d'];
            $k += $g['k'];
        }
        $j = $this->totalJurnal();
        $this->assertSame((int) $j->n, $n);
        $this->assertEqualsWithDelta((float) $j->d, $d, 0.5);
        $this->assertEqualsWithDelta((float) $j->k, $k, 0.5);
    }

    public function test_rinci_induk_sama_dengan_subtotal_dan_rinci_unit(): void
    {
        $this->skipJikaBelumSiap();
        $grup = $this->akuntansi()->get('/akuntansi/laporan-unit')->viewData('grup');
        $ff = sprintf('%02d', (int) array_key_first($grup));
        $g = reset($grup);

        $rinci = $this->akuntansi()->getJson('/akuntansi/laporan-unit/rinci?ff=' . $ff)->assertOk()->json('baris');
        $this->assertEqualsWithDelta($g['d'], array_sum(array_column($rinci, 'total_debet')), 0.5);
        $this->assertEqualsWithDelta($g['k'], array_sum(array_column($rinci, 'total_kredit')), 0.5);

        $u = $g['unit'][0];
        $ru = $this->akuntansi()->getJson('/akuntansi/laporan-unit/rinci?id_unit=' . $u->id_unit)->assertOk()->json('baris');
        $this->assertEqualsWithDelta((float) $u->total_debet, array_sum(array_column($ru, 'total_debet')), 0.5);
        $this->assertSame((int) $u->jml_transaksi, array_sum(array_column($ru, 'jml_transaksi')));

        $this->akuntansi()->getJson('/akuntansi/laporan-unit/rinci')->assertStatus(422);
        $this->akuntansi()->getJson('/akuntansi/laporan-unit/rinci?ff=1x')->assertStatus(422);
    }

    public function test_transaksi_rekening_bersambung_dengan_rinci(): void
    {
        $this->skipJikaBelumSiap();
        $grup = $this->akuntansi()->get('/akuntansi/laporan-unit')->viewData('grup');
        $ff = sprintf('%02d', (int) array_key_first($grup));
        $awal = '2025-12-01';   // agar ada baris saldo awal
        $rinci = $this->akuntansi()->getJson("/akuntansi/laporan-unit/rinci?ff={$ff}&awal={$awal}")->assertOk()->json('baris');
        $this->assertNotEmpty($rinci);
        $this->assertArrayHasKey('idrek6', $rinci[0]);

        foreach (array_slice($rinci, 0, 3) as $rek) {
            $d = $this->akuntansi()->getJson("/akuntansi/laporan-unit/transaksi?ff={$ff}&awal={$awal}&idrek6={$rek['idrek6']}")->assertOk()->json();
            $trx = array_filter($d['baris'], fn ($b) => $b['jenis'] === 1);
            $this->assertCount($rek['jml_transaksi'], $trx);
            $this->assertEqualsWithDelta($rek['total_debet'], array_sum(array_column($trx, 'debet')), 0.5);
            $this->assertEqualsWithDelta($rek['total_kredit'], array_sum(array_column($trx, 'kredit')), 0.5);
            $akhir = $d['baris'] ? end($d['baris'])['saldo'] : 0.0;
            $this->assertEqualsWithDelta($rek['saldo_akhir'], $akhir, 0.5, "saldo akhir {$rek['kode_rek']}");
        }

        $this->akuntansi()->getJson("/akuntansi/laporan-unit/transaksi?ff={$ff}")->assertStatus(422)->assertJsonValidationErrors('idrek6');
    }

    public function test_rekening_nonaktif_tidak_tampil(): void
    {
        $this->skipJikaBelumSiap();
        $nonaktif = DB::table('ueu_lap_unit_rek_nonaktif')->pluck('idrek6')->map(fn ($i) => (int) $i)->all();
        if (!$nonaktif) {
            $this->markTestSkipped('tidak ada rekening nonaktif');
        }
        $this->akuntansi()->get('/akuntansi/laporan-unit')->assertOk()->assertSee('Tidak ditampilkan (diatur admin)');
        $grup = $this->akuntansi()->get('/akuntansi/laporan-unit')->viewData('grup');
        foreach (array_keys($grup) as $ff) {
            $rinci = $this->akuntansi()->getJson('/akuntansi/laporan-unit/rinci?ff=' . sprintf('%02d', (int) $ff))->json('baris');
            $this->assertEmpty(array_intersect($nonaktif, array_column($rinci, 'idrek6')), "rekening nonaktif muncul di kelompok {$ff}");
        }
        $ff = sprintf('%02d', (int) array_key_first($grup));
        $this->akuntansi()->getJson("/akuntansi/laporan-unit/transaksi?ff={$ff}&idrek6={$nonaktif[0]}")->assertOk()->assertJsonCount(0, 'baris');
    }

    public function test_mode_neraca_hanya_rekening_kepala_1_2_3(): void
    {
        $this->skipJikaBelumSiap();
        $r = $this->akuntansi()->get('/akuntansi/laporan-unit?mode=neraca')->assertOk()->assertSee('Mutasi bersih');
        $grup = $r->viewData('grup');

        // Total rekap = jurnal unit terpetakan pada rekening kepala 1, 2, 3 (tanpa rekening nonaktif).
        $kepala = DB::table('ueu_tbl_rek6 as r6')
            ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
            ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
            ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')->whereIn('r1.koderek1', ['1', '2', '3'])->pluck('r6.idrek6');
        $j = DB::table('ueu_tbl_jurnal_spm as j')->join('ueu_map_unit_prodi as m', 'm.id_unit', '=', 'j.id_uk')
            ->where('j.tahun', (string) self::TAHUN)->whereBetween('j.tgl_trk', [self::AWAL, self::AKHIR])
            ->where(fn ($q) => $q->whereNull('m.berlaku_dari')->orWhereColumn('j.tgl_trk', '>=', 'm.berlaku_dari'))
            ->whereIn('j.idrek6', $kepala)->whereNotIn('j.idrek6', DB::table('ueu_lap_unit_rek_nonaktif')->select('idrek6'))
            ->selectRaw('COUNT(*) n, COALESCE(SUM(j.debet), 0) d, COALESCE(SUM(j.kredit), 0) k')->first();
        $this->assertSame((int) $j->n, array_sum(array_column($grup, 'n')));
        $this->assertEqualsWithDelta((float) $j->d, array_sum(array_column($grup, 'd')), 0.5);
        $this->assertEqualsWithDelta((float) $j->k, array_sum(array_column($grup, 'k')), 0.5);

        // Rincian hanya kode awalan 1/2/3; transaksi rekening beban kosong pada mode neraca.
        $ff = sprintf('%02d', (int) array_key_first($grup));
        $rinci = $this->akuntansi()->getJson("/akuntansi/laporan-unit/rinci?ff={$ff}&mode=neraca")->assertOk()->json('baris');
        foreach ($rinci as $b) {
            $this->assertContains($b['kode_rek'][0], ['1', '2', '3'], $b['kode_rek']);
        }
        $beban = collect($this->akuntansi()->getJson("/akuntansi/laporan-unit/rinci?ff={$ff}")->json('baris'))
            ->first(fn ($b) => $b['kode_rek'][0] === '5');
        if ($beban) {
            $this->akuntansi()->getJson("/akuntansi/laporan-unit/transaksi?ff={$ff}&mode=neraca&idrek6={$beban['idrek6']}")
                ->assertOk()->assertJsonCount(0, 'baris');
        }

        $x = $this->akuntansi()->get('/akuntansi/laporan-unit/excel?mode=neraca')->assertOk();
        $this->assertStringContainsString('LAP-KEUANGAN-UNIT-NERACA-', $x->headers->get('content-disposition'));
        $this->akuntansi()->get('/akuntansi/laporan-unit/cetak?mode=neraca')->assertOk()->assertSee('Rekening neraca');
        $this->akuntansi()->getJson('/akuntansi/laporan-unit/rinci?ff=01&mode=salah')->assertStatus(422);
    }

    public function test_excel_dan_cetak(): void
    {
        $this->skipJikaBelumSiap();
        $r = $this->akuntansi()->get('/akuntansi/laporan-unit/excel')->assertOk();
        $this->assertStringContainsString('LAP-KEUANGAN-UNIT-20250901-20260831.xlsx', $r->headers->get('content-disposition'));
        $this->akuntansi()->get('/akuntansi/laporan-unit/cetak?rinci=1')->assertOk()
            ->assertSee('Laporan Keuangan Unit')->assertSee('Rincian rekening')->assertSee('TOTAL');
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/laporan-unit')->assertStatus(401);
    }

    private function skipJikaBelumSiap(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('ueu_map_unit_prodi')) {
            $this->markTestSkipped('ueu_map_unit_prodi belum ada');
        }
    }
}
