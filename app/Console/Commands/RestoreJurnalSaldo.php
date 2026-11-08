<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Kembalikan backup dari saldo:reset ke ueu_tbl_jurnal_saldo.
 *
 *   php artisan saldo:restore backup/jurnal_saldo/2026_awal_20261108_101500.json [--dry-run] [--force]
 *
 * Baris dimasukkan dengan id_trans aslinya; baris yang id_trans-nya sudah ada di tabel dilewati
 * (jadi aman dijalankan ulang). Path relatif terhadap storage/app, atau path absolut.
 */
class RestoreJurnalSaldo extends Command
{
  protected $signature = 'saldo:restore
    {file : File backup JSON hasil saldo:reset}
    {--dry-run : Hanya tampilkan ringkasan}
    {--force : Tanpa konfirmasi}';

  protected $description = 'Kembalikan baris ueu_tbl_jurnal_saldo dari file backup saldo:reset';

  public function handle(): int
  {
    $arg = (string) $this->argument('file');
    $path = is_file($arg) ? $arg : Storage::disk('local')->path($arg);
    if (!is_file($path)) {
      $this->error("File tidak ditemukan: {$arg}");

      return self::INVALID;
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (($data['tabel'] ?? null) !== 'ueu_tbl_jurnal_saldo' || !is_array($data['baris'] ?? null)) {
      $this->error('Bukan file backup saldo:reset yang valid.');

      return self::INVALID;
    }

    $baris = collect($data['baris']);
    $ada = collect();
    foreach ($baris->pluck('id_trans')->chunk(1000) as $potong) {
      $ada = $ada->merge(DB::table('ueu_tbl_jurnal_saldo')->whereIn('id_trans', $potong->all())->pluck('id_trans'));
    }
    $ada = $ada->flip();
    $baru = $baris->reject(fn ($r) => isset($ada[$r['id_trans']]))->values();

    $this->info('Backup ' . basename($path) . ' · dibuat ' . ($data['dibuat'] ?? '?') . ' · filter ' . json_encode($data['filter'] ?? []));
    $this->table(['Baris di backup', 'Sudah ada (dilewati)', 'Akan dimasukkan'], [[$baris->count(), $ada->count(), $baru->count()]]);

    if ($baru->isEmpty()) {
      $this->line('Tidak ada yang perlu dikembalikan.');

      return self::SUCCESS;
    }
    if ($this->option('dry-run')) {
      $this->line('<comment>Dry run:</comment> tidak ada yang dimasukkan.');

      return self::SUCCESS;
    }
    if (!$this->option('force') && !$this->confirm("Masukkan {$baru->count()} baris ke ueu_tbl_jurnal_saldo?")) {
      $this->line('Dibatalkan.');

      return self::FAILURE;
    }

    DB::transaction(function () use ($baru) {
      foreach ($baru->chunk(500) as $potong) {
        DB::table('ueu_tbl_jurnal_saldo')->insert($potong->values()->all());
      }
    });
    $this->info($baru->count() . ' baris dikembalikan.');

    return self::SUCCESS;
  }
}
