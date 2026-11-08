<?php

namespace Tests\Feature;

use App\Models\Kuesioner;
use App\Models\Pertanyaan;
use App\Models\User;
use Tests\TestCase;

class KuisonerPreviewTest extends TestCase
{
    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function html(): string
    {
        return $this->actingAs($this->admin())
            ->get('/admin/kuisoner/' . Kuesioner::firstOrFail()->id . '/preview')
            ->assertOk()
            ->getContent();
    }

    public function test_preview_render(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('Mode pratinjau', $html);
        $this->assertStringContainsString('Tracer Study 2021', $html);
        $this->assertStringContainsString('pages-kuisoner-preview.js', $html);
    }

    public function test_prodi_ditolak(): void
    {
        $prodi = User::whereNotNull('kode_prodi')->firstOrFail();

        $this->actingAs($prodi)
            ->get('/admin/kuisoner/' . Kuesioner::firstOrFail()->id . '/preview')
            ->assertStatus(401);
    }

    public function test_tamu_dialihkan(): void
    {
        $this->get('/admin/kuisoner/' . Kuesioner::firstOrFail()->id . '/preview')
            ->assertRedirect('/operator/login');
    }

    public function test_semua_bagian_dan_pertanyaan_dirender(): void
    {
        $html = $this->html();
        $k = Kuesioner::with('bagian.pertanyaan')->firstOrFail();

        foreach ($k->bagian as $b) {
            $this->assertStringContainsString(e($b->judul), $html, 'bagian hilang: ' . $b->judul);
        }

        // Tiap pertanyaan harus punya kontrol input, apa pun bentuk tampilannya.
        // Ini pemeriksaan "tidak ada yang hilang" yang tidak terikat tata letak.
        foreach ($k->bagian as $b) {
            foreach ($b->pertanyaan as $p) {
                $this->assertMatchesRegularExpression(
                    '~name="' . preg_quote($p->kode, '~') . '(\[\])?"~',
                    $html,
                    'soal hilang: ' . $p->kode
                );
            }
        }

        // Pertanyaan non-f17 tetap dicetak sebagai blok kp-soal ber-data-kode.
        foreach ($k->bagian as $b) {
            foreach ($b->pertanyaan as $p) {
                if (str_starts_with($p->kode, 'f17')) {
                    continue;
                }

                $this->assertStringContainsString('data-kode="' . $p->kode . '"', $html, 'blok hilang: ' . $p->kode);
            }
        }
    }

    public function test_setiap_tipe_dirender_dengan_kontrol_yang_benar(): void
    {
        $html = $this->html();

        // radio: f8 punya 5 opsi
        $f8 = Pertanyaan::where('kode', 'f8')->with('opsi')->firstOrFail();
        foreach ($f8->opsi as $o) {
            $this->assertStringContainsString(
                'type="radio"' , $html
            );
            $this->assertStringContainsString('value="' . $o->nilai . '"', $html);
        }

        // number: f502
        $this->assertMatchesRegularExpression(
            '~data-kode="f502".*?type="number"~s',
            $html,
            'f502 harus dirender sebagai input number'
        );

        // select: f5c
        $this->assertMatchesRegularExpression('~data-kode="f5c".*?<select~s', $html);

        // date: f18d
        $this->assertMatchesRegularExpression('~data-kode="f18d".*?type="date"~s', $html);

        // checkbox: f16 memakai name bertanda [] agar bisa banyak nilai
        $this->assertStringContainsString('name="f16[]"', $html);

        // scale: f1761 kini berada di dalam sel matriks A/B
        $this->assertMatchesRegularExpression('~kp-mx-cell[^>]*>.*?kp-skala.*?name="f1761"~s', $html);
    }

    public function test_percabangan_dicetak_sebagai_atribut_data(): void
    {
        $html = $this->html();

        // Nilai pemicu dicetak sebagai daftar JSON karena satu pertanyaan
        // boleh dipicu beberapa opsi sekaligus.

        // f502 tampil bila f8 = 1
        $this->assertMatchesRegularExpression(
            '~data-kode="f502"\s+data-pemicu="f8"\s+data-pemicu-nilai="\[&quot;1&quot;\]"~s',
            $html
        );

        // f5c tampil bila f8 = 3
        $this->assertMatchesRegularExpression(
            '~data-kode="f5c"\s+data-pemicu="f8"\s+data-pemicu-nilai="\[&quot;3&quot;\]"~s',
            $html
        );

        // f18a tampil bila f8 = 4
        $this->assertMatchesRegularExpression(
            '~data-kode="f18a"\s+data-pemicu="f8"\s+data-pemicu-nilai="\[&quot;4&quot;\]"~s',
            $html
        );
    }

    public function test_pertanyaan_bersyarat_tersembunyi_di_awal(): void
    {
        $html = $this->html();

        // Dilingkupi ke kuesioner yang sedang dipratinjau: sejak ada periode
        // kedua, hitungan global ikut menghitung kuesioner lain.
        $bagian = \Illuminate\Support\Facades\DB::table('kuesioner_bagian')
            ->where('kuesioner_id', Kuesioner::firstOrFail()->id)
            ->pluck('id');

        $bersyarat = Pertanyaan::whereIn('kuesioner_bagian_id', $bagian)
            ->whereNotNull('tampil_jika_pertanyaan_id')
            ->pluck('kode');

        // Jumlahnya tidak dipatok angka: kuesioner bisa bertambah pertanyaan
        // bersyarat (mis. lokasi kerja f5a1/f5a2) tanpa membuat test ini salah.
        $this->assertNotEmpty($bersyarat);

        foreach ($bersyarat as $kode) {
            $this->assertMatchesRegularExpression(
                '~class="kp-soal kp-tersembunyi"\s+data-kode="' . preg_quote($kode, '~') . '"~',
                $html,
                $kode . ' harus tersembunyi di awal'
            );
        }

        // f8 (bukan bersyarat) tidak boleh tersembunyi
        $this->assertMatchesRegularExpression(
            '~class="kp-soal\s*"\s+data-kode="f8"~',
            $html
        );
    }

    public function test_pertanyaan_f17_digabung_jadi_matriks_a_dan_b(): void
    {
        $html = $this->html();

        // Satu matriks, bukan 14 pertanyaan terpisah. Dihitung dari markup-nya
        // saja, bukan dari nama kelas yang juga muncul di blok <style>.
        $this->assertSame(1, substr_count($html, '<div class="kp-mx-tabel">'), 'harus ada tepat satu matriks');

        // Kepala kolom A dan B.
        $this->assertMatchesRegularExpression('~<strong>A</strong>\s*&middot;\s*saat lulus~', $html);
        $this->assertMatchesRegularExpression('~<strong>B</strong>\s*&middot;\s*diperlukan pekerjaan~', $html);

        // Tujuh kompetensi, masing-masing satu baris dengan dua sel.
        $kompetensi = [
            'Etika',
            'Keahlian berdasarkan bidang ilmu',
            'Bahasa Inggris',
            'Penggunaan Teknologi Informasi',
            'Komunikasi',
            'Kerja sama tim',
            'Pengembangan Diri',
        ];

        foreach ($kompetensi as $nama) {
            $this->assertStringContainsString(
                '<div class="kp-mx-label">' . e($nama) . '</div>',
                $html,
                'baris kompetensi hilang: ' . $nama
            );
        }

        $this->assertSame(7, substr_count($html, '<div class="kp-mx-label">'), 'harus 7 baris kompetensi');
        $this->assertSame(7, substr_count($html, '<div class="kp-mx-row">'), 'harus 7 baris matriks');
        $this->assertSame(14, substr_count($html, '<div class="kp-mx-cell"'), 'harus 14 sel (7 x A/B)');

        // Pasangan A/B berada pada baris yang sama, berurutan.
        $pasangan = [
            ['Etika', 'f1761', 'f1762'],
            ['Bahasa Inggris', 'f1765', 'f1766'],
            ['Pengembangan Diri', 'f1773', 'f1774'],
        ];

        foreach ($pasangan as [$nama, $a, $b]) {
            $this->assertMatchesRegularExpression(
                '~kp-mx-label">' . preg_quote(e($nama), '~') . '</div>.*?name="' . $a . '".*?name="' . $b . '"~s',
                $html,
                'pasangan A/B tidak berurutan untuk ' . $nama
            );
        }

        // Teks panjang yang berulang hanya dicetak sekali, sebagai intro.
        $this->assertSame(
            1,
            substr_count($html, 'Pada saat lulus, pada tingkat mana kompetensi di bawah ini anda kuasai?'),
            'intro matriks harus dicetak sekali saja'
        );
    }

    public function test_kode_f17_tetap_bisa_ditampilkan_lewat_tombol_kode(): void
    {
        $html = $this->html();

        // Badge kode per sel supaya tombol "Tampilkan kode" tetap berguna.
        foreach (['f1761', 'f1762', 'f1773', 'f1774'] as $kode) {
            $this->assertStringContainsString(
                'kp-badge-kode d-none">' . $kode . '</span>',
                $html,
                'badge kode hilang: ' . $kode
            );
        }
    }

    public function test_jumlah_diringkas_di_kartu_atas(): void
    {
        $html = $this->html();

        $kuesioner = Kuesioner::firstOrFail();
        $bagian = \Illuminate\Support\Facades\DB::table('kuesioner_bagian')
            ->where('kuesioner_id', $kuesioner->id)
            ->pluck('id');

        $soal = Pertanyaan::whereIn('kuesioner_bagian_id', $bagian);

        // Angkanya dibaca dari kuesioner itu sendiri, supaya yang diuji adalah
        // "kartu meringkas isi sebenarnya" -- bukan isi tertentu.
        $this->assertStringContainsString($bagian->count() . ' bagian', $html);
        $this->assertStringContainsString($soal->count() . ' pertanyaan', $html);
        $this->assertStringContainsString(
            $soal->clone()->whereNotNull('tampil_jika_pertanyaan_id')->count() . ' bersyarat',
            $html
        );
    }

    public function test_tombol_kirim_dimatikan(): void
    {
        $html = $this->html();

        $this->assertMatchesRegularExpression('~Kirim Jawaban~', $html);
        $this->assertMatchesRegularExpression('~<button[^>]*disabled[^>]*>\s*Kirim Jawaban~s', $html);
    }
}
