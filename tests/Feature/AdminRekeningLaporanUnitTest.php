<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RekeningLaporanUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Admin > Rekening Laporan Unit (/admin/rekening-laporan-unit).
 * Halaman dibaca dari data asli; perubahan status di-mock (tidak ada penulisan ke database).
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AdminRekeningLaporanUnitTest
 */
class AdminRekeningLaporanUnitTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    if (!Schema::hasTable('ueu_lap_unit_rek_nonaktif')) {
      $this->markTestSkipped('ueu_lap_unit_rek_nonaktif belum ada');
    }
  }

  private function admin(): static
  {
    $admin = User::where('wewenang', 'admin')->whereNotIn('password', User::PASSWORD_ROLE)->firstOrFail();

    return $this->actingAs($admin)->withSession(['wewenang' => 'admin', 'periode' => 2025]);
  }

  public function test_halaman_menandai_rekening_nonaktif(): void
  {
    $r = $this->admin()->get('/admin/rekening-laporan-unit')->assertOk()->assertSee('Rekening Laporan Unit');
    $daftar = collect($r->viewData('daftar'));
    foreach (DB::table('ueu_lap_unit_rek_nonaktif')->pluck('idrek6') as $id) {
      $this->assertFalse($daftar->firstWhere('idrek6', (int) $id)->aktif, "idrek6 {$id} seharusnya nonaktif");
    }
    $this->assertTrue($daftar->where('aktif', true)->isNotEmpty());
  }

  public function test_atur_memanggil_service(): void
  {
    $this->partialMock(RekeningLaporanUnit::class, function ($m) {
      $m->shouldReceive('atur')->once()->with(570, false, 'uji', \Mockery::any());
      $m->shouldReceive('atur')->once()->with(570, true, null, \Mockery::any());
    });
    $this->admin()->from('/admin/rekening-laporan-unit')->post('/admin/rekening-laporan-unit/570', ['aktif' => '0', 'keterangan' => 'uji'])
      ->assertRedirect('/admin/rekening-laporan-unit')->assertSessionHas('status', fn ($s) => str_contains($s, 'disembunyikan'));
    $this->admin()->from('/admin/rekening-laporan-unit')->post('/admin/rekening-laporan-unit/570', ['aktif' => '1'])
      ->assertSessionHas('status', fn ($s) => str_contains($s, 'ditampilkan kembali'));
  }

  public function test_validasi_dan_rekening_tidak_ada(): void
  {
    $this->partialMock(RekeningLaporanUnit::class, fn ($m) => $m->shouldNotReceive('atur'));
    $this->admin()->from('/admin/rekening-laporan-unit')->post('/admin/rekening-laporan-unit/570', ['aktif' => 'x'])->assertSessionHasErrors('aktif');
    $this->admin()->post('/admin/rekening-laporan-unit/99999999', ['aktif' => '0'])->assertNotFound();
  }

  public function test_selain_admin_ditolak(): void
  {
    $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
      ->get('/admin/rekening-laporan-unit')->assertStatus(401);
  }
}
