<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reset (hapus) data saldo di ueu_tbl_jurnal_saldo untuk satu tahun anggaran, dengan backup otomatis.
 *
 *   php artisan saldo:reset 2026 --dry-run                 lihat apa yang akan dihapus, tanpa menghapus
 *   php artisan saldo:reset 2026                           hapus SALDO AWAL T.A 2026 (default --jenis=awal)
 *   php artisan saldo:reset 2026 --jenis=semua --sumber=49 hapus semua baris T.A 2026 untuk sumber dana 49
 *
 * Jenis baris (dari uraian):
 *   awal  = "SALDO AWAL …" / "saldo awal …" (input halaman Saldo Awal, ket SD)
 *   akhir = "SALDO AKHIR …" (hasil tutup buku rek 4/5, saldo_hidden enable)
 *   semua = semua baris tahun tersebut
 *
 * Sebelum menghapus, baris yang akan dihapus disimpan ke storage/app/backup/jurnal_saldo/*.json
 * (kembalikan dengan: php artisan saldo:restore <file>). Penghapusan memakai daftar id_trans yang sama
 * dengan isi backup, di dalam satu transaksi.
 */
class ResetJurnalSaldo extends Command
{
  protected $signature = 'saldo:reset
    {tahun : Tahun anggaran (kolom tahun), mis. 2026}
    {--jenis=awal : awal | akhir | semua}
    {--sumber= : Batasi ke satu id sumber dana}
    {--dry-run : Hanya tampilkan ringkasan, tidak menghapus}
    {--force : Tanpa konfirmasi (untuk skrip)}
    {--tanpa-backup : Jangan buat file backup (tidak disarankan)}';

  protected $description = 'Reset data saldo ueu_tbl_jurnal_saldo per tahun anggaran (dengan backup JSON)';

  public function handle(): int
  {
    $tahun = (string) $this->argument('tahun');
    $jenis = strtolower((string) $this->option('jenis'));
    $sumber = $this->option('sumber');

    if (!preg_match('/^\d{4}$/', $tahun)) {
      $this->error('Tahun harus 4 digit, mis. 2026.');

      return self::INVALID;
    }
    if (!in_array($jenis, ['awal', 'akhir', 'semua'], true)) {
      $this->error('--jenis harus salah satu dari: awal, akhir, semua.');

      return self::INVALID;
    }
    if ($sumber !== null && !ctype_digit((string) $sumber)) {
      $this->error('--sumber harus berupa id angka.');

      return self::INVALID;
    }

    $query = fn () => $this->query($tahun, $jenis, $sumber !== null ? (int) $sumber : null);
    $ringkas = $query()->selectRaw("
        CASE WHEN LOWER(uraian) LIKE 'saldo awal%' THEN 'SALDO AWAL' WHEN LOWER(uraian) LIKE 'saldo akhir%' THEN 'SALDO AKHIR' ELSE 'LAINNYA' END as jenis,
        COUNT(*) as baris, COUNT(DISTINCT idrek6) as akun, COUNT(DISTINCT id_sumber) as sumber,
        COALESCE(SUM(debet), 0) as debet, COALESCE(SUM(kredit), 0) as kredit")
      ->groupBy('jenis')->orderBy('jenis')->get();
    $jumlah = (int) $ringkas->sum('baris');

    $this->info("ueu_tbl_jurnal_saldo · tahun {$tahun} · jenis {$jenis}" . ($sumber !== null ? " · sumber {$sumber}" : ''));
    if ($jumlah === 0) {
      $this->warn('Tidak ada baris yang cocok. Tidak ada yang dihapus.');

      return self::SUCCESS;
    }
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
    $this->table(['Jenis', 'Baris', 'Akun', 'Sumber dana', 'Σ Debet', 'Σ Kredit'],
      $ringkas->map(fn ($r) => [$r->jenis, $r->baris, $r->akun, $r->sumber, $rp($r->debet), $rp($r->kredit)])->all());

    if ($this->option('dry-run')) {
      $this->line("<comment>Dry run:</comment> {$jumlah} baris akan dihapus. Jalankan tanpa --dry-run untuk menghapus.");

      return self::SUCCESS;
    }

    if (!$this->option('force')) {
      $this->warn("{$jumlah} baris akan DIHAPUS permanen dari ueu_tbl_jurnal_saldo.");
      if ($this->ask("Ketik tahun ({$tahun}) untuk melanjutkan") !== $tahun) {
        $this->line('Dibatalkan.');

        return self::FAILURE;
      }
    }

    // Ambil baris yang akan dihapus sekali saja: isi backup = baris yang dihapus.
    $rows = $query()->orderBy('id_trans')->get();
    $id = $rows->pluck('id_trans')->all();

    $file = null;
    if (!$this->option('tanpa-backup')) {
      $file = 'backup/jurnal_saldo/' . $tahun . '_' . $jenis . ($sumber !== null ? '_s' . $sumber : '') . '_' . now()->format('Ymd_His') . '.json';
      Storage::disk('local')->put($file, json_encode([
        'tabel' => 'ueu_tbl_jurnal_saldo',
        'dibuat' => now()->toIso8601String(),
        'filter' => ['tahun' => $tahun, 'jenis' => $jenis, 'sumber' => $sumber],
        'jumlah' => $rows->count(),
        'baris' => $rows,
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
      $this->line('Backup: <info>' . Storage::disk('local')->path($file) . '</info>');
    }

    $terhapus = DB::transaction(function () use ($id) {
      $n = 0;
      foreach (array_chunk($id, 1000) as $potong) {
        $n += DB::table('ueu_tbl_jurnal_saldo')->whereIn('id_trans', $potong)->delete();
      }

      return $n;
    });

    $this->info("{$terhapus} baris dihapus.");
    if ($file) {
      $this->line("Kembalikan dengan: php artisan saldo:restore {$file}");
    }

    return self::SUCCESS;
  }

  private function query(string $tahun, string $jenis, ?int $sumber): Builder
  {
    return DB::table('ueu_tbl_jurnal_saldo')->where('tahun', $tahun)
      ->when($jenis === 'awal', fn ($q) => $q->whereRaw("LOWER(uraian) LIKE 'saldo awal%'"))
      ->when($jenis === 'akhir', fn ($q) => $q->whereRaw("LOWER(uraian) LIKE 'saldo akhir%'"))
      ->when($sumber !== null, fn ($q) => $q->where('id_sumber', $sumber));
  }
}
