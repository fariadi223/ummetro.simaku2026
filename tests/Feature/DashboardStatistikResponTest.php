<?php

namespace Tests\Feature;

use App\Models\PeriodeTracer;
use App\Models\Respon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kartu "Responden per Periode" di dashboard administrator.
 */
class DashboardStatistikResponTest extends TestCase
{
    private function admin(): User
    {
        return User::where('username', 'admin')->firstOrFail();
    }

    private function html(): string
    {
        return $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();
    }

    public function test_kartu_dan_legenda_lengkap(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('Responden per Periode', $html);

        // Identitas tiap status harus punya label, bukan hanya warna.
        foreach (Respon::STATUS_SAAT_INI as $label) {
            $this->assertStringContainsString($label, $html, "label status hilang: {$label}");
        }

        // Lima slot warna kategorikal terpakai berurutan.
        foreach (range(1, count(Respon::STATUS_SAAT_INI)) as $slot) {
            $this->assertStringContainsString('--kotak: var(--seri-' . $slot . ')', $html);
        }
    }

    public function test_jumlah_responden_per_periode_ditampilkan(): void
    {
        $html = $this->html();

        $periode = PeriodeTracer::query()
            ->withCount(['respon as terkirim' => fn ($q) => $q->where('status', 'submitted')])
            ->whereHas('respon', fn ($q) => $q->where('status', 'submitted'))
            ->firstOrFail();

        $this->assertStringContainsString($periode->nama, $html);
        $this->assertStringContainsString(number_format($periode->terkirim), $html);
    }

    /**
     * Lebar segmen harus berjumlah 100%: kalau tidak, segmen terakhir
     * terpotong oleh overflow dan satu kategori hilang dari bar.
     */
    public function test_lebar_segmen_berjumlah_seratus_persen(): void
    {
        preg_match_all('~class="st-bar"(.+?)</div>~s', $this->html(), $bar);

        $this->assertNotEmpty($bar[1], 'tidak ada bar komposisi yang dirender');

        foreach ($bar[1] as $isi) {
            preg_match_all('~width: ([0-9.]+)%~', $isi, $lebar);

            $this->assertNotEmpty($lebar[1]);
            $this->assertEqualsWithDelta(100, array_sum(array_map('floatval', $lebar[1])), 0.05);
        }
    }

    public function test_periode_tanpa_jawaban_tidak_menampilkan_bar(): void
    {
        DB::beginTransaction();
        try {
            // Seluruh respon dihapus di dalam transaksi: tanpa jawaban, bar
            // tidak boleh dicetak sama sekali (pembaginya nol).
            DB::table('respon_jawaban')->delete();
            DB::table('respon')->delete();

            $html = $this->html();

            $this->assertStringContainsString('belum ada jawaban', $html);
            $this->assertStringNotContainsString('class="st-seg"', $html);
        } finally {
            DB::rollBack();
        }
    }

    public function test_tanpa_periode_sama_sekali_tetap_render(): void
    {
        DB::beginTransaction();
        try {
            DB::table('respon_jawaban')->delete();
            DB::table('respon')->delete();
            DB::table('pertanyaan_tampil_opsi')->delete();
            DB::table('periode_tracer')->delete();

            $html = $this->html();

            $this->assertStringContainsString('Belum ada periode tracer study', $html);
        } finally {
            DB::rollBack();
        }
    }
}
