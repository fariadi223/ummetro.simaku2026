<?php

namespace Tests\Feature;

use App\Models\Info;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Setting > Periode: buka/tutup penjurnalan T.A di luar periode berjalan (kolom ueu_tbl_info.jurnal_dibuka).
 * Tidak menulis ke DB: hanya GET dan PUT yang ditolak (periode berjalan / kolom belum ada).
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=AdminPeriodeJurnalTest
 */
class AdminPeriodeJurnalTest extends TestCase
{
  private function admin(): static
  {
    $admin = User::where('wewenang', 'admin')->whereNotIn('password', User::PASSWORD_ROLE)->firstOrFail();

    return $this->actingAs($admin)->withSession(['wewenang' => 'admin', 'periode' => 2026]);
  }

  private function snapshot(): array
  {
    return DB::table('ueu_tbl_info')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
  }

  public function test_halaman_menampilkan_status_jurnal(): void
  {
    $res = $this->admin()->get(route('admin.periode.index'))->assertOk()->assertSee('Jurnal berjalan');

    if (Info::adaKolomJurnalDibuka()) {
      $res->assertSee('Buka jurnal')->assertDontSee('2026_11_09_ueu_tbl_info_jurnal_dibuka.sql');
    } else {
      // Sebelum SQL dijalankan: fitur disembunyikan & admin diberi tahu file SQL-nya.
      $res->assertSee('2026_11_09_ueu_tbl_info_jurnal_dibuka.sql')->assertDontSee('Buka jurnal');
    }
  }

  public function test_periode_berjalan_tidak_bisa_diubah(): void
  {
    $berjalan = Info::where('penjurnalan', 'open')->firstOrFail();
    $sebelum = $this->snapshot();

    $this->admin()->from(route('admin.periode.index'))
      ->put(route('admin.periode.jurnal', $berjalan), ['dibuka' => 0])
      ->assertRedirect(route('admin.periode.index'))
      ->assertSessionHas('error');

    $this->assertSame($sebelum, $this->snapshot());
  }

  public function test_tanpa_kolom_permintaan_ditolak(): void
  {
    if (Info::adaKolomJurnalDibuka()) {
      $this->markTestSkipped('Kolom jurnal_dibuka sudah ada.');
    }
    $tutup = Info::where('penjurnalan', '!=', 'open')->orderByDesc('th_angg')->firstOrFail();
    $sebelum = $this->snapshot();

    $this->admin()->from(route('admin.periode.index'))
      ->put(route('admin.periode.jurnal', $tutup), ['dibuka' => 1])
      ->assertSessionHas('error', fn ($m) => str_contains($m, 'jurnal_dibuka belum ada'));

    $this->assertSame($sebelum, $this->snapshot());
  }

  /** Periode berjalan selalu terbuka; T.A tertutup terbuka hanya bila jurnal_dibuka = 'ya'. */
  public function test_jurnal_terbuka(): void
  {
    $berjalan = Info::where('penjurnalan', 'open')->value('th_angg');
    $this->assertTrue(Info::jurnalTerbuka($berjalan));

    $tutup = Info::where('penjurnalan', '!=', 'open')->orderByDesc('th_angg')->first();
    $this->assertSame(Info::adaKolomJurnalDibuka() && $tutup->jurnal_dibuka === 'ya', Info::jurnalTerbuka($tutup->th_angg));
    $this->assertFalse(Info::jurnalTerbuka(1990));
  }

  public function test_role_lain_ditolak(): void
  {
    $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())->withSession(['wewenang' => 'kasir'])
      ->put(route('admin.periode.jurnal', 1), ['dibuka' => 1])->assertStatus(401);
  }
}
