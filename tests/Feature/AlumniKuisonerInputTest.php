<?php

namespace Tests\Feature;

use App\Models\Alumni;
use App\Models\Kuesioner;
use App\Models\PeriodeTracer;
use App\Models\Pertanyaan;
use App\Models\Roles;
use App\Models\User;
use App\Services\StrukturKuesioner;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlumniKuisonerInputTest extends TestCase
{
    /**
     * Periode yang dipakai pengisian adalah periode aktif, dan itu keputusan
     * admin di produksi. Tes menetapkannya sendiri: periode yang punya
     * kuesioner lengkap.
     *
     * Harus dipanggil di dalam transaksi uji.
     */
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

    private function kodePertanyaan(PeriodeTracer $periode)
    {
        $kuesioner = Kuesioner::where('periode_tracer_id', $periode->id)->firstOrFail();

        return DB::table('pertanyaan')
            ->whereIn('kuesioner_bagian_id', DB::table('kuesioner_bagian')->where('kuesioner_id', $kuesioner->id)->select('id'))
            ->pluck('kode');
    }

    /** Pertanyaan periode ini; kode yang sama juga ada di periode lain. */
    private function soal(PeriodeTracer $periode, string $kode): Pertanyaan
    {
        $kuesioner = Kuesioner::where('periode_tracer_id', $periode->id)->firstOrFail();

        return Pertanyaan::whereIn(
            'kuesioner_bagian_id',
            DB::table('kuesioner_bagian')->where('kuesioner_id', $kuesioner->id)->select('id')
        )->where('kode', $kode)->firstOrFail();
    }

    public function test_halaman_render_dan_semua_pertanyaan_punya_kontrol(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $html = $this->actingAs($user)->get('/alumni/kuesioner')->assertOk()->getContent();

            foreach ($this->kodePertanyaan($periode) as $kode) {
                $this->assertMatchesRegularExpression(
                    '~name="' . preg_quote($kode, '~') . '(\[\])?"~',
                    $html,
                    'pertanyaan tanpa kontrol: ' . $kode
                );
            }

            $this->assertStringContainsString('id="form-kuisoner"', $html);
            $this->assertStringContainsString('value="draf"', $html);
            $this->assertStringContainsString('value="kirim"', $html);
        } finally {
            DB::rollBack();
        }
    }

    public function test_matriks_ab_dipakai_juga_di_halaman_pengisian(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $html = $this->actingAs($user)->get('/alumni/kuesioner')->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<div class="kp-mx-tabel">'));
            $this->assertSame(7, substr_count($html, '<div class="kp-mx-label">'));
            $this->assertSame(14, substr_count($html, '<div class="kp-mx-cell"'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_informasi_khusus_admin_tidak_bocor_ke_alumni(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $html = $this->actingAs($user)->get('/alumni/kuesioner')->assertOk()->getContent();

            // Kode pertanyaan dan aturan percabangan hanya untuk pratinjau admin.
            $this->assertStringNotContainsString('kp-badge-kode', $html);
            $this->assertStringNotContainsString('Tampil bila', $html);
            $this->assertStringNotContainsString('Mode pratinjau', $html);
        } finally {
            DB::rollBack();
        }
    }

    public function test_simpan_draf_menyimpan_jawaban_sesuai_tipenya(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '1',
                'f502' => '6',
                'f505' => '5000000',
                'f14' => '2',
                'f16' => ['f1601', 'f1603'],
                'f01_1' => 'Saran saya',
            ])->assertRedirect('/alumni/kuesioner');

            $respon = DB::table('respon')
                ->where('alumni_npm', $alumni->npm_mhs)
                ->where('periode_tracer_id', $periode->id)
                ->first();

            $this->assertNotNull($respon, 'respon draf tidak terbentuk');
            $this->assertSame('draft', $respon->status);
            $this->assertNull($respon->submitted_at);

            // Kolom ringkasan dipetakan ke nilai berdaftar, bukan angka mentah.
            $this->assertSame('bekerja', $respon->status_saat_ini);
            $this->assertSame('erat', $respon->kesesuaian_bidang);
            $this->assertSame(6, (int) $respon->waktu_tunggu_bulan);
            $this->assertSame(5000000, (int) $respon->pendapatan);

            $nilai = fn (string $kode, string $kolom) => DB::table('respon_jawaban as j')
                ->join('pertanyaan as p', 'p.id', '=', 'j.pertanyaan_id')
                ->where('j.respon_id', $respon->id)->where('p.kode', $kode)
                ->value('j.' . $kolom);

            // number -> nilai_angka, text -> nilai_teks
            $this->assertSame(5000000.0, (float) $nilai('f505', 'nilai_angka'));
            $this->assertSame('Saran saya', $nilai('f01_1', 'nilai_teks'));

            // radio -> opsi_id (bukan teks)
            $this->assertNotNull($nilai('f8', 'opsi_id'));
            $this->assertNull($nilai('f8', 'nilai_teks'));

            // checkbox -> satu baris per pilihan
            $f16 = DB::table('respon_jawaban as j')
                ->join('pertanyaan as p', 'p.id', '=', 'j.pertanyaan_id')
                ->join('pertanyaan_opsi as o', 'o.id', '=', 'j.opsi_id')
                ->where('j.respon_id', $respon->id)->where('p.kode', 'f16')
                ->pluck('o.nilai')->sort()->values()->all();

            $this->assertSame(['f1601', 'f1603'], $f16);
        } finally {
            DB::rollBack();
        }
    }

    public function test_draf_dimuat_kembali_saat_halaman_dibuka_lagi(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '1',
                'f505' => '4250000',
                'f16' => ['f1602'],
                'f01_1' => 'Jawaban teks saya',
            ]);

            $html = $this->actingAs($user)->get('/alumni/kuesioner')->assertOk()->getContent();

            $this->assertStringContainsString('Melanjutkan draf', $html);
            $this->assertStringContainsString('value="Jawaban teks saya"', $html);
            // 4250000.00 harus tampil rapi di input number
            $this->assertStringContainsString('value="4250000"', $html);
            $this->assertMatchesRegularExpression('~name="f8" value="1"[^>]*checked~', $html);
            $this->assertMatchesRegularExpression('~name="f16\[\]" value="f1602"[^>]*checked~', $html);
        } finally {
            DB::rollBack();
        }
    }

    public function test_kirim_tanpa_jawaban_wajib_ditolak(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            $wajib = DB::table('pertanyaan')
                ->whereIn('kuesioner_bagian_id', DB::table('kuesioner_bagian')
                    ->where('kuesioner_id', Kuesioner::where('periode_tracer_id', $periode->id)->value('id'))
                    ->select('id'))
                ->where('wajib', true)
                ->whereNull('tampil_jika_pertanyaan_id')
                ->pluck('kode');

            $this->assertGreaterThan(0, $wajib->count(), 'tidak ada pertanyaan wajib tanpa syarat');

            $this->actingAs($user)
                ->post('/alumni/kuesioner', ['aksi' => 'kirim'])
                ->assertSessionHasErrors($wajib->all());

            // Tidak boleh ada respon terkirim dari percobaan yang gagal.
            $this->assertSame(
                0,
                DB::table('respon')->where('alumni_npm', $alumni->npm_mhs)
                    ->where('periode_tracer_id', $periode->id)->where('status', 'submitted')->count()
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Pengiriman yang ditolak tidak boleh membuang isian alumni: yang sudah
     * dijawab disimpan sebagai draf, jadi tetap ada walau halaman pesan
     * kesalahan dimuat ulang.
     */
    public function test_kirim_gagal_menyimpan_isian_sebagai_draf(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            // f01_1 tidak wajib, jadi pengiriman tetap ditolak oleh pertanyaan
            // wajib lain yang dibiarkan kosong.
            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'kirim',
                'f8' => '1',
                'f505' => '5000000',
                'f16' => ['f1601', 'f1603'],
                'f01_1' => 'Jangan sampai hilang',
            ])->assertSessionHasErrors();

            $respon = DB::table('respon')
                ->where('alumni_npm', $alumni->npm_mhs)
                ->where('periode_tracer_id', $periode->id)
                ->first();

            $this->assertNotNull($respon, 'isian yang gagal dikirim harus tersimpan sebagai draf');
            $this->assertSame('draft', $respon->status);
            $this->assertNull($respon->submitted_at);

            $jawaban = DB::table('respon_jawaban as j')
                ->join('pertanyaan as p', 'p.id', '=', 'j.pertanyaan_id')
                ->leftJoin('pertanyaan_opsi as o', 'o.id', '=', 'j.opsi_id')
                ->where('j.respon_id', $respon->id)
                ->pluck('j.nilai_teks', 'p.kode');

            $this->assertSame('Jangan sampai hilang', $jawaban['f01_1']);

            // Halaman dimuat ulang tanpa isian lama pun tetap menampilkannya.
            $this->actingAs($user)
                ->get('/alumni/kuesioner')
                ->assertOk()
                ->assertSee('Jangan sampai hilang', false)
                ->assertSee('Melanjutkan draf');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Halaman pesan kesalahan menampilkan apa yang baru saja diisi alumni,
     * bukan isi database.
     */
    public function test_isian_terakhir_dipakai_lagi_setelah_validasi_gagal(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            // Draf lama: f01_1 terisi, f8 = 2.
            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => '2',
                'f01_1' => 'Draf lama',
            ])->assertRedirect('/alumni/kuesioner');

            // Kirim gagal, dengan f01_1 diganti dan f8 dikosongkan.
            // from(): validasi yang gagal mengembalikan alumni ke halaman
            // sebelumnya, jadi halaman itu harus disebut.
            $html = $this->actingAs($user)
                ->from('/alumni/kuesioner')
                ->followingRedirects()
                ->post('/alumni/kuesioner', [
                    'aksi' => 'kirim',
                    'f01_1' => 'Isian terbaru',
                ])
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Isian terbaru', $html);
            $this->assertStringNotContainsString('Draf lama', $html);

            // f8 yang dikosongkan tidak boleh kembali tercentang dari draf.
            $opsi = $this->soal($periode, 'f8')->opsi->firstWhere('nilai', '2');
            $this->assertDoesNotMatchRegularExpression(
                '~id="p-' . $opsi->pertanyaan_id . '-' . $opsi->id . '"[^>]*checked~',
                $html
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_pertanyaan_wajib_yang_tersembunyi_tidak_diwajibkan(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            // f1101 & f5b wajib, tapi hanya tampil bila f8 = 1. Dengan f8 = 2,
            // keduanya tidak boleh dituntut.
            $this->actingAs($user)
                ->post('/alumni/kuesioner', ['aksi' => 'kirim', 'f8' => '2'])
                ->assertSessionDoesntHaveErrors(['f1101', 'f5b']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_jawaban_cabang_yang_tidak_tampil_tidak_disimpan(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            // f502 hanya tampil bila f8 = 1. Dikirim bersama f8 = 2, maka
            // nilainya harus diabaikan server meski ada di request.
            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf', 'f8' => '2', 'f502' => '99',
            ]);

            $respon = DB::table('respon')->where('alumni_npm', $alumni->npm_mhs)
                ->where('periode_tracer_id', $periode->id)->first();

            $adaF502 = DB::table('respon_jawaban as j')
                ->join('pertanyaan as p', 'p.id', '=', 'j.pertanyaan_id')
                ->where('j.respon_id', $respon->id)->where('p.kode', 'f502')->count();

            $this->assertSame(0, $adaF502, 'jawaban cabang tersembunyi ikut tersimpan');
            $this->assertNull($respon->waktu_tunggu_bulan, 'ringkasan dari cabang tersembunyi ikut terisi');
        } finally {
            DB::rollBack();
        }
    }

    public function test_cabang_tampil_bila_salah_satu_opsi_pemicu_cocok(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            // f502 semula hanya tampil bila f8 = 1. Dengan f8 = 3 ditambahkan
            // sebagai pemicu kedua, jawaban f8 = 3 harus ikut memunculkannya.
            $f8 = $this->soal($periode, 'f8');
            $f502 = $this->soal($periode, 'f502');
            $opsiTiga = $f8->opsi()->where('nilai', '3')->firstOrFail();

            $this->assertSame($f8->id, $f502->tampil_jika_pertanyaan_id, 'f502 bukan cabang f8');

            $f502->pemicuOpsi()->syncWithoutDetaching([$opsiTiga->id]);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf', 'f8' => '3', 'f502' => '7',
            ]);

            $respon = DB::table('respon')->where('alumni_npm', $alumni->npm_mhs)
                ->where('periode_tracer_id', $periode->id)->first();

            $adaF502 = DB::table('respon_jawaban as j')
                ->join('pertanyaan as p', 'p.id', '=', 'j.pertanyaan_id')
                ->where('j.respon_id', $respon->id)->where('p.kode', 'f502')->count();

            $this->assertSame(1, $adaF502, 'cabang dengan pemicu kedua tidak ikut tersimpan');
            $this->assertSame(7, (int) $respon->waktu_tunggu_bulan);

            // Opsi pemicu pertama tetap berlaku.
            $this->assertEqualsCanonicalizing(['1', '3'], $f502->fresh()->nilaiPemicu());
        } finally {
            DB::rollBack();
        }
    }

    public function test_kirim_lengkap_menandai_submitted(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)
                ->post('/alumni/kuesioner', $this->jawabanLengkap($periode, ['aksi' => 'kirim']))
                ->assertRedirect('/alumni/dashboard');

            $respon = DB::table('respon')->where('alumni_npm', $alumni->npm_mhs)
                ->where('periode_tracer_id', $periode->id)->first();

            $this->assertSame('submitted', $respon->status);
            $this->assertNotNull($respon->submitted_at);
        } finally {
            DB::rollBack();
        }
    }

    public function test_sudah_dikirim_tidak_bisa_diisi_ulang(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', $this->jawabanLengkap($periode, ['aksi' => 'kirim']));

            $this->actingAs($user)->get('/alumni/kuesioner')->assertRedirect('/alumni/dashboard');
            $this->actingAs($user)->post('/alumni/kuesioner', ['aksi' => 'draf'])
                ->assertRedirect('/alumni/dashboard');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Email dan nomor telepon punya bentuk baku. Keduanya pertanyaan bertipe
     * 'text' di database, jadi yang membedakan adalah kodenya.
     */
    public function test_email_dan_telepon_tidak_valid_ditolak_saat_kirim(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $isi = $this->jawabanLengkap($periode, [
                'aksi' => 'kirim',
                'emailmsmh' => 'budi@gmail',
                'telpomsmh' => '12345',
            ]);

            $this->actingAs($user)->post('/alumni/kuesioner', $isi)
                ->assertSessionHasErrors(['emailmsmh', 'telpomsmh']);

            // Pertanyaan lain yang sudah benar tidak boleh ikut disalahkan.
            $this->actingAs($user)->post('/alumni/kuesioner', $isi)
                ->assertSessionDoesntHaveErrors(['f8', 'f01_1']);
        } finally {
            DB::rollBack();
        }
    }

    /** Draf memang boleh setengah jadi, jadi bentuknya belum dituntut. */
    public function test_simpan_draf_tidak_menuntut_email_valid(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'emailmsmh' => 'budi@',
            ])
                ->assertRedirect('/alumni/kuesioner')
                ->assertSessionHasNoErrors();
        } finally {
            DB::rollBack();
        }
    }

    /** Nomor telepon disimpan tanpa pemisah, apa pun cara mengetiknya. */
    public function test_nomor_telepon_dirapikan_sebelum_disimpan(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'telpomsmh' => ' (0812) 3456-7890 ',
            ])->assertRedirect('/alumni/kuesioner');

            $tersimpan = DB::table('respon_jawaban as j')
                ->join('pertanyaan as p', 'p.id', '=', 'j.pertanyaan_id')
                ->join('respon as r', 'r.id', '=', 'j.respon_id')
                ->where('r.alumni_npm', $alumni->npm_mhs)
                ->where('r.periode_tracer_id', $periode->id)
                ->where('p.kode', 'telpomsmh')
                ->value('j.nilai_teks');

            $this->assertSame('081234567890', $tersimpan);
        } finally {
            DB::rollBack();
        }
    }

    /** Kontrolnya ikut menyesuaikan, supaya papan tombol ponsel cocok. */
    public function test_kontrol_email_dan_telepon_memakai_tipe_yang_sesuai(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [, $user] = $this->alumniBaru($periode);

            $html = $this->actingAs($user)->get('/alumni/kuesioner')->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '~<input type="email"[^>]*name="emailmsmh"~',
                $html
            );
            $this->assertMatchesRegularExpression(
                '~<input type="tel"[^>]*name="telpomsmh"~',
                $html
            );

            // Pertanyaan teks biasa tetap input biasa.
            $this->assertMatchesRegularExpression(
                '~<input type="text"[^>]*name="f01_1"~',
                $html
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_menyimpan_dua_kali_tidak_menggandakan_jawaban(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            $isi = ['aksi' => 'draf', 'f8' => '1', 'f16' => ['f1601', 'f1603'], 'f01_1' => 'teks'];

            $this->actingAs($user)->post('/alumni/kuesioner', $isi);
            $pertama = $this->jumlahJawaban($alumni->npm_mhs, $periode->id);

            $this->actingAs($user)->post('/alumni/kuesioner', $isi);
            $kedua = $this->jumlahJawaban($alumni->npm_mhs, $periode->id);

            $this->assertSame($pertama, $kedua, 'jawaban bertambah saat disimpan ulang');
            $this->assertSame(
                1,
                DB::table('respon')->where('alumni_npm', $alumni->npm_mhs)
                    ->where('periode_tracer_id', $periode->id)->count(),
                'respon ganda untuk satu periode'
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_opsi_jawaban_selalu_milik_pertanyaannya(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            $this->actingAs($user)->post('/alumni/kuesioner', $this->jawabanLengkap($periode, ['aksi' => 'draf']));

            $respon = DB::table('respon')->where('alumni_npm', $alumni->npm_mhs)
                ->where('periode_tracer_id', $periode->id)->value('id');

            $salah = DB::table('respon_jawaban as j')
                ->join('pertanyaan_opsi as o', 'o.id', '=', 'j.opsi_id')
                ->where('j.respon_id', $respon)
                ->whereColumn('o.pertanyaan_id', '<>', 'j.pertanyaan_id')
                ->count();

            $this->assertSame(0, $salah);
        } finally {
            DB::rollBack();
        }
    }

    public function test_nilai_opsi_di_luar_daftar_ditolak(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->siapkanPeriode();
            [$alumni, $user] = $this->alumniBaru($periode);

            // f8 hanya punya opsi 1-5; nilai lain tidak boleh tersimpan
            // sebagai teks bebas, dan tidak boleh mengisi kolom ringkasan.
            $this->actingAs($user)->post('/alumni/kuesioner', [
                'aksi' => 'draf',
                'f8' => 'bukan-opsi',
                'f16' => ['f1601', 'palsu'],
            ]);

            $respon = DB::table('respon')->where('alumni_npm', $alumni->npm_mhs)
                ->where('periode_tracer_id', $periode->id)->first();

            $jawaban = fn (string $kode) => DB::table('respon_jawaban as j')
                ->join('pertanyaan as p', 'p.id', '=', 'j.pertanyaan_id')
                ->where('j.respon_id', $respon->id)->where('p.kode', $kode);

            $this->assertSame(0, $jawaban('f8')->count(), 'nilai radio palsu ikut tersimpan');
            $this->assertNull($respon->status_saat_ini);

            // Dari dua nilai checkbox, hanya yang sah yang masuk.
            $this->assertSame(1, $jawaban('f16')->count());
            $this->assertSame(
                0,
                DB::table('respon_jawaban')->where('respon_id', $respon->id)
                    ->whereNull('opsi_id')->whereNotNull('nilai_teks')
                    ->whereIn('pertanyaan_id', DB::table('pertanyaan')->whereIn('kode', ['f8', 'f16'])->select('id'))
                    ->count(),
                'nilai palsu tersimpan sebagai teks'
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_operator_tidak_boleh_mengisi_kuesioner_alumni(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->actingAs($admin)->get('/alumni/kuesioner')->assertStatus(401);
        $this->actingAs($prodi)->get('/alumni/kuesioner')->assertStatus(401);
        $this->actingAs($admin)->post('/alumni/kuesioner', ['aksi' => 'draf'])->assertStatus(401);
    }

    public function test_tamu_dialihkan_ke_login_alumni(): void
    {
        $this->get('/alumni/kuesioner')->assertRedirect('/login');
    }

    /** Isi semua pertanyaan wajib yang tampil, supaya pengiriman lolos. */
    private function jawabanLengkap(PeriodeTracer $periode, array $tambahan): array
    {
        $kuesionerId = Kuesioner::where('periode_tracer_id', $periode->id)->value('id');

        $soal = DB::table('pertanyaan')
            ->whereIn('kuesioner_bagian_id', DB::table('kuesioner_bagian')->where('kuesioner_id', $kuesionerId)->select('id'))
            ->where('wajib', true)
            ->get(['id', 'kode', 'tipe', 'tampil_jika_pertanyaan_id']);

        $isi = [];

        foreach ($soal as $p) {
            // Cabang dibiarkan tersembunyi: cukup isi yang tanpa syarat.
            if ($p->tampil_jika_pertanyaan_id) {
                continue;
            }

            $opsi = DB::table('pertanyaan_opsi')->where('pertanyaan_id', $p->id)->orderBy('urutan')->first();

            if (in_array($p->tipe, ['radio', 'select', 'scale'], true)) {
                // f8 = 2 agar cabang f8=1 tetap tersembunyi.
                $isi[$p->kode] = $p->kode === 'f8' ? '2' : ($opsi->nilai ?? '1');
            } elseif ($p->tipe === 'checkbox') {
                $isi[$p->kode] = [$opsi->nilai ?? '1'];
            } elseif ($p->tipe === 'number') {
                $isi[$p->kode] = '1';
            } elseif ($p->tipe === 'date') {
                $isi[$p->kode] = '2024-01-15';
            } else {
                // Pertanyaan email/telepon punya bentuk baku yang diperiksa
                // saat mengirim, jadi tidak bisa diisi teks sembarang.
                $isi[$p->kode] = match (StrukturKuesioner::jenisIsi($p->kode)) {
                    StrukturKuesioner::ISI_EMAIL => 'alumni.uji@contoh.ac.id',
                    StrukturKuesioner::ISI_TELEPON => '081234567890',
                    default => 'Jawaban uji',
                };
            }
        }

        return array_merge($isi, $tambahan);
    }

    private function jumlahJawaban(string $npm, int $periodeId): int
    {
        $respon = DB::table('respon')->where('alumni_npm', $npm)
            ->where('periode_tracer_id', $periodeId)->value('id');

        return DB::table('respon_jawaban')->where('respon_id', $respon)->count();
    }
}
