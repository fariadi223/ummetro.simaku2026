<?php

namespace App\Services;

use App\Http\Controllers\akuntansi\PenyetoranController;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Saldo anggaran unit (Kas di Unit Kerja) per unit induk FF.00 dan sub-unit.
 * Query di sql/saldo_unit/*.sql; parameter lewat variabel sesi (prepared statement). Hanya membaca jurnal.
 */
class SaldoUnit
{
  private const DIR = 'sql/saldo_unit';

  /**
   * Kelompok per FF: [ff => ['kode_induk', 'nama_induk', 'unit' => [baris sub-unit], total kolom...]].
   * Hanya FF yang punya unit induk FF.00 (seperti v1). $awal/$akhir null = tanpa batas tanggal.
   */
  public function rekap(int $tahun, ?string $awal, ?string $akhir): array
  {
    $baris = $this->jalankan('01_saldo_unit.sql', $tahun, $awal, $akhir);
    $unit = DB::table('ueu_tbl_unit')->get(['id_unit', 'kode', 'nama'])->keyBy('id_unit');
    $induk = $unit->filter(fn ($u) => str_ends_with((string) $u->kode, '.00'))->keyBy(fn ($u) => substr((string) $u->kode, 0, 2));

    $grup = [];
    foreach ($baris as $b) {
      $ff = (string) $b->ff;
      if (!isset($induk[$ff])) {
        continue; // v1: kodeunit tanpa unit induk tidak ditampilkan
      }
      $u = $unit[$b->id_uk] ?? null;
      $sub = (object) [
        'ff' => $ff,
        'id_uk' => (int) $b->id_uk,
        'kode' => $u->kode ?? null,
        'nama' => $u ? trim((string) $u->nama) : ((int) $b->id_uk === 0 ? '(Jurnal tanpa unit — data lama)' : '(id_uk ' . $b->id_uk . ' tidak ada)'),
      ];
      foreach (['saldo_awal', 'diterima', 'realisasi', 'disetor', 'lainnya', 'saldo'] as $k) {
        $sub->$k = (float) $b->$k;
      }
      $sub->jml_transaksi = (int) $b->jml_transaksi;

      $grup[$ff] ??= ['kode_induk' => $induk[$ff]->kode, 'nama_induk' => trim((string) $induk[$ff]->nama), 'unit' => [],
        'saldo_awal' => 0.0, 'diterima' => 0.0, 'realisasi' => 0.0, 'disetor' => 0.0, 'lainnya' => 0.0, 'saldo' => 0.0, 'jml_transaksi' => 0];
      $grup[$ff]['unit'][] = $sub;
      foreach (['saldo_awal', 'diterima', 'realisasi', 'disetor', 'lainnya', 'saldo', 'jml_transaksi'] as $k) {
        $grup[$ff][$k] += $sub->$k;
      }
    }
    // Sub-unit urut kode; baris tanpa unit di akhir.
    foreach ($grup as &$g) {
      usort($g['unit'], fn ($a, $b) => [$a->kode === null, $a->kode] <=> [$b->kode === null, $b->kode]);
    }
    unset($g);

    return $grup;
  }

  /** Transaksi Kas di Unit Kerja satu FF (id_uk null) atau satu sub-unit (id_uk, 0 = tanpa unit). */
  public function transaksi(int $tahun, ?string $awal, ?string $akhir, string $ff, ?int $idUk, int $batas): array
  {
    return $this->jalankan('02_transaksi_saldo_unit.sql', $tahun, $awal, $akhir, ['p_ff' => $ff, 'p_id_uk' => $idUk], $batas);
  }

  public function daftarTahun(): array
  {
    return array_map('intval', DB::table('ueu_tbl_jurnal_spm')->distinct()->orderByDesc('tahun')->pluck('tahun')->all());
  }

  private function jalankan(string $file, int $tahun, ?string $awal, ?string $akhir, array $lain = [], ?int $batas = null): array
  {
    $path = base_path(self::DIR . '/' . $file);
    $sql = is_file($path) ? file_get_contents($path) : false;
    if ($sql === false) {
      throw new RuntimeException("File SQL tidak ditemukan: {$path}");
    }
    $param = ['p_tahun' => (string) $tahun, 'p_idrek6' => PenyetoranController::KAS_UNIT, 'p_tgl_awal' => $awal, 'p_tgl_akhir' => $akhir] + $lain;
    $set = implode(', ', array_map(fn ($k) => "@{$k} = ?", array_keys($param))); // nama variabel tetap, bukan input

    $db = DB::connection();
    $db->statement('SET ' . $set, array_values($param));
    $sql = rtrim(trim($sql), ';') . ($batas ? ' LIMIT ' . max(1, $batas) : '');

    // useReadPdo = false: PDO yang sama dengan SET agar variabel sesi terbaca.
    return $db->select($sql, [], false);
  }
}
