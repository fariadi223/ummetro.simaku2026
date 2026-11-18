<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Area reviewer (/reviewer): hanya GET dan POST yang ditolak validasi, tidak menulis ke database.
 * Memakai penetapan reviewer nyata (tbl_review) pada T.A pertama yang tersedia.
 */
class ReviewerTest extends TestCase
{
  private function penetapan(): object
  {
    $p = DB::table('tbl_review as r')
      ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'r.unit_id')
      ->join('ueu_tbl_user as us', 'us.idlog', '=', 'r.user_id')
      ->where('us.wewenang', 'review')
      ->whereExists(fn ($q) => $q->from('ueu_tbl_kegiatan as k')->whereColumn('k.idunit', 'r.unit_id')->whereColumn('k.tahun', 'r.tahun'))
      ->orderByDesc('r.tahun')
      ->first(['r.user_id', 'r.tahun', 'u.kode', 'u.id_unit']);

    if (!$p) {
      $this->markTestSkipped('Tidak ada penetapan reviewer dengan kegiatan.');
    }

    $p->keg = DB::table('ueu_tbl_kegiatan')->where('idunit', $p->id_unit)->where('tahun', $p->tahun)->orderBy('idkeg')->first();

    return $p;
  }

  private function sebagai(object $p)
  {
    return $this->actingAs(User::findOrFail($p->user_id))->withSession(['wewenang' => 'review', 'periode' => $p->tahun]);
  }

  public function test_dashboard_daftar_unit_dan_periode_render(): void
  {
    $p = $this->penetapan();

    $this->sebagai($p)->get('/reviewer')->assertOk()->assertSee('Unit yang perlu ditinjau')->assertSee($p->tahun . '/');
    $this->sebagai($p)->get('/reviewer/unit')->assertOk()->assertSee('Daftar Unit yang di-Klinik')->assertSee($p->kode);
    $this->sebagai($p)->get('/reviewer/periode')->assertOk();
  }

  public function test_halaman_review_kegiatan_render(): void
  {
    $p = $this->penetapan();
    $mak = DB::table('ueu_tbl_mak1')->where('idmak1', $p->keg->idmak1)->value('kodemak1');

    $this->sebagai($p)->get("/reviewer/kegiatan/{$p->kode}?mak={$mak}")
      ->assertOk()
      ->assertSee('Rekap berbasis fungsi')
      ->assertSee(e($p->keg->uraiankeg), false)
      ->assertSee(route('reviewer.kegiatan.review', ['kode' => $p->kode, 'idkeg' => $p->keg->idkeg]), false)
      ->assertSee(route('reviewer.rinci.index', ['kode' => $p->kode, 'idkeg' => $p->keg->idkeg]), false);
  }

  public function test_rincian_dan_form_reviewer_render(): void
  {
    $p = $this->penetapan();
    $id = $p->keg->idkeg;

    $this->sebagai($p)->get("/reviewer/kegiatan/{$p->kode}/rinci/{$id}")
      ->assertOk()
      ->assertSee(route('reviewer.rinci.create', ['kode' => $p->kode, 'idkeg' => $id, 'jenis' => 'belanja']), false)
      ->assertSee(route('reviewer.kegiatan.index', ['kode' => $p->kode]), false);
    $this->sebagai($p)->get("/reviewer/kegiatan/{$p->kode}/rinci/{$id}/tambah/belanja")
      ->assertOk()->assertSee(route('reviewer.rinci.store', ['kode' => $p->kode, 'idkeg' => $id, 'jenis' => 'belanja']), false);
    $this->sebagai($p)->get("/reviewer/kegiatan/{$p->kode}/tambah")
      ->assertOk()->assertSee(route('reviewer.kegiatan.store', ['kode' => $p->kode]), false);
  }

  public function test_revisi_tanpa_komentar_ditolak(): void
  {
    $p = $this->penetapan();
    $sebelum = DB::table('tbl_review_kegiatan')->count();

    $this->sebagai($p)->postJson("/reviewer/kegiatan/{$p->kode}/review/{$p->keg->idkeg}", ['status' => 'ditolak', 'keterangan' => ''])
      ->assertStatus(422)->assertJsonValidationErrors('keterangan');
    $this->sebagai($p)->postJson("/reviewer/kegiatan/{$p->kode}/review/{$p->keg->idkeg}", ['status' => 'lain'])
      ->assertStatus(422)->assertJsonValidationErrors('status');

    $this->assertSame($sebelum, DB::table('tbl_review_kegiatan')->count());
  }

  public function test_rinci_tidak_valid_ditolak_tanpa_menyimpan(): void
  {
    $p = $this->penetapan();
    $sebelum = DB::table('ueu_tbl_rinci')->where('idkeg', $p->keg->idkeg)->count();

    $this->sebagai($p)->post("/reviewer/kegiatan/{$p->kode}/rinci/{$p->keg->idkeg}/belanja", [])
      ->assertSessionHasErrors(['idrek6', 'uraianrinci', 'hargasat', 'qty1']);

    $this->assertSame($sebelum, DB::table('ueu_tbl_rinci')->where('idkeg', $p->keg->idkeg)->count());
  }

  public function test_unit_yang_tidak_ditetapkan_dialihkan(): void
  {
    $p = $this->penetapan();
    $lain = DB::table('ueu_tbl_unit')
      ->whereNotIn('id_unit', DB::table('tbl_review')->where('user_id', $p->user_id)->where('tahun', $p->tahun)->select('unit_id'))
      ->value('kode');

    $this->sebagai($p)->get("/reviewer/kegiatan/{$lain}")->assertRedirect(route('reviewer.unit.index'));
    $this->sebagai($p)->postJson("/reviewer/kegiatan/{$lain}/review/{$p->keg->idkeg}", ['status' => 'disetujui'])->assertRedirect();
  }

  public function test_kegiatan_unit_lain_tidak_bisa_dinilai(): void
  {
    $p = $this->penetapan();
    $kegLain = DB::table('ueu_tbl_kegiatan')->where('idunit', '!=', $p->id_unit)->value('idkeg');

    $this->sebagai($p)->postJson("/reviewer/kegiatan/{$p->kode}/review/{$kegLain}", ['status' => 'disetujui'])->assertNotFound();
  }

  public function test_role_lain_tidak_boleh_masuk_area_reviewer(): void
  {
    $lain = User::where('wewenang', 'kasir')->firstOrFail();

    $this->actingAs($lain)->withSession(['wewenang' => 'kasir'])->get('/reviewer/unit')->assertStatus(401);
  }

  /** Regresi: halaman rincian & form kegiatan unit tetap memakai route unit.* setelah dipakai ulang reviewer. */
  public function test_halaman_unit_tetap_memakai_route_unit(): void
  {
    $p = $this->penetapan();
    $akun = User::where('id_unit', $p->id_unit)->where('wewenang', 'unit')->first();
    if (!$akun) {
      $this->markTestSkipped('Unit tidak punya akun.');
    }

    $this->actingAs($akun)->withSession(['wewenang' => 'unit', 'periode' => $p->tahun])
      ->get("/unit/rinci/{$p->keg->idkeg}")
      ->assertOk()
      ->assertSee(route('unit.kegiatan.index', ['mak' => DB::table('ueu_tbl_mak1')->where('idmak1', $p->keg->idmak1)->value('kodemak1')]), false)
      ->assertDontSee('/reviewer/', false);
  }
}
