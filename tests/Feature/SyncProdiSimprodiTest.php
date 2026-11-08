<?php

namespace Tests\Feature;

use App\Models\Prodi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * prodi:sync-simprodi.
 *
 * SIMPRODI tidak dihubungi sungguhan: balasannya dipalsukan memakai bentuk
 * dan nama kolom dari balasan nyata /api/prodi/filter, supaya test tidak
 * bergantung pada jaringan maupun isi data di server.
 */
class SyncProdiSimprodiTest extends TestCase
{
    /** Satu baris balasan, lengkap dengan kolom yang tidak dipakai. */
    private function baris(string $kode, string $nama, string $jenjang): array
    {
        return [
            'Kode_Fak' => '12',
            'Kode_Jur' => '5740',
            'Kode_Prg_Lama' => '10804',
            'Kode_Prg' => $kode,
            'Nama_Prg' => $nama,
            'Strata' => 'E',
            'Akreditasi_1' => 'TERAKREDITASI',
            'Akreditasi_2' => null,
            'Akreditasi_3' => null,
            'Npm_Prog' => '91',
            'Nama_Prg2' => 'Information Systems',
            'Gelar_Eng' => 'Bachelor',
            'Akreditasi_Tanggal_Ind' => null,
            'Akreditasi_Tanggal_Eng' => null,
            'Jenjang_Didik' => $jenjang,
        ];
    }

    private function palsukan(array $data, string $pesan = 'Ok'): void
    {
        Http::fake([
            '*' => Http::response(['msg' => $pesan, 'data' => $data], 200),
        ]);
    }

    public function test_prodi_baru_tersimpan_dan_yang_ada_diperbarui(): void
    {
        DB::beginTransaction();
        try {
            $lama = Prodi::create([
                'kode' => 'UJI01',
                'nama' => 'Nama Lama',
                'jenjang' => 'D3',
                'fakultas' => 'FT',
            ]);

            $this->palsukan([
                $this->baris('UJI01', 'Nama Baru', 'S1'),
                $this->baris('UJI02', 'Prodi Betul-betul Baru', 'S2'),
            ]);

            $this->artisan('prodi:sync-simprodi')->assertSuccessful();

            $diperbarui = $lama->fresh();

            $this->assertSame('Nama Baru', $diperbarui->nama);
            $this->assertSame('S1', $diperbarui->jenjang);

            // fakultas tidak ikut ditarik, jadi tidak boleh tertimpa null.
            $this->assertSame('FT', $diperbarui->fakultas);

            $baru = Prodi::where('kode', 'UJI02')->firstOrFail();

            $this->assertSame('Prodi Betul-betul Baru', $baru->nama);
            $this->assertSame('S2', $baru->jenjang);
        } finally {
            DB::rollBack();
        }
    }

    public function test_dry_run_tidak_menyimpan_apa_pun(): void
    {
        DB::beginTransaction();
        try {
            $this->palsukan([$this->baris('UJI03', 'Tidak Boleh Tersimpan', 'S1')]);

            $this->artisan('prodi:sync-simprodi --dry-run')->assertSuccessful();

            $this->assertFalse(Prodi::where('kode', 'UJI03')->exists());
        } finally {
            DB::rollBack();
        }
    }

    public function test_baris_tanpa_kode_atau_nama_dilewati(): void
    {
        DB::beginTransaction();
        try {
            $this->palsukan([
                $this->baris('', 'Tanpa Kode', 'S1'),
                $this->baris('UJI04', '', 'S1'),
                $this->baris('UJI05', 'Yang Ini Sah', 'S1'),
            ]);

            $this->artisan('prodi:sync-simprodi')->assertSuccessful();

            $this->assertFalse(Prodi::where('kode', 'UJI04')->exists());
            $this->assertTrue(Prodi::where('kode', 'UJI05')->exists());
        } finally {
            DB::rollBack();
        }
    }

    public function test_kode_ganda_dalam_satu_balasan_tidak_menggagalkan_upsert(): void
    {
        DB::beginTransaction();
        try {
            // PostgreSQL menolak upsert yang menyentuh baris sama dua kali,
            // jadi kemunculan terakhir yang dipakai.
            $this->palsukan([
                $this->baris('UJI06', 'Versi Pertama', 'D3'),
                $this->baris('UJI06', 'Versi Terakhir', 'S1'),
            ]);

            $this->artisan('prodi:sync-simprodi')->assertSuccessful();

            $prodi = Prodi::where('kode', 'UJI06')->firstOrFail();

            $this->assertSame('Versi Terakhir', $prodi->nama);
            $this->assertSame('S1', $prodi->jenjang);
        } finally {
            DB::rollBack();
        }
    }

    public function test_penolakan_token_dilaporkan_sebagai_gagal(): void
    {
        // Kunci salah dijawab HTTP 500 berisi err 100; pesan servernya harus
        // diteruskan, bukan tertutup pesan "status 500" saja.
        Http::fake([
            '*' => Http::response(['err' => 100, 'msg' => 'Token is invalid'], 500),
        ]);

        $this->artisan('prodi:sync-simprodi')
            ->expectsOutputToContain('Token is invalid')
            ->assertFailed();
    }

    public function test_balasan_tanpa_baris_dilaporkan_sebagai_gagal(): void
    {
        $this->palsukan([]);

        $this->artisan('prodi:sync-simprodi')
            ->expectsOutputToContain('Tidak ada baris prodi')
            ->assertFailed();
    }
}
