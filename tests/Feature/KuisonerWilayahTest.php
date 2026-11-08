<?php

namespace Tests\Feature;

use App\Models\Alumni;
use App\Models\Kuesioner;
use App\Models\KuesionerBagian;
use App\Models\Negara;
use App\Models\PeriodeTracer;
use App\Models\Pertanyaan;
use App\Models\Roles;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pertanyaan lokasi kerja: provinsi (f5a1) dan kabupaten/kota (f5a2) yang
 * pilihannya menyusut mengikuti provinsi terpilih.
 *
 * Kode wilayahnya memakai kode Dikti supaya jawaban lama di tcr_kuisoner
 * terpetakan apa adanya, jadi kode-kode di tes ini ditulis eksplisit.
 */
class KuisonerWilayahTest extends TestCase
{
    private const LAMPUNG = '120000';
    private const KOTA_METRO = '126100';
    private const BALI = '220000';
    private const MALAYSIA = 'MY';

    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    /**
     * Baris berkas acuan di database/data, sebagai pembanding isi tabel.
     *
     * Jumlahnya tidak ditulis sebagai angka di test: daftar wilayah dan
     * negara memang bertambah dari waktu ke waktu (pemekaran daerah, negara
     * baru). Yang perlu dijaga adalah tabelnya memuat seluruh baris acuan,
     * bukan angka tertentu.
     *
     * @return array<int, array>
     */
    private function acuan(string $berkas): array
    {
        return json_decode(file_get_contents(database_path('data/' . $berkas)), true);
    }

    /** Harus dipanggil di dalam transaksi uji. */
    private function siapkanPeriode(): PeriodeTracer
    {
        $kuesioner = Kuesioner::has('bagian')->firstOrFail();

        DB::table('periode_tracer')->update(['is_active' => false]);
        DB::table('periode_tracer')->where('id', $kuesioner->periode_tracer_id)->update(['is_active' => true]);

        return PeriodeTracer::findOrFail($kuesioner->periode_tracer_id);
    }

    /** Alumni yang belum punya respon di periode ini, beserta akunnya. */
    private function alumniBaru(PeriodeTracer $periode): array
    {
        $npm = DB::table('alumni')
            ->whereNotIn('npm_mhs', function ($q) use ($periode) {
                $q->select('alumni_npm')->from('respon')->where('periode_tracer_id', $periode->id);
            })
            ->value('npm_mhs');

        $this->assertNotNull($npm, 'tidak ada alumni tanpa respon di periode ini');

        $alumni = Alumni::findOrFail($npm);

        $user = User::create([
            'name' => trim((string) $alumni->nama_mhs) ?: 'Alumni ' . $npm,
            'username' => $npm,
            'email' => $npm . '@alumni.ummetro.ac.id',
            'mahasiswa_npm' => $npm,
            'password' => bcrypt('rahasia-uji'),
            'is_active' => true,
        ]);

        $user->roles()->attach(Roles::where('name', Roles::ALUMNI)->firstOrFail()->id);

        return [$alumni, $user];
    }

    private function soal(PeriodeTracer $periode, string $kode): Pertanyaan
    {
        $kuesioner = Kuesioner::where('periode_tracer_id', $periode->id)->firstOrFail();

        return Pertanyaan::whereIn(
            'kuesioner_bagian_id',
            DB::table('kuesioner_bagian')->where('kuesioner_id', $kuesioner->id)->select('id')
        )->where('kode', $kode)->firstOrFail();
    }

    /** Nilai tersimpan satu pertanyaan pada satu respon. */
    private function jawaban(int $responId, int $pertanyaanId): array
    {
        return DB::table('respon_jawaban')
            ->where('respon_id', $responId)
            ->where('pertanyaan_id', $pertanyaanId)
            ->pluck('nilai_teks')
            ->all();
    }

    private function responDari(Alumni $alumni, PeriodeTracer $periode)
    {
        return DB::table('respon')
            ->where('alumni_npm', $alumni->npm_mhs)
            ->where('periode_tracer_id', $periode->id)
            ->first();
    }

    // ------------------------------------------------------------- struktur

    public function test_f5a2_tertaut_ke_f5a1_sebagai_penyaring(): void
    {
        $periode = PeriodeTracer::whereHas('kuesioner.bagian')->firstOrFail();

        $provinsi = $this->soal($periode, 'f5a1');
        $kabupaten = $this->soal($periode, 'f5a2');

        $this->assertSame('provinsi', $provinsi->tipe);
        $this->assertSame('kabupaten', $kabupaten->tipe);
        $this->assertSame(
            $provinsi->id,
            $kabupaten->wilayah_induk_pertanyaan_id,
            'f5a2 harus disaring oleh f5a1'
        );

        // Keduanya hanya muncul bila status "bekerja", sama seperti form lama.
        $this->assertSame(['1'], $provinsi->nilaiPemicu());
        $this->assertSame(['1'], $kabupaten->nilaiPemicu());
    }

    public function test_acuan_wilayah_lengkap_dan_berjenjang(): void
    {
        $jmlProvinsi = count(array_filter($this->acuan('wilayah.json'), fn ($r) => $r[3] === 1));

        $this->assertCount($jmlProvinsi, Wilayah::provinsi());
        $this->assertSame('Lampung', Wilayah::provinsi()[self::LAMPUNG]);

        $kabupaten = Wilayah::kabupaten(self::LAMPUNG);

        $this->assertArrayHasKey(self::KOTA_METRO, $kabupaten);
        $this->assertSame('Kota Metro', $kabupaten[self::KOTA_METRO]);

        // Kabupaten provinsi lain tidak boleh ikut.
        $this->assertArrayNotHasKey(self::KOTA_METRO, Wilayah::kabupaten(self::BALI));

        // Provinsi belum dipilih berarti belum ada pilihan apa pun.
        $this->assertSame([], Wilayah::kabupaten(null));
    }

    // ------------------------------------------------------------ tampilan

    public function test_provinsi_dirender_lengkap_dan_kabupaten_menunggu_provinsi(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $html = $this->actingAs($user)->get('/alumni/kuesioner')->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '~name="f5a1"[^>]*data-wilayah="provinsi"~',
                $html
            );
            $this->assertStringContainsString('value="' . self::LAMPUNG . '"', $html);

            // Kabupaten menyebut pertanyaan penyaringnya, dan daftar seluruh
            // kabupaten dikirim sekali sebagai JSON untuk dipakai JS.
            $this->assertMatchesRegularExpression(
                '~name="f5a2"[^>]*data-wilayah="kabupaten"[^>]*data-wilayah-induk="f5a1"~',
                $html
            );
            $this->assertSame(1, substr_count($html, 'id="kp-wilayah-kabupaten"'));

            // Belum ada provinsi terpilih: f5a2 belum menawarkan pilihan.
            $this->assertStringNotContainsString('value="' . self::KOTA_METRO . '"', $html);
        } finally {
            DB::rollBack();
        }
    }

    public function test_kabupaten_dimuat_ulang_sesuai_provinsi_tersimpan(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '1',
                'f5a1' => self::LAMPUNG,
                'f5a2' => self::KOTA_METRO,
            ])->assertRedirect('/alumni/kuesioner');

            $html = $this->actingAs($user)->get('/alumni/kuesioner')->assertOk()->getContent();

            // Kabupaten Lampung ditawarkan, dan Kota Metro sudah terpilih.
            $this->assertMatchesRegularExpression(
                '~<option value="' . self::KOTA_METRO . '"\s+selected~',
                $html
            );

            // Kabupaten provinsi lain tetap tidak dicetak server.
            $kabupatenBali = array_key_first(Wilayah::kabupaten(self::BALI));
            $this->assertStringNotContainsString('<option value="' . $kabupatenBali . '"', $html);
        } finally {
            DB::rollBack();
        }
    }

    // ---------------------------------------------------------- penyimpanan

    public function test_lokasi_tersimpan_sebagai_kode_wilayah(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '1',
                'f5a1' => self::LAMPUNG,
                'f5a2' => self::KOTA_METRO,
            ])->assertRedirect('/alumni/kuesioner');

            $respon = $this->responDari($alumni, $periode);
            $this->assertNotNull($respon);

            $this->assertSame(
                [self::LAMPUNG],
                $this->jawaban($respon->id, $this->soal($periode, 'f5a1')->id)
            );
            $this->assertSame(
                [self::KOTA_METRO],
                $this->jawaban($respon->id, $this->soal($periode, 'f5a2')->id)
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_kabupaten_di_luar_provinsi_terpilih_dibuang(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            // Provinsi Bali, tapi kabupaten dari Lampung: pasangan mustahil.
            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '1',
                'f5a1' => self::BALI,
                'f5a2' => self::KOTA_METRO,
            ])->assertRedirect('/alumni/kuesioner');

            $respon = $this->responDari($alumni, $periode);

            // Provinsinya sah, jadi tetap tersimpan.
            $this->assertSame(
                [self::BALI],
                $this->jawaban($respon->id, $this->soal($periode, 'f5a1')->id)
            );

            // Kabupatennya dibuang, bukan disimpan sebagai teks bebas.
            $this->assertSame(
                [],
                $this->jawaban($respon->id, $this->soal($periode, 'f5a2')->id),
                'kabupaten yang bukan milik provinsi terpilih tidak boleh tersimpan'
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_kode_wilayah_karangan_ditolak(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '1',
                'f5a1' => '999999',
                'f5a2' => 'bukan-kode',
            ])->assertRedirect('/alumni/kuesioner');

            $respon = $this->responDari($alumni, $periode);

            $this->assertSame([], $this->jawaban($respon->id, $this->soal($periode, 'f5a1')->id));
            $this->assertSame([], $this->jawaban($respon->id, $this->soal($periode, 'f5a2')->id));
        } finally {
            DB::rollBack();
        }
    }

    public function test_lokasi_tidak_tersimpan_bila_status_bukan_bekerja(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            // f8 = 4 (melanjutkan pendidikan): pertanyaan lokasi tersembunyi,
            // jadi kiriman apa pun untuknya diabaikan.
            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '4',
                'f5a1' => self::LAMPUNG,
                'f5a2' => self::KOTA_METRO,
            ])->assertRedirect('/alumni/kuesioner');

            $respon = $this->responDari($alumni, $periode);

            $this->assertSame([], $this->jawaban($respon->id, $this->soal($periode, 'f5a1')->id));
            $this->assertSame([], $this->jawaban($respon->id, $this->soal($periode, 'f5a2')->id));
        } finally {
            DB::rollBack();
        }
    }

    // -------------------------------------------------------------- negara

    /**
     * f5a0 tidak dibuat importir (form lama tidak punya field negara), jadi
     * ditambahkan di dalam transaksi uji lewat perintah yang sama dengan yang
     * dipakai di produksi.
     */
    private function tambahNegara(PeriodeTracer $periode): Pertanyaan
    {
        $kuesioner = Kuesioner::where('periode_tracer_id', $periode->id)->firstOrFail();

        $this->artisan('tracer:tambah-pertanyaan-negara', ['--kuesioner' => $kuesioner->id])
            ->assertSuccessful();

        return $this->soal($periode, 'f5a0');
    }

    public function test_acuan_negara_lengkap(): void
    {
        $daftar = Negara::daftar();

        $this->assertCount(count($this->acuan('negara.json')), $daftar);
        $this->assertSame('Indonesia', $daftar[Negara::INDONESIA]);
        $this->assertSame('Malaysia', $daftar[self::MALAYSIA]);

        // Apostrof dari sumber SQL harus utuh, bukan ter-escape ganda.
        $this->assertSame("Cote D'Ivoire", $daftar['CI']);
    }

    public function test_negara_disisipkan_sebelum_provinsi_dengan_aturan_tampil_sama(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();

            $urutanProvinsiAwal = $this->soal($periode, 'f5a1')->urutan;

            $negara = $this->tambahNegara($periode);

            $this->assertSame('negara', $negara->tipe);
            $this->assertSame($urutanProvinsiAwal, $negara->urutan, 'f5a0 harus menempati posisi f5a1');

            // Provinsi dan kabupaten bergeser, urutannya tetap f5a0 -> f5a1 -> f5a2.
            $provinsi = $this->soal($periode, 'f5a1');
            $kabupaten = $this->soal($periode, 'f5a2');

            $this->assertSame($urutanProvinsiAwal + 1, $provinsi->urutan);
            $this->assertSame($urutanProvinsiAwal + 2, $kabupaten->urutan);

            // Satu blok lokasi: ketiganya tampil pada syarat yang sama.
            $this->assertSame($provinsi->tampil_jika_pertanyaan_id, $negara->tampil_jika_pertanyaan_id);
            $this->assertSame($provinsi->nilaiPemicu(), $negara->nilaiPemicu());
        } finally {
            DB::rollBack();
        }
    }

    public function test_negara_dirender_sebagai_pilihan(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            $this->tambahNegara($periode);
            [, $user] = $this->alumniBaru($periode);

            $html = $this->actingAs($user)->get('/alumni/kuesioner')->assertOk()->getContent();

            $this->assertMatchesRegularExpression('~name="f5a0"~', $html);
            $this->assertStringContainsString('>Indonesia</option>', $html);
            $this->assertStringContainsString('>Malaysia</option>', $html);
        } finally {
            DB::rollBack();
        }
    }

    public function test_negara_tersimpan_sebagai_kode_iso(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            $negara = $this->tambahNegara($periode);
            [$alumni, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '1',
                'f5a0' => self::MALAYSIA,
            ])->assertRedirect('/alumni/kuesioner');

            $respon = $this->responDari($alumni, $periode);

            $this->assertSame([self::MALAYSIA], $this->jawaban($respon->id, $negara->id));
        } finally {
            DB::rollBack();
        }
    }

    public function test_kode_negara_karangan_ditolak(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            $negara = $this->tambahNegara($periode);
            [$alumni, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '1',
                'f5a0' => 'ZZ',
            ])->assertRedirect('/alumni/kuesioner');

            $respon = $this->responDari($alumni, $periode);

            $this->assertSame([], $this->jawaban($respon->id, $negara->id));
        } finally {
            DB::rollBack();
        }
    }

    public function test_negara_hanya_ditambahkan_ke_kuesioner_yang_diminta(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();

            // Periode kedua dibuat dengan menyalin yang pertama, supaya ada
            // dua kuesioner lengkap untuk dibandingkan.
            $idSalinan = $this->actingAs($this->admin())
                ->postJson('/admin/kuisoner/periode/' . $periode->id . '/salin', [
                    'nama' => 'Salinan Uji Negara',
                    'tahun_lulusan' => 2029,
                    'tanggal_mulai' => '2029-01-01',
                    'tanggal_selesai' => '2029-12-31',
                ])
                ->assertStatus(201)
                ->json('data.id');

            $salinan = PeriodeTracer::findOrFail($idSalinan);

            // f5a0 hanya diminta untuk salinannya.
            $this->tambahNegara($salinan);

            $this->assertTrue(
                $this->adaF5a0($salinan),
                'kuesioner yang diminta harus punya f5a0'
            );
            $this->assertFalse(
                $this->adaF5a0($periode),
                'kuesioner periode lain tidak boleh ikut berubah'
            );
        } finally {
            DB::rollBack();
        }
    }

    private function adaF5a0(PeriodeTracer $periode): bool
    {
        $kuesioner = Kuesioner::where('periode_tracer_id', $periode->id)->firstOrFail();

        return Pertanyaan::whereHas('bagian', fn ($q) => $q->where('kuesioner_id', $kuesioner->id))
            ->where('kode', 'f5a0')
            ->exists();
    }

    // ----------------------------------------------------------- sisi admin

    public function test_kabupaten_tanpa_penyaring_ditolak(): void
    {
        $bagian = KuesionerBagian::firstOrFail();

        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/bagian/' . $bagian->id . '/pertanyaan', [
                'kode' => 'ujiwil1',
                'teks' => 'Kabupaten tanpa penyaring',
                'tipe' => 'kabupaten',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('wilayah_induk_pertanyaan_id');
    }

    public function test_penyaring_harus_pertanyaan_provinsi(): void
    {
        $periode = PeriodeTracer::whereHas('kuesioner.bagian')->firstOrFail();
        $bukanProvinsi = $this->soal($periode, 'f8');

        $this->actingAs($this->admin())
            ->postJson('/admin/kuisoner/bagian/' . $bukanProvinsi->kuesioner_bagian_id . '/pertanyaan', [
                'kode' => 'ujiwil2',
                'teks' => 'Kabupaten dengan penyaring keliru',
                'tipe' => 'kabupaten',
                'wilayah_induk_pertanyaan_id' => $bukanProvinsi->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('wilayah_induk_pertanyaan_id');
    }

    public function test_penyaring_dibuang_untuk_tipe_selain_kabupaten(): void
    {
        DB::beginTransaction();
        try {
            $periode = PeriodeTracer::whereHas('kuesioner.bagian')->firstOrFail();
            $provinsi = $this->soal($periode, 'f5a1');

            $id = $this->actingAs($this->admin())
                ->postJson('/admin/kuisoner/bagian/' . $provinsi->kuesioner_bagian_id . '/pertanyaan', [
                    'kode' => 'ujiwil3',
                    'teks' => 'Pertanyaan teks biasa',
                    'tipe' => 'text',
                    // Dikirim tapi tidak berlaku untuk tipe text.
                    'wilayah_induk_pertanyaan_id' => $provinsi->id,
                ])
                ->assertStatus(201)
                ->json('data.id');

            $this->assertNull(Pertanyaan::findOrFail($id)->wilayah_induk_pertanyaan_id);
        } finally {
            DB::rollBack();
        }
    }
}
