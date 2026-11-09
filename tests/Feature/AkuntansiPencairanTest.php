<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pencairan anggaran belanja (/akuntansi/pencairan). Hanya GET dan POST yang ditolak —
 * tidak menulis ke ueu_tbl_jurnal_spm / ueu_tbl_spp.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiPencairanTest
 */
class AkuntansiPencairanTest extends TestCase
{
    private const TAHUN = '2026';

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => self::TAHUN]);
    }

    public function test_daftar_hanya_belum_dijurnal_dan_sudah_dibayar(): void
    {
        $this->akuntansi()->get('/akuntansi/pencairan')->assertOk()
            ->assertSee('Pencairan Anggaran Belanja')
            ->assertViewHas('daftar', function ($d) {
                $ids = $d->pluck('id_spp')->all();
                $spp = DB::table('ueu_tbl_spp')->whereIn('id_spp', $ids ?: [0])->get()->keyBy('id_spp');

                return $d->every(fn ($r) => $spp[$r->id_spp]->jurnalspm === '' && $spp[$r->id_spp]->status_spm === 'Disetujui'
                    && $spp[$r->id_spp]->tahun === self::TAHUN && $r->no_bkk && str_contains($r->no_bkk, "/BKK/") && $r->induk);
            });
    }

    public function test_jurnal_id_tidak_valid_ditolak(): void
    {
        $sudah = DB::table('ueu_tbl_spp')->where('tahun', self::TAHUN)->where('jurnalspm', 'CLK')->value('id_spp');
        $jurnal = DB::table('ueu_tbl_jurnal_spm')->count();

        foreach ([999999999, $sudah] as $id) {
            $this->akuntansi()->post("/akuntansi/pencairan/{$id}/jurnal")
                ->assertRedirect('/akuntansi/pencairan')->assertSessionHas('error');
        }

        $this->assertSame($jurnal, DB::table('ueu_tbl_jurnal_spm')->count());
    }

    /** T.A tertutup (dan tidak dibuka admin): tombol Jurnalkan nonaktif & simpan ditolak sebelum baris dicari. */
    public function test_periode_tertutup_jurnal_dikunci(): void
    {
        $tutup = DB::table('ueu_tbl_info')->where('penjurnalan', '!=', 'open')
            ->when(\App\Models\Info::adaKolomJurnalDibuka(), fn ($q) => $q->where('jurnal_dibuka', '!=', 'ya'))
            ->orderByDesc('th_angg')->value('th_angg');
        if (!$tutup) {
            $this->markTestSkipped('Tidak ada T.A dengan penjurnalan ditutup.');
        }
        $sebelum = [DB::table('ueu_tbl_jurnal_spm')->count(), DB::table('ueu_tbl_spp')->where('jurnalspm', 'CLK')->count()];
        $sesi = fn () => $this->akuntansi()->withSession(['periode' => (string) $tutup]);

        $sesi()->get('/akuntansi/pencairan')->assertOk()
            ->assertSee('Form dikunci')->assertDontSee('class="d-inline form-jurnal"', false);

        $sesi()->post('/akuntansi/pencairan/1/jurnal')
            ->assertRedirect('/akuntansi/pencairan')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'bukan periode berjalan'));

        $this->akuntansi()->get('/akuntansi/pencairan')->assertOk()->assertDontSee('Form dikunci');

        $this->assertSame($sebelum, [DB::table('ueu_tbl_jurnal_spm')->count(), DB::table('ueu_tbl_spp')->where('jurnalspm', 'CLK')->count()]);
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/pencairan')->assertStatus(401);
    }
}
