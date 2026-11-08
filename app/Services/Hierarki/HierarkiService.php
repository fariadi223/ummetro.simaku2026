<?php

namespace App\Services\Hierarki;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Data kode berjenjang di tabel lama SIMAKU, dengan pola yang sama:
 *
 *   {awalanTabel}{n}  : tabel level n, mis. ueu_tbl_rek3 / ueu_tbl_mak3
 *   id{x}{n}          : primary key, mis. idrek3 / idmak3
 *   id{x}{n-1}        : penunjuk ke induk (level > 1)
 *   kode{x}{n}        : kode di level itu
 *   nama{x}{n}        : nama
 *
 * Turunan cukup mengisi konfigurasi (lihat CoaService, MakService).
 */
abstract class HierarkiService
{
  /** Awalan nama tabel, mis. "ueu_tbl_rek". */
  protected string $awalanTabel;

  /** Awalan nama kolom, mis. "rek" -> idrek1, koderek1, namarek1. */
  protected string $awalanKolom;

  protected int $levelMax;

  /**
   * Batas kolom per level: ['kode' => digit maks, 'nama' => karakter maks,
   * 'kode_int' => true bila kolom kode bertipe INT ("01" disimpan 1)].
   */
  protected array $panjang = [];

  /**
   * Kolom tambahan per level:
   *   [level => [kolom => ['label' => .., 'aturan' => [...], 'bawaan' => ..]]]
   * Nilai bawaan dipakai saat input kosong (kolom NOT NULL tanpa default).
   */
  protected array $kolomTambahan = [];

  /** Tabel punya kolom deleted_at (berisi CURRENT_TIMESTAMP bawaan, lihat CoaService). */
  protected bool $adaDeletedAt = false;

  public function levelMax(): int
  {
    return $this->levelMax;
  }

  public function cekLevel(int $level): void
  {
    if ($level < 1 || $level > $this->levelMax) {
      throw new InvalidArgumentException("Level tidak valid: {$level}");
    }
  }

  public function tabel(int $level): string
  {
    $this->cekLevel($level);

    return $this->awalanTabel . $level;
  }

  public function pk(int $level): string
  {
    return "id{$this->awalanKolom}{$level}";
  }

  public function kolomKode(int $level): string
  {
    return "kode{$this->awalanKolom}{$level}";
  }

  public function kolomNama(int $level): string
  {
    return "nama{$this->awalanKolom}{$level}";
  }

  public function kolomInduk(int $level): ?string
  {
    return $level > 1 ? $this->pk($level - 1) : null;
  }

  public function panjang(int $level): array
  {
    $this->cekLevel($level);

    return $this->panjang[$level] + ['kode_int' => false];
  }

  /** Definisi kolom tambahan untuk level ini ([] bila tidak ada). */
  public function tambahan(int $level): array
  {
    return $this->kolomTambahan[$level] ?? [];
  }

  /**
   * Query satu level lengkap dengan kode semua induknya. Kolom hasil:
   * id, induk_id, kode, nama, kode_lengkap, kode_1..kode_N, + kolom tambahan.
   */
  public function query(int $level): Builder
  {
    $this->cekLevel($level);

    $q = DB::table($this->tabel($level) . " as t{$level}");

    for ($i = $level - 1; $i >= 1; $i--) {
      $q->join($this->tabel($i) . " as t{$i}", "t{$i}." . $this->pk($i), '=', 't' . ($i + 1) . '.' . $this->pk($i));
    }

    $kolom = [
      "t{$level}." . $this->pk($level) . ' as id',
      "t{$level}." . $this->kolomKode($level) . ' as kode',
      "t{$level}." . $this->kolomNama($level) . ' as nama',
    ];
    if ($level > 1) {
      $kolom[] = "t{$level}." . $this->kolomInduk($level) . ' as induk_id';
    }
    foreach (array_keys($this->tambahan($level)) as $k) {
      $kolom[] = "t{$level}.{$k}";
    }

    $bagian = [];
    for ($i = 1; $i <= $level; $i++) {
      $kolom[] = "t{$i}." . $this->kolomKode($i) . " as kode_{$i}";
      $bagian[] = "t{$i}." . $this->kolomKode($i);
      $q->orderBy("t{$i}." . $this->kolomKode($i));
    }

    $kolom[] = DB::raw($this->sqlKodeLengkap($level) . ' as kode_lengkap');

    return $q->select($kolom);
  }

  private function sqlKodeLengkap(int $level): string
  {
    $bagian = array_map(fn ($i) => "t{$i}." . $this->kolomKode($i), range(1, $level));

    return "CONCAT_WS('.', " . implode(', ', $bagian) . ')';
  }

  public function cari(int $level, int $id): ?object
  {
    return $this->query($level)->where("t{$level}." . $this->pk($level), $id)->first();
  }

  /** Anak langsung dari sebuah node (atau semua level 1 bila $indukId null). */
  public function anak(int $level, ?int $indukId): Collection
  {
    $q = $this->query($level);

    if ($level > 1) {
      $q->where("t{$level}." . $this->kolomInduk($level), $indukId);
    }

    $hasil = $q->get();

    if ($level < $this->levelMax && $hasil->isNotEmpty()) {
      $jumlah = DB::table($this->tabel($level + 1))
        ->whereIn($this->pk($level), $hasil->pluck('id'))
        ->groupBy($this->pk($level))
        ->selectRaw($this->pk($level) . ' as induk, COUNT(*) as n')
        ->pluck('n', 'induk');

      $hasil->each(fn ($r) => $r->jumlah_anak = (int) ($jumlah[$r->id] ?? 0));
    }

    return $hasil;
  }

  /** Rantai induk dari level 1 sampai node ini. */
  public function jejak(int $level, int $id): array
  {
    $jejak = [];

    for ($l = $level; $l >= 1 && $id; $l--) {
      $row = DB::table($this->tabel($l))->where($this->pk($l), $id)->first();
      if (!$row) {
        break;
      }
      array_unshift($jejak, [
        'level' => $l,
        'id' => $id,
        'kode' => $row->{$this->kolomKode($l)},
        'nama' => $row->{$this->kolomNama($l)},
      ]);
      $id = $l > 1 ? $row->{$this->kolomInduk($l)} : null;
    }

    $kode = [];
    foreach ($jejak as &$j) {
      $kode[] = $j['kode'];
      $j['kode_lengkap'] = implode('.', $kode);
    }

    return $jejak;
  }

  public function kodeDipakai(int $level, ?int $indukId, string $kode, ?int $kecualiId = null): bool
  {
    $q = DB::table($this->tabel($level))->where($this->kolomKode($level), $kode);

    if ($level > 1) {
      $q->where($this->kolomInduk($level), $indukId);
    }
    if ($kecualiId) {
      $q->where($this->pk($level), '!=', $kecualiId);
    }

    return $q->exists();
  }

  /** @param array $tambahan nilai kolom tambahan level ini */
  public function simpan(int $level, ?int $indukId, string $kode, string $nama, array $tambahan = []): int
  {
    $data = [$this->kolomKode($level) => $kode, $this->kolomNama($level) => $nama];

    if ($level > 1) {
      $data[$this->kolomInduk($level)] = $indukId;
    }
    if ($this->adaDeletedAt) {
      $data['deleted_at'] = null;
    }

    $data += $this->isiTambahan($level, $tambahan);

    return DB::table($this->tabel($level))->insertGetId($data, $this->pk($level));
  }

  public function ubah(int $level, int $id, string $kode, string $nama, array $tambahan = []): void
  {
    $data = [$this->kolomKode($level) => $kode, $this->kolomNama($level) => $nama]
      + $this->isiTambahan($level, $tambahan);

    DB::table($this->tabel($level))->where($this->pk($level), $id)->update($data);
  }

  private function isiTambahan(int $level, array $nilai): array
  {
    $data = [];
    foreach ($this->tambahan($level) as $kolom => $def) {
      $v = $nilai[$kolom] ?? null;
      $data[$kolom] = ($v === null || $v === '') ? ($def['bawaan'] ?? null) : $v;
    }

    return $data;
  }

  /**
   * Alasan sebuah node tidak boleh dihapus, atau null bila aman.
   * (v1 menghapus langsung tanpa cek apa pun.)
   */
  public function alasanTidakBolehHapus(int $level, int $id): ?string
  {
    if ($level < $this->levelMax) {
      $anak = DB::table($this->tabel($level + 1))->where($this->pk($level), $id)->count();
      if ($anak > 0) {
        return "Masih memiliki {$anak} sub-data. Hapus sub-datanya terlebih dahulu.";
      }
    }

    foreach ($this->tabelPemakai($level) as [$tabel, $kolom]) {
      if (DB::table($tabel)->where($kolom, $id)->exists()) {
        return "Sudah dipakai di tabel {$tabel}, sehingga tidak dapat dihapus.";
      }
    }

    return null;
  }

  public function hapus(int $level, int $id): void
  {
    DB::table($this->tabel($level))->where($this->pk($level), $id)->delete();
  }

  /**
   * Salin semua turunan node sumber ke node tujuan pada level yang sama.
   * Kode yang sudah ada di tujuan tidak diduplikasi; isinya digabung.
   *
   * @return int jumlah data baru yang dibuat
   */
  public function salinSubData(int $level, int $sumberId, int $tujuanId): int
  {
    if ($level >= $this->levelMax) {
      return 0;
    }

    return DB::transaction(fn () => $this->salinRekursif($level + 1, $sumberId, $tujuanId));
  }

  private function salinRekursif(int $level, int $sumberIndukId, int $tujuanIndukId): int
  {
    $dibuat = 0;
    $induk = $this->kolomInduk($level);

    foreach (DB::table($this->tabel($level))->where($induk, $sumberIndukId)->get() as $row) {
      $kode = $row->{$this->kolomKode($level)};

      $tujuanId = DB::table($this->tabel($level))
        ->where($induk, $tujuanIndukId)
        ->where($this->kolomKode($level), $kode)
        ->value($this->pk($level));

      if (!$tujuanId) {
        $tambahan = [];
        foreach (array_keys($this->tambahan($level)) as $k) {
          $tambahan[$k] = $row->{$k};
        }
        $tujuanId = $this->simpan($level, $tujuanIndukId, $kode, $row->{$this->kolomNama($level)}, $tambahan);
        $dibuat++;
      }

      if ($level < $this->levelMax) {
        $dibuat += $this->salinRekursif($level + 1, $row->{$this->pk($level)}, $tujuanId);
      }
    }

    return $dibuat;
  }

  /** Cari di semua level (atau satu level) berdasarkan kode lengkap atau nama. */
  public function cariGlobal(string $kata, int $batas = 50, ?int $level = null): Collection
  {
    $hasil = collect();

    foreach ($level ? [$level] : range(1, $this->levelMax) as $l) {
      $q = $this->query($l)->where(function ($w) use ($l, $kata) {
        $w->where("t{$l}." . $this->kolomNama($l), 'like', "%{$kata}%")
          ->orWhereRaw($this->sqlKodeLengkap($l) . ' LIKE ?', ["{$kata}%"]);
      });

      $q->limit($batas)->get()->each(function ($r) use ($l, $hasil) {
        $r->level = $l;
        $hasil->push($r);
      });
    }

    return $hasil->sortBy('kode_lengkap', SORT_NATURAL)->take($batas)->values();
  }

  /** Jumlah data per level: [1 => n, ...]. */
  public function statistik(): array
  {
    $jumlah = [];
    for ($l = 1; $l <= $this->levelMax; $l++) {
      $jumlah[$l] = DB::table($this->tabel($l))->count();
    }

    return $jumlah;
  }

  /** Seluruh data dalam urutan pohon (1, 1.1, 1.1.01, ...), untuk ekspor. */
  public function semua(): Collection
  {
    $baris = collect();

    for ($l = 1; $l <= $this->levelMax; $l++) {
      $this->query($l)->get()->each(function ($r) use ($l, $baris) {
        $r->level = $l;
        $baris->push($r);
      });
    }

    return $baris->sort(function ($a, $b) {
      for ($i = 1; $i <= $this->levelMax; $i++) {
        $x = $a->{"kode_{$i}"} ?? null;
        $y = $b->{"kode_{$i}"} ?? null;
        if ($x === $y) {
          continue;
        }
        if ($x === null) {
          return -1;
        }
        if ($y === null) {
          return 1;
        }

        return strnatcmp((string) $x, (string) $y);
      }

      return 0;
    })->values();
  }

  /**
   * Semua [tabel, kolom] di luar hierarki ini yang menunjuk ke level tsb,
   * dideteksi dari skema (kolom bernama id{x}{n} atau id_{x}{n}).
   * Tabel salinan/arsip hierarki itu sendiri (mis. ueu_tbl_rek4_tmp...) dilewati.
   */
  private function tabelPemakai(int $level): array
  {
    $kolom = [$this->pk($level), "id_{$this->awalanKolom}{$level}"];

    return Cache::remember("hierarki.pemakai.{$this->awalanTabel}{$level}", now()->addDay(), function () use ($kolom) {
      return DB::table('information_schema.columns')
        ->where('table_schema', DB::getDatabaseName())
        ->whereIn('column_name', $kolom)
        ->where('table_name', 'not like', str_replace('_', '\_', $this->awalanTabel) . '%')
        ->get(['table_name', 'column_name'])
        ->map(fn ($r) => [$r->table_name ?? $r->TABLE_NAME, $r->column_name ?? $r->COLUMN_NAME])
        ->all();
    });
  }
}
