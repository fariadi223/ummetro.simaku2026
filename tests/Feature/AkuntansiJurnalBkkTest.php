<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jurnal non anggaran (/akuntansi/jurnal-bkk). Hanya GET dan POST/DELETE yang ditolak —
 * tidak menulis ke ueu_tbl_jurnal_spm / ueu_tbl_bkk / ueu_tbl_bkk_rinci.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiJurnalBkkTest
 */
class AkuntansiJurnalBkkTest extends TestCase
{
    private const TAHUN = '2026';

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    private function jumlah(): array
    {
        return [DB::table('ueu_tbl_jurnal_spm')->count(), DB::table('ueu_tbl_bkk')->count(), DB::table('ueu_tbl_bkk_rinci')->sum('realisasi')];
    }

    public function test_daftar_dan_realisasi(): void
    {
        $sudah = DB::table('ueu_tbl_bkk as b')->where('b.tahun', self::TAHUN)->where('b.status_bkk', 'Tunai')
            ->whereExists(fn ($q) => $q->from('ueu_tbl_jurnal_spm as j')->whereColumn('j.bukti', 'b.no_bkk')->where('j.tahun', self::TAHUN))
            ->first();

        $this->akuntansi()->get('/akuntansi/jurnal-bkk')->assertOk()
            ->assertSee('Jurnal Non Anggaran')->assertSee('id="modalJurnal"', false)
            ->assertViewHas('daftar', fn ($d) => collect($d->items())->every(fn ($b) => $b->status_bkk === 'Tunai' && $b->tahun === self::TAHUN));

        if ($sudah) {
            $kredit = (float) DB::table('ueu_tbl_jurnal_spm')->where('tahun', self::TAHUN)->where('bukti', $sudah->no_bkk)->sum('kredit');
            $this->akuntansi()->get('/akuntansi/jurnal-bkk?cari=' . urlencode($sudah->no_bkk))->assertOk()
                ->assertViewHas('daftar', fn ($d) => (float) collect($d->items())->firstWhere('id_bkk', $sudah->id_bkk)->realisasi === $kredit);
        }
    }

    public function test_filter_tanggal(): void
    {
        $tgl = DB::table('ueu_tbl_bkk')->where('tahun', self::TAHUN)->where('status_bkk', 'Tunai')->orderByDesc('id_bkk')->value('tgl_bkk')
            ?? $this->markTestSkipped('Tidak ada BKK tunai.');
        $jumlah = DB::table('ueu_tbl_bkk')->where('tahun', self::TAHUN)->where('status_bkk', 'Tunai')->where('tgl_bkk', $tgl)->count();

        $this->akuntansi()->get("/akuntansi/jurnal-bkk?dari={$tgl}&sampai={$tgl}")->assertOk()
            ->assertSee('value="' . $tgl . '"', false)
            ->assertViewHas('daftar', fn ($d) => $d->total() === $jumlah && collect($d->items())->every(fn ($b) => $b->tgl_bkk === $tgl));

        // Urutan terbalik ditukar; tanggal tidak valid diabaikan.
        $this->akuntansi()->get('/akuntansi/jurnal-bkk?dari=2026-12-31&sampai=2026-01-01')->assertOk()
            ->assertViewHas('dari', '2026-01-01')->assertViewHas('sampai', '2026-12-31');
        $this->akuntansi()->get('/akuntansi/jurnal-bkk?dari=2026-02-30&sampai=x')->assertOk()
            ->assertViewHas('dari', '')->assertViewHas('sampai', '');
    }

    public function test_simpan_tidak_valid_dan_hapus_ditolak(): void
    {
        $bkk = DB::table('ueu_tbl_bkk')->where('tahun', self::TAHUN)->where('status_bkk', 'Tunai')->orderByDesc('id_bkk')->first()
            ?? $this->markTestSkipped('Tidak ada BKK tunai.');
        $sub = DB::table('ueu_tbl_unit')->where('kode', 'not like', '%.00')->value('id_unit');
        $sebelum = $this->jumlah();
        $url = "/akuntansi/jurnal-bkk/{$bkk->id_bkk}";

        $this->akuntansi()->post($url, ['tgl_trk' => 'x', 'uraian' => '', 'nominal' => '0', 'debet' => 569, 'kredit' => 569,
            'id_uk' => '', 'id_sumber' => '', 'bkk_rinci_id' => 999999999, 'f_cari' => 'abc'])
            ->assertRedirect('/akuntansi/jurnal-bkk?cari=abc')
            ->assertSessionHasErrors(['tgl_trk', 'uraian', 'nominal', 'kredit', 'id_uk', 'id_sumber', 'bkk_rinci_id'])
            ->assertSessionHasInput('_bkk', $bkk->id_bkk);

        // Halaman setelah gagal: isian lama dikirim ke JS untuk membuka lagi modal.
        $this->akuntansi()->followingRedirects()->post($url, ['tgl_trk' => '2026-11-08', 'uraian' => 'Uraian lama', 'nominal' => '1.000',
            'debet' => 569, 'kredit' => 569, 'id_uk' => $sub, 'id_sumber' => 49])
            ->assertOk()->assertSee('"uraian":"Uraian lama"', false)->assertSee('"nominal":"1.000"', false)
            ->assertSee('harus berbeda');

        // Melebihi sisa BKK.
        $this->akuntansi()->post($url, ['tgl_trk' => '2026-11-08', 'uraian' => 'Uji', 'nominal' => '999999999999',
            'debet' => 569, 'kredit' => 570, 'id_uk' => $sub, 'id_sumber' => 49])
            ->assertRedirect('/akuntansi/jurnal-bkk')->assertSessionHasErrors(['nominal']);

        $this->akuntansi()->post('/akuntansi/jurnal-bkk/999999999', ['uraian' => 'x'])->assertNotFound();
        $this->akuntansi()->delete('/akuntansi/jurnal-bkk/999999999')->assertNotFound();

        // BKK yang sudah dijurnal tidak bisa dihapus.
        $sudah = DB::table('ueu_tbl_bkk as b')->where('b.tahun', self::TAHUN)->where('b.status_bkk', 'Tunai')
            ->whereExists(fn ($q) => $q->from('ueu_tbl_jurnal_spm as j')->whereColumn('j.bukti', 'b.no_bkk')->where('j.tahun', self::TAHUN))
            ->value('id_bkk');
        if ($sudah) {
            $this->akuntansi()->delete("/akuntansi/jurnal-bkk/{$sudah}")->assertRedirect('/akuntansi/jurnal-bkk')->assertSessionHas('error');
        }

        $this->assertSame($sebelum, $this->jumlah());
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/jurnal-bkk')->assertStatus(401);
    }
}
