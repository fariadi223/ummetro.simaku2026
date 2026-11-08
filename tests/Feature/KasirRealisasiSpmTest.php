<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RealisasiSpm;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Realisasi SPM kasir (/kasir/spm). Hanya GET dan POST yang ditolak — tidak menulis ke database.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=KasirRealisasiSpmTest
 */
class KasirRealisasiSpmTest extends TestCase
{
    private const TAHUN = '2026';

    private function kasir(): static
    {
        return $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())
            ->withSession(['wewenang' => 'kasir', 'periode' => self::TAHUN]);
    }

    private function spm(bool $dibayar): object
    {
        $s = app(RealisasiSpm::class)->daftar((int) self::TAHUN)->first(fn ($s) => ($s->bayar !== null) === $dibayar);
        if (!$s) {
            $this->markTestSkipped('Tidak ada SPM ' . ($dibayar ? 'sudah' : 'belum') . ' dibayar pada T.A ' . self::TAHUN);
        }

        return $s;
    }

    public function test_daftar_render_dan_filter(): void
    {
        $this->kasir()->get('/kasir/spm')->assertOk()->assertSee('Data SPM Disetujui')->assertSee('Realisasi SPM');
        $this->kasir()->get('/kasir/spm?status=belum')->assertOk();
        $this->kasir()->get('/kasir/spm?status=sudah&cari=SPM')->assertOk();
        $this->kasir()->get('/kasir/spm?page=3')->assertOk();
    }

    public function test_bkk_spm_sudah_dibayar(): void
    {
        $s = $this->spm(true);
        $this->kasir()->get("/kasir/spm/{$s->id_spp}/bkk")->assertOk()
            ->assertSee('Bukti Kas Keluar')->assertSee(RealisasiSpm::nomorBkk($s))->assertSee($s->no_spm);
    }

    public function test_bkk_spm_cetak_ulang_diberi_watermark_copy(): void
    {
        $s = $this->spm(true);
        $this->kasir()->get("/kasir/spm/{$s->id_spp}/bkk")->assertOk()->assertSee('wm-copy', false);

        $this->kasir()->withSession(['wewenang' => 'kasir', 'periode' => self::TAHUN, "bkk_asli.spm.{$s->id_spp}" => true])
            ->get("/kasir/spm/{$s->id_spp}/bkk")->assertOk()->assertDontSee('wm-copy', false);
        $this->get("/kasir/spm/{$s->id_spp}/bkk")->assertOk()->assertSee('wm-copy', false);
    }

    public function test_form_transfer_dan_validasi(): void
    {
        $s = $this->spm(false);
        $this->kasir()->get("/kasir/spm/{$s->id_spp}/transfer")->assertOk()->assertSee('Input Bukti Transfer SPM');

        $sebelum = DB::table('ueu_tbl_kasir')->count();
        $this->kasir()->from("/kasir/spm/{$s->id_spp}/transfer")
            ->post("/kasir/spm/{$s->id_spp}/transfer", ['namabank' => 'BRI Syariah', 'nomrek' => '', 'nomref' => ''])
            ->assertRedirect("/kasir/spm/{$s->id_spp}/transfer")->assertSessionHasErrors(['nomrek', 'nomref']);
        $this->assertSame($sebelum, DB::table('ueu_tbl_kasir')->count());
    }

    public function test_spm_sudah_dibayar_tidak_bisa_dibayar_lagi(): void
    {
        $s = $this->spm(true);
        $sebelum = DB::table('ueu_tbl_kasir')->count();

        $this->kasir()->get("/kasir/spm/{$s->id_spp}/transfer")->assertRedirect(route('kasir.spm.index'));
        $this->kasir()->post("/kasir/spm/{$s->id_spp}/tunai")->assertRedirect(route('kasir.spm.index'))->assertSessionHas('error');
        $this->kasir()->post("/kasir/spm/{$s->id_spp}/transfer", ['nomrek' => '1', 'nomref' => '1'])
            ->assertRedirect(route('kasir.spm.index'))->assertSessionHas('error');

        $this->assertSame($sebelum, DB::table('ueu_tbl_kasir')->count());
    }

    public function test_bkk_spm_belum_dibayar_dialihkan_dan_id_salah_404(): void
    {
        $s = $this->spm(false);
        $this->kasir()->get("/kasir/spm/{$s->id_spp}/bkk")->assertRedirect(route('kasir.spm.index'));
        $this->kasir()->get('/kasir/spm/999999999/bkk')->assertNotFound();
    }

    public function test_penerima_hanya_unit_tertentu(): void
    {
        $s = app(RealisasiSpm::class)->daftar((int) self::TAHUN)->first(fn ($s) => !in_array($s->kode_spm, config('simaku.kasir_unit_paraf'), true));
        $this->kasir()->get("/kasir/spm/{$s->id_spp}/penerima")->assertNotFound();

        $boleh = app(RealisasiSpm::class)->daftar((int) self::TAHUN)->first(fn ($s) => in_array($s->kode_spm, config('simaku.kasir_unit_paraf'), true));
        if ($boleh) {
            $this->kasir()->get("/kasir/spm/{$boleh->id_spp}/penerima")->assertOk()->assertSee('Input Penerima SPM');
            $this->kasir()->post("/kasir/spm/{$boleh->id_spp}/penerima", ['nm_pimp' => ''])->assertSessionHasErrors('nm_pimp');
        }
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'operatorspm')->firstOrFail())
            ->withSession(['wewenang' => 'operatorspm'])->get('/kasir/spm')->assertStatus(401);
    }
}
