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

        $grup = $this->akuntansi()->get('/akuntansi/saldo-unit')->assertOk()->assertSee('Saldo Anggaran Unit')->viewData('grup');
        $this->assertNotEmpty($grup);
        foreach ($grup as $ff => $g) {
            $this->assertSame(sprintf('%02d', (int) $ff) . '.00', $g['kode_induk']);
            $this->assertEqualsWithDelta((float) $v1[(int) $ff], $g['saldo'], 0.01, "saldo FF {$ff}");
            $this->assertEqualsWithDelta($g['diterima'] - $g['realisasi'] - $g['disetor'] - $g['lainnya'], $g['saldo'], 0.01);
            // Sub-unit menjumlah ke induk.
            $this->assertEqualsWithDelta($g['saldo'], array_sum(array_map(fn ($u) => $u->saldo, $g['unit'])), 0.01);
        }
    }

    public function test_transaksi_induk_dan_sub_unit_bersambung_dengan_saldo(): void
    {
        $grup = $this->akuntansi()->get('/akuntansi/saldo-unit')->viewData('grup');
        $ff = array_key_first($grup);
        $g = $grup[$ff];
        $ff = sprintf('%02d', (int) $ff);

        $d = $this->akuntansi()->getJson("/akuntansi/saldo-unit/transaksi?ff={$ff}")->assertOk()->json();
        $this->assertCount($g['jml_transaksi'], $d['baris']);
        $this->assertEqualsWithDelta($g['saldo'], end($d['baris'])['saldo'], 0.01);

        $u = $g['unit'][0];
        $du = $this->akuntansi()->getJson("/akuntansi/saldo-unit/transaksi?ff={$ff}&id_uk={$u->id_uk}")->assertOk()->json('baris');
        $this->assertCount($u->jml_transaksi, $du);
        $this->assertEqualsWithDelta($u->saldo, end($du)['saldo'], 0.01);
        $this->assertEqualsWithDelta($u->realisasi, array_sum(array_column(array_filter($du, fn ($b) => $b['jenis_kredit'] === 'realisasi'), 'kredit')), 0.01);

        $this->akuntansi()->getJson('/akuntansi/saldo-unit/transaksi')->assertStatus(422);
    }

    public function test_filter_periode_saldo_awal_bersambung(): void
    {
        // Saldo seluruh T.A = saldo awal (sebelum tanggal) + mutasi periode, per induk.
        $semua = $this->akuntansi()->get('/akuntansi/saldo-unit')->viewData('grup');
        $awal = (self::TAHUN + 1) . '-01-01';
        $r = $this->akuntansi()->get('/akuntansi/saldo-unit?awal=' . $awal)->assertOk()->assertSee('Saldo Awal');
        foreach ($r->viewData('grup') as $ff => $g) {
            $this->assertEqualsWithDelta($semua[$ff]['saldo'], $g['saldo'], 0.01, "FF {$ff}");
            $this->assertEqualsWithDelta($g['saldo_awal'] + $g['diterima'] - $g['realisasi'] - $g['disetor'] - $g['lainnya'], $g['saldo'], 0.01);
        }
    }

    public function test_excel(): void
    {
        $r = $this->akuntansi()->get('/akuntansi/saldo-unit/excel')->assertOk();
        $this->assertStringContainsString('SALDO-ANGGARAN-UNIT-' . self::TAHUN . '.xlsx', $r->headers->get('content-disposition'));
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/saldo-unit')->assertStatus(401);
    }
}
