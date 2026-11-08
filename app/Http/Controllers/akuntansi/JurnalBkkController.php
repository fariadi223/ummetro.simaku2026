<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Sumberdana;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Jurnal non anggaran / BKK (/akuntansi/jurnal-bkk), padanan Akuntansi::jurnalbkk, jurnalbkk_tabel,
 * jurnalbkk_json, jurnalbkk_proses, jurnalbkk_hapus v1.
 *
 *   Daftar    = ueu_tbl_bkk status Tunai pada T.A (dibuat kasir di /kasir/non-anggaran).
 *   Realisasi = Σ kredit ueu_tbl_jurnal_spm dengan bukti = no_bkk & tahun BKK (seperti v1).
 *   Jurnal    = dua baris (debet & kredit rekening pilihan), bukti = no_bkk, tahun = tahun BKK;
 *               bila item rincian dipilih: uraian + " ( <item> )" dan ueu_tbl_bkk_rinci.realisasi bertambah.
 *   Hapus     = BKK (beserta rinciannya) yang belum punya jurnal.
 *
 * Perbedaan dari v1: nominal dibatasi sisa BKK (dan sisa item bila dipilih), debet ≠ kredit, tanggal jurnal
 * bisa dipilih (v1 selalu hari ini), unit terisi otomatis dari BKK bila kasir mengisinya, insert + update
 * realisasi dalam satu transaksi, hapus juga menghapus rincian (v1 meninggalkan rincian yatim).
 */
class JurnalBkkController extends Controller
{
  public function index(Request $request)
  {
    $tahun = $this->tahun();
    $cari = trim((string) $request->query('cari', ''));
    [$dari, $sampai] = $this->rentang($request->query('dari'), $request->query('sampai'));

    $daftar = DB::table('ueu_tbl_bkk')->where('tahun', (string) $tahun)->where('status_bkk', 'Tunai')
      ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('no_bkk', 'like', "%{$cari}%")
        ->orWhere('uraian', 'like', "%{$cari}%")->orWhere('penerima', 'like', "%{$cari}%")
        ->orWhere('tgl_bkk', 'like', "%{$cari}%")))
      ->when($dari, fn ($q) => $q->where('tgl_bkk', '>=', $dari))
      ->when($sampai, fn ($q) => $q->where('tgl_bkk', '<=', $sampai))
      ->orderByDesc('id_bkk')->paginate(25)->withQueryString();

    $bkk = $daftar->getCollection();
    $real = $this->realisasi($tahun, $bkk->pluck('no_bkk')->all());
    $rinci = DB::table('ueu_tbl_bkk_rinci')->whereIn('bkk_id', $bkk->pluck('id_bkk')->all() ?: [0])
      ->orderBy('id')->get()->groupBy('bkk_id');
    $unitBkk = $this->adaKolomUnit()
      ? DB::table('ueu_tbl_unit')->whereIn('id_unit', $bkk->pluck('id_unit')->filter()->unique()->all() ?: [0])
        ->get(['id_unit', 'kode', 'nama'])->keyBy('id_unit')
      : collect();

    $bkk->each(function ($b) use ($real, $rinci, $unitBkk) {
      $b->realisasi = (float) ($real[$b->no_bkk] ?? 0);
      $b->sisa = (float) $b->nominal - $b->realisasi;
      $b->rinci = ($rinci[$b->id_bkk] ?? collect())->values();
      $b->unit = $unitBkk[$b->id_unit ?? 0] ?? null;
    });

    return view('content.akuntansi.jurnal-bkk.index', [
      'tahun' => $tahun,
      'cari' => $cari,
      'dari' => $dari ?? '',
      'sampai' => $sampai ?? '',
      'daftar' => $daftar,
      'rekening' => $this->rekening(),
      'subUnit' => DB::table('ueu_tbl_unit')->where('kode', 'not like', '%.00')->orderBy('kode')->get(['id_unit', 'kode', 'nama']),
      'mak' => $this->mak(),
      'sumber' => Sumberdana::whereNotNull('kode')->orderBy('kode')->get(['id', 'kode', 'nama_penerimaan']),
    ]);
  }

  /** Jurnalkan realisasi BKK (jurnalbkk_proses v1). Gagal validasi → kembali ke daftar, modal dibuka lagi. */
  public function store(Request $request, int $id)
  {
    try {
      return $this->simpan($request, $id);
    } catch (ValidationException $e) {
      return redirect($this->urlKembali($request))->withErrors($e->errors())
        ->withInput($request->except('_token') + ['_bkk' => $id]);
    }
  }

  /** Hapus BKK yang belum dijurnal (jurnalbkk_hapus v1). */
  public function destroy(Request $request, int $id)
  {
    $tahun = $this->tahun();
    $bkk = $this->bkk($tahun, $id);
    if (DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $bkk->tahun)->where('bukti', $bkk->no_bkk)->exists()) {
      return redirect($this->urlKembali($request))->with('error', 'BKK ' . $bkk->no_bkk . ' sudah dijurnal, tidak bisa dihapus.');
    }

    DB::transaction(function () use ($id) {
      DB::table('ueu_tbl_bkk_rinci')->where('bkk_id', $id)->delete();
      DB::table('ueu_tbl_bkk')->where('id_bkk', $id)->delete();
    });

    return redirect($this->urlKembali($request))->with('status', 'BKK ' . $bkk->no_bkk . ' dihapus.');
  }

  private function simpan(Request $request, int $id)
  {
    $tahun = $this->tahun();
    $bkk = $this->bkk($tahun, $id);

    $request->merge(['nominal' => ($v = preg_replace('/\D/', '', (string) $request->input('nominal'))) === '' ? null : (int) $v]);
    $data = $request->validate([
      'tgl_trk' => ['required', 'date_format:Y-m-d'],
      'uraian' => ['required', 'string', 'max:500'],
      'bkk_rinci_id' => ['nullable', 'integer', Rule::exists('ueu_tbl_bkk_rinci', 'id')->where('bkk_id', $id)],
      'nominal' => ['required', 'integer', 'min:1', 'max:999999999999'],
      'debet' => ['required', 'integer', Rule::exists('ueu_tbl_rek6', 'idrek6')],
      'kredit' => ['required', 'integer', 'different:debet', Rule::exists('ueu_tbl_rek6', 'idrek6')],
      'id_uk' => ['required', 'integer', Rule::exists('ueu_tbl_unit', 'id_unit')->where(fn ($q) => $q->where('kode', 'not like', '%.00'))],
      'id_mak' => ['nullable', 'integer', Rule::exists('ueu_tbl_mak4', 'idmak4')],
      'id_sumber' => ['required', 'integer', Rule::exists('ueu_tbl_sumberdana', 'id')],
    ], [
      'kredit.different' => 'Rekening yang di-KREDIT harus berbeda dengan rekening yang di-DEBET.',
      'bkk_rinci_id.exists' => 'Item tidak termasuk rincian BKK ini.',
    ], [
      'tgl_trk' => 'Tanggal', 'uraian' => 'Uraian', 'bkk_rinci_id' => 'Item', 'nominal' => 'Realisasi',
      'debet' => 'Rekening yang di-DEBET', 'kredit' => 'Rekening yang di-KREDIT', 'id_uk' => 'Unit',
      'id_mak' => 'Mata Anggaran', 'id_sumber' => 'Sumber Dana',
    ]);

    $unit = DB::table('ueu_tbl_unit')->where('id_unit', $data['id_uk'])->first(['kode']);
    $ff = explode('.', (string) $unit->kode)[0];

    DB::transaction(function () use ($bkk, $data, $ff) {
      // Kunci BKK lalu hitung ulang sisa agar dua kiriman bersamaan tidak melewati nominal BKK.
      DB::table('ueu_tbl_bkk')->where('id_bkk', $bkk->id_bkk)->lockForUpdate()->first();
      $sisa = (float) $bkk->nominal - (float) ($this->realisasi((int) $bkk->tahun, [$bkk->no_bkk])[$bkk->no_bkk] ?? 0);
      $item = !empty($data['bkk_rinci_id'])
        ? DB::table('ueu_tbl_bkk_rinci')->where('id', $data['bkk_rinci_id'])->lockForUpdate()->first() : null;

      $batas = [(int) floor($sisa) => 'sisa BKK'];
      if ($item) {
        $batas[(int) floor((float) $item->biaya - (float) $item->realisasi)] = 'sisa item "' . $item->uraian . '"';
      }
      $maks = min(array_keys($batas));
      if ($data['nominal'] > $maks) {
        throw ValidationException::withMessages(['nominal' => 'Melebihi ' . $batas[$maks] . '. Maksimal Rp ' . number_format(max(0, $maks), 0, ',', '.') . '.']);
      }

      $uraian = trim($data['uraian']) . ($item ? ' ( ' . $item->uraian . ')' : '');
      // Kolom NOT NULL tanpa default pada tabel lama (v1 berjalan non-strict) diisi eksplisit.
      $baris = fn (int $idrek6, int $debet, int $kredit) => [
        'tahun' => (string) $bkk->tahun,
        'id_uk' => (int) $data['id_uk'],
        'kodeunit' => ctype_digit($ff) ? (int) $ff : 0,
        'idkeg' => 0,
        'idtama' => 0,
        'id_sumber' => (int) $data['id_sumber'],
        'id_mak' => !empty($data['id_mak']) ? (int) $data['id_mak'] : null,
        'idrek6' => $idrek6,
        'idrinci' => 0,
        'idrinci_na' => 0,
        'tgl_trk' => $data['tgl_trk'],
        'uraian' => $uraian,
        'bukti' => (string) $bkk->no_bkk,
        'debet' => $debet,
        'kredit' => $kredit,
        'ket' => '',
        'tgl_trans' => '',
      ];

      DB::table('ueu_tbl_jurnal_spm')->insert([
        $baris((int) $data['debet'], $data['nominal'], 0),
        $baris((int) $data['kredit'], 0, $data['nominal']),
      ]);
      if ($item) {
        DB::table('ueu_tbl_bkk_rinci')->where('id', $item->id)->update(['realisasi' => (float) $item->realisasi + $data['nominal']]);
      }
    });

    return redirect($this->urlKembali($request))->with('status',
      'BKK ' . $bkk->no_bkk . ' dijurnal sebesar Rp ' . number_format($data['nominal'], 0, ',', '.') . '.');
  }

  /** Filter tanggal BKK: [dari, sampai] (Y-m-d|null); tanggal tidak valid diabaikan, urutan terbalik ditukar. */
  private function rentang($dari, $sampai): array
  {
    $sah = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
    [$dari, $sampai] = [$sah($dari), $sah($sampai)];

    return $dari && $sampai && $dari > $sampai ? [$sampai, $dari] : [$dari, $sampai];
  }

  /** Σ kredit jurnal per no_bkk (jurnalbkk_tabel v1). @return array<string, float> */
  private function realisasi(int $tahun, array $noBkk): array
  {
    return DB::table('ueu_tbl_jurnal_spm')->where('tahun', (string) $tahun)->whereIn('bukti', $noBkk ?: [''])
      ->groupBy('bukti')->selectRaw('bukti, COALESCE(SUM(kredit), 0) as total')->pluck('total', 'bukti')
      ->map(fn ($v) => (float) $v)->all();
  }

  private function bkk(int $tahun, int $id): object
  {
    return DB::table('ueu_tbl_bkk')->where('id_bkk', $id)->where('tahun', (string) $tahun)->where('status_bkk', 'Tunai')->first()
      ?? abort(404);
  }

  /** Semua rekening level 6 berkode lengkap (Mrek::get_rek_all v1). */
  private function rekening()
  {
    return DB::table('ueu_tbl_rek6 as r6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')
      ->orderBy('r4.koderek4')->orderBy('r5.koderek5')->orderBy('r6.koderek6')
      ->selectRaw('r6.idrek6, r6.namarek6, CONCAT(r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode, r1.namarek1')
      ->get();
  }

  /** Mata anggaran level 4 berkode lengkap (Mmak::get_mak4_b1 v1). */
  private function mak()
  {
    return DB::table('ueu_tbl_mak4 as m4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->whereNotNull('m4.kodemak4')
      ->orderBy('m1.kodemak1')->orderBy('m2.kodemak2')->orderBy('m3.kodemak3')->orderBy('m4.kodemak4')
      ->selectRaw('m4.idmak4, m4.namamak4, CONCAT(m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode, m1.namamak1')
      ->get();
  }

  /** Kolom ueu_tbl_bkk.id_unit ditambahkan lewat database/sql/2026_11_08_ueu_tbl_bkk_id_unit.sql. */
  private function adaKolomUnit(): bool
  {
    static $ada;

    return $ada ??= Schema::hasColumn('ueu_tbl_bkk', 'id_unit');
  }

  private function urlKembali(Request $request): string
  {
    return route('akuntansi.jurnal-bkk.index', array_filter([
      'cari' => (string) $request->input('f_cari', ''),
      'dari' => (string) $request->input('f_dari', ''),
      'sampai' => (string) $request->input('f_sampai', ''),
      'page' => (string) $request->input('f_page', ''),
    ], fn ($v) => $v !== ''));
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
