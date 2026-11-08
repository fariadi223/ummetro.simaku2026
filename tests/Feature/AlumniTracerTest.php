<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlumniTracerTest extends TestCase
{
    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    /** NPM yang punya respon. */
    private function npmTerisi(): string
    {
        return DB::table('respon')->orderBy('id')->value('alumni_npm');
    }

    /** NPM yang belum mengisi. */
    private function npmKosong(): string
    {
        return DB::table('alumni')
            ->whereNotExists(function ($q) {
                $q->from('respon')->whereColumn('respon.alumni_npm', 'alumni.npm_mhs');
            })->value('npm_mhs');
    }

    public function test_halaman_isian_render(): void
    {
        $npm = $this->npmTerisi();
        $alumni = DB::table('alumni')->where('npm_mhs', $npm)->first();

        // Nama periode diambil dari respon yang benar-benar ada, bukan
        // dipatok, karena periode berisi bisa lebih dari satu.
        $periode = DB::table('respon as r')
            ->join('periode_tracer as p', 'p.id', '=', 'r.periode_tracer_id')
            ->where('r.alumni_npm', $npm)
            ->orderByDesc('p.tahun_lulusan')
            ->value('p.nama');

        $this->actingAs($this->admin())
            ->get('/admin/alumni/' . $npm . '/tracer')
            ->assertOk()
            ->assertSee($alumni->nama_mhs)
            ->assertSee($npm)
            ->assertSee('Isian tracer study')
            ->assertSee(e($periode), false);
    }

    public function test_alumni_belum_mengisi_diberi_pesan(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/alumni/' . $this->npmKosong() . '/tracer')
            ->assertOk()
            ->assertSee('belum mengisi');
    }

    public function test_npm_tidak_ada_404(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/alumni/0000000000/tracer')
            ->assertStatus(404);
    }

    public function test_prodi_ditolak(): void
    {
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->actingAs($prodi)
            ->get('/admin/alumni/' . $this->npmTerisi() . '/tracer')
            ->assertStatus(401);
    }

    public function test_tamu_dialihkan(): void
    {
        $this->get('/admin/alumni/' . $this->npmTerisi() . '/tracer')
            ->assertRedirect('/operator/login');
    }

    public function test_ringkasan_ditampilkan_dengan_label_indonesia(): void
    {
        $npm = DB::table('respon')->where('status_saat_ini', 'bekerja')
            ->whereNotNull('kesesuaian_bidang')->value('alumni_npm');

        $html = $this->actingAs($this->admin())
            ->get('/admin/alumni/' . $npm . '/tracer')->assertOk()->getContent();

        // bukan 'bekerja' mentah, tapi label yang dibaca manusia
        $this->assertStringContainsString('Status saat ini', $html);
        $this->assertStringContainsString('Bekerja', $html);
        $this->assertStringContainsString('Kesesuaian bidang', $html);
        $this->assertStringNotContainsString('belum_memungkinkan_bekerja', $html);
    }

    public function test_semua_jawaban_muncul_sebagai_label_opsi(): void
    {
        $respon = DB::table('respon')->orderBy('id')->first();

        $html = $this->actingAs($this->admin())
            ->get('/admin/alumni/' . $respon->alumni_npm . '/tracer')->assertOk()->getContent();

        // Ambil jawaban ber-opsi dan pastikan labelnya tercetak
        $opsi = DB::table('respon_jawaban as j')
            ->join('pertanyaan_opsi as o', 'o.id', '=', 'j.opsi_id')
            ->where('j.respon_id', $respon->id)
            ->limit(12)
            ->get(['o.label']);

        $this->assertGreaterThan(0, $opsi->count());

        foreach ($opsi as $o) {
            // potong supaya aman dari pembungkusan/entity
            $potong = e(mb_substr($o->label, 0, 28));
            $this->assertStringContainsString($potong, $html, 'label opsi tidak tampil: ' . $o->label);
        }
    }

    public function test_pertanyaan_tanpa_jawaban_ditandai(): void
    {
        $respon = DB::table('respon')->orderBy('id')->first();

        $kuesionerId = DB::table('kuesioner')
            ->where('periode_tracer_id', $respon->periode_tracer_id)->value('id');
        $totalSoal = DB::table('pertanyaan')
            ->whereIn('kuesioner_bagian_id',
                DB::table('kuesioner_bagian')->where('kuesioner_id', $kuesionerId)->pluck('id'))
            ->count();
        $dijawab = DB::table('respon_jawaban')
            ->where('respon_id', $respon->id)->distinct()->count('pertanyaan_id');

        $html = $this->actingAs($this->admin())
            ->get('/admin/alumni/' . $respon->alumni_npm . '/tracer')->assertOk()->getContent();

        $this->assertLessThan($totalSoal, $dijawab, 'pilih responden yang tidak menjawab semuanya');
        $this->assertStringContainsString('Tidak dijawab', $html);
        $this->assertSame(
            $totalSoal - $dijawab,
            substr_count($html, 'Tidak dijawab'),
            'jumlah penanda "Tidak dijawab" tidak sesuai'
        );
    }

    public function test_tombol_reset_responden_tampil_hanya_bila_ada_isian(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/alumni/' . $this->npmTerisi() . '/tracer')
            ->assertOk()
            ->assertSee('btn-reset-responden', false)
            ->assertSee('js/config/admin/pages-alumni-tracer.js', false);

        // Alumni tanpa isian tidak punya yang bisa direset.
        $this->actingAs($admin)
            ->get('/admin/alumni/' . $this->npmKosong() . '/tracer')
            ->assertOk()
            ->assertDontSee('btn-reset-responden', false);
    }

    public function test_reset_menghapus_respon_dan_jawabannya(): void
    {
        DB::beginTransaction();
        try {
            $respon = DB::table('respon')->orderBy('id')->first();

            $this->assertGreaterThan(
                0,
                DB::table('respon_jawaban')->where('respon_id', $respon->id)->count(),
                'pilih responden yang punya jawaban'
            );

            $this->actingAs($this->admin())
                ->deleteJson('/admin/alumni/' . $respon->alumni_npm . '/tracer/' . $respon->periode_tracer_id)
                ->assertOk()
                ->assertJsonPath('messages', fn ($m) => str_contains($m, 'bisa mengisi ulang'));

            $this->assertDatabaseMissing('respon', ['id' => $respon->id]);
            $this->assertSame(0, DB::table('respon_jawaban')->where('respon_id', $respon->id)->count());

            // Halaman kembali ke keadaan "belum mengisi" bila itu satu-satunya
            // periode yang pernah diisi.
            $sisa = DB::table('respon')->where('alumni_npm', $respon->alumni_npm)->count();

            if ($sisa === 0) {
                $this->actingAs($this->admin())
                    ->get('/admin/alumni/' . $respon->alumni_npm . '/tracer')
                    ->assertOk()
                    ->assertSee('belum mengisi');
            }

            $this->assertDatabaseHas('activity_logs', ['aksi' => 'reset_tracer']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_reset_periode_tanpa_isian_ditolak(): void
    {
        // Alumni yang belum mengisi: periodenya ada, isiannya tidak.
        $periode = DB::table('periode_tracer')->orderBy('id')->value('id');

        $this->actingAs($this->admin())
            ->deleteJson('/admin/alumni/' . $this->npmKosong() . '/tracer/' . $periode)
            ->assertStatus(422)
            ->assertJsonPath('messages', fn ($m) => str_contains($m, 'tidak ada yang perlu direset'));
    }

    public function test_reset_npm_tidak_ada_404(): void
    {
        $periode = DB::table('periode_tracer')->orderBy('id')->value('id');

        $this->actingAs($this->admin())
            ->deleteJson('/admin/alumni/0000000000/tracer/' . $periode)
            ->assertStatus(404);
    }

    public function test_reset_hanya_untuk_admin(): void
    {
        $respon = DB::table('respon')->orderBy('id')->first();
        $url = '/admin/alumni/' . $respon->alumni_npm . '/tracer/' . $respon->periode_tracer_id;

        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->actingAs($prodi)->deleteJson($url)->assertStatus(401);
        $this->deleteJson($url)->assertStatus(401);

        // Datanya harus tetap utuh.
        $this->assertDatabaseHas('respon', ['id' => $respon->id]);
    }

    public function test_tombol_lihat_isian_ada_di_kolom_aksi(): void
    {
        // Tombolnya dirender oleh DataTables di sisi klien, jadi yang
        // diperiksa adalah berkas JS-nya, bukan HTML halaman.
        $js = file_get_contents(public_path('js/config/admin/pages-alumni-tables.js'));

        $this->assertStringContainsString("/tracer", $js);
        $this->assertStringContainsString('ti-file-text', $js, 'ikon tombol isian hilang');
        $this->assertStringContainsString('Belum mengisi tracer', $js, 'keadaan belum mengisi tidak ditangani');
        $this->assertStringContainsString('full.sudah_isi', $js, 'tombol harus bergantung pada sudah_isi');

        // Halaman tetap harus memuat berkas JS itu.
        $this->actingAs($this->admin())
            ->get('/admin/alumni')
            ->assertOk()
            ->assertSee('js/config/admin/pages-alumni-tables.js', false);
    }
}
