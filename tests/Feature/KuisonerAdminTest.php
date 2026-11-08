<?php

namespace Tests\Feature;

use App\Models\Kuesioner;
use App\Models\KuesionerBagian;
use App\Models\PeriodeTracer;
use App\Models\Pertanyaan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KuisonerAdminTest extends TestCase
{
    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function kuesioner(): Kuesioner
    {
        return Kuesioner::firstOrFail();
    }

    public function test_halaman_render(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/kuisoner')
            ->assertOk()
            ->assertSee('Kelola Kuisoner')
            ->assertSee('Tracer Study 2021')
            ->assertSee('modal-pertanyaan', false)
            ->assertSee('window.kuisonerConfig', false);
    }

    public function test_prodi_ditolak(): void
    {
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();
        $this->actingAs($prodi)->get('/admin/kuisoner')->assertStatus(401);
    }

    public function test_tamu_dialihkan_ke_login_operator(): void
    {
        $this->get('/admin/kuisoner')->assertRedirect('/operator/login');
    }

    public function test_struktur_mengembalikan_bagian_pertanyaan_opsi(): void
    {
        $res = $this->actingAs($this->admin())
            ->getJson('/admin/kuisoner/' . $this->kuesioner()->id . '/struktur')
            ->assertOk()
            ->assertJsonStructure(['kuesioner', 'bagian', 'pemicu'])
            ->json();

        // Jumlah bagian dibaca dari basis data; kuesioner boleh bertambah
        // bagian tanpa membuat test struktur ini salah.
        $this->assertCount(
            \App\Models\KuesionerBagian::where('kuesioner_id', $this->kuesioner()->id)->count(),
            $res['bagian']
        );

        $total = array_sum(array_map(fn ($b) => count($b['pertanyaan']), $res['bagian']));
        $this->assertSame(
            \App\Models\Pertanyaan::whereHas(
                'bagian',
                fn ($q) => $q->where('kuesioner_id', $this->kuesioner()->id)
            )->count(),
            $total
        );

        // f8 punya 5 opsi dan menjadi ringkasan status_saat_ini
        $f8 = null;
        foreach ($res['bagian'] as $b) {
            foreach ($b['pertanyaan'] as $p) {
                if ($p['kode'] === 'f8') $f8 = $p;
            }
        }
        $this->assertNotNull($f8);
        $this->assertCount(5, $f8['opsi']);
        $this->assertSame('status_saat_ini', $f8['ringkasan_field']);
    }

    public function test_crud_bagian(): void
    {
        DB::beginTransaction();
        try {
            $k = $this->kuesioner();

            $id = $this->actingAs($this->admin())
                ->postJson('/admin/kuisoner/' . $k->id . '/bagian', ['judul' => 'Bagian Uji'])
                ->assertStatus(201)
                ->json('data.id');

            $this->actingAs($this->admin())
                ->putJson('/admin/kuisoner/bagian/' . $id, ['judul' => 'Bagian Uji Diubah'])
                ->assertOk();

            $this->assertSame('Bagian Uji Diubah', KuesionerBagian::find($id)->judul);

            $this->actingAs($this->admin())
                ->deleteJson('/admin/kuisoner/bagian/' . $id)
                ->assertOk();

            $this->assertNull(KuesionerBagian::find($id));
        } finally {
            DB::rollBack();
        }
    }

    public function test_crud_pertanyaan_dengan_opsi(): void
    {
        DB::beginTransaction();
        try {
            $bagian = KuesionerBagian::firstOrFail();

            $id = $this->actingAs($this->admin())
                ->postJson('/admin/kuisoner/bagian/' . $bagian->id . '/pertanyaan', [
                    'kode' => 'uji01',
                    'teks' => 'Pertanyaan uji otomatis',
                    'tipe' => 'radio',
                    'wajib' => 1,
                    'opsi' => [
                        ['nilai' => '1', 'label' => 'Ya'],
                        ['nilai' => '2', 'label' => 'Tidak'],
                    ],
                ])
                ->assertStatus(201)
                ->assertJsonPath('data.kode', 'uji01')
                ->assertJsonCount(2, 'data.opsi')
                ->json('data.id');

            // ubah: tambah opsi ketiga
            $this->actingAs($this->admin())
                ->putJson('/admin/kuisoner/pertanyaan/' . $id, [
                    'kode' => 'uji01',
                    'teks' => 'Pertanyaan uji diubah',
                    'tipe' => 'radio',
                    'opsi' => [
                        ['nilai' => '1', 'label' => 'Ya'],
                        ['nilai' => '2', 'label' => 'Tidak'],
                        ['nilai' => '3', 'label' => 'Ragu'],
                    ],
                ])
                ->assertOk()
                ->assertJsonCount(3, 'data.opsi');

            $this->actingAs($this->admin())
                ->deleteJson('/admin/kuisoner/pertanyaan/' . $id)
                ->assertOk();

            $this->assertNull(Pertanyaan::find($id));
        } finally {
            DB::rollBack();
        }
    }

    public function test_kode_ganda_dalam_satu_bagian_ditolak(): void
    {
        $f8 = Pertanyaan::where('kode', 'f8')->firstOrFail();

        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/bagian/' . $f8->kuesioner_bagian_id . '/pertanyaan', [
                'kode' => 'f8',
                'teks' => 'Duplikat',
                'tipe' => 'text',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('kode');
    }

    public function test_tipe_beropsi_wajib_punya_opsi(): void
    {
        $bagian = KuesionerBagian::firstOrFail();

        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/bagian/' . $bagian->id . '/pertanyaan', [
                'kode' => 'uji-noopsi',
                'teks' => 'Radio tanpa opsi',
                'tipe' => 'radio',
                'opsi' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('opsi');
    }

    public function test_tipe_tidak_dikenal_ditolak(): void
    {
        $bagian = KuesionerBagian::firstOrFail();

        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/bagian/' . $bagian->id . '/pertanyaan', [
                'kode' => 'uji-tipe',
                'teks' => 'Tipe ngawur',
                'tipe' => 'ngawur',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipe');
    }

    public function test_opsi_pemicu_harus_milik_pertanyaan_pemicu(): void
    {
        $bagian = KuesionerBagian::firstOrFail();
        $f8 = Pertanyaan::where('kode', 'f8')->firstOrFail();
        $f14 = Pertanyaan::where('kode', 'f14')->firstOrFail();
        $opsiF14 = $f14->opsi()->firstOrFail();

        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/bagian/' . $bagian->id . '/pertanyaan', [
                'kode' => 'uji-cabang',
                'teks' => 'Percabangan salah',
                'tipe' => 'text',
                'tampil_jika_pertanyaan_id' => $f8->id,
                'tampil_jika_opsi_ids' => [$opsiF14->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tampil_jika_opsi_ids');
    }

    public function test_percabangan_boleh_beberapa_opsi_pemicu(): void
    {
        DB::beginTransaction();
        try {
            $bagian = KuesionerBagian::firstOrFail();
            $f8 = Pertanyaan::where('kode', 'f8')->firstOrFail();
            $opsi = $f8->opsi()->orderBy('urutan')->take(3)->pluck('id')->all();

            $this->assertGreaterThan(2, count($opsi), 'f8 perlu minimal 3 opsi untuk tes ini');

            $res = $this->actingAs($this->admin())
                ->postJson('/admin/kuisoner/bagian/' . $bagian->id . '/pertanyaan', [
                    'kode' => 'uji-cabang-banyak',
                    'teks' => 'Dipicu beberapa opsi',
                    'tipe' => 'text',
                    'tampil_jika_pertanyaan_id' => $f8->id,
                    'tampil_jika_opsi_ids' => $opsi,
                ])
                ->assertStatus(201)
                ->json();

            $this->assertEqualsCanonicalizing($opsi, $res['data']['tampil_jika_opsi_ids']);

            $tersimpan = DB::table('pertanyaan_tampil_opsi')
                ->where('pertanyaan_id', $res['data']['id'])
                ->pluck('pertanyaan_opsi_id')
                ->all();

            $this->assertEqualsCanonicalizing($opsi, $tersimpan);

            // Mengubah daftar opsi pemicu harus mengganti, bukan menumpuk.
            $this->actingAs($this->admin())
                ->putJson('/admin/kuisoner/pertanyaan/' . $res['data']['id'], [
                    'kode' => 'uji-cabang-banyak',
                    'teks' => 'Dipicu beberapa opsi',
                    'tipe' => 'text',
                    'tampil_jika_pertanyaan_id' => $f8->id,
                    'tampil_jika_opsi_ids' => [$opsi[0]],
                ])
                ->assertOk();

            $this->assertSame(
                [$opsi[0]],
                DB::table('pertanyaan_tampil_opsi')
                    ->where('pertanyaan_id', $res['data']['id'])
                    ->pluck('pertanyaan_opsi_id')
                    ->all()
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_pemicu_tanpa_opsi_ditolak(): void
    {
        $bagian = KuesionerBagian::firstOrFail();
        $f8 = Pertanyaan::where('kode', 'f8')->firstOrFail();

        // Pemicu tanpa opsi membuat pertanyaan tidak pernah tampil.
        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/bagian/' . $bagian->id . '/pertanyaan', [
                'kode' => 'uji-cabang-kosong',
                'teks' => 'Pemicu tanpa opsi',
                'tipe' => 'text',
                'tampil_jika_pertanyaan_id' => $f8->id,
                'tampil_jika_opsi_ids' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tampil_jika_opsi_ids');
    }

    public function test_pertanyaan_pemicu_tidak_boleh_dihapus(): void
    {
        $f8 = Pertanyaan::where('kode', 'f8')->firstOrFail();

        $this->actingAs($this->admin())
            ->deleteJson('/admin/kuisoner/pertanyaan/' . $f8->id)
            ->assertStatus(422);

        $this->assertNotNull(Pertanyaan::find($f8->id));
    }

    public function test_opsi_yang_dipakai_pemicu_tidak_boleh_hilang(): void
    {
        $f8 = Pertanyaan::where('kode', 'f8')->firstOrFail();

        // Buang opsi "1" yang dipakai f502/f504/... sebagai pemicu
        $this->actingAs($this->admin())
            ->putJson('/admin/kuisoner/pertanyaan/' . $f8->id, [
                'kode' => 'f8',
                'teks' => $f8->teks,
                'tipe' => 'radio',
                'opsi' => [['nilai' => '9', 'label' => 'Entah']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('opsi');

        $this->assertSame(5, $f8->opsi()->count());
    }

    public function test_urut_bagian_dan_pertanyaan(): void
    {
        DB::beginTransaction();
        try {
            $k = $this->kuesioner();
            $ids = $k->bagian()->pluck('id')->all();

            $this->actingAs($this->admin())
                ->putJson('/admin/kuisoner/' . $k->id . '/bagian/urut', ['urutan' => array_reverse($ids)])
                ->assertOk();

            $urutBaru = KuesionerBagian::whereIn('id', $ids)->orderBy('urutan')->pluck('id')->all();
            $this->assertSame(array_reverse($ids), $urutBaru);
        } finally {
            DB::rollBack();
        }
    }

    public function test_periode_baru_membuat_kuesioner(): void
    {
        DB::beginTransaction();
        try {
            $res = $this->actingAs($this->admin())
                ->postJson('/admin/kuisoner/periode', [
                    'nama' => 'Periode Uji Otomatis',
                    'tahun_lulusan' => 2026,
                    'tanggal_mulai' => '2026-01-01',
                    'tanggal_selesai' => '2026-06-30',
                ])
                ->assertStatus(201)
                ->json();

            $this->assertCount(1, $res['data']['kuesioner']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_periode_tanggal_terbalik_ditolak(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/periode', [
                'nama' => 'Periode Tanggal Salah',
                'tahun_lulusan' => 2026,
                'tanggal_mulai' => '2026-06-30',
                'tanggal_selesai' => '2026-01-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tanggal_selesai');
    }

    public function test_aktifkan_periode_menonaktifkan_yang_lain(): void
    {
        DB::beginTransaction();
        try {
            $p = PeriodeTracer::firstOrFail();

            $this->actingAs($this->admin())
                ->putJson('/admin/kuisoner/periode/' . $p->id . '/aktifkan')
                ->assertOk();

            $this->assertTrue($p->fresh()->is_active);
            $this->assertSame(1, PeriodeTracer::where('is_active', true)->count());
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Regresi: mengaktifkan periode yang sudah aktif pernah justru
     * menonaktifkannya, karena model dianggap tidak kotor sehingga
     * UPDATE-nya tidak pernah dikirim.
     */
    public function test_aktifkan_periode_yang_sudah_aktif_tetap_aktif(): void
    {
        DB::beginTransaction();
        try {
            $p = PeriodeTracer::firstOrFail();
            PeriodeTracer::whereKey($p->id)->update(['is_active' => true]);

            $this->actingAs($this->admin())
                ->putJson('/admin/kuisoner/periode/' . $p->id . '/aktifkan')
                ->assertOk();

            $this->assertTrue($p->fresh()->is_active, 'periode justru jadi non-aktif');
            $this->assertSame(1, PeriodeTracer::where('is_active', true)->count());
        } finally {
            DB::rollBack();
        }
    }
}
