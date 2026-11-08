<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Menarik data lulusan dari SIMPRODI lalu menyimpannya ke tabel `alumni`.
 *
 * Pengganti library Apiclient milik proyek CodeIgniter lama: kunci rahasia
 * dikirim sebagai sha1(), filter tahun lulus (`thn`) dan kode program studi
 * (`prg`) dikirim sebagai badan JSON ke /apiclient/synctracer.
 *
 * CATATAN PENTING soal perilaku endpoint-nya.
 * Di proyek lama, hasil panggilan ini hanya dibaca bagian `err`-nya saja
 * (Admin::impor_proses) -- tidak ada satu baris pun yang menyimpan data ke
 * tabel lulusan. Artinya besar kemungkinan SIMPRODI-lah yang menulis
 * langsung ke basis data tracer lama, bukan mengembalikan baris data.
 * Kalau ternyata begitu, endpoint ini tidak akan mengisi tabel `alumni`
 * milik proyek baru (yang memakai PostgreSQL terpisah), dan jalur impor
 * harus diubah ke yang mengembalikan data.
 *
 * Karena itu service ini:
 *   - menerima beberapa bentuk balasan yang masuk akal (lihat ambilBaris),
 *   - menerima dua gaya penamaan kolom (gaya SIMPRODI/MSSQL seperti `Npm`
 *     dan gaya tabel lama seperti `npm_mhs`), dan
 *   - mencatat struktur balasan ke log bila tidak ada baris yang terbaca,
 *     supaya bentuk sebenarnya bisa dilihat tanpa menebak.
 */
class SimprodiAlumniService
{
  /**
   * Peta nama kolom dari balasan SIMPRODI ke kolom tabel `alumni`.
   *
   * Kunci ditulis huruf kecil; pembandingan tidak memandang besar kecil huruf
   * maupun pemisah, jadi `Tanggal_Yudis`, `tanggal_yudis`, dan `tanggalyudis`
   * sama-sama dikenali.
   *
   * Dikelompokkan menurut tingkat kepastiannya, supaya jelas mana yang sudah
   * terbukti dari balasan nyata dan mana yang masih cadangan.
   */
  private const PETA_KOLOM = [
    /*
     * A. Terkonfirmasi dari balasan GET /api/alumni/filter.
     *    Contoh nilai di komentar diambil dari balasan sungguhan.
     */
    'npm' => 'npm_mhs',               // "20410009"
    'nama_mhs' => 'nama_mhs',         // "Aldi Riefanza"
    'progstudi' => 'kode_prg',        // "57401"
    'nama_prg' => 'nama_prg',         // "Sistem Informasi"
    'fakultas' => 'kode_fak',         // "08"
    'jurusan' => 'kode_jur',          // "5740"
    'ipk_mhs' => 'ipk_mhs',           // null atau "3.45"
    'tanggal_yudis' => 'tgl_yudisium',// "May 23 2025 12:00:00:000AM"
    'tanggal_lhr' => 'tgl_lahir',     // "02 September 1999" / "2004-06-11"
    'mhs_nik' => 'nik',               // "1808070209990002"
    'tlpn' => 'tlpn',                 // "081946512996"

    /*
     * B. Alias gaya snake_case / tabel lama tbl_lulusan. Dipertahankan agar
     *    balasan yang memakai nama kolom tabel tetap terbaca tanpa ubahan.
     */
    'npm_mhs' => 'npm_mhs',
    'kode_prg' => 'kode_prg',
    'kode_fak' => 'kode_fak',
    'kode_jur' => 'kode_jur',
    'nama_fak' => 'nama_fak',
    'nama_jur' => 'nama_jur',
    'thnlulus' => 'thnlulus',
    'periode' => 'periode',
    'tgl_yudisium' => 'tgl_yudisium',
    'tgl_lahir' => 'tgl_lahir',
    'tmpt_lahir' => 'tmpt_lahir',
    'alamat_asal' => 'alamat_asal',
    'nik' => 'nik',
    'nik_mhs' => 'nik',

    /*
     * C. Belum pernah muncul di balasan, tapi kolomnya ada di tabel alumni.
     *    Disiapkan supaya penambahan field di sisi SIMPRODI langsung terpakai
     *    tanpa menyentuh kode.
     */
    'email' => 'email',
    'telepon' => 'tlpn',
    'hp' => 'tlpn',
    'tempat_lhr' => 'tmpt_lahir',
    'alamat' => 'alamat_asal',
    'agama' => 'agama',
    'sks_tot' => 'sks_tot',
  ];

  /**
   * Field balasan yang sengaja tidak disimpan.
   *
   * `Angkatan` dikirim SIMPRODI tapi tabel `alumni` tidak punya kolomnya.
   * Didaftarkan di sini, bukan dibiarkan tanpa keterangan, supaya laporan
   * "kolom tidak dikenali" hanya memuat field yang benar-benar baru.
   */
  private const KOLOM_DIABAIKAN = ['angkatan'];

  /**
   * Endpoint yang dipakai bila pemanggil tidak menyebutkan apa pun dan
   * config('simprodi.endpoint') tidak ada. Nilainya menyalin library
   * Apiclient lama; endpoint sebenarnya diberikan per pemanggilan.
   */
  private const ENDPOINT_BAWAAN = 'api';

  /** Badan balasan terakhir, apa adanya. Dipakai command untuk opsi --raw. */
  private ?string $balasanMentah = null;

  /** Nama field balasan yang tidak ada di PETA_KOLOM maupun KOLOM_DIABAIKAN. */
  private array $kolomTakDikenali = [];

  /** Kolom bertipe date di tabel alumni. */
  private const KOLOM_TANGGAL = ['tgl_lahir', 'tgl_yudisium'];

  /** Kolom angka beserta jumlah desimalnya. */
  private const KOLOM_ANGKA = ['ipk_mhs' => 2, 'sks_tot' => 0];

  /**
   * Batas panjang kolom varchar di tabel alumni. Nilai yang lebih panjang
   * dipotong, bukan ditolak -- lebih baik satu baris masuk dengan nama
   * terpotong daripada seluruh batch gagal karena satu data kotor.
   */
  private const BATAS_PANJANG = [
    'thnlulus' => 4, 'periode' => 3, 'kode_fak' => 4, 'kode_jur' => 6,
    'kode_prg' => 8, 'nama_fak' => 100, 'nama_jur' => 100, 'nama_prg' => 180,
    'npm_mhs' => 15, 'nama_mhs' => 160, 'tmpt_lahir' => 160, 'agama' => 15,
    'tlpn' => 20, 'email' => 165, 'alamat_asal' => 185, 'nik' => 25,
  ];

  /**
   * Tarik data lulusan lalu simpan ke tabel alumni.
   *
   * @param  string|null  $tahun      tahun lulus, mis. '2024'
   * @param  string|null  $kodeProdi  kode program studi, mis. '84105'
   * @param  bool         $simulasi   true = hanya menghitung, tidak menyimpan
   * @param  string|null  $endpoint   endpoint lain, menimpa config simprodi.endpoint
   * @return array{terbaca:int,baru:int,diperbarui:int,dilewati:int,dilewati_tanpa_npm:int,pesan_server:string|null,bentuk_balasan:array<string>,kolom_baris:array<string>,kolom_tak_dikenali:array<string>,url:string}
   */
  public function tarik(
    ?string $tahun = null,
    ?string $kodeProdi = null,
    bool $simulasi = false,
    ?string $endpoint = null
  ): array {
    $url = $this->bangunUrl($endpoint);

    $this->kolomTakDikenali = [];

    $balasan = $this->panggilApi($url, $tahun, $kodeProdi);

    $baris = $this->ambilBaris($balasan);

    $hasil = [
      'terbaca' => count($baris),
      'baru' => 0,
      'diperbarui' => 0,
      'dilewati' => 0,
      'dilewati_tanpa_npm' => 0,
      'pesan_server' => $this->pesanServer($balasan),
      'bentuk_balasan' => $this->bentukBalasan($balasan),
      // Nama kolom pada baris pertama: penanda paling ringkas untuk tahu
      // apakah bentuk balasan SIMPRODI berubah.
      'kolom_baris' => array_keys((array) ($baris[0] ?? [])),
      'kolom_tak_dikenali' => [],
      'url' => $url,
    ];

    if ($baris === []) {
      Log::warning('SIMPRODI: tidak ada baris lulusan yang terbaca dari balasan.', [
        'url' => $url,
        'tahun' => $tahun,
        'prodi' => $kodeProdi,
        'bentuk_balasan' => $hasil['bentuk_balasan'],
      ]);

      return $hasil;
    }

    $siap = [];

    foreach ($baris as $mentah) {
      $rapi = $this->petakanBaris($mentah);

      // Tanpa NPM tidak ada kunci untuk menyimpan: npm_mhs adalah primary key.
      // Dihitung terpisah karena sebabnya bukan data kotor biasa, melainkan
      // balasan yang tidak menyertakan kolom Npm sama sekali.
      if (($rapi['npm_mhs'] ?? '') === '') {
        $hasil['dilewati']++;
        $hasil['dilewati_tanpa_npm']++;
        continue;
      }

      // Tahun lulus tidak selalu dikirim server; isi dari filter bila kosong.
      if (($rapi['thnlulus'] ?? null) === null && $tahun !== null) {
        $rapi['thnlulus'] = $tahun;
      }

      $siap[$rapi['npm_mhs']] = $rapi;
    }

    $hasil['kolom_tak_dikenali'] = array_keys($this->kolomTakDikenali);

    if ($siap === []) {
      return $hasil;
    }

    $sudahAda = DB::table('alumni')
      ->whereIn('npm_mhs', array_keys($siap))
      ->pluck('npm_mhs')
      ->all();

    $sudahAda = array_flip($sudahAda);

    foreach ($siap as $npm => $_) {
      isset($sudahAda[$npm]) ? $hasil['diperbarui']++ : $hasil['baru']++;
    }

    if (! $simulasi) {
      $this->simpan($siap);
    }

    return $hasil;
  }

  /**
   * Susun URL tujuan.
   *
   * $endpoint boleh berupa:
   *   - null                           -> config('simprodi.endpoint'), lalu ENDPOINT_BAWAAN
   *   - 'lulusan'                      -> digabung ke config('simprodi.url')
   *   - '/lulusan'                     -> sama, garis miring ganda dihindari
   *   - 'https://host.lain/path'       -> dipakai utuh, base URL diabaikan
   *
   * Urutan itu membuat endpoint tidak lagi terikat pada satu key config:
   * config('simprodi.endpoint') boleh tidak ada sama sekali.
   */
  public function bangunUrl(?string $endpoint = null): string
  {
    $endpoint = trim((string) (
      $endpoint
      ?? config('simprodi.endpoint')
      ?? self::ENDPOINT_BAWAAN
    ));

    if ($endpoint === '') {
      throw new RuntimeException(
        'Endpoint SIMPRODI kosong. Berikan lewat parameter, atau isi SIMPRODI_ENDPOINT di .env.'
      );
    }

    // Endpoint yang sudah berupa URL lengkap dipakai apa adanya, supaya bisa
    // diarahkan ke host lain tanpa mengubah konfigurasi.
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

  /** Badan balasan terakhir dari SIMPRODI, sebelum di-decode. */
  public function balasanMentah(): ?string
  {
    return $this->balasanMentah;
  }

  /** Panggil endpoint SIMPRODI dan kembalikan balasannya sebagai array. */
  private function panggilApi(string $url, ?string $tahun, ?string $kodeProdi): array
  {
    $rahasia = config('simprodi.secret');

    if (blank($rahasia)) {
      throw new RuntimeException(
        'SIMPRODI_SECRET belum diisi di .env, permintaan tidak dikirim.'
      );
    }

    $muatan = array_filter([
      'tahun' => $tahun,
      'prodi' => $kodeProdi,
    ], fn ($v) => $v !== null && $v !== '');

    // Sama seperti library lama: kunci dikirim sebagai sha1, bukan apa adanya.
    $muatan['apikey'] = sha1($rahasia);

    try {
      $respons = Http::withBody(json_encode($muatan), config('simprodi.content_type'))
        ->withOptions(['verify' => config('simprodi.verify')])
        ->timeout((int) config('simprodi.timeout'))
        ->post($url);
    } catch (ConnectionException $e) {
      throw new RuntimeException('SIMPRODI tidak dapat dihubungi: ' . $e->getMessage(), 0, $e);
    }

    if ($respons->failed()) {
      throw new RuntimeException(
        'SIMPRODI menjawab dengan status HTTP ' . $respons->status() . '.'
      );
    }

    $this->balasanMentah = $respons->body();

    $isi = $respons->json();

    if (! is_array($isi)) {
      throw new RuntimeException(
        'Balasan SIMPRODI bukan JSON yang bisa dibaca. Potongan awal: '
        . mb_substr(trim($respons->body()), 0, 200)
      );
    }

    return $isi;
  }

  /**
   * Cari daftar baris lulusan di dalam balasan.
   *
   * Bentuk balasan endpoint ini tidak terdokumentasi, jadi beberapa
   * kemungkinan dicoba: array di akar, atau array di bawah salah satu kunci
   * yang lazim dipakai.
   */
  private function ambilBaris(array $balasan): array
  {
    if ($this->daftarObjek($balasan)) {
      return $balasan;
    }

    foreach (['data', 'lulusan', 'rows', 'result', 'hasil', 'mahasiswa'] as $kunci) {
      $isi = $balasan[$kunci] ?? null;

      if (! is_array($isi) || $isi === []) {
        continue;
      }

      // Bentuk lazim: daftar baris.
      if ($this->daftarObjek($isi)) {
        return array_values($isi);
      }

      // Peta berkunci, mis. {"22410014": {...}} -- nilainya tetap baris data.
      if ($this->petaBaris($isi)) {
        return array_values($isi);
      }

      // SIMPRODI juga pernah mengirim satu baris sebagai objek tunggal,
      // bukan daftar berisi satu elemen:
      //   {"msg":"...","data":{"Nama_Mhs":"...","Mhs_Nik":"..."}}
      if ($this->barisTunggal($isi)) {
        return [$isi];
      }
    }

    return [];
  }

  /** Benar bila $nilai adalah peta berkunci yang setiap nilainya baris data. */
  private function petaBaris(array $nilai): bool
  {
    if ($nilai === [] || array_is_list($nilai)) {
      return false;
    }

    foreach ($nilai as $isi) {
      if (! is_array($isi) && ! is_object($isi)) {
        return false;
      }
    }

    return true;
  }

  /** Benar bila $nilai adalah satu baris data: peta berkunci bernilai skalar. */
  private function barisTunggal(array $nilai): bool
  {
    if ($nilai === [] || array_is_list($nilai)) {
      return false;
    }

    foreach ($nilai as $isi) {
      if (is_array($isi) || is_object($isi)) {
        return false;
      }
    }

    return true;
  }

  /** Benar bila $nilai adalah daftar yang isinya baris data (array asosiatif). */
  private function daftarObjek(array $nilai): bool
  {
    if ($nilai === [] || ! array_is_list($nilai)) {
      return false;
    }

    return is_array($nilai[0]) || is_object($nilai[0]);
  }

  /** Ambil pesan/kode error dari server bila ada, untuk ditampilkan ke operator. */
  private function pesanServer(array $balasan): ?string
  {
    foreach (['err', 'error', 'message', 'pesan', 'msg'] as $kunci) {
      $nilai = $balasan[$kunci] ?? null;

      if (is_scalar($nilai) && (string) $nilai !== '') {
        return (string) $nilai;
      }
    }

    return null;
  }

  /** Daftar kunci tingkat pertama dari balasan, dipakai untuk log diagnosa. */
  private function bentukBalasan(array $balasan): array
  {
    return array_is_list($balasan)
      ? ['(daftar berisi ' . count($balasan) . ' elemen)']
      : array_keys($balasan);
  }

  /** Ubah satu baris balasan menjadi kolom-kolom tabel alumni. */
  private function petakanBaris(mixed $mentah): array
  {
    $mentah = (array) $mentah;
    $rapi = [];

    foreach ($mentah as $nama => $nilai) {
      $kunci = strtolower(str_replace([' ', '-'], '_', (string) $nama));
      $kolom = self::PETA_KOLOM[$kunci] ?? self::PETA_KOLOM[str_replace('_', '', $kunci)] ?? null;

      if ($kolom === null) {
        // Field baru dari SIMPRODI: dicatat agar terlihat di log, tidak
        // hilang begitu saja seperti sebelumnya.
        if (! in_array($kunci, self::KOLOM_DIABAIKAN, true)) {
          $this->kolomTakDikenali[$nama] = true;
        }

        continue;
      }

      if (is_array($nilai) || is_object($nilai)) {
        continue;
      }

      $nilai = is_string($nilai) ? trim($nilai) : $nilai;

      // String kosong diperlakukan sebagai "tidak dikirim", bukan "kosongkan".
      // Penting: email dan telepon di tabel alumni bisa sudah disunting
      // operator, dan tidak boleh terhapus oleh balasan yang kolomnya hampa.
      if ($nilai === null || $nilai === '') {
        continue;
      }

      $rapi[$kolom] = $this->rapikanNilai($kolom, $nilai);
    }

    // Tahun lulus bisa disimpulkan dari tanggal yudisium bila tidak dikirim.
    if (! isset($rapi['thnlulus']) && isset($rapi['tgl_yudisium'])) {
      $rapi['thnlulus'] = substr($rapi['tgl_yudisium'], 0, 4);
    }

    return array_filter($rapi, fn ($v) => $v !== null);
  }

  /**
   * Format tanggal yang dicoba secara berurutan sebelum menyerah ke
   * Carbon::parse. Urutannya penting: tanpa ini '15/08/2024' dibaca
   * Carbon sebagai bulan ke-15 dan tanggalnya hilang jadi NULL.
   */
  private const FORMAT_TANGGAL = [
    'Y-m-d',
    'Y-m-d H:i:s',
    'd/m/Y',
    'd/m/Y H:i:s',
    'd-m-Y',
    'd-m-Y H:i:s',
    // '02 September 1999' dan '2 September 1999'
    'd F Y',
    'j F Y',
    // 'May 23 2025' -- gaya tampilan MSSQL
    'M j Y',
    'M d Y',
    'd M Y',
  ];

  /**
   * Nama bulan Indonesia -> Inggris.
   *
   * SIMPRODI mengirim tgl_lahir dengan nama bulan Indonesia ('14 Oktober
   * 2003'). Tanpa penerjemahan ini hasilnya rusak dua macam: 'Oktober' dan
   * 'Agustus' gagal dibaca lalu jadi NULL, sedangkan 'Juli' dan 'Juni'
   * terbaca sebagian oleh strtotime sehingga menghasilkan tanggal dengan
   * TAHUN BERJALAN -- '04 Juli 2003' pernah tersimpan sebagai 2026-07-04.
   */
  private const BULAN_INDONESIA = [
    'januari' => 'January',
    'februari' => 'February',
    'pebruari' => 'February',
    'maret' => 'March',
    'april' => 'April',
    'mei' => 'May',
    'juni' => 'June',
    'juli' => 'July',
    'agustus' => 'August',
    'september' => 'September',
    'oktober' => 'October',
    'november' => 'November',
    'nopember' => 'November',
    'desember' => 'December',
  ];

  /** Ubah teks tanggal dari SIMPRODI menjadi 'Y-m-d', atau null bila tak sah. */
  private function rapikanTanggal(string $nilai): ?string
  {
    $nilai = $this->normalkanTeksTanggal($nilai);

    if ($nilai === '') {
      return null;
    }

    $tanggal = null;

    foreach (self::FORMAT_TANGGAL as $format) {
      try {
        $calon = Carbon::createFromFormat($format, $nilai);
      } catch (Throwable) {
        continue;
      }

      // createFromFormat tetap lolos untuk '32/13/2024' (tanggalnya digeser),
      // jadi hasilnya dicocokkan ulang dengan teks aslinya.
      if ($calon !== false && $calon->format($format) === $nilai) {
        $tanggal = $calon;
        break;
      }
    }

    if ($tanggal === null) {
      try {
        $tanggal = Carbon::parse($nilai);
      } catch (Throwable) {
        return null;
      }

      // Penjaga penting: bila sebagian teks tidak terbaca, strtotime
      // melengkapinya dengan tanggal hari ini, sehingga hasilnya tanggal
      // yang salah -- bukan kegagalan yang terlihat. Jadi tahun hasil parse
      // wajib sama dengan tahun yang tertulis di teks aslinya.
      if (preg_match('/\b(?:19|20)\d{2}\b/', $nilai, $cocok)
        && (int) $cocok[0] !== $tanggal->year) {
        return null;
      }
    }

    // SIMPRODI memakai MSSQL; tanggal kosong di sana sering tersimpan
    // sebagai 1900-01-01 dan bukan NULL.
    return $tanggal->year <= 1900 ? null : $tanggal->format('Y-m-d');
  }

  /**
   * Rapikan teks tanggal sebelum diparse: rapatkan spasi ganda, buang
   * bagian waktu bergaya MSSQL ('12:00:00:000AM' -- empat bagian berkolon,
   * tidak dikenali format apa pun), lalu terjemahkan nama bulan Indonesia.
   */
  private function normalkanTeksTanggal(string $nilai): string
  {
    $nilai = preg_replace('/\s+/', ' ', trim($nilai)) ?? '';

    // 'May 23 2025 12:00:00:000AM' -> 'May 23 2025'
    $nilai = preg_replace('/\s+\d{1,2}:\d{2}(?::\d{2}){1,2}\s*(?:[AP]M)?$/i', '', $nilai) ?? $nilai;

    foreach (self::BULAN_INDONESIA as $id => $en) {
      $nilai = preg_replace('/\b' . $id . '\b/iu', $en, $nilai) ?? $nilai;
    }

    return trim($nilai);
  }

  /** Normalkan satu nilai sesuai tipe kolom tujuannya. */
  private function rapikanNilai(string $kolom, mixed $nilai): mixed
  {
    if (in_array($kolom, self::KOLOM_TANGGAL, true)) {
      return $this->rapikanTanggal((string) $nilai);
    }

    if (isset(self::KOLOM_ANGKA[$kolom])) {
      return is_numeric($nilai)
        ? round((float) $nilai, self::KOLOM_ANGKA[$kolom])
        : null;
    }

    $teks = (string) $nilai;

    if (isset(self::BATAS_PANJANG[$kolom])) {
      $teks = mb_substr($teks, 0, self::BATAS_PANJANG[$kolom]);
    }

    return $teks;
  }

  /**
   * Simpan baris ke tabel alumni.
   *
   * Baris dikelompokkan menurut kumpulan kolom yang terisi, lalu tiap
   * kelompok di-upsert dengan daftar kolom pembaruannya sendiri. Dengan cara
   * ini kolom yang tidak dikirim SIMPRODI tidak ikut ditimpa NULL, dan
   * penyimpanan tetap berupa batch, bukan satu query per baris.
   */
  private function simpan(array $baris): void
  {
    $kelompok = [];

    foreach ($baris as $satu) {
      $kolom = array_keys($satu);
      sort($kolom);
      $kelompok[implode(',', $kolom)][] = $satu;
    }

    DB::transaction(function () use ($kelompok) {
      foreach ($kelompok as $tandaTangan => $isi) {
        $kolom = explode(',', $tandaTangan);
        $diperbarui = array_values(array_diff($kolom, ['npm_mhs']));

        foreach (array_chunk($isi, 500) as $potongan) {
          $diperbarui === []
            ? DB::table('alumni')->insertOrIgnore($potongan)
            : DB::table('alumni')->upsert($potongan, ['npm_mhs'], $diperbarui);
        }
      }
    });
  }
}
