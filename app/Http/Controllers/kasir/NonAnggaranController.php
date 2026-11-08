<?php

namespace App\Http\Controllers\kasir;

use App\Http\Controllers\Controller;
use App\Models\Info;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * BKK non anggaran oleh kasir (/kasir/non-anggaran), padanan Kasir::input_non_anggaran,
 * simpan_non_anggaran, non_anggaran, bkk_json, bkk_tunai, bkk_hapus, bkk_tunai_ct/cp v1.
 *
 *   Input  : ueu_tbl_bkk (no_bkk "N/BKK-UMM/<T.A>") + rincian ueu_tbl_bkk_rinci;
 *            nominal BKK = Σ biaya rincian.
 *   Tunai  : status_bkk = Tunai, tgl_tunai = hari ini, lalu cetak BKK. BKK Tunai dibaca
 *            modul Akuntansi (jurnal BKK), jadi tidak bisa dihapus lagi.
 *   Hapus  : hanya BKK yang belum Tunai (beserta rinciannya).
 *
 * Perbedaan dari v1: nomor urut = nomor terbesar T.A + 1 (v1: jumlah baris + 1, bisa ganda
 * setelah ada BKK dihapus), rincian ikut terhapus, isian wajib divalidasi, aksi tulis lewat POST/DELETE.
 */
class NonAnggaranController extends Controller
{
  /** ?cari=, ?rentang= (preset / YYYY-MM / khusus) + ?dari=&sampai= (Y-m-d) untuk rentang khusus, ?page= */
  public function index(Request $request)
  {
    $tahun = $this->tahun();
    $cari = trim((string) $request->query('cari', ''));
    [$rentang, $dari, $sampai] = $this->rentang($request);

    $q = DB::table('ueu_tbl_bkk')->where('tahun', (string) $tahun)
      ->when($dari, fn ($q) => $q->where('tgl_bkk', '>=', $dari->toDateString()))
      ->when($sampai, fn ($q) => $q->where('tgl_bkk', '<=', $sampai->toDateString()))
      ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('no_bkk', 'like', "%{$cari}%")
        ->orWhere('uraian', 'like', "%{$cari}%")->orWhere('penerima', 'like', "%{$cari}%")
        ->orWhere('no_ref', 'like', "%{$cari}%")->orWhere('tgl_bkk', 'like', "%{$cari}%")));

    $daftar = (clone $q)->orderByDesc('id_bkk')->paginate(25)->withQueryString();
    $rinci = DB::table('ueu_tbl_bkk_rinci')->whereIn('bkk_id', $daftar->pluck('id_bkk')->all() ?: [0])
      ->orderBy('id')->get()->groupBy('bkk_id');
    $unit = $this->unitDari($daftar->getCollection());

    $semua = DB::table('ueu_tbl_bkk')->where('tahun', (string) $tahun)
      ->selectRaw("COUNT(*) as n, COALESCE(SUM(nominal), 0) as total, SUM(status_bkk = 'Tunai') as tunai")->first();

    // Pilihan bulan = bulan yang punya BKK pada T.A, terbaru dulu.
    $bulan = DB::table('ueu_tbl_bkk')->where('tahun', (string) $tahun)
      ->selectRaw("DATE_FORMAT(tgl_bkk, '%Y-%m') as bln, COUNT(*) as n")->groupBy('bln')->orderByDesc('bln')
      ->pluck('n', 'bln');

    return view('content.kasir.non-anggaran.index', [
      'tahun' => $tahun,
      'daftar' => $daftar,
      'rinci' => $rinci,
      'unit' => $unit,
      'cari' => $cari,
      'rentang' => $rentang,
      'dari' => $dari,
      'sampai' => $sampai,
      'bulan' => $bulan,
      'difilter' => $cari !== '' || $rentang !== '',
      'total' => (float) (clone $q)->sum('nominal'),
      'ringkas' => $semua,
    ]);
  }

  /**
   * Rentang tanggal BKK dari query string: [kunci, dari, sampai] (Carbon|null).
   * Kunci: hari-ini, 7-hari, bulan-ini, bulan-lalu, YYYY-MM, khusus (pakai dari & sampai).
   */
  private function rentang(Request $request): array
  {
    $kunci = (string) $request->query('rentang', '');
    $tgl = function ($v) {
      try {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? Carbon::createFromFormat('Y-m-d', $v)->startOfDay() : null;
      } catch (\Throwable) {
        return null;
      }
    };
    $hariIni = today();

    [$dari, $sampai] = match (true) {
      $kunci === 'hari-ini' => [$hariIni->copy(), $hariIni->copy()],
      $kunci === '7-hari' => [$hariIni->copy()->subDays(6), $hariIni->copy()],
      $kunci === 'bulan-ini' => [$hariIni->copy()->startOfMonth(), $hariIni->copy()->endOfMonth()],
      $kunci === 'bulan-lalu' => [$hariIni->copy()->subMonthNoOverflow()->startOfMonth(), $hariIni->copy()->subMonthNoOverflow()->endOfMonth()],
      (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $kunci) => [Carbon::createFromFormat('Y-m-d', $kunci . '-01')->startOfDay(),
        Carbon::createFromFormat('Y-m-d', $kunci . '-01')->endOfMonth()->startOfDay()],
      $kunci === 'khusus' => [$tgl($request->query('dari')), $tgl($request->query('sampai'))],
      default => [null, null],
    };

    if ($dari && $sampai && $dari->gt($sampai)) {
      [$dari, $sampai] = [$sampai, $dari];
    }
    if (!$dari && !$sampai) {
      $kunci = '';
    }

    return [$kunci, $dari, $sampai];
  }

  public function create()
  {
    $tahun = $this->tahun();

    // Panel kanan: BKK yang sudah diinput tetapi belum ditunaikan (terlama dulu).
    $belum = DB::table('ueu_tbl_bkk')->where('tahun', (string) $tahun)->where('status_bkk', '!=', 'Tunai');

    return view('content.kasir.non-anggaran.form', [
      'tahun' => $tahun,
      'nomorBkk' => $this->nomorBerikut($tahun),
      'belumTunai' => (clone $belum)->orderBy('id_bkk')->limit(15)->get(),
      'unitPilihan' => $this->unitPilihan(),
      'unitAktif' => $this->adaKolomUnit(),
      'belumTunaiRingkas' => (clone $belum)->selectRaw('COUNT(*) as n, COALESCE(SUM(nominal), 0) as total')->first(),
    ]);
  }

  public function store(Request $request)
  {
    // Rupiah boleh diketik dengan pemisah ribuan ("1.250.000").
    $angka = fn ($v) => ($v = preg_replace('/\D/', '', (string) $v)) === '' ? null : (int) $v;
    $request->merge([
      'ppn' => $angka($request->input('ppn')) ?? 0,
      'pph' => $angka($request->input('pph')) ?? 0,
      'rinci' => collect($request->input('rinci', []))
        ->map(fn ($r) => ['uraian' => trim((string) ($r['uraian'] ?? '')), 'biaya' => $angka($r['biaya'] ?? null)])
        ->filter(fn ($r) => $r['uraian'] !== '' || $r['biaya'] !== null)->values()->all(),
    ]);

    $data = $request->validate([
      'nomref' => ['nullable', 'string', 'max:100'],
      'id_unit' => ['required', 'integer', Rule::exists('ueu_tbl_unit', 'id_unit')->where(fn ($q) => $q->where(
        fn ($w) => $w->where('kode', 'not like', '%.00')->orWhereIn('kode', $this->indukBoleh())
      ))],
      'terimadari' => ['required', 'string', 'max:60'],
      'uraian_kegiatan' => ['required', 'string', 'max:5000'],
      'rinci' => ['required', 'array', 'min:1'],
      'rinci.*.uraian' => ['nullable', 'string', 'max:255'],
      'rinci.*.biaya' => ['required', 'integer', 'min:1', 'max:999999999999'],
      'penerima' => ['required', 'string', 'max:100'],
      'ppn' => ['integer', 'min:0', 'max:999999999999'],
      'pph' => ['integer', 'min:0', 'max:999999999999'],
    ], [
      'rinci.required' => 'Isi minimal satu rincian biaya.',
      'rinci.*.biaya.required' => 'Biaya rincian ke-:position wajib diisi.',
      'rinci.*.biaya.min' => 'Biaya rincian ke-:position minimal Rp 1.',
    ], [
      'nomref' => 'Nomor Ref.', 'id_unit' => 'Unit Kerja', 'terimadari' => 'Sudah Terima Dari', 'uraian_kegiatan' => 'Uraian Kegiatan',
      'penerima' => 'Penerima', 'ppn' => 'PPN', 'pph' => 'PPH',
    ]);

    $tahun = $this->tahun();

    $nomor = DB::transaction(function () use ($tahun, $data) {
      // Kunci baris T.A agar dua kasir tidak mendapat nomor yang sama.
      DB::table('ueu_tbl_bkk')->where('tahun', (string) $tahun)->lockForUpdate()->get(['id_bkk']);
      $nomor = $this->nomorBerikut($tahun);
      $hariIni = today()->toDateString();

      // Semua kolom NOT NULL tanpa default (v1 berjalan non-strict) diisi eksplisit.
      $id = DB::table('ueu_tbl_bkk')->insertGetId(($this->adaKolomUnit() ? ['id_unit' => (int) $data['id_unit']] : []) + [
        'tahun' => (string) $tahun,
        'no_bkk' => $nomor,
        'no_ref' => trim((string) ($data['nomref'] ?? '')),
        'tgl_bkk' => $hariIni,
        'terima_dari' => trim($data['terimadari']),
        'nominal' => array_sum(array_column($data['rinci'], 'biaya')),
        'uraian' => trim($data['uraian_kegiatan']),
        'ppn' => $data['ppn'],
        'pph' => $data['pph'],
        'penerima' => trim($data['penerima']),
        'status_bkk' => '',
        'tgl_tunai' => $hariIni,
        'otorisasi' => '',
        'jurnalspm' => '',
      ], 'id_bkk');

      DB::table('ueu_tbl_bkk_rinci')->insert(array_map(fn ($r) => [
        'bkk_id' => $id, 'uraian' => $r['uraian'], 'biaya' => $r['biaya'], 'realisasi' => 0,
      ], $data['rinci']));

      return $nomor;
    });

    return redirect()->route('kasir.non-anggaran.create')
      ->with('status', "BKK {$nomor} disimpan. Proses Tunai di daftar BKK untuk mencetaknya.");
  }

  /** Tunai (Kasir::bkk_tunai v1), lalu cetak BKK. */
  public function tunai(int $id)
  {
    $bkk = $this->bkk($id);
    if ($bkk->status_bkk === 'Tunai') {
      return back()->with('error', "BKK {$bkk->no_bkk} sudah tunai.");
    }

    DB::table('ueu_tbl_bkk')->where('id_bkk', $id)->where('status_bkk', '!=', 'Tunai')
      ->update(['status_bkk' => 'Tunai', 'tgl_tunai' => today()->toDateString()]);

    // Cetakan pertama setelah tunai = asli; cetak berikutnya diberi watermark COPY.
    session()->put("bkk_asli.na.{$id}", true);

    return back()->with('status', "BKK {$bkk->no_bkk} sudah tunai.")
      ->with('cetak', route('kasir.non-anggaran.cetak', $id));
  }

  /** Hapus BKK yang belum Tunai (Kasir::bkk_hapus v1). */
  public function destroy(int $id)
  {
    $bkk = $this->bkk($id);
    if ($bkk->status_bkk === 'Tunai') {
      return back()->with('error', "BKK {$bkk->no_bkk} sudah tunai dan tidak dapat dihapus.");
    }

    DB::transaction(function () use ($id) {
      DB::table('ueu_tbl_bkk_rinci')->where('bkk_id', $id)->delete();
      DB::table('ueu_tbl_bkk')->where('id_bkk', $id)->where('status_bkk', '!=', 'Tunai')->delete();
    });

    return back()->with('status', "BKK {$bkk->no_bkk} dihapus.");
  }

  /** Cetak BKK non anggaran (bkk_ct_rinci / bkk_cp v1). */
  public function cetak(int $id)
  {
    $bkk = $this->bkk($id);
    if ($bkk->status_bkk !== 'Tunai') {
      return redirect()->route('kasir.non-anggaran.index')->with('error', "BKK {$bkk->no_bkk} belum tunai.");
    }

    $ttd = config('simaku.bkk_ttd');

    return view('content.kasir.non-anggaran.cetak', [
      'bkk' => $bkk,
      'unit' => $this->unitDari(collect([$bkk]))->first(),
      'rinci' => DB::table('ueu_tbl_bkk_rinci')->where('bkk_id', $id)->orderBy('id')->get(),
      'ttd' => $ttd,
      // v1: kolom "Mengetahui Rektor" hanya untuk BKK di atas Rp 5.000.000.
      // ?lihat=1 = tampilan baca saja: tanpa watermark & tidak memakai jatah cetakan asli.
      'salinan' => request()->boolean('lihat') ? false : !session()->pull("bkk_asli.na.{$id}", false),
      'denganRektor' => (float) $bkk->nominal > (float) ($ttd['batas_rektor'] ?? 5000000),
      'tahun' => $this->tahun(),
    ]);
  }

  /**
   * Sub unit (kode bukan "*.00"), dikelompokkan per unit induk "FF.00" untuk select.
   * Pengecualian: unit induk di simaku.bkk_unit_induk_boleh (default 00.00 Rektorat) ikut bisa dipilih.
   */
  private function unitPilihan()
  {
    $semua = DB::table('ueu_tbl_unit')->orderBy('kode')->get(['id_unit', 'kode', 'nama']);
    $induk = $semua->filter(fn ($u) => str_ends_with((string) $u->kode, '.00'))->keyBy(fn ($u) => explode('.', $u->kode)[0]);
    $boleh = $this->indukBoleh();

    return $semua->reject(fn ($u) => str_ends_with((string) $u->kode, '.00') && !in_array($u->kode, $boleh, true))
      ->groupBy(fn ($u) => explode('.', (string) $u->kode)[0])
      ->mapWithKeys(fn ($sub, $ff) => [($induk[$ff]->nama ?? "Unit {$ff}") . " ({$ff}.00)" => $sub->values()]);
  }

  /** Kode unit induk (*.00) yang tetap boleh dipilih pada BKK. */
  private function indukBoleh(): array
  {
    return config('simaku.bkk_unit_induk_boleh', ['00.00']);
  }

  /** Unit kerja per id_bkk untuk baris BKK yang punya id_unit. */
  private function unitDari($bkk)
  {
    if (!$this->adaKolomUnit()) {
      return collect();
    }
    $id = $bkk->pluck('id_unit')->filter()->unique()->all();
    $unit = DB::table('ueu_tbl_unit')->whereIn('id_unit', $id ?: [0])->get(['id_unit', 'kode', 'nama'])->keyBy('id_unit');

    return $bkk->mapWithKeys(fn ($b) => [$b->id_bkk => $unit[$b->id_unit ?? 0] ?? null])->filter();
  }

  /** Kolom ueu_tbl_bkk.id_unit ditambahkan lewat database/sql/2026_11_08_ueu_tbl_bkk_id_unit.sql. */
  private function adaKolomUnit(): bool
  {
    static $ada;

    return $ada ??= Schema::hasColumn('ueu_tbl_bkk', 'id_unit');
  }

  /** "N/BKK-UMM/<T.A>", N = nomor urut terbesar pada T.A + 1. */
  private function nomorBerikut(int $tahun): string
  {
    $akhir = (int) DB::table('ueu_tbl_bkk')->where('tahun', (string) $tahun)
      ->selectRaw('MAX(CAST(no_bkk AS UNSIGNED)) as n')->value('n');

    return ($akhir + 1) . '/BKK-UMM/' . $tahun;
  }

  private function bkk(int $id): object
  {
    return DB::table('ueu_tbl_bkk')->where('id_bkk', $id)->where('tahun', (string) $this->tahun())->first() ?? abort(404);
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
