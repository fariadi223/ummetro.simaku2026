<?php

namespace App\Http\Controllers\unit;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Kegiatan;
use App\Models\PaguUnit;
use App\Models\Rinci;
use App\Models\Unit;
use App\Services\KomiteAnggaran;
use App\Services\RincianAnggaran;
use App\Services\StatusKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Rincian pendapatan & belanja kegiatan, padanan Unit::rinci, add_pendptn,
 * add_belanja, process_belanja, detail_*, delete_belanja, surplus_proses,
 * rinci_cetak di v1.
 *
 * Algoritma v1 yang dipertahankan:
 *   - rekening pendapatan = COA kode 4; rekening belanja = COA kode 5.1
 *   - qty2..qty4 kosong disimpan 0 dan dihitung 1
 *   - belanja ditolak bila (total belanja unit T.A ini + nilai baru [- nilai lama])
 *     melebihi pagu unit untuk sumber dana kegiatan; pendapatan tidak dibatasi
 *   - boleh ubah sama dengan aturan kegiatan (lihat StatusKegiatan)
 */
class RinciController extends Controller
{
  public const JENIS = ['pendapatan' => 'Pendapatan', 'belanja' => 'Belanja'];

  public function __construct(
    private KomiteAnggaran $komite,
    private StatusKegiatan $status,
    private RincianAnggaran $anggaran,
  ) {
  }

  public function index(int $idkeg)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatan($unit, $tahun, $idkeg);

    $rinci = $this->queryRinci($idkeg)->get()->each(function ($r) {
      $r->jumlah = Rinci::nilai($r);
      $r->volume = Rinci::volume($r);
    })->groupBy(fn ($r) => $r->koderek1 == '4' ? 'pendapatan' : 'belanja');

    $komite = $this->komite->status($unit, $tahun);
    $review = $this->status->review($unit, $tahun, [$idkeg])[$idkeg];

    $pendapatan = (int) round(($rinci['pendapatan'] ?? collect())->sum('jumlah'));
    $belanja = (int) round(($rinci['belanja'] ?? collect())->sum('jumlah'));

    return view('content.unit.rinci.index', [
      'unit' => $unit,
      'tahun' => $tahun,
      'keg' => $keg,
      'rinci' => $rinci,
      'komite' => $komite,
      'review' => $review,
      'bolehUbah' => $this->status->bolehUbah($komite, $review),
      'pendapatan' => $pendapatan,
      'belanja' => $belanja,
      'pagu' => $this->infoPagu($unit, $tahun, $keg->idsumberdana),
    ]);
  }

  public function create(int $idkeg, string $jenis)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatan($unit, $tahun, $idkeg);
    $this->pastikanBolehUbah($unit, $tahun, $keg);

    return $this->form($unit, $tahun, $keg, $jenis, null);
  }

  public function store(Request $request, int $idkeg, string $jenis)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatan($unit, $tahun, $idkeg);
    $this->pastikanBolehUbah($unit, $tahun, $keg);

    $data = $this->validasi($request, $jenis);
    $this->cekPagu($unit, $tahun, $keg, $jenis, $data, null);

    Rinci::create($data + [
      'tahun' => (string) $tahun,
      'idkeg' => $keg->idkeg,
      'created_by' => Auth::user()->username,
      'created_date' => now('Asia/Jakarta')->format('d-m-Y G:i:s'),
    ]);

    return redirect()->route('unit.rinci.index', [$keg->idkeg, 'tab' => $jenis])
      ->with('status', self::JENIS[$jenis] . " \"{$data['uraianrinci']}\" berhasil ditambahkan.");
  }

  public function edit(int $idkeg, int $idrinci)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatan($unit, $tahun, $idkeg);
    $this->pastikanBolehUbah($unit, $tahun, $keg);
    $item = $this->item($keg, $idrinci);

    return $this->form($unit, $tahun, $keg, $this->jenisItem($item), $item);
  }

  public function update(Request $request, int $idkeg, int $idrinci)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatan($unit, $tahun, $idkeg);
    $this->pastikanBolehUbah($unit, $tahun, $keg);
    $item = $this->item($keg, $idrinci);
    $jenis = $this->jenisItem($item);

    $data = $this->validasi($request, $jenis);
    $this->cekPagu($unit, $tahun, $keg, $jenis, $data, $item);

    $item->update($data + [
      'modified_by' => Auth::user()->username,
      'modified_date' => now('Asia/Jakarta')->format('d-m-Y G:i:s'),
    ]);

    return redirect()->route('unit.rinci.index', [$keg->idkeg, 'tab' => $jenis])
      ->with('status', self::JENIS[$jenis] . " \"{$data['uraianrinci']}\" berhasil diperbarui.");
  }

  public function destroy(int $idkeg, int $idrinci)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatan($unit, $tahun, $idkeg);
    $this->pastikanBolehUbah($unit, $tahun, $keg);
    $item = $this->item($keg, $idrinci);
    $jenis = $this->jenisItem($item);

    // Rinci yang sudah dijurnal (SPM) tidak boleh hilang.
    if (DB::table('ueu_tbl_jurnal_spm')->where('idrinci', $item->idrinci)->exists()) {
      return back()->with('error', "\"{$item->uraianrinci}\" sudah dipakai di jurnal, sehingga tidak dapat dihapus.");
    }

    $item->delete();

    return redirect()->route('unit.rinci.index', [$keg->idkeg, 'tab' => $jenis])
      ->with('status', self::JENIS[$jenis] . " \"{$item->uraianrinci}\" berhasil dihapus.");
  }

  /** Pilihan surplus: ditambahkan ke pagu unit atau tidak (kolom kegiatan.ket, v1). */
  public function surplus(Request $request, int $idkeg)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatan($unit, $tahun, $idkeg);
    $data = $request->validate(['opsi' => ['required', Rule::in(['plus', 'no'])]], [], ['opsi' => 'pilihan surplus']);

    $keg->update(['ket' => $data['opsi']]);

    return back()->with('status', $data['opsi'] === 'plus'
      ? 'Surplus akan ditambahkan pada pagu unit.'
      : 'Surplus tidak ditambahkan pada pagu unit.');
  }

  public function cetak(int $idkeg)
  {
    [$unit, $tahun] = $this->konteks();
    $keg = $this->kegiatan($unit, $tahun, $idkeg);

    $rinci = $this->queryRinci($idkeg)->get()->each(function ($r) {
      $r->jumlah = Rinci::nilai($r);
      $r->volume = Rinci::volume($r);
    })->groupBy(fn ($r) => $r->koderek1 == '4' ? 'pendapatan' : 'belanja');

    return view('content.unit.rinci.cetak', compact('unit', 'tahun', 'keg', 'rinci'));
  }

  // ---------------------------------------------------------------------------

  private function form(Unit $unit, int $tahun, object $keg, string $jenis, ?Rinci $item)
  {
    return view('content.unit.rinci.form', [
      'unit' => $unit,
      'tahun' => $tahun,
      'keg' => $keg,
      'jenis' => $jenis,
      'item' => $item,
      'rekening' => $this->rekening($jenis)->get(),
      'pagu' => $jenis === 'belanja' ? $this->infoPagu($unit, $tahun, $keg->idsumberdana) : null,
      'komite' => $this->komite->status($unit, $tahun),
    ]);
  }

  private function validasi(Request $request, string $jenis): array
  {
    // Harga: terima "1.500.000". Kuantitas (input number): titik = desimal; koma juga diterima.
    $request->merge(['hargasat' => preg_replace('/\D/', '', (string) $request->input('hargasat'))]);
    foreach (['qty1', 'qty2', 'qty3', 'qty4'] as $q) {
      $v = trim((string) $request->input($q));
      $request->merge([$q => $v === '' ? null : str_replace(',', '.', $v)]);
    }

    $data = $request->validate([
      'idrek6' => ['required', 'integer', Rule::in($this->rekening($jenis)->pluck('r6.idrek6')->all())],
      'uraianrinci' => ['required', 'string', 'max:198'],
      'hargasat' => ['required', 'integer', 'min:1', 'max:999999999999'],
      'qty1' => ['required', 'numeric', 'gt:0', 'max:9999999'],
      'qty2' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
      'qty3' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
      'qty4' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
      'satuan1' => ['nullable', 'string', 'max:50'],
      'satuan2' => ['nullable', 'string', 'max:15'],
      'satuan3' => ['nullable', 'string', 'max:15'],
      'satuan4' => ['nullable', 'string', 'max:15'],
    ], [
      'idrek6.in' => 'Rekening tidak sesuai untuk ' . strtolower(self::JENIS[$jenis]) . '.',
      'qty1.gt' => 'Kuantitas pertama harus lebih dari 0.',
    ], [
      'idrek6' => 'rekening', 'uraianrinci' => 'uraian', 'hargasat' => 'harga satuan',
      'qty1' => 'kuantitas', 'qty2' => 'kuantitas ke-2', 'qty3' => 'kuantitas ke-3', 'qty4' => 'kuantitas ke-4',
      'satuan1' => 'satuan', 'satuan2' => 'satuan ke-2', 'satuan3' => 'satuan ke-3', 'satuan4' => 'satuan ke-4',
    ]);

    // Kosong -> 0 (qty) / '' (satuan), seperti v1.
    foreach ([2, 3, 4] as $i) {
      $data["qty{$i}"] = $data["qty{$i}"] ?? 0;
    }
    foreach ([1, 2, 3, 4] as $i) {
      $data["satuan{$i}"] = trim((string) ($data["satuan{$i}"] ?? ''));
    }
    $data['uraianrinci'] = trim($data['uraianrinci']);
    $data['hargasat'] = (int) $data['hargasat'];

    return $data;
  }

  /**
   * Cek pagu (process_belanja v1): total belanja unit T.A ini + nilai baru
   * (dikurangi nilai lama bila mengubah) tidak boleh melebihi pagu unit sumber dana kegiatan.
   */
  private function cekPagu(Unit $unit, int $tahun, object $keg, string $jenis, array $data, ?Rinci $lama): void
  {
    if ($jenis !== 'belanja') {
      return;
    }

    $pagu = $this->infoPagu($unit, $tahun, $keg->idsumberdana);
    $baru = Rinci::nilai((object) $data);
    $total = $pagu['belanja_unit'] + $baru - ($lama ? Rinci::nilai($lama) : 0);

    if ($total > $pagu['nominal']) {
      $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
      throw \Illuminate\Validation\ValidationException::withMessages([
        'hargasat' => "Melebihi pagu anggaran {$tahun}: total belanja unit menjadi {$rp($total)}, pagu {$pagu['sumber']} {$rp($pagu['nominal'])}"
          . ' (kelebihan ' . $rp($total - $pagu['nominal']) . ').',
      ]);
    }
  }

  /** Pagu unit untuk sumber dana kegiatan dan total belanja unit (semua kegiatan) T.A ini. */
  private function infoPagu(Unit $unit, int $tahun, ?string $idPagu): array
  {
    $pu = PaguUnit::with('pagu:id_pagu,sumber')->where('tahun', (string) $tahun)->where('idunit', $unit->id_unit)
      ->where('idpagu', $idPagu)->first();

    $belanja = $this->anggaran->query()
      ->where('k.idunit', $unit->id_unit)->where('k.tahun', (string) $tahun)->where('r1.koderek1', '5')
      ->selectRaw('SUM(' . RincianAnggaran::SQL_NILAI . ') as total')->value('total');

    $nominal = (int) ($pu->nominal ?? 0);
    $belanja = (float) ($belanja ?? 0);

    return [
      'sumber' => $pu?->pagu?->sumber ?? ($idPagu ?: '-'),
      'nominal' => $nominal,
      'belanja_unit' => $belanja,
      'sisa' => $nominal - $belanja,
    ];
  }

  /** Rekening yang boleh dipilih: pendapatan = 4, belanja = 5.1 (v1). */
  private function rekening(string $jenis)
  {
    abort_unless(isset(self::JENIS[$jenis]), 404);

    return DB::table('ueu_tbl_rek6 as r6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->where('r1.koderek1', $jenis === 'pendapatan' ? 4 : 5)
      ->when($jenis === 'belanja', fn ($q) => $q->where('r2.koderek2', 1))
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')->orderBy('r4.koderek4')->orderBy('r5.koderek5')->orderBy('r6.koderek6')
      ->select('r6.idrek6', 'r6.namarek6', 'r5.namarek5', 'r4.namarek4',
        DB::raw("CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode"));
  }

  private function queryRinci(int $idkeg)
  {
    return $this->anggaran->query()
      ->where('r.idkeg', $idkeg)
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')->orderBy('r4.koderek4')
      ->orderBy('r5.koderek5')->orderBy('r6.koderek6')->orderBy('r.idrinci')
      ->select('r.*', 'r1.koderek1', 'r6.namarek6',
        DB::raw("CONCAT_WS('.', r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode_rek"));
  }

  private function konteks(): array
  {
    $unit = Auth::user()->unit;
    abort_unless($unit, 404, 'Akun ini tidak terhubung ke unit kerja.');

    return [$unit, (int) (session('periode') ?: Info::tahun(['aktif' => 'enable']))];
  }

  /** Kegiatan milik unit pada T.A ini, lengkap dengan info MAK & pagu (get_keg_unit v1). */
  private function kegiatan(Unit $unit, int $tahun, int $idkeg): Kegiatan
  {
    $keg = Kegiatan::query()
      ->from('ueu_tbl_kegiatan as k')
      ->join('ueu_tbl_mak4 as m4', 'm4.idmak4', '=', 'k.idmak4')
      ->join('ueu_tbl_mak3 as m3', 'm3.idmak3', '=', 'm4.idmak3')
      ->join('ueu_tbl_mak2 as m2', 'm2.idmak2', '=', 'm3.idmak2')
      ->join('ueu_tbl_mak1 as m1', 'm1.idmak1', '=', 'm2.idmak1')
      ->leftJoin('ueu_tbl_pagu as p', 'p.id_pagu', '=', 'k.idsumberdana')
      ->where('k.idkeg', $idkeg)->where('k.idunit', $unit->id_unit)->where('k.tahun', (string) $tahun)
      ->select('k.*', 'm4.namamak4', 'm1.namamak1', 'm1.kodemak1', 'p.sumber as nama_pagu',
        DB::raw("CONCAT_WS('.', m1.kodemak1, m2.kodemak2, m3.kodemak3, m4.kodemak4) as kode_mak"))
      ->first();

    abort_unless($keg, 404, 'Kegiatan tidak ditemukan pada T.A ini.');

    return $keg;
  }

  private function item(object $keg, int $idrinci): Rinci
  {
    return Rinci::where('idrinci', $idrinci)->where('idkeg', $keg->idkeg)->firstOrFail();
  }

  private function jenisItem(Rinci $item): string
  {
    $kode1 = $this->anggaran->query()->where('r.idrinci', $item->idrinci)->value('r1.koderek1');

    return $kode1 == '4' ? 'pendapatan' : 'belanja';
  }

  private function pastikanBolehUbah(Unit $unit, int $tahun, Kegiatan $keg): void
  {
    if (!$this->status->bolehUbahKegiatan($unit, $tahun, $keg)) {
      abort(redirect()->route('unit.rinci.index', $keg->idkeg)
        ->with('error', 'Rincian tidak dapat diubah: jadwal komite sudah ditutup dan kegiatan tidak sedang direvisi.'));
    }
  }
}
