<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BKK non anggaran kasir (/kasir/non-anggaran). Hanya GET dan POST yang ditolak — tidak menulis ke database.
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=KasirNonAnggaranTest
 */
class KasirNonAnggaranTest extends TestCase
{
    private const TAHUN = '2026';

    private function kasir(): static
    {
        return $this->actingAs(User::where('wewenang', 'kasir')->firstOrFail())
            ->withSession(['wewenang' => 'kasir', 'periode' => self::TAHUN]);
    }

    private function jumlah(): array
    {
        return [DB::table('ueu_tbl_bkk')->count(), DB::table('ueu_tbl_bkk_rinci')->count()];
    }

    public function test_form_dan_nomor_berikut(): void
    {
        $akhir = (int) DB::table('ueu_tbl_bkk')->where('tahun', self::TAHUN)->selectRaw('MAX(CAST(no_bkk AS UNSIGNED)) n')->value('n');

        $this->kasir()->get('/kasir/non-anggaran/input')->assertOk()
            ->assertSee('Input Bukti Kas Keluar')->assertSee(($akhir + 1) . '/BKK-UMM/' . self::TAHUN);
    }

    public function test_pilihan_unit_hanya_sub_unit(): void
    {
        $sub = DB::table('ueu_tbl_unit')->where('kode', 'not like', '%.00')->orderBy('kode')->first();
        $induk = DB::table('ueu_tbl_unit')->where('kode', 'like', '%.00')->where('kode', '!=', '00.00')->orderBy('kode')->first();

        $this->kasir()->get('/kasir/non-anggaran/input')->assertOk()
            ->assertSee('name="id_unit"', false)
            ->assertSee('data-kode="00.00"', false)
            ->assertSee('value="' . $sub->id_unit . '" data-kode="' . $sub->kode . '"', false)
            ->assertDontSee('data-kode="' . $induk->kode . '"', false);
    }

    public function test_daftar_dan_cari(): void
    {
        $this->kasir()->get('/kasir/non-anggaran')->assertOk()->assertSee('Bukti Kas Keluar');
        $this->kasir()->get('/kasir/non-anggaran?cari=Transfer&page=2')->assertOk();
    }

    public function test_filter_rentang_tanggal(): void
    {
        $hitung = fn ($dari, $sampai) => DB::table('ueu_tbl_bkk')->where('tahun', self::TAHUN)
            ->whereBetween('tgl_bkk', [$dari, $sampai])->count();

        // Per bulan: jumlah hasil = jumlah BKK pada bulan itu.
        $bln = DB::table('ueu_tbl_bkk')->where('tahun', self::TAHUN)->max('tgl_bkk');
        $ym = substr($bln, 0, 7);
        $n = $hitung("{$ym}-01", date('Y-m-t', strtotime("{$ym}-01")));
        $this->kasir()->get("/kasir/non-anggaran?rentang={$ym}")->assertOk()
            ->assertViewHas('daftar', fn ($d) => $d->total() === $n);

        // Rentang khusus (terbalik pun ditukar), dan preset cepat.
        $n2 = $hitung("{$ym}-01", "{$ym}-15");
        $this->kasir()->get("/kasir/non-anggaran?rentang=khusus&dari={$ym}-15&sampai={$ym}-01")->assertOk()
            ->assertViewHas('daftar', fn ($d) => $d->total() === $n2);
        foreach (['hari-ini', '7-hari', 'bulan-ini', 'bulan-lalu'] as $k) {
            $this->kasir()->get("/kasir/non-anggaran?rentang={$k}")->assertOk()->assertViewHas('rentang', $k);
        }

        // Nilai tidak sah diabaikan.
        $this->kasir()->get('/kasir/non-anggaran?rentang=khusus&dari=x&sampai=')->assertOk()->assertViewHas('rentang', '');
        $this->kasir()->get('/kasir/non-anggaran?rentang=2026-13')->assertOk()->assertViewHas('rentang', '');
    }

    public function test_cetak_bkk_tunai(): void
    {
        $b = DB::table('ueu_tbl_bkk')->where('tahun', self::TAHUN)->where('status_bkk', 'Tunai')->orderByDesc('id_bkk')->first();
        $this->kasir()->get("/kasir/non-anggaran/{$b->id_bkk}/cetak")->assertOk()
            ->assertSee($b->no_bkk)->assertSee('BUKTI KAS KELUAR')->assertSee('Yang Menerima');
        $this->kasir()->get('/kasir/non-anggaran/999999999/cetak')->assertNotFound();
    }

    public function test_cetak_ulang_diberi_watermark_copy(): void
    {
        $b = DB::table('ueu_tbl_bkk')->where('tahun', self::TAHUN)->where('status_bkk', 'Tunai')->orderByDesc('id_bkk')->first();

        // Lihat (?lihat=1) -> tanpa watermark & tanpa dialog cetak.
        $this->kasir()->get("/kasir/non-anggaran/{$b->id_bkk}/cetak?lihat=1")->assertOk()
            ->assertDontSee('wm-copy', false)->assertDontSee('window.print(); }, 300', false);

        // Cetak ulang (tanpa tanda "baru ditunaikan") -> COPY.
        $this->kasir()->get("/kasir/non-anggaran/{$b->id_bkk}/cetak")->assertOk()->assertSee('wm-copy', false);

        // Cetakan pertama setelah tunai -> asli, berikutnya COPY lagi.
        $this->kasir()->withSession(['wewenang' => 'kasir', 'periode' => self::TAHUN, "bkk_asli.na.{$b->id_bkk}" => true])
            ->get("/kasir/non-anggaran/{$b->id_bkk}/cetak")->assertOk()->assertDontSee('wm-copy', false);
        $this->get("/kasir/non-anggaran/{$b->id_bkk}/cetak")->assertOk()->assertSee('wm-copy', false);
    }

    public function test_simpan_tidak_valid_ditolak(): void
    {
        $sebelum = $this->jumlah();

        $this->kasir()->from('/kasir/non-anggaran/input')->post('/kasir/non-anggaran', [
            'terimadari' => '', 'uraian_kegiatan' => '', 'penerima' => '',
            'rinci' => [['uraian' => 'Transport', 'biaya' => ''], ['uraian' => '', 'biaya' => '']],
        ])->assertRedirect('/kasir/non-anggaran/input')
          ->assertSessionHasErrors(['id_unit', 'terimadari', 'uraian_kegiatan', 'penerima', 'rinci.0.biaya']);

        // Unit induk (*.00) tidak boleh dipilih.
        $induk = DB::table('ueu_tbl_unit')->where('kode', 'like', '%.00')->where('kode', '!=', '00.00')->value('id_unit');
        $this->kasir()->from('/kasir/non-anggaran/input')->post('/kasir/non-anggaran', ['id_unit' => $induk])
            ->assertSessionHasErrors('id_unit');

        // Pengecualian 00.00 lolos validasi unit (isian lain kosong -> tetap ditolak, tidak tersimpan).
        $rektorat = DB::table('ueu_tbl_unit')->where('kode', '00.00')->value('id_unit');
        $this->kasir()->from('/kasir/non-anggaran/input')->post('/kasir/non-anggaran', ['id_unit' => $rektorat])
            ->assertSessionDoesntHaveErrors('id_unit')->assertSessionHasErrors('penerima');

        $this->kasir()->from('/kasir/non-anggaran/input')->post('/kasir/non-anggaran', [
            'terimadari' => 'UM Metro', 'uraian_kegiatan' => 'Uji', 'penerima' => 'Uji', 'rinci' => [],
        ])->assertSessionHasErrors('rinci');

        $this->assertSame($sebelum, $this->jumlah());
    }

    public function test_bkk_tunai_tidak_bisa_ditunai_atau_dihapus_lagi(): void
    {
        $b = DB::table('ueu_tbl_bkk')->where('tahun', self::TAHUN)->where('status_bkk', 'Tunai')->orderByDesc('id_bkk')->first();
        $sebelum = $this->jumlah();

        $this->kasir()->from('/kasir/non-anggaran')->post("/kasir/non-anggaran/{$b->id_bkk}/tunai")->assertSessionHas('error');
        $this->kasir()->from('/kasir/non-anggaran')->delete("/kasir/non-anggaran/{$b->id_bkk}")->assertSessionHas('error');

        $this->assertSame($sebelum, $this->jumlah());
        $this->assertSame($b->tgl_tunai, DB::table('ueu_tbl_bkk')->where('id_bkk', $b->id_bkk)->value('tgl_tunai'));
    }

    public function test_role_lain_ditolak(): void
    {
        $this->actingAs(User::where('wewenang', 'operatorspm')->firstOrFail())
            ->withSession(['wewenang' => 'operatorspm'])->get('/kasir/non-anggaran')->assertStatus(401);
    }
}
