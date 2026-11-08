<?php

namespace App\Http\Controllers\admin;

use App\Exports\HierarkiExport;
use App\Http\Controllers\Controller;
use App\Services\Hierarki\HierarkiService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pengelola data kode berjenjang (COA, MAK, ...). Navigasinya seperti v1:
 * daftar level 1, klik sebuah data untuk melihat turunannya, dst.
 *
 * Turunan mengisi service() dan meta().
 */
abstract class HierarkiController extends Controller
{
  abstract protected function service(): HierarkiService;

  /**
   * Teks & tampilan halaman:
   *   route    : awalan nama route, mis. 'admin.coa'
   *   judul    : judul halaman
   *   sub      : subjudul
   *   ikon     : kelas ikon Tabler
   *   istilah  : sebutan satu data, mis. 'rekening'
   *   anak     : sebutan turunannya, mis. 'sub-akun'
   *   file     : awalan nama berkas ekspor
   */
  abstract protected function meta(): array;

  public function index()
  {
    return $this->tampil(null, null);
  }

  public function show(int $level, int $id)
  {
    abort_if($level >= $this->service()->levelMax(), 404);
    abort_unless($this->service()->cari($level, $id), 404);

    return $this->tampil($level, $id);
  }

  private function tampil(?int $level, ?int $id)
  {
    $svc = $this->service();
    $levelAnak = ($level ?? 0) + 1;

    return view('content.admin.hierarki.index', [
      'meta' => $this->meta(),
      'induk' => $id ? $svc->cari($level, $id) : null,
      'levelAnak' => $levelAnak,
      'jejak' => $id ? $svc->jejak($level, $id) : [],
      'daftar' => $svc->anak($levelAnak, $id),
      'panjang' => $svc->panjang($levelAnak),
      'tambahan' => $svc->tambahan($levelAnak),
      'levelMax' => $svc->levelMax(),
      'statistik' => $svc->statistik(),
    ]);
  }

  public function store(Request $request, int $level)
  {
    $svc = $this->service();
    $svc->cekLevel($level);
    $indukId = $level > 1 ? (int) $request->input('induk_id') : null;

    if ($level > 1) {
      abort_unless($svc->cari($level - 1, $indukId), 404);
    }

    [$kode, $nama, $tambahan] = $this->validasi($request, $level, $indukId);

    $svc->simpan($level, $indukId, $kode, $nama, $tambahan);

    return back()->with('status', ucfirst($this->meta()['istilah']) . " {$kode} - {$nama} berhasil ditambahkan.");
  }

  public function update(Request $request, int $level, int $id)
  {
    $svc = $this->service();
    $svc->cekLevel($level);
    $data = $svc->cari($level, $id);
    abort_unless($data, 404);

    [$kode, $nama, $tambahan] = $this->validasi($request, $level, $data->induk_id ?? null, $id, (string) $data->kode);

    $svc->ubah($level, $id, $kode, $nama, $tambahan);

    return back()->with('status', ucfirst($this->meta()['istilah']) . " {$data->kode_lengkap} berhasil diperbarui.");
  }

  public function destroy(int $level, int $id)
  {
    $svc = $this->service();
    $svc->cekLevel($level);
    $data = $svc->cari($level, $id);
    abort_unless($data, 404);

    if ($alasan = $svc->alasanTidakBolehHapus($level, $id)) {
      return back()->with('error', "{$data->kode_lengkap} - {$data->nama}: {$alasan}");
    }

    $svc->hapus($level, $id);

    return back()->with('status', ucfirst($this->meta()['istilah']) . " {$data->kode_lengkap} - {$data->nama} berhasil dihapus.");
  }

  /** Salin semua turunan data ini ke data lain pada level yang sama. */
  public function salin(Request $request, int $level, int $id)
  {
    $svc = $this->service();
    abort_if($level >= $svc->levelMax(), 404);
    $sumber = $svc->cari($level, $id);
    abort_unless($sumber, 404);

    $input = $request->validate(['tujuan_id' => ['required', 'integer']], [], ['tujuan_id' => 'tujuan']);

    $tujuan = $svc->cari($level, (int) $input['tujuan_id']);
    if (!$tujuan || $tujuan->id === $sumber->id) {
      return back()->with('error', 'Tujuan salin tidak valid.');
    }

    $jumlah = $svc->salinSubData($level, $sumber->id, $tujuan->id);

    return back()->with('status', $jumlah > 0
      ? "{$jumlah} sub-data dari {$sumber->kode_lengkap} berhasil disalin ke {$tujuan->kode_lengkap}."
      : "Tidak ada sub-data baru: semua kode sudah ada di {$tujuan->kode_lengkap}.");
  }

  /** Pencarian (JSON) untuk kotak cari dan pilihan tujuan salin. */
  public function cari(Request $request)
  {
    $svc = $this->service();
    $kata = trim((string) $request->input('q'));
    $level = $request->integer('level') ?: null;

    if ($kata === '') {
      return response()->json(['results' => []]);
    }
    if ($level) {
      $svc->cekLevel($level);
    }

    $route = $this->meta()['route'];
    $hasil = $svc->cariGlobal($kata, 30, $level)->map(fn ($r) => [
      'id' => $r->id,
      'level' => $r->level,
      'text' => "{$r->kode_lengkap} - {$r->nama}",
      // Level terakhir tidak punya halaman sendiri: buka daftar induknya.
      'url' => $r->level < $svc->levelMax()
        ? route("{$route}.show", [$r->level, $r->id])
        : ($r->level > 1 ? route("{$route}.show", [$r->level - 1, $r->induk_id]) : route("{$route}.index")),
    ]);

    return response()->json(['results' => $hasil]);
  }

  /** Halaman siap cetak berisi seluruh data (padanan "Cetak" mode=all di v1). */
  public function cetak()
  {
    $svc = $this->service();

    $tambahan = [];
    for ($l = 1; $l <= $svc->levelMax(); $l++) {
      $tambahan += $svc->tambahan($l);
    }

    $baris = $svc->semua();

    return view('content.admin.hierarki.cetak', [
      'meta' => $this->meta(),
      'baris' => $baris,
      'levelMax' => $svc->levelMax(),
      'tambahan' => $tambahan,
      // Hitung dari baris yang tercetak; data yatim (induknya sudah tidak ada)
      // tidak ikut tercetak sehingga tidak boleh ikut dijumlah.
      'statistik' => $baris->countBy('level')->sortKeys()->all(),
    ]);
  }

  public function export()
  {
    $svc = $this->service();
    $meta = $this->meta();

    return Excel::download(
      new HierarkiExport($svc->semua(), $svc->levelMax()),
      $meta['file'] . '-SIMAKU-' . date('Ymd') . '.xlsx'
    );
  }

  /**
   * @param string|null $kodeLama kode saat ini (mode ubah). Data lama memuat
   *        kode kembar dalam satu induk; data seperti itu tetap boleh diubah
   *        namanya selama kodenya tidak diganti.
   * @return array{0: string, 1: string, 2: array}
   */
  private function validasi(Request $request, int $level, ?int $indukId, ?int $kecualiId = null, ?string $kodeLama = null): array
  {
    $svc = $this->service();
    $panjang = $svc->panjang($level);
    $tambahan = $svc->tambahan($level);
    $istilah = $this->meta()['istilah'];

    $aturan = [
      'kode' => ['required', 'regex:/^[0-9]+$/', 'max:' . $panjang['kode']],
      'nama' => ['required', 'string', 'max:' . $panjang['nama']],
    ];
    $label = ['kode' => "kode {$istilah}", 'nama' => "nama {$istilah}"];

    foreach ($tambahan as $kolom => $def) {
      $aturan["tambahan.{$kolom}"] = $def['aturan'];
      $label["tambahan.{$kolom}"] = $def['label'];
    }

    $data = $request->validate($aturan, ['kode.regex' => 'Kode hanya boleh berisi angka.'], $label);

    // Kolom kode bertipe INT menyimpan "01" sebagai 1.
    $kode = $panjang['kode_int'] ? (string) (int) $data['kode'] : $data['kode'];
    $nama = trim($data['nama']);

    $kodeBerubah = $kodeLama === null || $kodeLama !== $kode;

    if ($kodeBerubah && $svc->kodeDipakai($level, $indukId, $kode, $kecualiId)) {
      throw ValidationException::withMessages([
        'kode' => "Kode {$kode} sudah dipakai {$istilah} lain pada induk yang sama.",
      ]);
    }

    return [$kode, $nama, array_intersect_key($data['tambahan'] ?? [], $tambahan)];
  }
}
