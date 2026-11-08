<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saldo awal (/akuntansi/saldo-awal), padanan saldo/Saldoawal::index (view saldo_awal_v3) & jurnalSaldo v1.
 *
 *   Sumber data = ueu_tbl_jurnal_saldo pada tahun terpilih (semua baris, termasuk saldo akhir hasil tutup buku),
 *                 hanya yang ber-sumber dana (group_jurnal_rek6_sumber v1).
 *   Saldo akun  = per (rekening level 6, sumber dana), saldo_awal_lv6 v1:
 *                   saldo normal debet  (rek 1 kecuali 1.2.02 & 1.2.04, dan rek 5) → D = Σ debet − Σ kredit
 *                   saldo normal kredit (lainnya)                                   → K = Σ kredit − Σ debet
 *                 Konsolidasi = jumlah semua sumber dana; level 3 = jumlah akun level 6 di bawahnya.
 *   Default tanpa rek 4 & 5 (pendapatan, biaya); ?saldo=all menampilkan semua (seperti v1).
 *   Input       = satu baris saldo (D/K) per akun & sumber dana, ket 'SD', uraian "SALDO AWAL 01-09-<T.A>".
 *
 * Perbedaan dari v1: input hanya untuk T.A login (v1 tidak mengisi kolom tahun sehingga tersimpan ke tahun
 * default 2023), modal input berfungsi (di view v3 JS modalnya tidak dimuat), total D/K dihitung (v1 selalu 0),
 * level 3 juga menampilkan nilai per sumber dana, dan opsi "hanya akun bersaldo".
 */
class SaldoAwalController extends Controller
{
  /** Akun level 3 bersaldo normal kredit walau berada di rek 1 (akumulasi penyusutan). */
  private const KONTRA_ASET = ['1.2.02', '1.2.04'];

  public function index(Request $request)
  {
    $aktif = $this->tahun();
    $pilihanTahun = DB::table('ueu_tbl_jurnal_saldo')->distinct()->orderByDesc('tahun')->pluck('tahun')
      ->push((string) $aktif)->unique()->sortDesc()->values();
    $tahun = $pilihanTahun->contains((string) $request->query('tahun')) ? (int) $request->query('tahun') : $aktif;
    $semua = $request->query('saldo') === 'all';
    $hanyaIsi = $request->boolean('isi');

    // Σ debet/kredit per (idrek6, id_sumber) — satu query, menggantikan dua query per akun di v1.
    $jumlah = DB::table('ueu_tbl_jurnal_saldo as s')->join('ueu_tbl_sumberdana as d', 'd.id', '=', 's.id_sumber')
      ->where('s.tahun', (string) $tahun)->whereNotNull('s.id_sumber')
      ->groupBy('s.idrek6', 's.id_sumber')
      ->selectRaw('s.idrek6, s.id_sumber, COALESCE(SUM(s.debet), 0) as debet, COALESCE(SUM(s.kredit), 0) as kredit')
      ->get()->groupBy('idrek6');

    $sumber = DB::table('ueu_tbl_sumberdana')
      ->whereIn('id', $jumlah->flatten(1)->pluck('id_sumber')->unique()->all() ?: [0])
      ->orderBy('id')->get(['id', 'kode', 'nama_penerimaan']);
    $idSumber = $sumber->pluck('id')->all();

    $akun = $this->rekening($semua);
    [$baris, $total] = $this->susun($akun, $jumlah, $idSumber);
    if ($hanyaIsi) {
      $baris = $this->hanyaBersaldo($baris);
    }

    return view('content.akuntansi.saldo-awal.index', [
      'tahun' => $tahun,
      'aktif' => $aktif,
      'bisaInput' => $tahun === $aktif,
      'pilihanTahun' => $pilihanTahun,
      'semua' => $semua,
      'hanyaIsi' => $hanyaIsi,
      'sumber' => $sumber,
      'semuaSumber' => DB::table('ueu_tbl_sumberdana')->whereNotNull('kode')->orderBy('kode')->get(['id', 'kode', 'nama_penerimaan']),
      'baris' => $baris,
      'total' => $total,
    ]);
  }

  /** Input saldo awal satu akun (jurnalSaldo v1). Gagal validasi → kembali dengan modal dibuka lagi. */
  public function store(Request $request)
  {
    $tahun = $this->tahun();
    $kembali = route('akuntansi.saldo-awal.index', array_filter([
      'saldo' => $request->input('f_saldo') === 'all' ? 'all' : null,
      'isi' => $request->input('f_isi') ? '1' : null,
    ]));

    $request->merge(['nominal' => ($v = preg_replace('/\D/', '', (string) $request->input('nominal'))) === '' ? null : (int) $v]);
    try {
      $data = $request->validate([
        'idrek6' => ['required', 'integer', Rule::exists('ueu_tbl_rek6', 'idrek6')],
        'pencatatan' => ['required', Rule::in(['D', 'K'])],
        'nominal' => ['required', 'integer', 'min:1', 'max:999999999999'],
        'id_sumber' => ['required', 'integer', Rule::exists('ueu_tbl_sumberdana', 'id')],
      ], [], ['idrek6' => 'Akun', 'pencatatan' => 'Posisi (D/K)', 'nominal' => 'Nominal', 'id_sumber' => 'Sumber Dana']);
    } catch (ValidationException $e) {
      return redirect($kembali)->withErrors($e->errors())->withInput($request->except('_token'));
    }

    $tgl = '01-09-' . $tahun;
    // Kolom NOT NULL tanpa default pada tabel lama diisi eksplisit; tahun ditulis (v1 tidak).
    DB::table('ueu_tbl_jurnal_saldo')->insert([
      'tahun' => (string) $tahun,
      'id_sumber' => (int) $data['id_sumber'],
      'idrek6' => (int) $data['idrek6'],
      'idrinci' => 0,
      'idrinci_na' => 0,
      'tgl_trk' => today()->toDateString(),
      'uraian' => 'SALDO AWAL ' . $tgl,
      'bukti' => '',
      'debet' => $data['pencatatan'] === 'D' ? $data['nominal'] : 0,
      'kredit' => $data['pencatatan'] === 'K' ? $data['nominal'] : 0,
      'ket' => 'SD',
      'tgl_trans' => $tgl,
    ]);

    $nama = DB::table('ueu_tbl_rek6')->where('idrek6', $data['idrek6'])->value('namarek6');

    return redirect($kembali)->with('status', 'Saldo awal ' . ($data['pencatatan'] === 'D' ? 'debet' : 'kredit') . ' "' . $nama
      . '" sebesar Rp ' . number_format($data['nominal'], 0, ',', '.') . ' tersimpan (T.A ' . $tahun . ').');
  }

  /** Rekening level 6 berurutan kode lengkap, beserta nama level 1–3. */
  private function rekening(bool $semua): Collection
  {
    return DB::table('ueu_tbl_rek6 as r6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->when(!$semua, fn ($q) => $q->whereNotIn('r1.koderek1', ['4', '5']))
      ->orderBy('r1.koderek1')->orderBy('r2.koderek2')->orderBy('r3.koderek3')
      ->orderBy('r4.koderek4')->orderBy('r5.koderek5')->orderBy('r6.koderek6')
      ->get(['r6.idrek6', 'r6.namarek6', 'r1.idrek1', 'r1.koderek1', 'r1.namarek1', 'r2.idrek2', 'r2.koderek2', 'r2.namarek2',
        'r3.idrek3', 'r3.koderek3', 'r3.namarek3', 'r4.koderek4', 'r5.koderek5', 'r6.koderek6']);
  }

  /**
   * Susun baris tampilan (level 1, 2, 3 + akun level 6) dan total kolom.
   * Nilai: ['s' => [id_sumber => [d, k]], 'd' => konsolidasi D, 'k' => konsolidasi K].
   */
  private function susun(Collection $akun, Collection $jumlah, array $idSumber): array
  {
    $kosong = fn () => ['s' => array_fill_keys($idSumber, [0.0, 0.0]), 'd' => 0.0, 'k' => 0.0];
    $tambah = function (array &$ke, array $dari) {
      foreach ($dari['s'] as $id => [$d, $k]) {
        $ke['s'][$id][0] += $d;
        $ke['s'][$id][1] += $k;
      }
      $ke['d'] += $dari['d'];
      $ke['k'] += $dari['k'];
    };

    $baris = [];
    $total = $kosong();
    $l1 = $l2 = $l3 = null;
    foreach ($akun as $a) {
      $k3 = $a->koderek1 . '.' . $a->koderek2 . '.' . $a->koderek3;
      if ($l1 !== $a->idrek1) {
        $baris[] = (object) ['lv' => 1, 'kode' => (string) $a->koderek1, 'nama' => $a->namarek1];
        $l1 = $a->idrek1;
        $l2 = $l3 = null;
      }
      if ($l2 !== $a->idrek2) {
        $baris[] = (object) ['lv' => 2, 'kode' => $a->koderek1 . '.' . $a->koderek2, 'nama' => $a->namarek2];
        $l2 = $a->idrek2;
        $l3 = null;
      }
      if ($l3 !== $a->idrek3) {
        $induk3 = (object) ['lv' => 3, 'kode' => $k3, 'nama' => $a->namarek3, 'id' => $a->idrek3, 'nilai' => $kosong()];
        $baris[] = $induk3;
        $l3 = $a->idrek3;
      }

      $normalDebet = ((string) $a->koderek1 === '1' && !in_array($k3, self::KONTRA_ASET, true)) || (string) $a->koderek1 === '5';
      $nilai = $kosong();
      foreach ($jumlah[$a->idrek6] ?? [] as $j) {
        if (!array_key_exists($j->id_sumber, $nilai['s'])) {
          continue;
        }
        $saldo = $normalDebet ? (float) $j->debet - (float) $j->kredit : (float) $j->kredit - (float) $j->debet;
        $nilai['s'][$j->id_sumber] = $normalDebet ? [$saldo, 0.0] : [0.0, $saldo];
        $nilai[$normalDebet ? 'd' : 'k'] += $saldo;
      }

      $baris[] = (object) ['lv' => 6, 'induk' => $a->idrek3, 'id' => $a->idrek6, 'nama' => $a->namarek6,
        'kode' => implode('.', [$a->koderek1, $a->koderek2, $a->koderek3, $a->koderek4, $a->koderek5, $a->koderek6]),
        'nilai' => $nilai, 'ada' => $nilai['d'] != 0 || $nilai['k'] != 0];
      $tambah($induk3->nilai, $nilai);
      $tambah($total, $nilai);
    }

    return [collect($baris), $total];
  }

  /** Buang akun tanpa saldo serta kelompok yang menjadi kosong. */
  private function hanyaBersaldo(Collection $baris): Collection
  {
    $ada3 = $baris->where('lv', 6)->where('ada', true)->pluck('induk')->unique()->flip();
    $sisa = $baris->filter(fn ($b) => $b->lv !== 6 || $b->ada)->filter(fn ($b) => $b->lv !== 3 || isset($ada3[$b->id]))->values();

    // Level 1/2 hanya dipertahankan bila ada level 3 di bawahnya.
    $hasil = [];
    foreach ($sisa as $i => $b) {
      if ($b->lv === 1 || $b->lv === 2) {
        $berisi = false;
        for ($j = $i + 1; $j < $sisa->count() && $sisa[$j]->lv > $b->lv; $j++) {
          if ($sisa[$j]->lv === 3) {
            $berisi = true;
            break;
          }
        }
        if (!$berisi) {
          continue;
        }
      }
      $hasil[] = $b;
    }

    return collect($hasil);
  }

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
