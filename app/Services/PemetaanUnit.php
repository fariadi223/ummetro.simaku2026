<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Pemetaan unit kerja -> prodi ATAU fakultas (ueu_map_unit_prodi.jenis), dipakai Laporan Keuangan Unit.
 *   jenis prodi    : id_sms -> ueu_ref_prodi   (mis. 01.02 Prodi Pendidikan Ekonomi)
 *   jenis fakultas : kode_fak -> ueu_ref_fakultas (mis. 01.01 Wakil Dekan II FKIP)
 * Hanya menulis ke ueu_map_unit_prodi; tabel lama tidak diubah.
 */
class PemetaanUnit
{
  public function siap(): bool
  {
    return Schema::hasTable('ueu_map_unit_prodi') && Schema::hasTable('ueu_ref_prodi');
  }

  /** Pemetaan fakultas tersedia (sql/laporan_prodi/01c_pemetaan_fakultas.sql)? */
  public function adaFakultas(): bool
  {
    return Schema::hasTable('ueu_ref_fakultas') && Schema::hasColumn('ueu_map_unit_prodi', 'jenis');
  }

  /** Semua fakultas referensi (tbl_fak). */
  public function daftarFakultas(): array
  {
    return $this->adaFakultas()
      ? DB::select("SELECT kode_fak, nama_fak, COALESCE(NULLIF(nama_fak2, ''), nama_fak) AS nama FROM ueu_ref_fakultas ORDER BY kode_fak")
      : [];
  }

  /** Kolom kode_npm sudah ditambahkan (sql/laporan_prodi/01a_tambah_kode_npm.sql)? */
  public function adaKodeNpm(): bool
  {
    return Schema::hasColumn('ueu_map_unit_prodi', 'kode_npm');
  }

  /** Kolom jenjang_didik di ueu_ref_prodi (01b_tambah_jenjang_didik.sql); '' jika belum ada. */
  private function jenjang(): string
  {
    return Schema::hasColumn('ueu_ref_prodi', 'jenjang_didik') ? 'p.jenjang_didik' : "''";
  }

  /** Mapping yang ada + data unit & prodi. */
  public function daftar(): array
  {
    $npm = $this->adaKodeNpm() ? 'm.kode_npm' : 'NULL AS kode_npm';

    return DB::select(
      "SELECT m.id_map, m.id_unit, u.kode, u.nama, m.id_sms, m.kode_prg, {$npm}, m.berlaku_dari, p.kode_prodi, p.nm_lemb,
              {$this->jenjang()} AS jenjang_didik, {$this->kolomFak()}
         FROM ueu_map_unit_prodi m
         LEFT JOIN ueu_tbl_unit u ON u.id_unit = m.id_unit
         LEFT JOIN ueu_ref_prodi p ON p.id_sms = m.id_sms
         {$this->joinFak()}
        ORDER BY u.kode, m.id_unit"
    );
  }

  /** Semua unit + mapping saat ini (pilihan di form). */
  public function daftarUnit(): array
  {
    return DB::select(
      "SELECT u.id_unit, u.kode, TRIM(u.nama) AS nama, m.id_map, p.kode_prodi, p.nm_lemb, {$this->jenjang()} AS jenjang_didik,
              {$this->kolomFak()},
              -- usulan fakultas = kode_fak unit induk FF.00 (mis. 01.00 Dekan FKIP -> 01)
              NULLIF(TRIM(i.kode_fak), '') AS usulan_fak,
              -- unit pimpinan fakultas (Dekan / Wakil Dekan / Direktur): usulkan jenis fakultas
              (u.nama REGEXP '(^|[[:space:]])(Wakil[[:space:]]+)?(Dekan|Direktur)') AS pimpinan
         FROM ueu_tbl_unit u
         LEFT JOIN ueu_map_unit_prodi m ON m.id_unit = u.id_unit
         LEFT JOIN ueu_ref_prodi p ON p.id_sms = m.id_sms
         LEFT JOIN ueu_tbl_unit i ON i.kode = CONCAT(LEFT(u.kode, 2), '.00')
         {$this->joinFak()}
        ORDER BY u.kode, u.id_unit"
    );
  }

  /** Semua prodi referensi + kode NPM yang sudah dipakai mapping lain (usulan isian). */
  public function daftarProdi(): array
  {
    $npm = $this->adaKodeNpm()
      ? '(SELECT MAX(m.kode_npm) FROM ueu_map_unit_prodi m WHERE m.id_sms = p.id_sms)'
      : 'NULL';

    return DB::select("SELECT p.id_sms, p.kode_prodi, p.nm_lemb, {$this->jenjang()} AS jenjang_didik, {$npm} AS kode_npm
                         FROM ueu_ref_prodi p ORDER BY p.nm_lemb, p.kode_prodi");
  }

  /** Unit belum terpetakan + jumlah transaksi (03_unit_belum_dipetakan.sql). $tahun null = semua tahun. */
  public function belumDipetakan(?int $tahun): array
  {
    $path = base_path('sql/laporan_prodi/03_unit_belum_dipetakan.sql');
    $sql = is_file($path) ? file_get_contents($path) : false;
    if ($sql === false) {
      throw new RuntimeException("File SQL tidak ditemukan: {$path}");
    }
    $db = DB::connection();
    $db->statement('SET @p_tahun = ?', [$tahun === null ? null : (string) $tahun]);

    return $db->select(rtrim(trim($sql), ';'), [], false);
  }

  public function jumlahProdi(): int
  {
    return DB::table('ueu_ref_prodi')->count();
  }

  public function daftarTahun(): array
  {
    return array_map('intval', DB::table('ueu_tbl_jurnal_spm')->distinct()->orderByDesc('tahun')->pluck('tahun')->all());
  }

  /**
   * Tambah atau ubah mapping satu unit (id_unit UNIQUE). True jika baris baru.
   * $jenis 'prodi' memakai $idSms (+ kode NPM); 'fakultas' memakai $kodeFak (prodi & NPM dikosongkan).
   */
  public function simpan(int $idUnit, string $jenis, ?string $idSms, ?string $kodeFak, ?string $kodeNpm, ?string $berlakuDari): bool
  {
    $baru = !DB::table('ueu_map_unit_prodi')->where('id_unit', $idUnit)->exists();
    $prodi = $jenis === 'prodi';
    $data = [
      'id_sms' => $prodi ? $idSms : null,
      'kode_prg' => $prodi ? DB::table('ueu_ref_prodi')->where('id_sms', $idSms)->value('kode_prodi') : null,
      'berlaku_dari' => $berlakuDari,
    ];
    if ($this->adaFakultas()) {
      $data['jenis'] = $jenis;
      $data['kode_fak'] = $prodi ? null : $kodeFak;
    }
    if ($this->adaKodeNpm()) {
      $data['kode_npm'] = $prodi ? $kodeNpm : null;
    }
    DB::table('ueu_map_unit_prodi')->updateOrInsert(['id_unit' => $idUnit], $data);

    return $baru;
  }

  /** Hapus satu mapping; jumlah baris terhapus. */
  public function hapus(int $idMap): int
  {
    return DB::table('ueu_map_unit_prodi')->where('id_map', $idMap)->delete();
  }

  /** Kolom jenis & fakultas untuk SELECT (nilai bawaan bila 01c belum dijalankan). */
  private function kolomFak(): string
  {
    return $this->adaFakultas()
      ? "m.jenis, m.kode_fak, COALESCE(NULLIF(f.nama_fak2, ''), f.nama_fak) AS nama_fak"
      : "'prodi' AS jenis, NULL AS kode_fak, NULL AS nama_fak";
  }

  private function joinFak(): string
  {
    return $this->adaFakultas() ? 'LEFT JOIN ueu_ref_fakultas f ON f.kode_fak = m.kode_fak' : '';
  }
}
