<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Review;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Setting > Penetapan Review: menetapkan reviewer ke unit per tahun anggaran
 * (tbl_review), padanan Admin::review_unit* di v1.
 *
 * Seperti v1, yang bisa ditetapkan hanya sub-unit (kode bukan xx.00) dan
 * reviewer = akun ber-wewenang 'review'.
 */
class ReviewController extends Controller
{
  public function index()
  {
    $tahun = $this->tahun();

    $units = $this->subUnit()->get(['id_unit', 'kode', 'nama']);
    $induk = Unit::where('kode', 'like', '%.00')->pluck('nama', 'kode');
    $reviewer = User::where('wewenang', 'review')->orderBy('nama_lengkap')->get(['idlog', 'username', 'nama_lengkap']);

    $penetapan = Review::where('tahun', $tahun)->get(['id', 'user_id', 'unit_id'])->groupBy('unit_id');

    // Jumlah kegiatan T.A ini yang sudah direview per [unit][reviewer].
    $direview = DB::table('tbl_review_kegiatan as rk')
      ->join('ueu_tbl_kegiatan as k', 'k.idkeg', '=', 'rk.kegiatan_id')
      ->where('k.tahun', $tahun)
      ->groupBy('rk.unit_id', 'rk.user_id')
      ->selectRaw('rk.unit_id, rk.user_id, COUNT(*) as n')
      ->get()
      ->groupBy('unit_id')
      ->map(fn ($g) => $g->pluck('n', 'user_id'));

    $grup = $units->groupBy(fn ($u) => $u->kode_induk)->map(fn ($anggota, $kode) => (object) [
      'kode' => (string) $kode,
      'nama' => $induk[$kode . '.00'] ?? "Kelompok {$kode}",
      'unit' => $anggota,
    ]);

    $sebelum = Review::where('tahun', (string) ((int) $tahun - 1))->count();

    return view('content.admin.review.index', [
      'tahun' => (int) $tahun,
      'grup' => $grup,
      'reviewer' => $reviewer,
      'namaReviewer' => $reviewer->mapWithKeys(fn ($r) => [$r->idlog => $r->nama_lengkap ?: $r->username]),
      'penetapan' => $penetapan,
      'direview' => $direview,
      'perReviewer' => Review::where('tahun', $tahun)->get(['user_id'])->countBy('user_id'),
      'sebelum' => $sebelum,
      'statistik' => [
        'unit' => $units->count(),
        'ditetapkan' => $units->filter(fn ($u) => $penetapan->has($u->id_unit))->count(),
        'reviewer' => $penetapan->flatten()->pluck('user_id')->unique()->count(),
      ],
    ]);
  }

  /** Tetapkan satu reviewer ke satu atau banyak unit sekaligus. */
  public function tetapkan(Request $request)
  {
    $tahun = $this->tahun();
    $data = $request->validate([
      'user_id' => ['required', Rule::exists('ueu_tbl_user', 'idlog')->where('wewenang', 'review')],
      'unit_id' => ['required', 'array', 'min:1'],
      'unit_id.*' => ['integer', Rule::exists('ueu_tbl_unit', 'id_unit')->where(fn ($q) => $q->where('kode', 'not like', '%.00'))],
    ], [
      'unit_id.required' => 'Pilih minimal satu unit.',
      'unit_id.*.exists' => 'Unit tidak valid (hanya sub-unit yang dapat ditetapkan).',
    ], ['user_id' => 'reviewer', 'unit_id' => 'unit']);

    $sudah = Review::where('tahun', $tahun)->where('user_id', $data['user_id'])->pluck('unit_id')->flip();
    $baru = collect($data['unit_id'])->map(fn ($id) => (int) $id)->unique()->reject(fn ($id) => isset($sudah[$id]));

    DB::transaction(fn () => $baru->each(fn ($id) => Review::create([
      'user_id' => $data['user_id'], 'unit_id' => $id, 'tahun' => $tahun,
    ])));

    $nama = User::find($data['user_id'])?->name;
    $lewat = count($data['unit_id']) - $baru->count();

    return back()->with('status', "{$baru->count()} unit ditetapkan ke reviewer {$nama}"
      . ($lewat ? " ({$lewat} unit sudah ditetapkan sebelumnya)." : '.'));
  }

  public function hapus(Review $review)
  {
    abort_unless((string) $review->tahun === $this->tahun(), 404);

    $review->delete();

    return back()->with('status', 'Penetapan ' . ($review->reviewer?->name ?? 'reviewer')
      . ' pada unit ' . ($review->unit?->kode ?? '') . ' - ' . ($review->unit?->nama ?? '') . ' dihapus.');
  }

  /** Lepas satu reviewer dari unit-unit terpilih. */
  public function lepas(Request $request)
  {
    $data = $request->validate([
      'user_id' => ['required', 'integer'],
      'unit_id' => ['required', 'array', 'min:1'],
      'unit_id.*' => ['integer'],
    ], ['unit_id.required' => 'Pilih minimal satu unit.'], ['user_id' => 'reviewer']);

    $n = Review::where('tahun', $this->tahun())->where('user_id', $data['user_id'])->whereIn('unit_id', $data['unit_id'])->delete();

    return back()->with('status', "{$n} penetapan reviewer " . (User::find($data['user_id'])?->name ?? '') . ' dihapus.');
  }

  /** Salin semua penetapan T.A sebelumnya yang belum ada di T.A ini. */
  public function salin()
  {
    $tahun = $this->tahun();
    $lalu = (string) ((int) $tahun - 1);

    $ada = Review::where('tahun', $tahun)->get(['user_id', 'unit_id'])->map(fn ($r) => "{$r->user_id}|{$r->unit_id}")->flip();
    $reviewerAktif = User::where('wewenang', 'review')->pluck('idlog')->flip();
    $unitAda = $this->subUnit()->pluck('id_unit')->flip();

    $baru = Review::where('tahun', $lalu)->get(['user_id', 'unit_id'])
      ->reject(fn ($r) => isset($ada["{$r->user_id}|{$r->unit_id}"]))
      ->filter(fn ($r) => isset($reviewerAktif[$r->user_id]) && isset($unitAda[$r->unit_id]))
      ->unique(fn ($r) => "{$r->user_id}|{$r->unit_id}");

    DB::transaction(fn () => $baru->each(fn ($r) => Review::create([
      'user_id' => $r->user_id, 'unit_id' => $r->unit_id, 'tahun' => $tahun,
    ])));

    return back()->with('status', $baru->isEmpty()
      ? "Tidak ada penetapan baru: semua penetapan T.A {$lalu} sudah ada di T.A {$tahun}."
      : "{$baru->count()} penetapan disalin dari T.A {$lalu}.");
  }

  private function subUnit()
  {
    return Unit::where('kode', 'not like', '%.00')->orderBy('kode');
  }

  private function tahun(): string
  {
    return (string) (session('periode') ?: Info::tahun(['aktif' => 'enable']));
  }
}
