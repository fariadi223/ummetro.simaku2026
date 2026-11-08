<?php

namespace App\Console\Commands;

use App\Services\NeracaPercobaan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pindahkan saldo akhir neraca percobaan T.A asal ke saldo awal T.A berikutnya, hanya akun rek 1, 2, 3
 * (padanan Tutupbuku::neraca_proses v1).
 *
 *   php artisan saldo:pindah 2025 --dry-run     lihat hasil (T.A 2025/2026 → saldo awal tahun 2026), tanpa menulis
 *   php artisan saldo:pindah 2025               tulis ke ueu_tbl_jurnal_saldo tahun 2026
 *   php artisan saldo:pindah 2025 --ganti       ganti SALDO AWAL tahun 2026 yang sudah ada (dibackup dulu)
 *   php artisan saldo:pindah 2025 --surplus     sekaligus tutup surplus/defisit rek 4 & 5 ke aset neto (seimbang)
 *
 *   Saldo per (akun level 6, sumber dana) = saldo awal T.A asal + mutasi T.A asal (tanpa jurnal TB)
 *   = kolom Saldo Akhir di halaman Neraca Percobaan (seluruh T.A), dipecah per sumber dana.
 *   Baris baru: tahun = asal + 1, uraian "SALDO AWAL 01-09-<asal+1>", tgl 01-09-<asal+1>, ket SD,
 *   bukti = penanda batch (detik unix, seperti v1), saldo bersih positif → debet, negatif → kredit.
 *
 * Rek 4 & 5 tidak dipindahkan (sesuai permintaan / v1). Tanpa --surplus, saldo awal baru tidak seimbang sebesar
 * surplus/defisit T.A asal. Dengan --surplus, surplus per sumber dana (pendapatan − biaya) ditambahkan sebagai saldo awal
 * aset neto: sumber dana kelompok Pembatasan → rek6 274, lainnya → rek6 262 (Tutupbuku::surplus_proses v1). Baris aset
 * neto untuk akun & sumber dana yang sama digabung dengan saldo bawaan rek 3. Jurnal "SALDO AKHIR" (penutup rek 4/5)
 * di tahun asal tidak ditulis ulang oleh command ini.
 */
class PindahSaldoAwal extends Command
{
  protected $signature = 'saldo:pindah
    {tahun : T.A asal, mis. 2025 untuk periode 2025/2026}
    {--dry-run : Hanya tampilkan ringkasan, tidak menulis}
    {--ganti : Hapus dulu SALDO AWAL tahun tujuan yang sudah ada (dibackup ke JSON)}
    {--force : Tanpa konfirmasi (untuk skrip)}
    {--detail : Tampilkan semua baris yang akan ditulis}
    {--surplus : Tutup surplus/defisit rek 4 & 5 ke aset neto (262 / 274) agar saldo awal seimbang}';

  protected $description = 'Pindahkan saldo akhir neraca percobaan (rek 1, 2, 3) ke saldo awal T.A berikutnya';

  public function handle(NeracaPercobaan $np): int
  {
    $asal = (string) $this->argument('tahun');
    if (!preg_match('/^\d{4}$/', $asal)) {
      $this->error('Tahun harus 4 digit, mis. 2025.');

      return self::INVALID;
    }
    $tujuan = (string) ((int) $asal + 1);
    $tgl = $tujuan . '-09-01';
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');

    $baris = $np->saldoAkhirPerSumber((int) $asal);
    $this->info("Saldo akhir T.A {$asal}/{$tujuan} (rek 1, 2, 3) → saldo awal tahun {$tujuan} (01-09-{$tujuan})"
      . ($this->option('surplus') ? ' + surplus ke aset neto' : ''));
    if ($baris->isEmpty()) {
      $this->warn('Tidak ada saldo untuk dipindahkan.');

      return self::SUCCESS;
    }

    // Ringkasan per rek 1 + cocokkan dengan Neraca Percobaan (kolom Saldo Akhir).
    $neraca = $np->hitung((int) $asal)['baris']->where('lv', 1)->keyBy('kode');
    $ringkas = $baris->groupBy('koderek1')->sortKeys()->map(function ($g, $k1) use ($neraca, $rp) {
      $d = $g->sum(fn ($r) => max($r->net, 0));
      $k = $g->sum(fn ($r) => max(-$r->net, 0));
      $cek = isset($neraca[$k1]) && abs(($d - $k) - $neraca[$k1]->akhir) < 0.5 ? 'cocok' : 'BEDA';

      return [$k1, $g->count(), $g->pluck('idrek6')->unique()->count(), $rp($d), $rp($k), $cek];
    })->values();
    $this->table(['Rek 1', 'Baris', 'Akun', 'Σ Debet', 'Σ Kredit', 'vs Neraca Percobaan'], $ringkas->all());

    if ($this->option('surplus')) {
      $surplusRows = $np->surplusPerSumber((int) $asal);
      $this->table(['Aset neto', 'Sumber dana', 'Baris', 'Surplus (+) / defisit (−)'], $surplusRows->groupBy('idrek6')->map(fn ($g, $id) => [
        $id . ($id == NeracaPercobaan::ASET_NETO_DENGAN_PEMBATASAN ? ' dengan pembatasan' : ' tanpa pembatasan'),
        $g->pluck('id_sumber')->implode(', '), $g->count(), $rp($g->sum('surplus')),
      ])->values()->all());
      // Gabungkan ke baris rek 3 yang sama (akun + sumber dana).
      $gabung = $baris->keyBy(fn ($r) => $r->idrek6 . '|' . $r->id_sumber);
      foreach ($surplusRows as $s) {
        $k = $s->idrek6 . '|' . $s->id_sumber;
        if (isset($gabung[$k])) {
          $gabung[$k]->net = round($gabung[$k]->net + $s->net, 2);
        } else {
          $gabung[$k] = (object) ['idrek6' => $s->idrek6, 'id_sumber' => $s->id_sumber, 'koderek1' => '3',
            'kode' => $s->idrek6 == NeracaPercobaan::ASET_NETO_DENGAN_PEMBATASAN ? '3.2.01.01.01.01' : '3.1.01.01.01.01',
            'nama' => $s->idrek6 == NeracaPercobaan::ASET_NETO_DENGAN_PEMBATASAN ? 'Aset Neto Dengan Pembatasan' : 'Aset neto tanpa pembatasan',
            'net' => $s->net];
        }
      }
      $baris = $gabung->filter(fn ($r) => abs($r->net) >= 0.005)->values();
    }

    $totD = $baris->sum(fn ($r) => max($r->net, 0));
    $totK = $baris->sum(fn ($r) => max(-$r->net, 0));
    $this->line("Total debet {$rp($totD)} · total kredit {$rp($totK)} · baris {$baris->count()}");
    if (abs($totD - $totK) < 0.5) {
      $this->info('Seimbang: total debet = total kredit.');
    } else {
      $surplus = $neraca->only(['4', '5'])->sum(fn ($r) => -$r->akhir);
      $this->warn('Tidak seimbang: selisih D − K = ' . $rp($totD - $totK)
        . ". Surplus/defisit T.A {$asal} (rek 4 & 5, belum ditutup ke aset neto) = " . $rp($surplus) . '.'
        . ($this->option('surplus') ? '' : ' Tambahkan --surplus untuk menutupnya ke aset neto.'));
    }

    if ($this->option('detail')) {
      $sumber = DB::table('ueu_tbl_sumberdana')->pluck('nama_penerimaan', 'id');
      $this->table(['Kode', 'Akun', 'Sumber dana', 'Debet', 'Kredit'], $baris->map(fn ($r) => [
        $r->kode, $r->nama, $sumber[$r->id_sumber] ?? $r->id_sumber, $r->net > 0 ? $rp($r->net) : '', $r->net < 0 ? $rp(-$r->net) : '',
      ])->all());
    }

    // SALDO AWAL tahun tujuan yang sudah ada.
    $lama = fn () => DB::table('ueu_tbl_jurnal_saldo')->where('tahun', $tujuan)->whereRaw("LOWER(uraian) LIKE 'saldo awal%'");
    $jumlahLama = $lama()->count();
    if ($jumlahLama > 0) {
      $this->warn("Tahun {$tujuan} sudah punya {$jumlahLama} baris SALDO AWAL.");
      if (!$this->option('ganti')) {
        $this->line('Tambahkan --ganti untuk menggantinya (dibackup dulu), atau hapus dengan: php artisan saldo:reset ' . $tujuan);
        if (!$this->option('dry-run')) {
          return self::FAILURE;
        }
      }
    }

    if ($this->option('dry-run')) {
      $this->line("<comment>Dry run:</comment> {$baris->count()} baris akan ditulis ke tahun {$tujuan}"
        . ($jumlahLama && $this->option('ganti') ? ", {$jumlahLama} baris SALDO AWAL lama diganti" : '') . '. Tidak ada yang ditulis.');

      return self::SUCCESS;
    }

    if (!$this->option('force')) {
      $this->warn("{$baris->count()} baris akan DITULIS ke ueu_tbl_jurnal_saldo tahun {$tujuan}"
        . ($jumlahLama ? " dan {$jumlahLama} baris SALDO AWAL lama DIHAPUS" : '') . '.');
      if ($this->ask("Ketik tahun tujuan ({$tujuan}) untuk melanjutkan") !== $tujuan) {
        $this->line('Dibatalkan.');

        return self::FAILURE;
      }
    }

    $batch = (string) time();
    $file = null;
    $lamaRows = $jumlahLama ? $lama()->orderBy('id_trans')->get() : collect();
    if ($lamaRows->isNotEmpty()) {
      $file = 'backup/jurnal_saldo/' . $tujuan . '_awal_sebelum-pindah_' . now()->format('Ymd_His') . '.json';
      Storage::disk('local')->put($file, json_encode([
        'tabel' => 'ueu_tbl_jurnal_saldo', 'dibuat' => now()->toIso8601String(),
        'filter' => ['tahun' => $tujuan, 'jenis' => 'awal', 'sebab' => "saldo:pindah {$asal} --ganti"],
        'jumlah' => $lamaRows->count(), 'baris' => $lamaRows,
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
      $this->line('Backup SALDO AWAL lama: <info>' . Storage::disk('local')->path($file) . '</info>');
    }

    // Kolom NOT NULL tanpa default diisi eksplisit; pembatasan dibiarkan default (v1 menulis '' yang ditolak mode strict).
    $isi = $baris->map(fn ($r) => [
      'tahun' => $tujuan,
      'id_sumber' => $r->id_sumber,
      'idrek6' => $r->idrek6,
      'idrinci' => 0,
      'idrinci_na' => 0,
      'tgl_trk' => $tgl,
      'uraian' => 'SALDO AWAL 01-09-' . $tujuan,
      'bukti' => $batch,
      'debet' => $r->net > 0 ? $r->net : 0,
      'kredit' => $r->net < 0 ? -$r->net : 0,
      'ket' => 'SD',
      'tgl_trans' => '01-09-' . $tujuan,
    ])->all();

    DB::transaction(function () use ($lamaRows, $isi) {
      foreach ($lamaRows->pluck('id_trans')->chunk(1000) as $potong) {
        DB::table('ueu_tbl_jurnal_saldo')->whereIn('id_trans', $potong->all())->delete();
      }
      foreach (array_chunk($isi, 500) as $potong) {
        DB::table('ueu_tbl_jurnal_saldo')->insert($potong);
      }
    });

    $this->info(count($isi) . " baris SALDO AWAL tahun {$tujuan} ditulis (bukti/batch {$batch}).");
    if ($file) {
      $this->line("SALDO AWAL lama bisa dikembalikan dengan: php artisan saldo:reset {$tujuan} lalu php artisan saldo:restore {$file}");
    }
    $this->line("Batalkan hasil ini dengan: php artisan saldo:reset {$tujuan}");

    return self::SUCCESS;
  }
}
