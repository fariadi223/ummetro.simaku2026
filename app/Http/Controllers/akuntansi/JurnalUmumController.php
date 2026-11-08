<?php

namespace App\Http\Controllers\akuntansi;

use App\Http\Controllers\Controller;
use App\Models\Info;
use App\Models\Sumberdana;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Input jurnal umum (/akuntansi/jurnal-umum), padanan Akuntansi::jurnalumum & jurnalumum_proses v1.
 *
 *   Simpan = satu baris ueu_tbl_jurnal_spm per rekening (multi rekening) pada T.A login;
 *            tiap baris hanya debet atau kredit, Σ debet = Σ kredit. Unit, MAK, sumber dana,
 *            tanggal, uraian & bukti sama untuk semua baris.
 *
 * Perbedaan dari v1: unit (id_uk + kodeunit = FF), mata anggaran (id_mak) dan no. bukti ikut disimpan
 * (form v1 menampilkan unit & MAK tetapi controller-nya tidak menyimpan; jurnal BKK v1 menyimpannya),
 * bisa lebih dari dua rekening (v1 tepat satu debet & satu kredit), jurnal wajib seimbang,
 * semua baris disimpan dalam satu transaksi, dan ditandai ket = 'JU' untuk riwayat di sebelah kanan form.
 */
class JurnalUmumController extends Controller
{
  public const MAKS_BARIS = 50;
  /** Penanda sumber di kolom ket (seperti 'TB', 'adminpenerimaan' di v1) agar riwayat jurnal umum bisa ditampilkan. */
  public const KET = 'JU';
  /** Jumlah entri riwayat yang ditampilkan di samping form (terbaru dulu). */
  public const MAKS_RIWAYAT = 10;

  public function create()
  {
    $tahun = $this->tahun();
    $tgl = today()->toDateString(); // riwayat selalu hari ini

    return view('content.akuntansi.jurnal-umum.form', [
      'tahun' => $tahun,
      'tglRiwayat' => $tgl,
      'riwayat' => ($semua = $this->riwayat($tahun, $tgl))->take(self::MAKS_RIWAYAT),
      'riwayatTotal' => $semua->count(),
      'rekening' => $this->rekening(),
      'unit' => $this->subUnit(),
      'mak' => $this->mak(),
      'sumber' => Sumberdana::whereNotNull('kode')->orderBy('kode')->get(['id', 'kode', 'nama_penerimaan']),
    ]);
  }

  public function store(Request $request)
  {
    $tahun = $this->tahun();

    // Normalisasi angka ("1.500.000" → 1500000) & buang baris yang benar-benar kosong.
    $angka = fn ($v) => ($v = preg_replace('/\D/', '', (string) $v)) === '' ? 0 : (int) $v;
    $baris = collect((array) $request->input('baris', []))
      ->map(fn ($b) => ['idrek6' => ($b['idrek6'] ?? '') === '' ? null : $b['idrek6'], 'debet' => $angka($b['debet'] ?? ''), 'kredit' => $angka($b['kredit'] ?? '')])
      ->reject(fn ($b) => $b['idrek6'] === null && !$b['debet'] && !$b['kredit'])
      ->values()->all();
    $request->merge(['baris' => $baris]);

    $validator = Validator::make($request->all(), [
      'tgl_trk' => ['required', 'date_format:Y-m-d'],
      'bukti' => ['nullable', 'string', 'max:100'],
      'id_uk' => ['required', 'integer', Rule::exists('ueu_tbl_unit', 'id_unit')->where(fn ($q) => $q->where('kode', 'not like', '%.00'))],
      'id_mak' => ['nullable', 'integer', Rule::exists('ueu_tbl_mak4', 'idmak4')],
      'uraian' => ['required', 'string', 'max:500'],
      'id_sumber' => ['required', 'integer', Rule::exists('ueu_tbl_sumberdana', 'id')],
      'baris' => ['required', 'array', 'min:2', 'max:' . self::MAKS_BARIS],
      'baris.*.idrek6' => ['required', 'integer', Rule::exists('ueu_tbl_rek6', 'idrek6')],
      'baris.*.debet' => ['integer', 'min:0', 'max:999999999999'],
      'baris.*.kredit' => ['integer', 'min:0', 'max:999999999999'],
    ], [
      'baris.min' => 'Jurnal minimal terdiri dari 2 baris rekening.',
      'baris.required' => 'Jurnal minimal terdiri dari 2 baris rekening.',
      'baris.*.idrek6.required' => 'Baris :position: rekening belum dipilih.',
      'baris.*.idrek6.exists' => 'Baris :position: rekening tidak ditemukan.',
    ], [
      'tgl_trk' => 'Tanggal', 'bukti' => 'No. Bukti', 'id_uk' => 'Unit', 'id_mak' => 'Mata Anggaran',
      'uraian' => 'Uraian', 'id_sumber' => 'Sumber Dana', 'baris' => 'Baris jurnal',
    ]);
    $validator->after(function ($v) use ($baris) {
      foreach ($baris as $i => $b) {
        if (($b['debet'] > 0) === ($b['kredit'] > 0)) {
          $v->errors()->add("baris.$i.debet", 'Baris ' . ($i + 1) . ': isi salah satu, debet ATAU kredit.');
        }
      }
      $d = array_sum(array_column($baris, 'debet'));
      $k = array_sum(array_column($baris, 'kredit'));
      if ($d !== $k) {
        $v->errors()->add('seimbang', 'Jurnal tidak seimbang: total debet Rp ' . number_format($d, 0, ',', '.')
          . ' ≠ total kredit Rp ' . number_format($k, 0, ',', '.') . '.');
      }
    });
    $data = $validator->validate();

    $unit = DB::table('ueu_tbl_unit')->where('id_unit', $data['id_uk'])->first(['kode', 'nama']);
    $ff = explode('.', (string) $unit->kode)[0];

    // Kolom NOT NULL tanpa default pada tabel lama (v1 berjalan non-strict) diisi eksplisit.
    $isi = fn (array $b) => [
      'tahun' => (string) $tahun,
      'id_uk' => (int) $data['id_uk'],
      'kodeunit' => ctype_digit($ff) ? (int) $ff : 0,
      'idkeg' => 0,
      'idtama' => 0,
      'id_sumber' => (int) $data['id_sumber'],
      'id_mak' => !empty($data['id_mak']) ? (int) $data['id_mak'] : null,
      'idrek6' => (int) $b['idrek6'],
      'idrinci' => 0,
      'idrinci_na' => 0,
      'tgl_trk' => $data['tgl_trk'],
      'uraian' => trim($data['uraian']),
      'bukti' => trim((string) ($data['bukti'] ?? '')),
      'debet' => $b['debet'],
      'kredit' => $b['kredit'],
      'ket' => self::KET,
      'tgl_trans' => '',
    ];

    DB::transaction(fn () => DB::table('ueu_tbl_jurnal_spm')->insert(array_map($isi, $data['baris'])));

    return redirect()->route('akuntansi.jurnal-umum.create')
      ->withInput($request->only('tgl_trk', 'id_uk', 'id_sumber'))
      ->with('status', 'Jurnal umum "' . trim($data['uraian']) . '" (' . count($data['baris']) . ' baris, total Rp '
        . number_format(array_sum(array_column($data['baris'], 'debet')), 0, ',', '.') . ', ' . $unit->nama . ') tersimpan.');
  }

  /**
   * Jurnal umum (ket JU) bertanggal $tgl, dikelompokkan per entri: baris berurutan (id_trans) dengan
   * kepala sama (tgl, bukti, uraian, unit, sumber, MAK) sampai debet = kredit. Terbaru dulu.
   */
  private function riwayat(int $tahun, string $tgl)
  {
    $baris = DB::table('ueu_tbl_jurnal_spm as j')
      ->join('ueu_tbl_rek6 as r6', 'r6.idrek6', '=', 'j.idrek6')
      ->join('ueu_tbl_rek5 as r5', 'r5.idrek5', '=', 'r6.idrek5')
      ->join('ueu_tbl_rek4 as r4', 'r4.idrek4', '=', 'r5.idrek4')
      ->join('ueu_tbl_rek3 as r3', 'r3.idrek3', '=', 'r4.idrek3')
      ->join('ueu_tbl_rek2 as r2', 'r2.idrek2', '=', 'r3.idrek2')
      ->join('ueu_tbl_rek1 as r1', 'r1.idrek1', '=', 'r2.idrek1')
      ->leftJoin('ueu_tbl_unit as u', 'u.id_unit', '=', 'j.id_uk')
      ->where('j.tahun', (string) $tahun)->where('j.ket', self::KET)->where('j.tgl_trk', $tgl)
      ->orderBy('j.id_trans')->limit(1000)
      ->select('j.id_trans', 'j.tgl_trk', 'j.bukti', 'j.uraian', 'j.id_uk', 'j.id_sumber', 'j.id_mak', 'j.debet', 'j.kredit',
        'r6.namarek6', 'u.kode as kode_unit', 'u.nama as nama_unit',
        DB::raw('CONCAT(r1.koderek1, r2.koderek2, r3.koderek3, r4.koderek4, r5.koderek5, r6.koderek6) as kode_rek'))
      ->get();

    $entri = [];
    $aktif = null;
    foreach ($baris as $b) {
      $kepala = implode('|', [$b->bukti, $b->uraian, $b->id_uk, $b->id_sumber, $b->id_mak]);
      if (!$aktif || $aktif->kepala !== $kepala || $aktif->selesai) {
        $aktif = (object) ['kepala' => $kepala, 'id' => $b->id_trans, 'bukti' => $b->bukti, 'uraian' => $b->uraian,
          'unit' => trim($b->kode_unit . ' ' . $b->nama_unit), 'debet' => 0, 'kredit' => 0, 'baris' => [], 'selesai' => false];
        $entri[] = $aktif;
      }
      $aktif->baris[] = $b;
      $aktif->debet += (int) $b->debet;
      $aktif->kredit += (int) $b->kredit;
      $aktif->selesai = $aktif->debet > 0 && $aktif->debet === $aktif->kredit;
    }

    return collect($entri)->reverse()->values();
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

  /** Sub unit (kode bukan "FF.00"), Munit::get_sub_unit_ v1. */
  private function subUnit()
  {
    return DB::table('ueu_tbl_unit')->where('kode', 'not like', '%.00')->orderBy('kode')->get(['id_unit', 'kode', 'nama']);
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

  private function tahun(): int
  {
    return (int) (session('periode') ?: Info::tahun(['penjurnalan' => 'open']));
  }
}
