<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Unit;
use App\Services\RincianAnggaran;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Realisasi anggaran (/akuntansi/realisasi), padanan Akuntansi::anggaran_search, anggaran_real,
 * jurnal_unit*, jurnal_univ*, jurnal_validasi, jurnalkasunit v1.
 *
 *   Daftar   = SPP ber-SPM Disetujui yang pencairannya sudah dijurnal (jurnalspm CLK/OK) pada T.A,
 *              dipecah per rinci anggaran kegiatannya (get_spm_kegiatan_rinci v1).
 *   Realisasi rinci = Σ jurnal ueu_tbl_jurnal_spm pada idrinci & idrek6 rinci itu
 *              (rek 5 belanja: debet, rek 4 pendapatan: kredit).
 *   Jurnal   = dua baris, uraian = uraian rinci, id_sumber 49 (UKT), lalu jurnalspm = 'OK':
 *              rek 5 → debet rekening belanja, kredit kas
 *              rek 4 → debet kas, kredit rekening pendapatan
 *              kas   = 570 Kas di Unit Kerja (kodeunit = FF unit) atau 569 Kas Universitas.
 *
 * Perbedaan dari v1 (disetujui user 2026-11-08):
 *   - pilihan "Kas Universitas" benar-benar memakai 569 (v1 tetap 570);
 *   - kolom bukti berisi No. Bukti yang diinput (v1 terisi nilai anggaran rinci);
 *   - batas nominal = sisa anggaran rinci (anggaran − realisasi), dan saldo kas hanya dicek untuk belanja;
 *   - filter unit / no. SPM / no. SPP lewat query string, daftar dipaginasi;
 *   - jurnal hanya pada periode berjalan atau T.A yang penjurnalannya dibuka admin (Info::jurnalTerbuka):
 *     selain itu tombol Jurnal (modal) dinonaktifkan & simpan ditolak.
 */
class RealisasiController extends Controller
{
  public const KAS_UNIV = PenyetoranController::KAS_UNIV;
  public const KAS_UNIT = PenyetoranController::KAS_UNIT;
  public const ID_SUMBER = 49;

  public function index(Request $request)
  {
    $tahun = $this->tahun();
    $unitInduk = $this->unitInduk();
    $f = [
      'unit' => $unitInduk->has((string) $request->query('unit')) ? (string) $request->query('unit') : '',
      'spm' => trim((string) $request->query('spm')),
      'spp' => trim((string) $request->query('spp')),
    ];

    $baris = $this->query($tahun)
      ->when($f['unit'] !== '', fn ($q) => $q->where('u.kode', 'like', $f['unit'] . '.%'))
      ->when($f['spm'] !== '', fn ($q) => $q->where('s.no_spm', $f['spm']))
      ->when($f['spp'] !== '', fn ($q) => $q->where('s.no_spp', $f['spp']));

    $total = (clone $baris)->reorder()->count();
    $halaman = max(1, (int) $request->query('page', 1));
    $items = $baris->forPage($halaman, 50)->get();
    $this->lengkapi($items, $tahun, $unitInduk);

    // Saldo kas untuk modal jurnal: Kas di Unit Kerja per FF yang tampil + Kas Universitas.
    $saldoUnit = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->where('idrek6', self::KAS_UNIT)
      ->whereIn('kodeunit', $items->pluck('ff')->filter(fn ($x) => ctype_digit((string) $x))->map(fn ($x) => (int) $x)->unique()->all() ?: [-1])
      ->groupBy('kodeunit')->selectRaw('kodeunit, COALESCE(SUM(debet), 0) - COALESCE(SUM(kredit), 0) as saldo')
      ->pluck('saldo', 'kodeunit')->map(fn ($v) => (float) $v);

    return view('content.akuntansi.realisasi.index', [
      'tahun' => $tahun,
      'terbuka' => Info::jurnalTerbuka($tahun),
      'tahunBerjalan' => Info::tahunPenjurnalan(),
      'f' => $f,
      'saldoUnit' => $saldoUnit,
      'saldoUniv' => $this->saldoKas($tahun, self::KAS_UNIV),
      'unitInduk' => $unitInduk,
      'opsiSpm' => $this->opsiBelumJurnal($tahun, 'no_spm', $f['unit'], $f['spm']),
      'opsiSpp' => $this->opsiBelumJurnal($tahun, 'no_spp', $f['unit'], $f['spp']),
      'daftar' => new LengthAwarePaginator($items, $total, 50, $halaman, [
        'path' => $request->url(), 'query' => array_filter($f, fn ($v) => $v !== ''),
      ]),
    ]);
  }

  /** Simpan jurnal realisasi dari modal di daftar (jurnal_unit/jurnal_univ + *_simpan + jurnal_validasi + jurnalkasunit v1). */
  public function store(Request $request, int $spp, int $rinci)
  {
    $tahun = $this->tahun();
    if (!Info::jurnalTerbuka($tahun)) {
      // Tanpa _baris: modal tidak dibuka lagi.
      return redirect($this->urlKembali($request))
        ->with('error', "Periode T.A {$tahun}/" . ($tahun + 1) . ' bukan periode berjalan dan penjurnalannya tidak dibuka. Jurnal tidak disimpan.');
    }

    try {
      return $this->simpan($request, $spp, $rinci);
    } catch (ValidationException $e) {
      // Kembali ke daftar; modal dibuka lagi untuk baris yang sama (lihat _baris di view).
      return redirect($this->urlKembali($request))->withErrors($e->errors())
        ->withInput($request->except('_token') + ['_baris' => $spp . '-' . $rinci]);
    }
  }

  private function simpan(Request $request, int $spp, int $rinci)
  {
    $tahun = $this->tahun();
    $r = $this->barisAtau404($tahun, $spp, $rinci);

    $request->merge(['nominal' => ($v = preg_replace('/\D/', '', (string) $request->input('nominal'))) === '' ? null : (int) $v]);
    $data = $request->validate([
      'bukti' => ['required', 'string', 'max:100'],
      'tgl_trk' => ['required', 'date_format:Y-m-d'],
      'nominal' => ['required', 'integer', 'min:1', 'max:999999999999'],
      'kas' => ['required', Rule::in(['unit', 'univ'])],
    ], [], [
      'bukti' => 'No. Bukti Transaksi', 'tgl_trk' => 'Tanggal', 'nominal' => 'Besar uang', 'kas' => 'Sumber kas',
    ]);

    $kas = $data['kas'] === 'univ' ? self::KAS_UNIV : self::KAS_UNIT;
    $belanja = $r->koderek1 === '5';

    DB::transaction(function () use ($tahun, $spp, $rinci, $data, $kas, $belanja) {
      // Kunci baris SPP lalu hitung ulang agar dua kiriman bersamaan tidak melewati batas.
      DB::table('ueu_tbl_spp')->where('id_spp', $spp)->lockForUpdate()->first();
      $r = $this->barisAtau404($tahun, $spp, $rinci);

      $batas = [(int) floor($r->sisa) => 'sisa anggaran rinci'];
      if ($belanja) {
        $batas[(int) floor($this->saldoKas($tahun, $kas, $kas === self::KAS_UNIT ? (int) $r->ff : null))] =
          $kas === self::KAS_UNIT ? 'saldo Kas di Unit Kerja' : 'saldo Kas Universitas';
      }
      $maks = min(array_keys($batas));
      if ($data['nominal'] > $maks) {
        throw ValidationException::withMessages(['nominal' => 'Melebihi ' . $batas[$maks] . '. Maksimal Rp ' . number_format(max(0, $maks), 0, ',', '.') . '.']);
      }

      $baris = fn (int $idrek6, int $debet, int $kredit) => [
        'tahun' => (string) $tahun,
        'id_uk' => (int) $r->idunit,
        'kodeunit' => (int) $r->ff,
        'idkeg' => (int) $r->idkeg,
        'idtama' => 0,
        'id_sumber' => self::ID_SUMBER,
        'idrek6' => $idrek6,
        'idrinci' => (int) $r->idrinci,
        'idrinci_na' => 0,
        'tgl_trk' => $data['tgl_trk'],
        'uraian' => (string) $r->uraianrinci,
        'bukti' => trim($data['bukti']),
        'debet' => $debet,
        'kredit' => $kredit,
        'ket' => '',
        'tgl_trans' => '',
      ];
      $n = $data['nominal'];

      DB::table('ueu_tbl_jurnal_spm')->insert($belanja
        ? [$baris((int) $r->idrek6, $n, 0), $baris($kas, 0, $n)]
        : [$baris($kas, $n, 0), $baris((int) $r->idrek6, 0, $n)]);
      DB::table('ueu_tbl_spp')->where('id_spp', $spp)->update(['jurnalspm' => 'OK']);
    });

    return redirect($this->urlKembali($request))->with('status',
      'Realisasi "' . $r->uraianrinci . '" (' . $r->no_spm . ') sebesar Rp ' . number_format($data['nominal'], 0, ',', '.')
      . ' dijurnal dari ' . ($kas === self::KAS_UNIV ? 'Kas Universitas' : 'Kas di Unit Kerja') . '.');
  }

  /** SPP × rinci kegiatan (get_spm_kegiatan_rinci v1). */
  private function query(int $tahun): Builder
  {
    return DB::table('ueu_tbl_spp as s')
      ->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 's.idkeg')
      ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'k.idunit')
      ->join('ueu_tbl_mak4 as m4', 'm4.idmak4', '=', 'k.idmak4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->join('ueu_tbl_rinci as r', 'r.idkeg', '=', 's.idkeg')
      ->join('ueu_tbl_rek6 as r6', 'r6.idrek6', '=', 'r.idrek6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->where('s.tahun', (string) $tahun)->where('s.status_spm', 'Disetujui')->where('s.jurnalspm', '!=', '')
      ->orderBy('s.no_spm')->orderBy('m1.kodemak1')->orderBy('m2.kodemak2')->orderBy('m3.kodemak3')->orderBy('m4.kodemak4')
      ->orderBy('r.idrinci')
      ->select('s.id_spp', 's.no_spp', 's.no_spm', 's.jurnalspm', 's.idkeg', 'k.idunit', 'u.kode as kode_unit', 'u.nama as nama_unit',
        'm4.namamak4', 'r.idrinci', 'r.idrek6', 'r.uraianrinci', 'r6.namarek6', 'r1.koderek1',
        DB::raw("CONCAT(m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode_mak"),
        DB::raw('CONCAT(r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode_rek'),
        DB::raw(RincianAnggaran::SQL_NILAI . ' as anggaran'));
  }

  /** Tambah realisasi, sisa, unit induk, FF ke setiap baris. */
  private function lengkapi(Collection $items, int $tahun, Collection $unitInduk): void
  {
    $real = DB::table('ueu_tbl_jurnal_spm as j')
      ->join('ueu_tbl_rinci as r', fn ($j) => $j->on('r.idrinci', '=', 'j.idrinci')->on('r.idrek6', '=', 'j.idrek6'))
      ->where('j.tahun', (string) $tahun)->whereIn('j.idrinci', $items->pluck('idrinci')->unique()->all() ?: [0])
      ->groupBy('j.idrinci')
      ->selectRaw('j.idrinci, COALESCE(SUM(j.debet), 0) as debet, COALESCE(SUM(j.kredit), 0) as kredit')
      ->get()->keyBy('idrinci');

    $items->each(function ($b) use ($real, $unitInduk) {
      $j = $real[$b->idrinci] ?? null;
      $b->koderek1 = (string) $b->koderek1; // kolom int di tabel lama
      $b->anggaran = (float) $b->anggaran;
      $b->realisasi = $j ? (float) ($b->koderek1 === '5' ? $j->debet : $j->kredit) : 0.0;
      $b->sisa = $b->anggaran - $b->realisasi;
      $b->ff = explode('.', (string) $b->kode_unit)[0];
      $b->induk = $unitInduk[$b->ff] ?? null;
      $b->bisa = in_array($b->koderek1, ['4', '5'], true) && $b->sisa > 0 && $b->induk && ctype_digit($b->ff);
    });
  }

  private function barisAtau404(int $tahun, int $spp, int $rinci): object
  {
    $r = $this->query($tahun)->where('s.id_spp', $spp)->where('r.idrinci', $rinci)->first() ?? abort(404);
    $this->lengkapi(collect([$r]), $tahun, $this->unitInduk());
    abort_unless(in_array($r->koderek1, ['4', '5'], true) && $r->induk && ctype_digit($r->ff), 404);

    return $r;
  }

  /**
   * No. SPM / SPP yang belum dijurnal realisasinya (jurnalspm = CLK) pada T.A, opsional per unit.
   * Nilai yang sedang dipilih tetap disertakan agar filter tidak hilang setelah rinci pertama dijurnal.
   */
  private function opsiBelumJurnal(int $tahun, string $kolom, string $unit, string $pilih): Collection
  {
    $opsi = DB::table('ueu_tbl_spp as s')->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 's.idkeg')
      ->join('ueu_tbl_unit as u', 'u.id_unit', '=', 'k.idunit')
      ->where('s.tahun', (string) $tahun)->where('s.status_spm', 'Disetujui')->where('s.jurnalspm', 'CLK')
      ->where("s.$kolom", '!=', '')
      ->when($unit !== '', fn ($q) => $q->where('u.kode', 'like', $unit . '.%'))
      ->distinct()->pluck("s.$kolom");
    if ($pilih !== '' && !$opsi->contains($pilih)) {
      $opsi->push($pilih);
    }

    return $opsi->sortBy(fn ($n) => str_pad((string) (int) $n, 6, '0', STR_PAD_LEFT) . $n)->values();
  }

  /** Saldo kas (debet − kredit) T.A; kodeunit hanya untuk Kas di Unit Kerja. */
  private function saldoKas(int $tahun, int $idrek6, ?int $kodeunit = null): float
  {
    $r = DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->where('idrek6', $idrek6)
      ->when($kodeunit !== null, fn ($q) => $q->where('kodeunit', $kodeunit))
      ->selectRaw('COALESCE(SUM(debet), 0) - COALESCE(SUM(kredit), 0) as saldo')->first();

    return (float) ($r->saldo ?? 0);
  }

  /** Unit induk "FF.00" diindeks dengan "FF" (get_unit00 v1). */
  private function unitInduk(): Collection
  {
    return Unit::where('kode', 'like', '%.00')->orderBy('kode')->get(['id_unit', 'kode', 'nama'])
      ->keyBy(fn ($u) => explode('.', $u->kode)[0]);
  }

  /** Kembali ke daftar dengan filter yang sama (hanya parameter yang dikenal). */
  private function urlKembali(Request $request): string
  {
    $q = array_filter([
      'unit' => (string) $request->input('f_unit', $request->query('unit', '')),
      'spm' => (string) $request->input('f_spm', $request->query('spm', '')),
      'spp' => (string) $request->input('f_spp', $request->query('spp', '')),
      'page' => (string) $request->input('f_page', $request->query('page', '')),
    ], fn ($v) => $v !== '');

    return route('akuntansi.realisasi.index', $q);
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
