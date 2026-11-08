<?php

namespace Tests\Feature;

use App\Models\Alumni;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlumniAdminTest extends TestCase
{
    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    public function test_halaman_alumni_render(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/alumni')
            ->assertOk()
            ->assertSee('Data Alumni')
            ->assertSee('dt-table-alumni', false)
            ->assertSee('modal-alumni-edit', false);
    }

    public function test_prodi_tidak_boleh_akses(): void
    {
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->actingAs($prodi)->get('/admin/alumni')->assertStatus(401);
    }

    public function test_tamu_dialihkan_ke_login_operator(): void
    {
        $this->get('/admin/alumni')->assertRedirect('/operator/login');
    }

    public function test_endpoint_data_mengembalikan_format_datatables(): void
    {
        $res = $this->actingAs($this->admin())
            ->getJson('/admin/alumni/data?draw=3&start=0&length=5')
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        $this->assertSame(3, $res['draw']);
        // Dibaca dari basis data: jumlah alumni berubah setiap kali sinkron
        // SIMPRODI berjalan, jadi tidak boleh dipatok angka.
        $this->assertSame(Alumni::count(), $res['recordsTotal']);
        $this->assertCount(5, $res['data']);
        $this->assertArrayHasKey('npm_mhs', $res['data'][0]);
        $this->assertArrayHasKey('sudah_isi', $res['data'][0]);
    }

    public function test_search_menyaring_data(): void
    {
        $alumni = Alumni::first();

        $res = $this->actingAs($this->admin())
            ->getJson('/admin/alumni/data?draw=1&start=0&length=10&search[value]=' . $alumni->npm_mhs)
            ->assertOk()
            ->json();

        $this->assertSame(1, $res['recordsFiltered']);
        $this->assertSame($alumni->npm_mhs, $res['data'][0]['npm_mhs']);
    }

    public function test_filter_tahun_lulus(): void
    {
        $tahun = Alumni::whereNotNull('thnlulus')->value('thnlulus');
        $harap = Alumni::where('thnlulus', $tahun)->count();

        $res = $this->actingAs($this->admin())
            ->getJson('/admin/alumni/data?draw=1&start=0&length=1&tahun_lulus=' . $tahun)
            ->assertOk()
            ->json();

        $this->assertSame($harap, $res['recordsFiltered']);
        $this->assertGreaterThan(0, $harap);
    }

    public function test_filter_prodi(): void
    {
        $kode = Alumni::whereNotNull('kode_prg')->value('kode_prg');
        $harap = Alumni::where('kode_prg', $kode)->count();

        $res = $this->actingAs($this->admin())
            ->getJson('/admin/alumni/data?draw=1&start=0&length=1&prodi=' . $kode)
            ->assertOk()
            ->json();

        $this->assertSame($harap, $res['recordsFiltered']);
    }

    public function test_filter_status_sudah_dan_belum_mengisi(): void
    {
        $belum = $this->actingAs($this->admin())
            ->getJson('/admin/alumni/data?draw=1&start=0&length=1&status=belum')->json();
        $sudah = $this->actingAs($this->admin())
            ->getJson('/admin/alumni/data?draw=1&start=0&length=1&status=sudah')->json();

        // Dihitung dari data, bukan angka tetap, karena respon bisa diimpor.
        $totalAlumni = Alumni::count();
        $sudahIsi = DB::table('alumni')
            ->whereExists(function ($q) {
                $q->from('respon')
                    ->whereColumn('respon.alumni_npm', 'alumni.npm_mhs')
                    ->where('respon.status', 'submitted');
            })->count();

        $this->assertSame($sudahIsi, $sudah['recordsFiltered']);
        $this->assertSame($totalAlumni - $sudahIsi, $belum['recordsFiltered']);
        $this->assertSame($totalAlumni, $sudah['recordsFiltered'] + $belum['recordsFiltered']);
    }

    public function test_show_mengembalikan_detail(): void
    {
        $alumni = Alumni::first();

        $this->actingAs($this->admin())
            ->getJson('/admin/alumni/' . $alumni->npm_mhs)
            ->assertOk()
            ->assertJsonPath('data.npm_mhs', $alumni->npm_mhs)
            ->assertJsonPath('data.nama_mhs', $alumni->nama_mhs);
    }

    public function test_show_npm_tidak_ada_404(): void
    {
        $this->actingAs($this->admin())
            ->getJson('/admin/alumni/0000000000')
            ->assertStatus(404);
    }

    public function test_update_menyimpan_perubahan(): void
    {
        $alumni = Alumni::first();
        $asli = ['nama_mhs' => $alumni->nama_mhs, 'email' => $alumni->email, 'tlpn' => $alumni->tlpn];

        try {
            $this->actingAs($this->admin())
                ->putJson('/admin/alumni/' . $alumni->npm_mhs, [
                    'nama_mhs' => 'Nama Uji Otomatis',
                    'email' => 'uji.otomatis@ummetro.ac.id',
                    'tlpn' => '08111111111',
                ])
                ->assertOk()
                ->assertJsonPath('data.nama_mhs', 'Nama Uji Otomatis');

            $this->assertSame(
                'Nama Uji Otomatis',
                DB::table('alumni')->where('npm_mhs', $alumni->npm_mhs)->value('nama_mhs')
            );
        } finally {
            DB::table('alumni')->where('npm_mhs', $alumni->npm_mhs)->update($asli);
        }
    }

    public function test_update_menolak_data_tidak_valid(): void
    {
        $alumni = Alumni::first();

        $this->actingAs($this->admin())
            ->putJson('/admin/alumni/' . $alumni->npm_mhs, [
                'nama_mhs' => '',
                'email' => 'bukan-email',
                'ipk_mhs' => 9,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nama_mhs', 'email', 'ipk_mhs']);
    }

    public function test_hapus_ditolak_bila_punya_data_tracer(): void
    {
        $alumni = Alumni::first();

        DB::beginTransaction();
        try {
            $p = DB::table('periode_tracer')->insertGetId([
                'nama' => 'Uji', 'tahun_lulusan' => 2025,
                'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31', 'is_active' => true,
            ]);
            DB::table('respon')->insert([
                'alumni_npm' => $alumni->npm_mhs, 'periode_tracer_id' => $p, 'status' => 'submitted',
            ]);

            $this->actingAs($this->admin())
                ->deleteJson('/admin/alumni/' . $alumni->npm_mhs)
                ->assertStatus(422);

            $this->assertDatabaseHas('alumni', ['npm_mhs' => $alumni->npm_mhs]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_hapus_berhasil_bila_tanpa_data_tracer(): void
    {
        DB::beginTransaction();
        try {
            DB::table('alumni')->insert(['npm_mhs' => 'UJI-HAPUS-1', 'nama_mhs' => 'Alumni Uji']);

            $this->actingAs($this->admin())
                ->deleteJson('/admin/alumni/UJI-HAPUS-1')
                ->assertOk();

            $this->assertDatabaseMissing('alumni', ['npm_mhs' => 'UJI-HAPUS-1']);
        } finally {
            DB::rollBack();
        }
    }
}
