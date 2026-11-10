<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PemetaanUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Kertas Kerja > Pemetaan Unit (/akuntansi/pemetaan-unit).
 * Halaman dibaca dari data asli (GET); simpan/hapus di-mock sehingga tidak ada penulisan ke database.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AkuntansiPemetaanUnitTest
 */
class AkuntansiPemetaanUnitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('ueu_map_unit_prodi') || !Schema::hasTable('ueu_ref_prodi')) {
            $this->markTestSkipped('tabel pemetaan belum ada');
        }
    }

    private function akuntansi(): static
    {
        return $this->actingAs(User::where('wewenang', 'admin')->where('password', 'akuntansi')->firstOrFail())
            ->withSession(['wewenang' => 'akuntansi', 'periode' => 2025]);
    }

    /** Service asli untuk bacaan, hanya simpan/hapus yang diganti. */
    private function mockTulis(callable $atur): void
    {
        $this->partialMock(PemetaanUnit::class, $atur);
    }

    public function test_halaman_data_asli(): void
    {
        $m = DB::table('ueu_map_unit_prodi as m')->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'm.id_unit')->first(['u.kode', 'u.nama']);
        $r = $this->akuntansi()->get('/akuntansi/pemetaan-unit')->assertOk()
            ->assertSee('Pemetaan Unit')->assertSee('Sudah terpetakan')->assertSee('Belum terpetakan')->assertSee('Kode NPM');
        if ($m) {
            $r->assertSee($m->kode);
        }
        $this->assertCount(DB::table('ueu_map_unit_prodi')->count(), $r->viewData('pemetaan'));
        $this->akuntansi()->get('/akuntansi/pemetaan-unit?tahun=semua')->assertOk()->assertSee('Semua tahun');
    }

    public function test_simpan_validasi_ditolak(): void
    {
        $this->mockTulis(fn ($m) => $m->shouldNotReceive('simpan'));
        $this->akuntansi()->from('/akuntansi/pemetaan-unit')
            ->post('/akuntansi/pemetaan-unit', ['id_unit' => 999999, 'id_sms' => 'tidak-ada', 'kode_npm' => '8-1', 'berlaku_dari' => '01-01-2026'])
            ->assertRedirect('/akuntansi/pemetaan-unit')->assertSessionHasErrors(['id_unit', 'id_sms', 'kode_npm', 'berlaku_dari']);
    }

    public function test_simpan_memanggil_service(): void
    {
        $idSms = DB::table('ueu_ref_prodi')->value('id_sms');
        $this->mockTulis(fn ($m) => $m->shouldReceive('simpan')->once()->with(23, 'prodi', $idSms, null, '84', null)->andReturn(true));
        $this->akuntansi()->from('/akuntansi/pemetaan-unit')
            ->post('/akuntansi/pemetaan-unit', ['id_unit' => 23, 'id_sms' => $idSms, 'kode_npm' => '84'])
            ->assertRedirect('/akuntansi/pemetaan-unit')->assertSessionHasNoErrors()->assertSessionHas('status', 'Pemetaan unit 23 ke prodi ditambahkan.');
    }

    public function test_pemetaan_fakultas_untuk_wakil_dekan(): void
    {
        if (!Schema::hasTable('ueu_ref_fakultas') || !Schema::hasColumn('ueu_map_unit_prodi', 'jenis')) {
            $this->markTestSkipped('01c_pemetaan_fakultas.sql belum dijalankan');
        }
        $wd = DB::table('ueu_tbl_unit')->where('kode', '01.01')->first(['id_unit', 'nama']);

        // Halaman: unit 01.01 ditandai pimpinan dengan usulan fakultas induk 01.00.
        $unit = collect($this->akuntansi()->get('/akuntansi/pemetaan-unit')->assertOk()
            ->assertSee('Dipetakan ke')->viewData('daftarUnit'))->firstWhere('id_unit', $wd->id_unit);
        $this->assertSame(1, (int) $unit->pimpinan);
        $this->assertSame(DB::table('ueu_tbl_unit')->where('kode', '01.00')->value('kode_fak'), $unit->usulan_fak);

        // Jenis fakultas tanpa kode_fak ditolak; prodi tidak wajib.
        $this->mockTulis(fn ($m) => $m->shouldNotReceive('simpan'));
        $this->akuntansi()->from('/akuntansi/pemetaan-unit')
            ->post('/akuntansi/pemetaan-unit', ['id_unit' => $wd->id_unit, 'jenis' => 'fakultas'])
            ->assertSessionHasErrors('kode_fak')->assertSessionDoesntHaveErrors('id_sms');
    }

    public function test_simpan_fakultas_memanggil_service(): void
    {
        $kodeFak = Schema::hasTable('ueu_ref_fakultas') ? DB::table('ueu_ref_fakultas')->value('kode_fak') : null;
        if (!$kodeFak) {
            $this->markTestSkipped('ueu_ref_fakultas kosong; jalankan php artisan simaku:sinkron-prodi');
        }
        $id = (int) DB::table('ueu_tbl_unit')->where('kode', '01.01')->value('id_unit');
        $this->mockTulis(fn ($m) => $m->shouldReceive('simpan')->once()->with($id, 'fakultas', null, $kodeFak, null, null)->andReturn(true));
        $this->akuntansi()->from('/akuntansi/pemetaan-unit')
            ->post('/akuntansi/pemetaan-unit', ['id_unit' => $id, 'jenis' => 'fakultas', 'kode_fak' => $kodeFak])
            ->assertSessionHasNoErrors()->assertSessionHas('status', "Pemetaan unit {$id} ke fakultas ditambahkan.");
    }

    public function test_hapus(): void
    {
        $this->mockTulis(function ($m) {
            $m->shouldReceive('hapus')->with(7)->andReturn(1);
            $m->shouldReceive('hapus')->with(8)->andReturn(0);
        });
        $this->akuntansi()->from('/akuntansi/pemetaan-unit')->delete('/akuntansi/pemetaan-unit/7')->assertRedirect('/akuntansi/pemetaan-unit');
        $this->akuntansi()->delete('/akuntansi/pemetaan-unit/8')->assertNotFound();
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
            ->get('/akuntansi/pemetaan-unit')->assertStatus(401);
    }
}
