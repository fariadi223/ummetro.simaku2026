<?php

namespace App\Services;

use App\Models\Prodi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Menarik daftar program studi dari SIMPRODI ke tabel `prodi`.
 *
 * Autentikasinya sama dengan SimprodiAlumniService: kunci rahasia dikirim
 * sebagai sha1() di dalam badan JSON, bukan sebagai header.
 *
 * Bentuk balasan /api/prodi/filter (terkonfirmasi dari balasan nyata):
 *
 *   {"msg":"Ok","data":[{"Kode_Fak":"09","Kode_Jur":"1140","Kode_Prg":"11401",
 *     "Nama_Prg":"Fisioterapi","Strata":"C","Jenjang_Didik":"D3", ...}, ...]}
 *
 * Dari belasan kolom yang dikirim, hanya tiga yang diambil (lihat PETA_KOLOM).
 * Kolom `fakultas` dan `is_active` di tabel prodi sengaja tidak disentuh:
 * keduanya diisi/diatur dari sisi aplikasi ini, jadi penarikan ulang tidak
 * boleh menimpanya.
 */
class SimprodiProdiService
{
  /** Endpoint bawaan, relatif terhadap SIMPRODI_URL. */
  private const ENDPOINT_BAWAAN = 'prodi/filter';

  /**
   * Kolom balasan -> kolom tabel prodi.
   *
   * Ditulis huruf kecil; pembandingan tidak memandang besar kecil huruf
   * maupun garis bawah, jadi `Kode_Prg`, `kode_prg`, dan `kodeprg`
   * sama-sama dikenali.
   */
  private const PETA_KOLOM = [
    'kode_prg' => 'kode',          // "57401"
    'nama_prg' => 'nama',          // "Sistem Informasi"
    'jenjang_didik' => 'jenjang',  // "S1", "D3", "S2", "Profesi"
  ];

  /** Batas panjang kolom tabel prodi, supaya nilai panjang tidak ditolak. */
  private const BATAS_PANJANG = [
    'kode' => 10,
    'nama' => 255,
    'jenjang' => 10,
  ];

  private ?string $balasanMentah = null;

  /**
   * Tarik daftar prodi dan simpan.
   *
   * @return array<string, mixed>
   */
  public function tarik(bool $simulasi = false, ?string $endpoint = null): array
  {
    $balasan = $this->panggilApi($this->bangunUrl($endpoint));
    $mentah = $this->ambilBaris($balasan);

    $baris = [];
    $dilewati = 0;

    foreach ($mentah as $item) {
      $petakan = $this->petakanBaris($item);

      // Tanpa kode, baris tidak bisa dicocokkan maupun disimpan: `kode`
      // adalah kolom unik yang dipakai sebagai acuan upsert.
      if (($petakan['kode'] ?? '') === '' || ($petakan['nama'] ?? '') === '') {
        $dilewati++;
        continue;
      }

      $baris[$petakan['kode']] = $petakan;
    }

    // Kode ganda dalam satu balasan akan membuat upsert gagal di PostgreSQL
    // ("ON CONFLICT DO UPDATE command cannot affect row a second time"),
    // jadi yang dipakai adalah kemunculan terakhir -- sudah dijamin oleh
    // pengindeksan berdasarkan kode di atas.
    $baris = array_values($baris);

    $kodeAda = Prodi::whereIn('kode', array_column($baris, 'kode'))->pluck('kode')->all();
    $baru = count($baris) - count($kodeAda);

    if (!$simulasi && $baris !== []) {
      foreach (array_chunk($baris, 200) as $potongan) {
        Prodi::upsert($potongan, ['kode'], ['nama', 'jenjang']);
      }
    }

    return [
      'terbaca' => count($mentah),
      'tersimpan' => count($baris),
      'baru' => $baru,
      'diperbarui' => count($kodeAda),
      'dilewati' => $dilewati,
      'pesan_server' => $this->pesanServer($balasan),
      'bentuk_balasan' => array_keys($balasan),
      'kolom_baris' => $mentah === [] ? [] : array_keys((array) $mentah[0]),
      'kolom_tak_dikenali' => $this->kolomTakDikenali($mentah),
      // Prodi lokal yang tidak ada di balasan; tidak diubah, hanya dilaporkan.
      'lokal_tak_ada_di_simprodi' => $baris === []
        ? []
        : Prodi::whereNotIn('kode', array_column($baris, 'kode'))->pluck('kode')->all(),
    ];
  }

  /** Badan balasan terakhir dari SIMPRODI, sebelum di-decode. */
  public function balasanMentah(): ?string
  {
    return $this->balasanMentah;
  }

  public function bangunUrl(?string $endpoint = null): string
  {
    $endpoint = trim((string) ($endpoint ?: self::ENDPOINT_BAWAAN));

    if ($endpoint === '') {
      throw new RuntimeException('Endpoint SIMPRODI kosong.');
    }

    // Endpoint berupa URL lengkap dipakai apa adanya, supaya bisa diarahkan
    // ke host lain tanpa mengubah konfigurasi.
    if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
      return $endpoint;
    }

    $basis = rtrim((string) config('simprodi.url'), '/');

    if ($basis === '') {
      throw new RuntimeException(
        'SIMPRODI_URL kosong, sedangkan endpoint yang diberikan bukan URL lengkap.'
      );
    }

    return $basis . '/' . ltrim($endpoint, '/');
  }

  /** @return array<string, mixed> */
  private function panggilApi(string $url): array
  {
    $rahasia = config('simprodi.secret');

    if (blank($rahasia)) {
      throw new RuntimeException(
        'SIMPRODI_SECRET belum diisi di .env, permintaan tidak dikirim.'
      );
    }

    try {
      $respons = Http::withBody(
        json_encode(['apikey' => sha1($rahasia)]),
        config('simprodi.content_type')
      )
        ->withOptions(['verify' => config('simprodi.verify')])
        ->timeout((int) config('simprodi.timeout'))
        ->post($url);
    } catch (ConnectionException $e) {
      throw new RuntimeException('SIMPRODI tidak dapat dihubungi: ' . $e->getMessage(), 0, $e);
    }

    $this->balasanMentah = $respons->body();

    // Kunci yang salah dijawab HTTP 500 berisi {"err":100,"msg":"Token is
    // invalid"}, jadi badan balasan dibaca lebih dulu agar pesannya bisa
    // diteruskan apa adanya, bukan tertutup pesan "status 500" saja.
    $isi = $respons->json();

    if (is_array($isi) && isset($isi['err']) && (int) $isi['err'] !== 200) {
      throw new RuntimeException(sprintf(
        'SIMPRODI menolak permintaan (err %s): %s',
        $isi['err'],
        $isi['msg'] ?? 'tanpa pesan'
      ));
    }

    if ($respons->failed()) {
      throw new RuntimeException('SIMPRODI menjawab dengan status HTTP ' . $respons->status() . '.');
    }

    if (!is_array($isi)) {
      throw new RuntimeException(
        'Balasan SIMPRODI bukan JSON yang bisa dibaca. Potongan awal: '
        . mb_substr(trim($respons->body()), 0, 200)
      );
    }

    return $isi;
  }

  /**
   * Cari daftar baris prodi di dalam balasan.
   *
   * Bentuk yang dipakai sekarang adalah `data`, tapi array di akar juga
   * diterima supaya perubahan kecil di sisi SIMPRODI tidak langsung memutus
   * penarikan.
   *
   * @return array<int, mixed>
   */
  private function ambilBaris(array $balasan): array
  {
    foreach (['data', 'rows', 'result', 'prodi'] as $kunci) {
      if (isset($balasan[$kunci]) && is_array($balasan[$kunci])) {
        return array_values($balasan[$kunci]);
      }
    }

    return $this->daftarObjek($balasan) ? array_values($balasan) : [];
  }

  /** Array yang isinya baris-baris, bukan satu baris tunggal. */
  private function daftarObjek(array $nilai): bool
  {
    if ($nilai === [] || array_keys($nilai) !== range(0, count($nilai) - 1)) {
      return false;
    }

    return is_array($nilai[0]) || is_object($nilai[0]);
  }

  private function pesanServer(array $balasan): ?string
  {
    foreach (['msg', 'message', 'pesan'] as $kunci) {
      if (isset($balasan[$kunci]) && is_scalar($balasan[$kunci])) {
        return (string) $balasan[$kunci];
      }
    }

    return null;
  }

  /**
   * Ubah satu baris balasan menjadi kolom tabel prodi.
   *
   * @return array<string, string>
   */
  private function petakanBaris(mixed $mentah): array
  {
    $hasil = [];

    foreach ((array) $mentah as $kolom => $nilai) {
      $tujuan = self::PETA_KOLOM[$this->normalkanNamaKolom($kolom)] ?? null;

      if ($tujuan === null || !is_scalar($nilai)) {
        continue;
      }

      $hasil[$tujuan] = mb_substr(trim((string) $nilai), 0, self::BATAS_PANJANG[$tujuan]);
    }

    return $hasil;
  }

  /** `Kode_Prg`, `kode prg`, dan `KodePrg` sama-sama menjadi `kode_prg`. */
  private function normalkanNamaKolom(string $kolom): string
  {
    $kolom = strtolower(trim($kolom));

    return in_array($kolom, array_keys(self::PETA_KOLOM), true)
      ? $kolom
      : (string) preg_replace('~[^a-z0-9]~', '', $kolom);
  }

  /**
   * Kolom balasan yang tidak dipetakan ke mana pun.
   *
   * Dilaporkan sebagai keterangan, bukan masalah: endpoint ini memang
   * mengirim banyak kolom yang tidak diperlukan tabel prodi.
   *
   * @return array<int, string>
   */
  private function kolomTakDikenali(array $mentah): array
  {
    if ($mentah === []) {
      return [];
    }

    $dikenal = [];

    foreach (array_keys(self::PETA_KOLOM) as $kunci) {
      $dikenal[] = (string) preg_replace('~[^a-z0-9]~', '', $kunci);
    }

    $sisa = [];

    foreach (array_keys((array) $mentah[0]) as $kolom) {
      if (!in_array($this->normalkanNamaKolom((string) $kolom), array_merge(array_keys(self::PETA_KOLOM), $dikenal), true)) {
        $sisa[] = (string) $kolom;
      }
    }

    return $sisa;
  }
}
