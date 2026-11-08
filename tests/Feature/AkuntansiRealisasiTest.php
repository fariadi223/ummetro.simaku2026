<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Realisasi anggaran (/akuntansi/realisasi). Hanya GET dan POST yang ditolak validasi —
 * tidak menulis ke ueu_tbl_jurnal_spm / ueu_tbl_spp.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiRealisasiTest
 */
class AkuntansiRealisasiTest extends TestCase
{
    private const TAHUN = '2026';

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    /** Satu SPP belum dijurnal realisasinya beserta rinci belanjanya. */
    private function contoh(): object
    {
        return DB::table('ueu_tbl_spp as s')->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 's.idkeg')
            ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'k.idunit')
            ->join('ueu_tbl_rinci as r', 'r.idkeg', '=', 's.idkeg')
            ->join('ueu_tbl_rek6 as r6', 'r6.idrek6', '=', 'r.idrek6')->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
            ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
            ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
            ->where('s.tahun', self::TAHUN)->where('s.status_spm', 'Disetujui')->where('s.jurnalspm', 'CLK')->where('r1.koderek1', '5')
            ->select('s.id_spp', 's.no_spm', 's.no_spp', 'r.idrinci', 'u.kode')->first() ?? $this->markTestSkipped('Tidak ada SPP CLK.');
    }

    public function test_daftar_dan_filter(): void
    {
        $c = $this->contoh();
        $ff = explode('.', $c->kode)[0];

        $this->akuntansi()->get('/akuntansi/realisasi')->assertOk()
            ->assertSee('Realisasi Anggaran')->assertSee('name="unit"', false)->assertSee('name="spm"', false)->assertSee('name="spp"', false)
            ->assertViewHas('opsiSpm', fn ($o) => $o->contains($c->no_spm));

        $this->akuntansi()->get('/akuntansi/realisasi?unit=' . $ff . '&spm=' . urlencode($c->no_spm))->assertOk()
            ->assertViewHas('daftar', fn ($d) => $d->total() > 0 && collect($d->items())->every(fn ($r) => $r->no_spm === $c->no_spm))
            ->assertViewHas('opsiSpp', fn ($o) => $o->contains($c->no_spp));

        $this->akuntansi()->get('/akuntansi/realisasi?spp=' . urlencode($c->no_spp))->assertOk()
            ->assertViewHas('daftar', fn ($d) => collect($d->items())->every(fn ($r) => $r->no_spp === $c->no_spp));
    }

    public function test_modal_dan_simpan_tidak_valid(): void
    {
        $c = $this->contoh();
        $url = "/akuntansi/realisasi/{$c->id_spp}/{$c->idrinci}";
        $daftar = '/akuntansi/realisasi?spp=' . urlencode($c->no_spp);

        // Tombol Jurnal memuat URL simpan berbasis id (bukan no. SPP filter) dan modal ada di halaman.
        $this->akuntansi()->get($daftar)->assertOk()
            ->assertSee('id="modalJurnal"', false)->assertSee('data-action="' . url($url) . '"', false);
        $this->akuntansi()->get($url)->assertStatus(405);

        $sebelum = DB::table('ueu_tbl_jurnal_spm')->count();
        $this->akuntansi()->post($url, ['bukti' => '', 'tgl_trk' => 'x', 'nominal' => '0', 'kas' => 'lain', 'f_spp' => $c->no_spp])
            ->assertRedirect(url($daftar))->assertSessionHasErrors(['bukti', 'tgl_trk', 'nominal', 'kas'])
            ->assertSessionHasInput('_baris', $c->id_spp . '-' . $c->idrinci);
        // Halaman daftar setelah gagal validasi: modal dibuka lagi dengan pesan error.
        $this->akuntansi()->followingRedirects()
            ->post($url, ['bukti' => 'B-9', 'tgl_trk' => 'x', 'nominal' => '1000', 'kas' => 'univ', 'f_spp' => $c->no_spp])
            ->assertOk()->assertSee('value="B-9"', false)->assertSee('value="1.000"', false)
            ->assertSee('id="kas-univ" checked', false)->assertSee('is-invalid', false);
        // Melebihi sisa anggaran → ditolak sebelum insert.
        $this->akuntansi()->post($url, ['bukti' => 'T-1', 'tgl_trk' => '2026-11-08', 'nominal' => '999999999999', 'kas' => 'univ'])
            ->assertRedirect('/akuntansi/realisasi')->assertSessionHasErrors(['nominal']);
        $this->akuntansi()->post("/akuntansi/realisasi/{$c->id_spp}/999999999", ['bukti' => 'T-1'])->assertNotFound();

        $this->assertSame($sebelum, DB::table('ueu_tbl_jurnal_spm')->count());
        $this->assertSame('CLK', DB::table('ueu_tbl_spp')->where('id_spp', $c->id_spp)->value('jurnalspm'));
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/realisasi')->assertStatus(401);
    }
}
