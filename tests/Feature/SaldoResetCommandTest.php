<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Command saldo:reset & saldo:restore — hanya jalur yang TIDAK menulis (dry-run, validasi, batal).
 * Jalankan: DB_DATABASE=ummetro_simaku php artisan test --filter=SaldoResetCommandTest
 */
class SaldoResetCommandTest extends TestCase
{
    private function jumlah(): int
    {
        return DB::table('ueu_tbl_jurnal_saldo')->count();
    }

    public function test_dry_run_dan_validasi_tidak_menghapus(): void
    {
        $sebelum = $this->jumlah();
        $awal2026 = DB::table('ueu_tbl_jurnal_saldo')->where('tahun', '2026')->whereRaw("LOWER(uraian) LIKE 'saldo awal%'")->count();

        $this->artisan('saldo:reset', ['tahun' => '2026', '--dry-run' => true])
            ->expectsOutputToContain($awal2026 ? "Dry run: {$awal2026} baris akan dihapus" : 'Tidak ada baris')->assertSuccessful();
        $this->artisan('saldo:reset', ['tahun' => '26'])->assertExitCode(2);
        $this->artisan('saldo:reset', ['tahun' => '2026', '--jenis' => 'x'])->assertExitCode(2);
        $this->artisan('saldo:reset', ['tahun' => '1999'])->expectsOutputToContain('Tidak ada baris')->assertSuccessful();

        // Konfirmasi salah → batal, tanpa backup & tanpa hapus (pakai tahun yang punya data).
        Storage::fake('local');
        $thn = (string) DB::table('ueu_tbl_jurnal_saldo')->whereRaw("LOWER(uraian) LIKE 'saldo awal%'")->max('tahun');
        $this->artisan('saldo:reset', ['tahun' => $thn])
            ->expectsQuestion("Ketik tahun ({$thn}) untuk melanjutkan", 'salah')
            ->expectsOutput('Dibatalkan.')->assertFailed();
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->assertSame($sebelum, $this->jumlah());
    }

    public function test_restore_dry_run_melewati_id_yang_ada(): void
    {
        Storage::fake('local');
        $rows = DB::table('ueu_tbl_jurnal_saldo')->orderBy('id_trans')->limit(3)->get();
        Storage::disk('local')->put('backup/jurnal_saldo/uji.json', json_encode([
            'tabel' => 'ueu_tbl_jurnal_saldo', 'dibuat' => now()->toIso8601String(), 'filter' => [], 'jumlah' => 3, 'baris' => $rows,
        ]));
        $sebelum = $this->jumlah();

        $this->artisan('saldo:restore', ['file' => 'backup/jurnal_saldo/uji.json', '--dry-run' => true])
            ->expectsOutputToContain('Tidak ada yang perlu dikembalikan')->assertSuccessful();
        $this->artisan('saldo:restore', ['file' => 'tidak-ada.json'])->assertExitCode(2);
        Storage::disk('local')->put('salah.json', '{"tabel":"lain"}');
        $this->artisan('saldo:restore', ['file' => 'salah.json'])->assertExitCode(2);

        $this->assertSame($sebelum, $this->jumlah());
    }
}
