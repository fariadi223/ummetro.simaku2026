<?php

namespace Tests\Feature;

use App\Models\PeriodeTracer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportResponTest extends TestCase
{
    private function periode(): PeriodeTracer
    {
        return PeriodeTracer::where('nama', 'Tracer Study 2021')->firstOrFail();
    }

    /**
     * Periode yang benar-benar menyimpan respon hasil impor. Dipakai oleh tes
     * yang memeriksa hasil impor, supaya tidak terpatok pada satu periode
     * tertentu -- periode mana yang terisi adalah keputusan operasional.
     */
    private function periodeBerisi(): int
    {
        $id = DB::table('respon')
            ->groupBy('periode_tracer_id')
            ->orderByRaw('count(*) desc')
            ->value('periode_tracer_id');

        $this->assertNotNull($id, 'belum ada periode yang berisi respon hasil impor');

        return (int) $id;
    }

    private function jalankan(array $opsi = []): string
    {
        $kode = Artisan::call('tracer:import-respon', array_merge([
            '--periode' => 'Tracer Study 2021',
        ], $opsi));

        $this->assertSame(0, $kode, 'command gagal');

        return Artisan::output();
    }

    public function test_command_terdaftar(): void
    {
        $this->assertArrayHasKey('tracer:import-respon', Artisan::all());
    }

    public function test_periode_tidak_ada_gagal(): void
    {
        $kode = Artisan::call('tracer:import-respon', ['--periode' => 'Tidak Ada 9999']);

        $this->assertSame(1, $kode);
        $this->assertStringContainsString('tidak ditemukan', Artisan::output());
    }

    public function test_dry_run_tidak_menyimpan(): void
    {
        DB::beginTransaction();
        try {
            DB::table('respon')->where('periode_tracer_id', $this->periode()->id)->delete();

            $out = $this->jalankan(['--dry-run' => true]);

            $this->assertStringContainsString('tidak ada yang disimpan', $out);
            $this->assertSame(0, DB::table('respon')->where('periode_tracer_id', $this->periode()->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_angka_rekonsiliasi(): void
    {
        $out = $this->jalankan(['--dry-run' => true]);

        // 4890 = 32 tanpa NPM + 2 NPM asing + 82 duplikat + 4774 terpilih
        preg_match('~Baris tcr_kuisoner dibaca\s*\|\s*([\d,]+)~', $out, $a);
        preg_match('~NPM kosong\s*\|\s*([\d,]+)~', $out, $b);
        preg_match('~tidak ada di alumni\s*\|\s*([\d,]+)~', $out, $c);
        preg_match('~Duplikat NPM dibuang\s*\|\s*([\d,]+)~', $out, $d);
        preg_match('~Alumni terpilih\s*\|\s*([\d,]+)~', $out, $e);

        $n = fn ($m) => (int) str_replace(',', '', $m[1]);

        $this->assertSame(4890, $n($a));
        $this->assertSame($n($a), $n($b) + $n($c) + $n($d) + $n($e), 'angka tidak rekonsiliasi');
    }

    /** Jumlah baris tcr_kuisoner untuk sederet tahun lulus. */
    private function barisTahun(array $tahun): int
    {
        return DB::table('tcr_kuisoner')
            ->whereIn(DB::raw('trim(thnlulus)'), $tahun)
            ->count();
    }

    private function angka(string $label, string $out): int
    {
        $this->assertMatchesRegularExpression(
            '~' . preg_quote($label, '~') . '\s*\|\s*[\d,]+~',
            $out,
            'baris statistik tidak ditemukan: ' . $label
        );

        preg_match('~' . preg_quote($label, '~') . '\s*\|\s*([\d,]+)~', $out, $m);

        return (int) str_replace(',', '', $m[1]);
    }

    public function test_thnlulus_menyaring_satu_tahun(): void
    {
        $out = $this->jalankan(['--dry-run' => true, '--thnlulus' => '2021']);

        $this->assertStringContainsString('Tahun lulus    : 2021', $out);
        $this->assertSame(
            $this->barisTahun(['2021']),
            $this->angka('Baris tcr_kuisoner dibaca', $out),
            'baris yang dibaca harus sebanyak baris tahun 2021 saja'
        );
    }

    public function test_thnlulus_menerima_beberapa_tahun(): void
    {
        $out = $this->jalankan(['--dry-run' => true, '--thnlulus' => '2020,2021']);

        $this->assertStringContainsString('Tahun lulus    : 2020, 2021', $out);
        $this->assertSame(
            $this->barisTahun(['2020', '2021']),
            $this->angka('Baris tcr_kuisoner dibaca', $out)
        );
    }

    public function test_thnlulus_dirapikan_dari_spasi_dan_duplikat(): void
    {
        $out = $this->jalankan(['--dry-run' => true, '--thnlulus' => ' 2021 , 2020 ,2021']);

        $this->assertStringContainsString('Tahun lulus    : 2020, 2021', $out);
        $this->assertSame(
            $this->barisTahun(['2020', '2021']),
            $this->angka('Baris tcr_kuisoner dibaca', $out)
        );
    }

    public function test_tanpa_thnlulus_membaca_semua_baris(): void
    {
        $out = $this->jalankan(['--dry-run' => true]);

        $this->assertStringContainsString('semua (tanpa filter)', $out);
        $this->assertSame(
            DB::table('tcr_kuisoner')->count(),
            $this->angka('Baris tcr_kuisoner dibaca', $out)
        );
    }

    public function test_thnlulus_tidak_valid_gagal(): void
    {
        foreach (['abcd', '21', '2021x'] as $buruk) {
            $kode = Artisan::call('tracer:import-respon', [
                '--periode' => 'Tracer Study 2021',
                '--thnlulus' => $buruk,
                '--dry-run' => true,
            ]);

            $this->assertSame(1, $kode, 'harus gagal untuk: ' . $buruk);
            $this->assertStringContainsString('tidak valid', Artisan::output());
        }
    }

    public function test_thnlulus_tanpa_data_gagal_dan_menyebut_pilihan(): void
    {
        $kode = Artisan::call('tracer:import-respon', [
            '--periode' => 'Tracer Study 2021',
            '--thnlulus' => '1899',
            '--dry-run' => true,
        ]);

        $out = Artisan::output();

        $this->assertSame(1, $kode);
        $this->assertStringContainsString('Tidak ada baris tcr_kuisoner untuk tahun lulus 1899', $out);
        $this->assertStringContainsString('Tahun yang tersedia', $out);
    }

    public function test_thnlulus_sebagian_tanpa_data_tetap_jalan(): void
    {
        $out = $this->jalankan(['--dry-run' => true, '--thnlulus' => '1899,2021']);

        $this->assertStringContainsString('Tahun lulus tanpa data, dilewati: 1899', $out);
        $this->assertStringContainsString('Tahun lulus    : 2021', $out);
        $this->assertSame(
            $this->barisTahun(['2021']),
            $this->angka('Baris tcr_kuisoner dibaca', $out)
        );
    }

    public function test_impor_nyata_hanya_menulis_tahun_terpilih(): void
    {
        $pid = $this->periode()->id;

        DB::beginTransaction();
        try {
            // Sengaja TANPA --fresh: tes ini tidak boleh menghapus respon yang
            // sudah ada, karena berjalan di basis data yang sama dengan data
            // operasional. Yang diperiksa adalah baris yang baru ditambahkan.
            $sebelum = DB::table('respon')->where('periode_tracer_id', $pid)->pluck('alumni_npm')->all();

            $this->jalankan(['--thnlulus' => '2021']);

            $sesudah = DB::table('respon')->where('periode_tracer_id', $pid)->pluck('alumni_npm')->all();
            $baru = array_values(array_diff($sesudah, $sebelum));

            $this->assertNotEmpty($baru, 'impor tidak menambah respon apa pun');

            // Tiap NPM baru harus punya baris sumber bertahun lulus 2021.
            $bukan2021 = DB::table('tcr_kuisoner')
                ->whereIn(DB::raw('trim(nimhsmsmh)'), $baru)
                ->whereRaw("trim(thnlulus) = '2021'")
                ->distinct()
                ->count(DB::raw('trim(nimhsmsmh)'));

            $this->assertSame(
                count($baru),
                $bukan2021,
                'ada respon baru yang sumbernya bukan tahun lulus 2021'
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_limit_membatasi_jumlah(): void
    {
        DB::beginTransaction();
        try {
            DB::table('respon')->where('periode_tracer_id', $this->periode()->id)->delete();

            $this->jalankan(['--limit' => 10]);

            $this->assertSame(10, DB::table('respon')->where('periode_tracer_id', $this->periode()->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_idempoten(): void
    {
        DB::beginTransaction();
        try {
            DB::table('respon')->where('periode_tracer_id', $this->periode()->id)->delete();

            $this->jalankan(['--limit' => 15]);
            $r1 = DB::table('respon')->where('periode_tracer_id', $this->periode()->id)->count();
            $j1 = DB::table('respon_jawaban')->count();

            $this->jalankan(['--limit' => 15]);
            $r2 = DB::table('respon')->where('periode_tracer_id', $this->periode()->id)->count();
            $j2 = DB::table('respon_jawaban')->count();

            $this->assertSame($r1, $r2, 'respon bertambah saat dijalankan ulang');
            $this->assertSame($j1, $j2, 'jawaban bertambah saat dijalankan ulang');
        } finally {
            DB::rollBack();
        }
    }

    public function test_ringkasan_dipetakan_benar(): void
    {
        $pid = $this->periodeBerisi();

        $petaF8 = [
            '1' => 'bekerja', '2' => 'belum_memungkinkan_bekerja', '3' => 'wiraswasta',
            '4' => 'studi_lanjut', '5' => 'mencari_kerja',
        ];

        // Ambil 20 respon dan bandingkan dengan baris sumbernya
        $respon = DB::table('respon')->where('periode_tracer_id', $pid)
            ->whereNotNull('status_saat_ini')->limit(20)->get();

        $this->assertGreaterThan(0, $respon->count());

        foreach ($respon as $r) {
            $f8 = DB::table('tcr_kuisoner')->where('nimhsmsmh', $r->alumni_npm)
                ->orderByDesc('id')->value('f8');

            if ($f8 === null || trim((string) $f8) === '') {
                continue;
            }

            $this->assertSame(
                $petaF8[trim((string) $f8)] ?? null,
                $r->status_saat_ini,
                'status_saat_ini salah untuk NPM ' . $r->alumni_npm
            );
        }
    }

    public function test_grup_checkbox_satu_baris_per_kolom_terisi(): void
    {
        $pid = $this->periodeBerisi();

        $respon = DB::table('respon_jawaban as j')
            ->join('respon as r', 'r.id', '=', 'j.respon_id')
            ->join('pertanyaan as p', 'p.id', '=', 'j.pertanyaan_id')
            ->where('p.kode', 'f16')->where('r.periode_tracer_id', $pid)
            ->select('r.id', 'r.alumni_npm')->distinct()->limit(10)->get();

        $this->assertGreaterThan(0, $respon->count());

        foreach ($respon as $r) {
            $hasil = DB::table('respon_jawaban as j')
                ->join('pertanyaan as p', 'p.id', '=', 'j.pertanyaan_id')
                ->join('pertanyaan_opsi as o', 'o.id', '=', 'j.opsi_id')
                ->where('j.respon_id', $r->id)->where('p.kode', 'f16')
                ->pluck('o.nilai')->sort()->values()->all();

            $lama = DB::table('tcr_kuisoner')->where('nimhsmsmh', $r->alumni_npm)
                ->orderByDesc('id')->first();

            $sumber = [];
            foreach (range(1, 13) as $i) {
                $kolom = 'f16' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $v = $lama->{$kolom} ?? null;
                if ($v !== null && trim((string) $v) !== '') {
                    $sumber[] = $kolom;
                }
            }
            sort($sumber);

            $this->assertSame($sumber, $hasil, 'f16 tidak cocok untuk NPM ' . $r->alumni_npm);
        }
    }

    public function test_opsi_selalu_milik_pertanyaannya(): void
    {
        $salah = DB::table('respon_jawaban as j')
            ->join('pertanyaan_opsi as o', 'o.id', '=', 'j.opsi_id')
            ->whereColumn('o.pertanyaan_id', '<>', 'j.pertanyaan_id')
            ->count();

        $this->assertSame(0, $salah);
    }

    public function test_tidak_ada_respon_tanpa_alumni_atau_ganda(): void
    {
        $this->assertSame(
            0,
            DB::table('respon as r')->leftJoin('alumni as a', 'a.npm_mhs', '=', 'r.alumni_npm')
                ->whereNull('a.npm_mhs')->count()
        );

        $this->assertSame(
            0,
            DB::table('respon')->selectRaw('alumni_npm, periode_tracer_id')
                ->groupBy('alumni_npm', 'periode_tracer_id')->havingRaw('count(*) > 1')->get()->count()
        );
    }

    public function test_tanggal_tak_terbaca_disimpan_sebagai_teks(): void
    {
        // f18d berisi "Agustus 2021", "September", "2020" dsb.
        // Dilingkupi ke kuesioner periode yang berisi, karena kode yang sama
        // ada di tiap kuesioner.
        $kuesionerId = DB::table('kuesioner')->where('periode_tracer_id', $this->periodeBerisi())->value('id');

        $soal = DB::table('pertanyaan')
            ->whereIn('kuesioner_bagian_id', DB::table('kuesioner_bagian')->where('kuesioner_id', $kuesionerId)->select('id'))
            ->where('kode', 'f18d')
            ->first();

        $this->assertNotNull($soal);
        $this->assertSame('date', $soal->tipe);

        $teks = DB::table('respon_jawaban')->where('pertanyaan_id', $soal->id)
            ->whereNotNull('nilai_teks')->count();
        $tanggal = DB::table('respon_jawaban')->where('pertanyaan_id', $soal->id)
            ->whereNotNull('nilai_tanggal')->count();

        $this->assertGreaterThan(0, $teks, 'harus ada yang disimpan sebagai teks');
        $this->assertGreaterThan(0, $tanggal, 'harus ada yang terbaca sebagai tanggal');

        // yang tersimpan sebagai tanggal wajib valid
        foreach (DB::table('respon_jawaban')->where('pertanyaan_id', $soal->id)
            ->whereNotNull('nilai_tanggal')->limit(10)->pluck('nilai_tanggal') as $t) {
            $this->assertMatchesRegularExpression('~^\d{4}-\d{2}-\d{2}~', (string) $t);
        }
    }
}
