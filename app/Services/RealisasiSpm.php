<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Realisasi (pembayaran) SPM oleh kasir, padanan Kasir::spm/tunai/transfer v1.
 *
 *   SPM disetujui = ueu_tbl_spp status_spm Disetujui pada T.A, dikelompokkan per no_spm
 *                   (Mspp::get_group_nospm), nominal = Σ ajuan (Mkasir::jml_diminta).
 *   Sudah dibayar = ada baris ueu_tbl_kasir dengan no_spm = nomor urut SPM (segmen 1 dari
 *                   "N/SPM/<kode>/dd/mm/YYYY"), id_unit = unit dari <kode>, tahun = T.A.
 *   Metode        = Transfer bila namabank/norekbank terisi, selain itu Tunai.
 */
class RealisasiSpm
{
  public function __construct(private SppAnggaran $spp)
  {
  }

  /** Semua SPM disetujui pada T.A, terbaru dulu (tgl_spm desc, no_spm desc seperti v1). */
  public function daftar(int $tahun): Collection
  {
    $spm = $this->spp->query()->where('s.tahun', (string) $tahun)->where('s.status_spm', 'Disetujui')
      ->where('s.no_spm', '!=', '')->get()
      ->groupBy('no_spm')->map(fn (Collection $b) => $this->ringkas($b))->values();

    $this->lengkapi($spm, $tahun);

    return $spm->sortByDesc(fn ($s) => ($s->tgl_setuju ?? '0000-00-00') . '|' . str_pad((string) $s->urut, 8, '0', STR_PAD_LEFT))
      ->values();
  }

  /** Satu SPM disetujui berdasarkan id_spp salah satu barisnya, atau null. */
  public function cari(int $tahun, int $idSpp): ?object
  {
    $noSpm = DB::table('ueu_tbl_spp')->where('tahun', (string) $tahun)->where('id_spp', $idSpp)
      ->where('status_spm', 'Disetujui')->where('no_spm', '!=', '')->first(['no_spm'])?->no_spm;
    if ($noSpm === null) {
      return null;
    }

    $baris = $this->spp->query()->where('s.tahun', (string) $tahun)->where('s.status_spm', 'Disetujui')
      ->where('s.no_spm', $noSpm)->get();
    if ($baris->isEmpty()) {
      return null;
    }

    $spm = collect([$this->ringkas($baris)]);
    $this->lengkapi($spm, $tahun);

    return $spm->first();
  }

  /** Nomor BKK (spm_ct v1): "<no_kasir>/BKK/<kode unit>/dd/mm/YYYY" dari tanggal kasir. */
  public static function nomorBkk(object $spm): ?string
  {
    if (!$spm->bayar) {
      return null;
    }
    $tgl = Carbon::parse($spm->bayar->tgl_kasir);

    return $spm->bayar->no_kasir . '/BKK/' . $spm->kode_spm . '/' . $tgl->format('d/m/Y');
  }

  private function ringkas(Collection $b): object
  {
    $s = clone $b->first();
    $p = explode('/', (string) $s->no_spm);
    $s->urut = (int) ($p[0] ?? 0);
    $s->kode_spm = $p[2] ?? (string) $s->kode_unit;
    $s->kode_induk = explode('.', $s->kode_spm)[0] . '.00';
    $s->nominal = (float) $b->sum('ajuan');
    $s->kegiatan = $b->count();
    $s->tgl_setuju = $b->max('tgl_spm');
    $s->baris = $b->values();
    // Penerima pada BKK (paraf_spm v1) — sama untuk semua baris SPM.
    $s->paraf_nama = $b->pluck('paraf_nama')->map(fn ($x) => trim((string) $x))->first(fn ($x) => $x !== '') ?? '';

    return $s;
  }

  private function lengkapi(Collection $spm, int $tahun): void
  {
    $kode = $spm->pluck('kode_spm')->merge($spm->pluck('kode_induk'))->unique()->values()->all();
    $unit = Unit::whereIn('kode', $kode ?: [''])->get(['id_unit', 'kode', 'nama', 'nama_pimp', 'an_nama_pimp'])->keyBy('kode');

    $idUnit = $spm->map(fn ($s) => $unit[$s->kode_spm]->id_unit ?? 0)->unique()->all();
    $kasir = DB::table('ueu_tbl_kasir')->where('tahun', (string) $tahun)
      ->whereIn('no_spm', $spm->pluck('urut')->unique()->all() ?: [0])->whereIn('id_unit', $idUnit ?: [0])
      ->orderBy('id_kasir')->get()
      // Data lama bisa punya baris ganda; pakai pembayaran pertama.
      ->unique(fn ($k) => $k->no_spm . '|' . $k->id_unit)
      ->keyBy(fn ($k) => $k->no_spm . '|' . $k->id_unit);

    $spm->each(function ($s) use ($unit, $kasir) {
      $s->id_unit_spm = $unit[$s->kode_spm]->id_unit ?? null;
      $s->sub_unit = $unit[$s->kode_spm] ?? null;
      $s->induk = $unit[$s->kode_induk] ?? null;
      $s->bayar = $s->id_unit_spm ? ($kasir[$s->urut . '|' . $s->id_unit_spm] ?? null) : null;
      $s->metode = $s->bayar
        ? ((trim((string) $s->bayar->namabank) !== '' || trim((string) $s->bayar->norekbank) !== '') ? 'transfer' : 'tunai')
        : null;
    });
  }
}
