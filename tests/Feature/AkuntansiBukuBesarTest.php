<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Buku besar (/akuntansi/buku-besar), hanya GET.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiBukuBesarTest
 */
class AkuntansiBukuBesarTest extends TestCase
{
    private const TAHUN = 2026;

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    private function kode1(int $id): string
    {
        return (string) DB::table('ueu_tbl_rek6 as r6')->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
            ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
            ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
            ->where('r6.idrek6', $id)->value('r1.koderek1');
    }

    /** Saldo akhir menurut algoritma v1 tanpa filter (saldo awal + semua transaksi periode). */
    private function saldoV1(int $id): float
    {
        $k1 = $this->kode1($id);
        $normal = in_array($k1, ['1', '5'], true);
        $saldo = 0.0;
        if (!in_array($k1, ['4', '5'], true)) {
            $j = DB::table('ueu_tbl_jurnal_saldo')->where('tahun', (string) self::TAHUN)->where('idrek6', $id)
                ->selectRaw('COALESCE(SUM(debet),0) d, COALESCE(SUM(kredit),0) k')->first();
            $saldo = $normal ? $j->d - $j->k : $j->k - $j->d; // kontra aset: v1 menaruh di kredit lalu dikurangkan → hasil sama
        }
        $t = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) self::TAHUN)->where('idrek6', $id)
            ->whereBetween('tgl_trk', [self::TAHUN . '-09-01', (self::TAHUN + 1) . '-08-31'])
            ->selectRaw('COALESCE(SUM(debet),0) d, COALESCE(SUM(kredit),0) k')->first();

        return (float) $saldo + ($normal ? $t->d - $t->k : $t->k - $t->d);
    }

    public function test_daftar_dan_detail_sesuai_v1(): void
    {
        $akun = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) self::TAHUN)->distinct()->pluck('idrek6');

        $this->akuntansi()->get('/akuntansi/buku-besar')->assertOk()
            ->assertSee('Buku Besar')->assertSee('id="tabelAkun"', false)
            ->assertViewHas('akun', fn ($a) => $a->count() === $akun->count());

        foreach ([570, 569] as $id) {
            if (!$akun->contains($id)) {
                continue;
            }
            $jumlah = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) self::TAHUN)->where('idrek6', $id)
                ->whereBetween('tgl_trk', [self::TAHUN . '-09-01', (self::TAHUN + 1) . '-08-31'])->count();
            $r = $this->akuntansi()->getJson("/akuntansi/buku-besar/{$id}")->assertOk()->json();
            $this->assertSame($jumlah, $r['ringkas']['transaksi']);
            $this->assertEqualsWithDelta($this->saldoV1($id), $r['ringkas']['saldo'], 0.01);
        }
    }

    public function test_filter_tanggal_konsisten(): void
    {
        $id = 570;
        $penuh = collect($this->akuntansi()->getJson("/akuntansi/buku-besar/{$id}")->json('baris'));
        $trx = $penuh->where('jenis', 'trx')->values();
        if ($trx->count() < 2) {
            $this->markTestSkipped('Transaksi kurang.');
        }
        $tengah = $trx[intdiv($trx->count(), 2)]['tgl'];
        $sebelum = date('Y-m-d', strtotime($tengah . ' -1 day'));
        $saldoSebelum = (float) ($penuh->filter(fn ($b) => $b['tgl'] <= $sebelum)->last()['saldo'] ?? 0);

        $b = collect($this->akuntansi()->getJson("/akuntansi/buku-besar/{$id}?dari={$tengah}&sampai=" . (self::TAHUN + 1) . '-08-31')->json('baris'));
        $this->assertSame('sd', $b->first()['jenis']);
        $this->assertEqualsWithDelta($saldoSebelum, $b->first()['saldo'], 0.01);
        $this->assertSame('Saldo Awal per ' . date('d-m-Y', strtotime($tengah)), $b->first()['uraian']);
        $this->assertSame('akhir', $b->last()['jenis']);
        $this->assertStringStartsWith('Saldo Akhir per ', $b->last()['uraian']);
        $this->assertEqualsWithDelta($b->where('jenis', 'trx')->sum('debet'), $b->last()['debet'], 0.01);
        $this->assertEqualsWithDelta($b->where('jenis', 'trx')->sum('kredit'), $b->last()['kredit'], 0.01);
        $this->assertEqualsWithDelta((float) $penuh->last()['saldo'], $b->last()['saldo'], 0.01);

        // Dari = awal periode: tidak ada transaksi yang terhitung dua kali.
        $c = collect($this->akuntansi()->getJson("/akuntansi/buku-besar/{$id}?dari=" . self::TAHUN . '-09-01&sampai=' . (self::TAHUN + 1) . '-08-31')->json('baris'));
        $this->assertEqualsWithDelta((float) $penuh->last()['saldo'], $c->last()['saldo'], 0.01);
        $this->assertSame($trx->count(), $c->where('jenis', 'trx')->count());

        $this->akuntansi()->getJson('/akuntansi/buku-besar/99999999')->assertNotFound();
    }

    public function test_ekspor(): void
    {
        $r = $this->akuntansi()->get('/akuntansi/buku-besar/570/excel')->assertOk();
        $this->assertMatchesRegularExpression('/^attachment; filename=LAP-BUKUBESAR-\d+-' . self::TAHUN . '\.xlsx$/', $r->headers->get('content-disposition'));
        $this->akuntansi()->get('/akuntansi/buku-besar/570/kelompok?kelompok=Pembatasan')->assertOk();
        $this->akuntansi()->get('/akuntansi/buku-besar/570/kelompok?kelompok=Lain')->assertSessionHasErrors('kelompok');

        $csv = $this->akuntansi()->get('/akuntansi/buku-besar/semua')->assertOk()->streamedContent();
        $baris = substr_count($csv, "\n") - 4;
        $this->assertSame(DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) self::TAHUN)->count(), $baris);
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/buku-besar')->assertStatus(401);
    }
}
