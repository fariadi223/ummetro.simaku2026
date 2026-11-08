<?php

namespace Tests\Feature;

use App\Exports\TracerStudyExport;
use App\Models\Alumni;
use App\Models\Kuesioner;
use App\Models\KuesionerBagian;
use App\Models\PeriodeTracer;
use App\Models\Pertanyaan;
use App\Models\PertanyaanOpsi;
use App\Models\Respon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Berkas unggahan pelaporan tracer study.
 *
 * Yang diuji adalah susunan kolom dan pemetaan nilainya, bukan penulisan
 * berkas xlsx-nya: headings() dan map() dipanggil langsung supaya kesalahan
 * urutan kolom langsung terlihat tanpa menulis berkas lebih dulu.
 */
class TracerStudyExportTest extends TestCase
{
    /** Susunan kolom yang ditetapkan sistem pelaporan, lengkap dan berurutan. */
    private const KOLOM = [
        'Kode PT', 'Kode Prodi', 'NIM/Nomor Mhs', 'Nama Mhs', 'Nomor HP Mhs',
        'Email Mhs', 'Tahun Lulus Keluar', 'NIK', 'NPWP',
        'F8', 'F502', 'F505', 'F5A1', 'F5A2', 'F1101', 'F1102', 'F5B', 'F5C', 'F5D',
        'F18A', 'F18B', 'F18C', 'F18D', 'F1201', 'F1202', 'F14', 'F15',
        'F1761', 'F1763', 'F1765', 'F1767', 'F1769', 'F1771', 'F1773',
        'F1762', 'F1764', 'F1766', 'F1768', 'F1770', 'F1772', 'F1774',
        'F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27',
        'F301', 'F302', 'F303',
        'F401', 'F402', 'F403', 'F404', 'F405', 'F406', 'F407', 'F408',
        'F409', 'F410', 'F411', 'F412', 'F413', 'F414', 'F415', 'F416',
        'F6', 'F7', 'F7A', 'F1001', 'F1002',
        'F1601', 'F1602', 'F1603', 'F1604', 'F1605', 'F1606', 'F1607',
        'F1608', 'F1609', 'F1610', 'F1611', 'F1612', 'F1613', 'F1614',
    ];

    /**
     * Susunan kolom format 2026, sesuai berkas unggahan
     * "Pelaporan Tracer Study: Periode Pelaporan Tracer Study 2026".
     *
     * Bedanya dengan susunan lama: urutannya lain, ada F5A0, F5E, kompetensi
     * F1775-F1782, dan metode pembelajaran F28-F37; sedangkan F410, F412,
     * dan F1611 tidak dilaporkan lagi.
     */
    private const KOLOM_2026 = [
        'Kode PT', 'Kode Prodi', 'NIM/Nomor Mhs', 'Nama Mhs', 'Nomor HP Mhs',
        'Email Mhs', 'Tahun Lulus Keluar', 'NIK', 'NPWP',
        'F8', 'F502', 'F505', 'F5C', 'F18A', 'F18B', 'F18C', 'F18D', 'F5B',
        'F5A0', 'F5A1', 'F5A2', 'F5E', 'F1101', 'F1102', 'F5D', 'F14', 'F15',
        'F301', 'F302', 'F303',
        'F401', 'F402', 'F403', 'F404', 'F405', 'F406', 'F407', 'F408', 'F409',
        'F411', 'F413', 'F414', 'F415', 'F416',
        'F6', 'F7', 'F7A', 'F1001', 'F1002',
        'F1601', 'F1602', 'F1603', 'F1604', 'F1605', 'F1606', 'F1607', 'F1608',
        'F1609', 'F1610', 'F1612', 'F1613', 'F1614',
        'F1201', 'F1202',
        'F1761', 'F1763', 'F1765', 'F1767', 'F1769', 'F1771', 'F1773',
        'F1775', 'F1777', 'F1779', 'F1781',
        'F1762', 'F1764', 'F1766', 'F1768', 'F1770', 'F1772', 'F1774',
        'F1776', 'F1778', 'F1780', 'F1782',
        'F21', 'F22', 'F23', 'F24', 'F25', 'F26', 'F27', 'F28', 'F29',
        'F30', 'F31', 'F32', 'F33', 'F34', 'F35', 'F36', 'F37',
    ];

    /** Periode dengan susunan lama (lulusan sebelum 2025). */
    private function periode(): PeriodeTracer
    {
        return PeriodeTracer::whereHas('kuesioner.bagian')
            ->where('tahun_lulusan', '<', 2025)
            ->firstOrFail();
    }

    /**
     * Periode dengan susunan 2026, dibuat sendiri oleh test.
     *
     * Database test tidak selalu punya periode pelaporan lulusan 2025 ke
     * atas, sedangkan yang menentukan susunan kolom hanya tahun lulusannya.
     *
     * Harus dipanggil di dalam transaksi uji.
     */
    private function periode2026(): PeriodeTracer
    {
        return PeriodeTracer::create([
            'nama' => 'Tracer Study 2026',
            'tahun_lulusan' => 2025,
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->addMonth()->toDateString(),
            'is_active' => false,
        ]);
    }

    /**
     * Kuesioner seadanya untuk periode 2026: hanya pertanyaan yang dipakai
     * tes pemetaan, bukan seluruh instrumen.
     *
     * @param array<string, string> $soal kode => tipe
     * @param array<string, array<int, string>> $opsi kode => nilai opsi
     */
    private function kuesioner2026(PeriodeTracer $periode, array $soal, array $opsi = []): void
    {
        $kuesioner = Kuesioner::create([
            'periode_tracer_id' => $periode->id,
            'judul' => 'Kuesioner uji 2026',
        ]);

        $bagian = KuesionerBagian::create([
            'kuesioner_id' => $kuesioner->id,
            'judul' => 'Bagian uji',
            'urutan' => 1,
        ]);

        $urutan = 0;

        foreach ($soal as $kode => $tipe) {
            $pertanyaan = Pertanyaan::create([
                'kuesioner_bagian_id' => $bagian->id,
                'kode' => $kode,
                'teks' => 'Pertanyaan uji ' . strtoupper($kode),
                'tipe' => $tipe,
                'urutan' => ++$urutan,
            ]);

            foreach ($opsi[$kode] ?? [] as $i => $nilai) {
                PertanyaanOpsi::create([
                    'pertanyaan_id' => $pertanyaan->id,
                    'label' => 'Opsi ' . $nilai,
                    'nilai' => $nilai,
                    'urutan' => $i + 1,
                ]);
            }
        }
    }

    private function soal(PeriodeTracer $periode, string $kode): Pertanyaan
    {
        $kuesioner = Kuesioner::where('periode_tracer_id', $periode->id)->firstOrFail();

        return Pertanyaan::whereIn(
            'kuesioner_bagian_id',
            DB::table('kuesioner_bagian')->where('kuesioner_id', $kuesioner->id)->select('id')
        )->where('kode', $kode)->firstOrFail();
    }

    /**
     * Responden baru dengan jawaban yang ditentukan sendiri.
     *
     * @param array<string, array<int, string>|string> $jawaban kode => nilai opsi / teks
     */
    private function responden(PeriodeTracer $periode, array $jawaban): Respon
    {
        $npm = DB::table('alumni')
            ->whereNotIn('npm_mhs', function ($q) use ($periode) {
                $q->select('alumni_npm')->from('respon')->where('periode_tracer_id', $periode->id);
            })
            ->value('npm_mhs');

        $this->assertNotNull($npm, 'tidak ada alumni tanpa respon di periode ini');

        $respon = Respon::create([
            'alumni_npm' => $npm,
            'periode_tracer_id' => $periode->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        foreach ($jawaban as $kode => $nilai) {
            $soal = $this->soal($periode, $kode);

            foreach ((array) $nilai as $v) {
                $opsi = $soal->opsi->firstWhere('nilai', (string) $v);

                DB::table('respon_jawaban')->insert([
                    'respon_id' => $respon->id,
                    'pertanyaan_id' => $soal->id,
                    'opsi_id' => $opsi?->id,
                    'nilai_teks' => $opsi ? null : (string) $v,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $respon;
    }

    /** @return array<string, mixed> kolom => nilai */
    private function barisUntuk(TracerStudyExport $export, Respon $respon): array
    {
        return array_combine($export->headings(), $export->map($respon->load('alumni')));
    }

    public function test_susunan_kolom_tepat_dan_berurutan(): void
    {
        $export = new TracerStudyExport($this->periode());

        $this->assertCount(86, self::KOLOM, 'daftar acuan di test harus 86 kolom');
        $this->assertSame(self::KOLOM, $export->headings());
    }

    public function test_susunan_kolom_2026_tepat_dan_berurutan(): void
    {
        DB::beginTransaction();
        try {
            $export = new TracerStudyExport($this->periode2026());

            $this->assertCount(103, self::KOLOM_2026, 'daftar acuan di test harus 103 kolom');
            $this->assertSame(self::KOLOM_2026, $export->headings());

            // Kolom yang tidak ada lagi di susunan ini tidak boleh ikut tercetak.
            foreach (['F410', 'F412', 'F1611', 'F504'] as $kode) {
                $this->assertNotContains($kode, $export->headings(), $kode . ' tidak dilaporkan lagi');
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Susunan 2026 memakai baris 1 untuk judul periode pelaporan, jadi nama
     * kolom mulai di baris 2; susunan lama tetap mulai di baris 1 supaya
     * berkas periode lama tidak berubah bentuk.
     */
    public function test_baris_judul_hanya_pada_susunan_2026(): void
    {
        DB::beginTransaction();
        try {
            $this->assertSame('A2', (new TracerStudyExport($this->periode2026()))->startCell());
            $this->assertSame('A1', (new TracerStudyExport($this->periode()))->startCell());
        } finally {
            DB::rollBack();
        }
    }

    public function test_jawaban_khas_2026_terpetakan(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->periode2026();

            $this->kuesioner2026(
                $periode,
                [
                    'f8' => 'radio', 'f5a0' => 'negara', 'f28' => 'radio',
                    'f1775' => 'scale', 'f1776' => 'scale', 'f16' => 'checkbox',
                ],
                [
                    'f8' => ['1', '2'],
                    'f28' => ['1', '2', '3'],
                    'f1775' => ['1', '2', '3', '4', '5'],
                    'f1776' => ['1', '2', '3', '4', '5'],
                    'f16' => ['f1611', 'f1612', 'f1613'],
                ]
            );

            $respon = $this->responden($periode, [
                'f8' => '1',
                'f5a0' => 'ID',
                'f28' => '3',
                'f1775' => '4',
                'f1776' => '5',
                // F1611 dibuang dari laporan, tapi F1612/F1613 tetap bernilai
                // nomor urut opsinya.
                'f16' => ['f1611', 'f1612'],
            ]);

            $baris = $this->barisUntuk(new TracerStudyExport($periode), $respon);

            $this->assertSame('ID', $baris['F5A0']);
            $this->assertSame('3', $baris['F28']);
            $this->assertSame('4', $baris['F1775']);
            $this->assertSame('5', $baris['F1776']);
            $this->assertSame('12', $baris['F1612']);
            $this->assertNull($baris['F1613']);

            // Kode yang pertanyaannya belum ada di kuesioner tetap jadi kolom
            // kosong, bukan hilang dari berkas.
            $this->assertArrayHasKey('F5E', $baris);
            $this->assertNull($baris['F5E']);
            $this->assertNull($baris['F29']);
            $this->assertNull($baris['F37']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_identitas_dan_jawaban_terpetakan(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->periode();

            $respon = $this->responden($periode, [
                'telpomsmh' => '081234567890',
                'emailmsmh' => 'uji@contoh.ac.id',
                'npwpmsmh' => '099887766',
                'f8' => '1',
                'f5a1' => '120000',
                'f5a2' => '126100',
                'f14' => '2',
            ]);

            $baris = $this->barisUntuk(new TracerStudyExport($periode), $respon);
            $alumni = Alumni::findOrFail($respon->alumni_npm);

            $this->assertSame(config('tracer.kode_pt'), $baris['Kode PT']);
            $this->assertSame($alumni->npm_mhs, $baris['NIM/Nomor Mhs']);
            $this->assertSame(trim((string) $alumni->kode_prg), $baris['Kode Prodi']);
            $this->assertSame('081234567890', $baris['Nomor HP Mhs']);
            $this->assertSame('uji@contoh.ac.id', $baris['Email Mhs']);
            $this->assertSame('099887766', $baris['NPWP']);
            $this->assertSame('1', $baris['F8']);
            $this->assertSame('2', $baris['F14']);

            // Lokasi kerja dilaporkan sebagai kode wilayah, bukan namanya.
            $this->assertSame('120000', $baris['F5A1']);
            $this->assertSame('126100', $baris['F5A2']);

            // Pertanyaan yang tidak dijawab harus kosong, bukan nol.
            $this->assertNull($baris['F502']);
            $this->assertNull($baris['F1614']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_nomor_hp_dan_email_jatuh_ke_data_akademik_bila_tak_dijawab(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->periode();
            $respon = $this->responden($periode, ['f8' => '1']);

            Alumni::whereKey($respon->alumni_npm)->update([
                'tlpn' => '0899000111',
                'email' => 'akademik@contoh.ac.id',
            ]);

            $baris = $this->barisUntuk(new TracerStudyExport($periode), $respon->fresh());

            $this->assertSame('0899000111', $baris['Nomor HP Mhs']);
            $this->assertSame('akademik@contoh.ac.id', $baris['Email Mhs']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * Grup checkbox dipecah kembali menjadi satu kolom per opsi, dengan nilai
     * seperti yang dikirim formulir lama: f4xx selalu 1, f16xx sesuai urutan.
     */
    public function test_grup_centang_dipecah_dengan_nilai_formulir_lama(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->periode();

            $respon = $this->responden($periode, [
                'f4' => ['f401', 'f403'],
                'f16' => ['f1602', 'f1613'],
            ]);

            $baris = $this->barisUntuk(new TracerStudyExport($periode), $respon);

            // f4: yang dicentang bernilai 1, yang tidak kosong.
            $this->assertSame('1', $baris['F401']);
            $this->assertSame('1', $baris['F403']);
            $this->assertNull($baris['F402']);
            $this->assertNull($baris['F415']);

            // f16: nilainya mengikuti urutan opsinya, bukan selalu 1.
            $this->assertSame('2', $baris['F1602']);
            $this->assertSame('13', $baris['F1613']);
            $this->assertNull($baris['F1601']);
            $this->assertNull($baris['F1612']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_hanya_jawaban_terkirim_yang_dilaporkan(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->periode();
            $respon = $this->responden($periode, ['f8' => '1']);

            $export = new TracerStudyExport($periode);
            $this->assertTrue($export->query()->whereKey($respon->id)->exists());

            // Draf belum tentu lengkap, jadi tidak boleh ikut dilaporkan.
            $respon->update(['status' => 'draft']);

            $export = new TracerStudyExport($periode);
            $this->assertFalse($export->query()->whereKey($respon->id)->exists());
        } finally {
            DB::rollBack();
        }
    }

    public function test_pemilih_periode_menawarkan_semua_periode(): void
    {
        DB::beginTransaction();
        try {
            $berisi = $this->periode();

            // Periode kedua sengaja dibuat tanpa jawaban, untuk memastikan
            // periode kosong tetap terlihat tapi tidak bisa dipilih.
            $kosong = PeriodeTracer::create([
                'nama' => 'Periode Tanpa Jawaban',
                'tahun_lulusan' => 2030,
                'tanggal_mulai' => '2030-01-01',
                'tanggal_selesai' => '2030-12-31',
                'is_active' => false,
            ]);

            $html = $this->actingAs(\App\Models\User::where('username', 'admin')->firstOrFail())
                ->get('/admin/alumni')
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('id="periode-laporan"', $html);

            // Periode kosong: tampil, bertanda "belum ada jawaban", dimatikan.
            $this->assertMatchesRegularExpression(
                '~<option value="' . $kosong->id . '"[^>]*\bdisabled\b~',
                $html,
                'periode tanpa jawaban harus dimatikan'
            );
            $this->assertStringContainsString('belum ada jawaban', $html);

            // Periode berisi jawaban: terpilih sejak awal dan tidak dimatikan.
            $this->assertMatchesRegularExpression(
                '~<option value="' . $berisi->id . '"[^>]*\bselected\b~',
                $html,
                'periode berisi jawaban harus terpilih di awal'
            );
            $this->assertDoesNotMatchRegularExpression(
                '~<option value="' . $berisi->id . '"[^>]*\bdisabled\b~',
                $html
            );

            // Tombol unduhnya aktif karena ada periode yang bisa dilaporkan.
            $this->assertDoesNotMatchRegularExpression(
                '~id="btn-export-tracer"[^>]*\bdisabled\b~',
                $html
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_periode_wajib_dan_harus_ada(): void
    {
        $admin = \App\Models\User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.alumni.export-tracer'))
            ->assertSessionHasErrors('periode');

        $this->actingAs($admin)
            ->get(route('admin.alumni.export-tracer', ['periode' => 999999]))
            ->assertSessionHasErrors('periode');
    }

    /**
     * Bentuk berkas susunan 2026: baris 1 judul periode pelaporan (digabung
     * selebar tabel), baris 2 nama kolom, data mulai baris 3.
     */
    public function test_berkas_2026_menaruh_judul_di_baris_pertama(): void
    {
        DB::beginTransaction();
        try {
            $periode = $this->periode2026();
            $this->kuesioner2026($periode, ['f8' => 'radio'], ['f8' => ['1', '2']]);
            $respon = $this->responden($periode, ['f8' => '1']);

            $berkas = 'uji-tracer-2026-' . $periode->id . '.xlsx';
            $disk = Storage::disk('local');

            try {
                (new TracerStudyExport($periode))->store($berkas, 'local');

                $lembar = IOFactory::load($disk->path($berkas))->getActiveSheet();

                $akhir = $lembar->getHighestColumn();

                $this->assertSame(
                    'Pelaporan Tracer Study: Periode Pelaporan Tracer Study 2026 (Lulusan 2025)',
                    $lembar->getCell('A1')->getValue()
                );
                $this->assertContains('A1:' . $akhir . '1', array_keys($lembar->getMergeCells()));

                $this->assertSame('Kode PT', $lembar->getCell('A2')->getValue());
                $this->assertSame('F37', $lembar->getCell($akhir . '2')->getValue());

                // Data responden mulai di baris 3.
                $this->assertSame(
                    $respon->alumni_npm,
                    (string) $lembar->getCell('C3')->getValue()
                );
            } finally {
                $disk->delete($berkas);
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_unduhan_berhasil_untuk_periode_berisi_jawaban(): void
    {
        $periode = PeriodeTracer::query()
            ->whereHas('respon', fn ($q) => $q->where('status', 'submitted'))
            ->firstOrFail();

        $this->actingAs(\App\Models\User::where('username', 'admin')->firstOrFail())
            ->get(route('admin.alumni.export-tracer', ['periode' => $periode->id]))
            ->assertOk()
            ->assertDownload();
    }
}
