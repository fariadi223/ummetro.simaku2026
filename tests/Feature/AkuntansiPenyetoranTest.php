<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penyetoran kas unit (/akuntansi/penyetoran). Hanya GET dan POST yang ditolak validasi —
 * tidak menulis ke ueu_tbl_jurnal_spm.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiPenyetoranTest
 */
class AkuntansiPenyetoranTest extends TestCase
{
    private const TAHUN = '2026';

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    private function jumlahJurnal(): int
    {
        return DB::table('ueu_tbl_jurnal_spm')->where('tahun', self::TAHUN)->count();
    }

    public function test_daftar_saldo_sesuai_jurnal(): void
    {
        $saldo = DB::table('ueu_tbl_jurnal_spm')->where('tahun', self::TAHUN)->where('idrek6', 570)->where('kodeunit', 1)
            ->selectRaw('SUM(debet) - SUM(kredit) as s')->value('s');

        $this->akuntansi()->get('/akuntansi/penyetoran')->assertOk()
            ->assertSee('Penyetoran Kas Unit ke Kas Universitas')
            ->assertViewHas('daftar', fn ($d) => (float) $d->firstWhere('ff', '01')->saldo === (float) $saldo);
    }

    public function test_form_setor(): void
    {
        $this->akuntansi()->get('/akuntansi/penyetoran/01')->assertOk()
            ->assertSee('Rekening yang di-DEBET')->assertSee('name="id_sumber"', false)
            ->assertSee('value="569" selected', false)
            ->assertDontSee('<option value="570"', false);

        $this->akuntansi()->get('/akuntansi/penyetoran/99')->assertNotFound();
        $this->akuntansi()->get('/akuntansi/penyetoran/1')->assertNotFound();
    }

    public function test_simpan_tidak_valid_ditolak(): void
    {
        $sebelum = $this->jumlahJurnal();

        $this->akuntansi()->from('/akuntansi/penyetoran/01')->post('/akuntansi/penyetoran/01', [
            'bukti' => '', 'tgl_trk' => '2026-13-01', 'nominal' => '0', 'debet' => 570, 'id_sumber' => '',
        ])->assertRedirect('/akuntansi/penyetoran/01')
          ->assertSessionHasErrors(['bukti', 'tgl_trk', 'nominal', 'debet', 'id_sumber']);

        $this->assertSame($sebelum, $this->jumlahJurnal());
    }

    /** Periode bukan periode berjalan (penjurnalan ditutup): form dikunci & simpan ditolak sebelum validasi. */
    public function test_periode_bukan_berjalan_dikunci(): void
    {
        // T.A tertutup yang juga tidak dibuka admin (jurnal_dibuka, bila kolomnya sudah ada).
        $tutup = DB::table('ueu_tbl_info')->where('penjurnalan', '!=', 'open')
            ->when(\App\Models\Info::adaKolomJurnalDibuka(), fn ($q) => $q->where('jurnal_dibuka', '!=', 'ya'))
            ->orderByDesc('th_angg')->value('th_angg');
        if (!$tutup) {
            $this->markTestSkipped('Tidak ada T.A dengan penjurnalan ditutup.');
        }
        $sebelum = DB::table('ueu_tbl_jurnal_spm')->count();
        $sesi = fn () => $this->akuntansi()->withSession(['periode' => (string) $tutup]);

        $sesi()->get('/akuntansi/penyetoran/01')->assertOk()
            ->assertSee('Form dikunci')->assertSee('<fieldset class="setor-kunci" disabled', false)
            ->assertSee('Kembali');

        $sesi()->from('/akuntansi/penyetoran/01')->post('/akuntansi/penyetoran/01', [
            'bukti' => 'UJI', 'tgl_trk' => $tutup . '-10-01', 'nominal' => '', 'debet' => '', 'id_sumber' => '',
        ])->assertRedirect('/akuntansi/penyetoran/01')
          ->assertSessionHas('error', fn ($m) => str_contains($m, 'bukan periode berjalan'))
          ->assertSessionHasNoErrors();

        $this->akuntansi()->get('/akuntansi/penyetoran/01')->assertOk()
            ->assertDontSee('Form dikunci')->assertDontSee('<fieldset class="setor-kunci" disabled', false);

        $this->assertSame($sebelum, DB::table('ueu_tbl_jurnal_spm')->count());
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())
            ->withSession(['wewenang' => 'kasir'])->get('/akuntansi/penyetoran')->assertStatus(401);
    }
}
