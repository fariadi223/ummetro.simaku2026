<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Input jurnal umum (/akuntansi/jurnal-umum). Hanya GET dan POST yang ditolak validasi —
 * tidak menulis ke ueu_tbl_jurnal_spm.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiJurnalUmumTest
 */
class AkuntansiJurnalUmumTest extends TestCase
{
    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => '2026']);
    }

    public function test_form_tampil(): void
    {
        $sub = DB::table('ueu_tbl_unit')->where('kode', 'not like', '%.00')->first();
        $induk = DB::table('ueu_tbl_unit')->where('kode', 'like', '%.00')->first();

        $this->akuntansi()->get('/akuntansi/jurnal-umum')->assertOk()
            ->assertSee('Input Jurnal Umum')
            ->assertSee('id="tplBaris"', false)->assertSee('data-nama="debet"', false)->assertSee('data-nama="kredit"', false)
            ->assertSee('name="id_mak"', false)
            ->assertSee('<option value="' . $sub->id_unit . '"', false)
            ->assertSee('<option value="569"', false);
        $this->assertNotNull($induk);
    }

    public function test_riwayat_per_tanggal(): void
    {
        $this->akuntansi()->get('/akuntansi/jurnal-umum')->assertOk()
            ->assertSee('Riwayat Jurnal Umum')->assertSee('Hari ini')
            ->assertViewHas('tglRiwayat', today()->toDateString())
            ->assertViewHas('riwayat', fn ($r) => $r->count() <= 10)
            ->assertViewHas('riwayat', fn ($r) => $r->every(fn ($e) => collect($e->baris)->every(fn ($b) => $b->tgl_trk === today()->toDateString())));

        // Tidak ada filter tanggal: ?tgl diabaikan.
        $this->akuntansi()->get('/akuntansi/jurnal-umum?tgl=2026-01-05')->assertOk()
            ->assertViewHas('tglRiwayat', today()->toDateString())->assertDontSee('name="tgl"', false);
    }

    public function test_simpan_tidak_valid_ditolak(): void
    {
        $sebelum = DB::table('ueu_tbl_jurnal_spm')->count();
        $induk = DB::table('ueu_tbl_unit')->where('kode', 'like', '%.00')->value('id_unit');
        $sub = DB::table('ueu_tbl_unit')->where('kode', 'not like', '%.00')->value('id_unit');
        $valid = ['tgl_trk' => '2026-11-08', 'id_uk' => $sub, 'uraian' => 'Uji', 'id_sumber' => 49];

        $this->akuntansi()->from('/akuntansi/jurnal-umum')->post('/akuntansi/jurnal-umum', [
            'tgl_trk' => '2026-02-30', 'id_uk' => $induk, 'id_mak' => 999999, 'uraian' => '', 'id_sumber' => '',
            'baris' => [['idrek6' => '569', 'debet' => '1.000', 'kredit' => '']],
        ])->assertRedirect('/akuntansi/jurnal-umum')
          ->assertSessionHasErrors(['tgl_trk', 'id_uk', 'id_mak', 'uraian', 'id_sumber', 'baris']);

        // Tidak seimbang, baris dengan debet & kredit sekaligus, rekening kosong / tidak ada.
        $this->akuntansi()->from('/akuntansi/jurnal-umum')->post('/akuntansi/jurnal-umum', $valid + ['baris' => [
            ['idrek6' => '569', 'debet' => '1.500.000', 'kredit' => ''],
            ['idrek6' => '570', 'debet' => '', 'kredit' => '1.000.000'],
            ['idrek6' => '809', 'debet' => '100', 'kredit' => '100'],
            ['idrek6' => '', 'debet' => '', 'kredit' => '400.000'],
            ['idrek6' => '99999999', 'debet' => '', 'kredit' => '1'],
        ]])->assertRedirect('/akuntansi/jurnal-umum')
          ->assertSessionHasErrors(['seimbang', 'baris.2.debet', 'baris.3.idrek6', 'baris.4.idrek6'])
          ->assertSessionDoesntHaveErrors(['baris.0.debet', 'baris.1.debet', 'tgl_trk', 'id_uk']);

        // Halaman setelah gagal: baris lama dikembalikan & pesan tampil.
        $this->akuntansi()->followingRedirects()->post('/akuntansi/jurnal-umum', $valid + ['baris' => [
            ['idrek6' => '569', 'debet' => '2.000', 'kredit' => ''],
            ['idrek6' => '570', 'debet' => '', 'kredit' => '1.000'],
            ['idrek6' => '809', 'debet' => '', 'kredit' => '500'],
        ]])->assertOk()->assertSee('Jurnal tidak seimbang')->assertSee('"debet":"2.000"', false)->assertSee('"idrek6":"809"', false);

        $this->assertSame($sebelum, DB::table('ueu_tbl_jurnal_spm')->count());
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/jurnal-umum')->assertStatus(401);
    }
}
