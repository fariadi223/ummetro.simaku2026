<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Salin tabel prodi dari PostgreSQL akademik (ummetro_pdptumm.prodi) ke MySQL ueu_ref_prodi.
 * MySQL tidak bisa JOIN langsung ke PostgreSQL, jadi laporan per prodi membaca salinan ini.
 *
 *   php artisan simaku:sinkron-prodi            # upsert (tambah baru / perbarui kode & nama)
 *   php artisan simaku:sinkron-prodi --dry-run  # hanya tampilkan, tidak menulis
 *
 * Prodi yang hilang di sumber TIDAK dihapus dari ueu_ref_prodi, karena mapping lama mungkin masih memakainya.
 * kode_npm: tbl_sms.npm_prog (kode prodi di NPM) disalin ke ueu_map_unit_prodi.kode_npm untuk semua unit
 * yang dipetakan ke prodi itu (sumber = tbl_sms; npm_prog kosong tidak menimpa isian yang ada).
 * Koneksi: config/database.php 'akademik' (env AKADEMIK_DB_*).
 */
class SinkronProdi extends Command
{
  protected $signature = 'simaku:sinkron-prodi {--dry-run : Tampilkan data tanpa menulis ke ueu_ref_prodi / ueu_map_unit_prodi}';

  protected $description = 'Sinkron referensi prodi (tbl_sms) & fakultas (tbl_fak) dari database akademik (PostgreSQL)';

  public function handle(): int
  {
    if (!Schema::hasTable('ueu_ref_prodi')) {
      $this->error('Tabel ueu_ref_prodi belum ada. Jalankan dulu sql/laporan_prodi/01_buat_tabel.sql.');

      return self::FAILURE;
    }

    try {
      $sumber = DB::connection('akademik')->table('tbl_sms')
        ->select('id_sms', 'kode_prodi', 'jenjang_didik', 'nm_lemb', 'npm_prog')->orderBy('kode_prodi')->get();
    } catch (Throwable $e) {
      $this->error('Gagal membaca prodi dari koneksi akademik: ' . $e->getMessage());

      return self::FAILURE;
    }

    $kini = now()->toDateTimeString();
    $data = $sumber
      ->filter(fn ($p) => trim((string) $p->id_sms) !== '')
      ->map(fn ($p) => [
        'id_sms' => trim((string) $p->id_sms),
        'kode_prodi' => trim((string) $p->kode_prodi), // kolom char() di PostgreSQL bisa berisi spasi
        'jenjang_didik' => trim((string) $p->jenjang_didik), // D3 / S1 / S2 / Profesi
        'nm_lemb' => trim((string) $p->nm_lemb),
        'disinkron_pada' => $kini,
      ])->values()->all();

    // id_sms => kode NPM (npm_prog), hanya yang terisi.
    $npm = $sumber
      ->mapWithKeys(fn ($p) => [trim((string) $p->id_sms) => trim((string) $p->npm_prog)])
      ->filter(fn ($k, $id) => $id !== '' && $k !== '')->all();

    // Kolom jenjang_didik ditambahkan belakangan (01b_tambah_jenjang_didik.sql); lewati jika belum ada.
    $adaJenjang = Schema::hasColumn('ueu_ref_prodi', 'jenjang_didik');
    if (!$adaJenjang) {
      $this->warn('Kolom ueu_ref_prodi.jenjang_didik belum ada; jalankan sql/laporan_prodi/01b_tambah_jenjang_didik.sql. jenjang_didik dilewati.');
      $data = array_map(fn ($r) => array_diff_key($r, ['jenjang_didik' => 1]), $data);
    }

    $this->table(['id_sms', 'kode_prodi', 'jenjang_didik', 'kode_npm', 'nm_lemb'],
      array_map(fn ($r) => [$r['id_sms'], $r['kode_prodi'], ($r['jenjang_didik'] ?? '') ?: '—', $npm[$r['id_sms']] ?? '—', $r['nm_lemb']], $data));

    // Baris mapping yang kode_npm-nya akan berubah.
    $adaKolomNpm = Schema::hasTable('ueu_map_unit_prodi') && Schema::hasColumn('ueu_map_unit_prodi', 'kode_npm');
    $ubahNpm = $adaKolomNpm
      ? DB::table('ueu_map_unit_prodi')->whereIn('id_sms', array_keys($npm) ?: [''])->get(['id_map', 'id_sms', 'kode_npm'])
        ->filter(fn ($m) => (string) $m->kode_npm !== $npm[$m->id_sms])
      : collect();
    if (!$adaKolomNpm) {
      $this->warn('Kolom ueu_map_unit_prodi.kode_npm belum ada; jalankan sql/laporan_prodi/01a_tambah_kode_npm.sql. kode_npm dilewati.');
    }

    if ($this->option('dry-run')) {
      $this->sinkronFakultas(true);
      $this->info(count($data) . ' prodi dibaca, ' . count($npm) . ' punya kode NPM; '
        . $ubahNpm->count() . ' mapping unit akan diisi/diubah kode_npm-nya (dry-run, tidak ada yang ditulis).');

      return self::SUCCESS;
    }

    $lama = DB::table('ueu_ref_prodi')->count();
    foreach (array_chunk($data, 200) as $potong) {
      DB::table('ueu_ref_prodi')->upsert($potong, ['id_sms'],
        array_merge(['kode_prodi', 'nm_lemb', 'disinkron_pada'], $adaJenjang ? ['jenjang_didik'] : []));
    }
    $this->info(count($data) . ' prodi disinkron (sebelumnya ' . $lama . ', sekarang ' . DB::table('ueu_ref_prodi')->count() . ' baris).');

    // Salin kode NPM ke mapping unit (hanya baris yang berubah).
    foreach ($ubahNpm as $m) {
      DB::table('ueu_map_unit_prodi')->where('id_map', $m->id_map)->update(['kode_npm' => $npm[$m->id_sms]]);
    }
    if ($adaKolomNpm) {
      $this->info($ubahNpm->count() . ' mapping unit diperbarui kode_npm-nya.');
    }

    $this->sinkronFakultas(false);

    return self::SUCCESS;
  }

  /** Salin tbl_fak (akademik) ke ueu_ref_fakultas, untuk pemetaan unit jenis fakultas (mis. Wakil Dekan). */
  private function sinkronFakultas(bool $dryRun): void
  {
    if (!Schema::hasTable('ueu_ref_fakultas')) {
      $this->warn('Tabel ueu_ref_fakultas belum ada; jalankan sql/laporan_prodi/01c_pemetaan_fakultas.sql. Fakultas dilewati.');

      return;
    }
    try {
      $fak = DB::connection('akademik')->table('tbl_fak')->select('kode_fak', 'nama_fak', 'nama_fak2')->orderBy('kode_fak')->get();
    } catch (Throwable $e) {
      $this->warn('Gagal membaca tbl_fak: ' . $e->getMessage());

      return;
    }
    $kini = now()->toDateTimeString();
    $data = $fak->filter(fn ($f) => trim((string) $f->kode_fak) !== '')->map(fn ($f) => [
      'kode_fak' => trim((string) $f->kode_fak),
      'nama_fak' => trim((string) $f->nama_fak),
      'nama_fak2' => trim((string) $f->nama_fak2),
      'disinkron_pada' => $kini,
    ])->values()->all();

    $this->table(['kode_fak', 'nama_fak2'], array_map(fn ($r) => [$r['kode_fak'], $r['nama_fak2'] ?: $r['nama_fak']], $data));
    if ($dryRun) {
      $this->info(count($data) . ' fakultas dibaca (dry-run).');

      return;
    }
    DB::table('ueu_ref_fakultas')->upsert($data, ['kode_fak'], ['nama_fak', 'nama_fak2', 'disinkron_pada']);
    $this->info(count($data) . ' fakultas disinkron ke ueu_ref_fakultas.');
  }
}
