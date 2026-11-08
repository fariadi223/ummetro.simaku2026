<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * Membaca view kuisoner lama (CodeIgniter) dan mengubahnya menjadi struktur
 * bagian + pertanyaan + opsi yang siap dimasukkan ke skema dinamis.
 *
 * Tiga pola markup yang ditangani:
 *   A. soal tunggal  : satu <tr> dengan satu name (radio/select/text/number/date)
 *   B. grup checkbox : satu <tr> berisi banyak checkbox dengan name berbeda
 *                      (di tabel lama tiap kotak jadi satu kolom sendiri)
 *   C. soal tanpa id : <td class="h"> kosong, teks soal di <td> berikutnya
 *   D. kontrol PHP   : <tr> yang kontrolnya dicetak form_dropdown() di dalam
 *                      blok PHP, sehingga tidak terbaca sebagai HTML
 */
class KuisonerParser
{
  /** Pertanyaan yang nilainya diringkas ke kolom tabel respon. */
  private const RINGKASAN = [
    'f8' => 'status_saat_ini',
    'f502' => 'waktu_tunggu_bulan',
    'f505' => 'pendapatan',
    'f14' => 'kesesuaian_bidang',
  ];

  /**
   * Aturan percabangan, disalin dari handleChangeF8() di
   * views/alumni/javascript/kuisoner_2021.php.
   *
   * kode pertanyaan => [kode pemicu, nilai opsi pemicu]
   */
  private const PERCABANGAN = [
    'f502' => ['f8', '1'],
    'f504' => ['f8', '1'],
    'f505' => ['f8', '1'],
    // f510 adalah id baris, bukan name kontrol: isinya dua dropdown
    // f5a1/f5a2 (lihat KONTROL_PHP).
    'f5a1' => ['f8', '1'],
    'f5a2' => ['f8', '1'],
    'f11' => ['f8', '1'],
    'f1101' => ['f8', '1'],
    'f1102' => ['f8', '1'],
    'f5b' => ['f8', '1'],
    'f5d' => ['f8', '1'],
    'f5c' => ['f8', '3'],
    'f18' => ['f8', '4'],
    'f18a' => ['f8', '4'],
    'f18b' => ['f8', '4'],
    'f18c' => ['f8', '4'],
    'f18d' => ['f8', '4'],
  ];

  /**
   * Koreksi salah ketik name di view lama.
   *
   * f118c: view menulis name="f118c" sedangkan kolom tabel bernama f18c,
   * sehingga jawaban field itu tidak pernah tersimpan (f18c terisi 0 baris,
   * sementara f18a/f18b/f18d terisi ratusan).
   */
  private const KOREKSI_KODE = [
    'f118c' => 'f18c',
  ];

  /**
   * Kontrol yang dicetak helper PHP, bukan ditulis sebagai HTML.
   *
   * Isi blok PHP dibuang sebelum dokumen diparse (lihat parse()), jadi baris
   * f510 -- lokasi tempat bekerja -- tampak tanpa kontrol apa pun dan akan
   * terlewat. Dua dropdown berantainya dideklarasikan di sini supaya tetap
   * ikut terimpor.
   *
   * Dikunci berdasarkan id <tr>, dengan teks pertanyaan diambil dari barisnya
   * lalu diberi akhiran agar keduanya bisa dibedakan.
   */
  private const KONTROL_PHP = [
    'f510' => [
      ['kode' => 'f5a1', 'tipe' => 'provinsi', 'akhiran' => 'Provinsi'],
      ['kode' => 'f5a2', 'tipe' => 'kabupaten', 'akhiran' => 'Kabupaten/Kota', 'induk' => 'f5a1'],
    ],
  ];

  /**
   * Tipe yang ditetapkan manual, mengalahkan hasil penebakan.
   *
   * telpomsmh disaring oninput supaya hanya berisi angka, sehingga tertebak
   * sebagai 'number'. Padahal nomor telepon bukan besaran: bila disimpan
   * sebagai angka, "08123..." kehilangan nol depannya dan awalan +62 tidak
   * terwakili.
   */
  private const TIPE_PAKSA = [
    'telpomsmh' => 'text',
  ];

  /**
   * Field yang bukan pertanyaan: identitas yang sudah terisi dari data
   * akademik, plus kontrol bantu formulir.
   *
   * telpomsmh/emailmsmh/npwpmsmh sengaja TIDAK di sini. Di form lama ketiganya
   * input kosong yang diisi sendiri alumni (dua yang pertama bahkan required),
   * berbeda dari nimhsmsmh/nmmhsmsmh dkk. yang dicetak disabled sebagai
   * tampilan data. Mengeluarkannya berarti nomor HP, email, dan NPWP tidak
   * pernah tertanya -- padahal jawabannya ada ribuan di tabel lama.
   */
  private const IDENTITAS = [
    'nikmsmh', 'nimhsmsmh', 'kdptimsmh', 'kdpstmsmh', 'nmmhsmsmh',
    'thnlulus', 'tahun_lulus', 'submit',
  ];

  public function parse(string $path): array
  {
    if (!is_file($path)) {
      throw new RuntimeException("File kuisoner tidak ditemukan: {$path}");
    }

    $html = file_get_contents($path);

    // Buang blok PHP agar bisa di-parse sebagai HTML biasa.
    $html = preg_replace('~<\?php.*?\?>~s', '', $html);
    $html = preg_replace('~<\?=.*?\?>~s', '', $html);
    $html = preg_replace('~<\?php.*$~s', '', $html);

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"?>' . $html);
    libxml_clear_errors();

    $xp = new DOMXPath($doc);

    // Peta kode -> label sub-soal, diambil dari <span class="hl">KODE</span>
    $petaLabel = $this->petaLabel($xp);

    $bagian = [];
    $bagianAktif = null;
    $urutanBagian = 0;
    $urutanSoal = 0;
    $sudahAda = [];

    foreach ($xp->query('//tr') as $tr) {
      /** @var DOMElement $tr */

      // --- Header bagian: <td colspan="4"><b>Judul</b>
      $judulBagian = $this->judulBagian($xp, $tr);
      if ($judulBagian !== null) {
        // "KUISONER TRACER STUDY" hanya judul besar, bukan bagian.
        if (preg_match('~^kuisoner tracer study$~i', $judulBagian)) {
          continue;
        }
        $urutanBagian++;
        $urutanSoal = 0;
        $bagianAktif = $judulBagian;
        $bagian[$bagianAktif] = ['judul' => $judulBagian, 'urutan' => $urutanBagian, 'pertanyaan' => []];
        continue;
      }

      // --- Baris pertanyaan ditandai adanya <td class="h">
      $sel = $xp->query('./td[contains(@class,"h")]', $tr);
      if ($sel->length === 0) {
        continue;
      }

      $soal = $this->bacaSoal($xp, $tr, $petaLabel) ?? $this->soalKontrolPhp($xp, $tr);
      if ($soal === null) {
        continue;
      }

      if ($bagianAktif === null) {
        // Pertanyaan sebelum header bagian pertama: di form lama ini blok
        // identitas, yang judulnya ada di <td class="i"> baris pertamanya.
        $bagianAktif = $this->judulAwal($xp);
        $bagian[$bagianAktif] = [
          'judul' => $bagianAktif,
          'urutan' => ++$urutanBagian,
          'pertanyaan' => [],
        ];
      }

      foreach ($soal as $s) {
        // Kode harus unik per bagian (uq_pertanyaan_kode_per_bagian).
        $kunci = $bagianAktif . '|' . $s['kode'];
        if (isset($sudahAda[$kunci])) {
          continue;
        }
        $sudahAda[$kunci] = true;

        $s['urutan'] = ++$urutanSoal;
        $s['ringkasan_field'] = self::RINGKASAN[$s['kode']] ?? null;
        $s['tampil_jika'] = self::PERCABANGAN[$s['kode']] ?? null;
        $s['wilayah_induk'] = $s['wilayah_induk'] ?? null;

        $bagian[$bagianAktif]['pertanyaan'][] = $s;
      }
    }

    return array_values($bagian);
  }

  /**
   * Judul blok pembuka, diambil dari <td class="i"> pertama; "Umum" bila
   * markup-nya tidak menyebutkan apa pun.
   */
  private function judulAwal(DOMXPath $xp): string
  {
    foreach ($xp->query('//td[contains(@class,"i")]') as $td) {
      // Teks sel ini diikuti <br> lalu penanda kode (mis. "Identitas f1"),
      // jadi yang diambil hanya simpul teks langsungnya.
      foreach ($td->childNodes as $anak) {
        if ($anak->nodeType !== XML_TEXT_NODE) {
          continue;
        }

        $teks = $this->bersih($anak->textContent);

        if ($teks !== '') {
          return $teks;
        }
      }
    }

    return 'Umum';
  }

  private function judulBagian(DOMXPath $xp, DOMElement $tr): ?string
  {
    $td = $xp->query('./td[@colspan="4"]', $tr);
    if ($td->length === 0) {
      return null;
    }
    $b = $xp->query('.//b', $td->item(0));
    if ($b->length === 0) {
      return null;
    }
    $teks = $this->bersih($b->item(0)->textContent);

    return $teks === '' ? null : $teks;
  }

  /**
   * Baca satu baris menjadi satu atau beberapa pertanyaan.
   *
   * @return array<int,array>|null
   */
  private function bacaSoal(DOMXPath $xp, DOMElement $tr, array $petaLabel = []): ?array
  {
    $input = $xp->query('.//input[@name] | .//select[@name] | .//textarea[@name]', $tr);
    if ($input->length === 0) {
      return null;
    }

    // Kelompokkan kontrol berdasarkan name
    $perNama = [];
    foreach ($input as $el) {
      /** @var DOMElement $el */
      $nama = preg_replace('~\[\]$~', '', $el->getAttribute('name'));
      $nama = self::KOREKSI_KODE[$nama] ?? $nama;
      if ($nama === '' || in_array($nama, self::IDENTITAS, true)) {
        continue;
      }
      if (strtolower($el->getAttribute('type')) === 'hidden') {
        continue;
      }
      // disabled = nilainya dicetak dari data akademik dan tidak ikut
      // terkirim, jadi bukan sesuatu yang ditanyakan.
      if ($el->hasAttribute('disabled')) {
        continue;
      }
      $perNama[$nama][] = $el;
    }

    if (!$perNama) {
      return null;
    }

    $teksSoal = $this->teksSoal($xp, $tr);
    $namaList = array_keys($perNama);

    // --- Pola B: banyak checkbox dengan name berbeda = satu pertanyaan checkbox
    $checkbox = array_filter($namaList, function ($n) use ($perNama) {
      return strtolower($perNama[$n][0]->getAttribute('type')) === 'checkbox';
    });

    if (count($checkbox) > 1) {
      $kodeInduk = $this->kodeInduk($xp, $tr, $checkbox);
      $opsi = [];
      $u = 0;
      foreach ($checkbox as $n) {
        $opsi[] = [
          // nilai memakai kode kolom lama supaya jawaban lama mudah dipetakan
          'nilai' => $n,
          'label' => $this->labelKontrol($perNama[$n][0]) ?: ($petaLabel[$n] ?? $n),
          'urutan' => ++$u,
        ];
      }

      $hasil = [[
        'kode' => $kodeInduk,
        'teks' => $teksSoal ?: $kodeInduk,
        'tipe' => 'checkbox',
        'wajib' => false,
        'opsi' => $opsi,
      ]];

      // Sisa kontrol non-checkbox pada baris yang sama (mis. isian "Lainnya")
      foreach ($namaList as $n) {
        if (in_array($n, $checkbox, true)) {
          continue;
        }
        $hasil[] = $this->soalTunggal($n, $perNama[$n], $petaLabel[$n] ?? ($teksSoal . ' - lainnya'));
      }

      return $hasil;
    }

    // --- Pola A & C: satu atau beberapa kontrol bernama, masing-masing satu soal
    $hasil = [];
    foreach ($namaList as $n) {
      if (count($namaList) === 1) {
        // Satu kontrol: teks baris adalah pertanyaannya.
        $teks = $teksSoal ?: ($petaLabel[$n] ?? $n);
      } else {
        // Baris matriks: label sub-soal dari span.hl, teks baris jadi awalan.
        $sub = $petaLabel[$n] ?? null;
        $teks = $sub !== null
          ? ($teksSoal !== '' ? $teksSoal . ' - ' . $sub : $sub)
          : trim($teksSoal . ' (' . $n . ')');
      }
      $hasil[] = $this->soalTunggal($n, $perNama[$n], $teks);
    }

    return $hasil;
  }

  /** Bentuk satu pertanyaan dari sekumpulan kontrol dengan name sama. */
  private function soalTunggal(string $nama, array $els, string $teks): array
  {
    /** @var DOMElement $pertama */
    $pertama = $els[0];
    $tag = strtolower($pertama->nodeName);
    $type = strtolower($pertama->getAttribute('type'));

    $opsi = [];
    $tipe = 'text';

    if ($tag === 'select') {
      $tipe = 'select';
      $u = 0;
      foreach ($pertama->getElementsByTagName('option') as $op) {
        $nilai = $op->getAttribute('value');
        if ($nilai === '') {
          continue;
        }
        $opsi[] = [
          'nilai' => $nilai,
          'label' => $this->bersihLabel($op->textContent) ?: $nilai,
          'urutan' => ++$u,
        ];
      }
    } elseif ($type === 'radio' || $type === 'checkbox') {
      $tipe = $type;
      $u = 0;
      foreach ($els as $el) {
        $opsi[] = [
          'nilai' => $el->getAttribute('value'),
          'label' => $this->labelKontrol($el) ?: $el->getAttribute('value'),
          'urutan' => ++$u,
        ];
      }
      // Skala 1..5 tanpa label berarti = pertanyaan skala
      if ($tipe === 'radio' && count($opsi) >= 4 && $this->semuaLabelAngka($opsi)) {
        $tipe = 'scale';
      }
    } elseif ($tag === 'textarea') {
      $tipe = 'text';
    } elseif ($type === 'date' || str_contains($pertama->getAttribute('class'), 'input_f18d')) {
      $tipe = 'date';
    } elseif (str_contains($pertama->getAttribute('oninput'), '[^0-9]')) {
      $tipe = 'number';
    }

    $wajib = false;
    foreach ($els as $el) {
      if ($el->hasAttribute('required')) {
        $wajib = true;
        break;
      }
    }

    return [
      'kode' => $nama,
      'teks' => $this->bersih($teks) ?: $nama,
      'tipe' => self::TIPE_PAKSA[$nama] ?? $tipe,
      'wajib' => $wajib,
      'opsi' => $opsi,
    ];
  }

  /**
   * Pola D: baris yang kontrolnya dicetak PHP.
   *
   * @return array<int,array>|null
   */
  private function soalKontrolPhp(DOMXPath $xp, DOMElement $tr): ?array
  {
    $daftar = self::KONTROL_PHP[$tr->getAttribute('id')] ?? null;

    if ($daftar === null) {
      return null;
    }

    $teksSoal = $this->teksSoal($xp, $tr);

    return array_map(fn ($k) => [
      'kode' => $k['kode'],
      'teks' => trim(($teksSoal !== '' ? $teksSoal . ' - ' : '') . $k['akhiran']),
      'tipe' => $k['tipe'],
      // Form lama tidak mewajibkan lokasi kerja; dibiarkan sama supaya
      // alumni yang tidak tahu kodenya tidak terhalang mengirim.
      'wajib' => false,
      // Pilihannya datang dari tabel wilayah, bukan pertanyaan_opsi.
      'opsi' => [],
      'wilayah_induk' => $k['induk'] ?? null,
    ], $daftar);
  }

  /** Tebak kode induk grup checkbox: f1601..f1613 -> f16 */
  private function kodeInduk(DOMXPath $xp, DOMElement $tr, array $kodeAnak): string
  {
    $id = $tr->getAttribute('id');
    if ($id !== '') {
      return $id;
    }

    $pertama = reset($kodeAnak);
    if (preg_match('~^(f\d{1,2})\d{2}~', $pertama, $m)) {
      return $m[1];
    }

    return $pertama;
  }

  /** Teks pertanyaan: <td> kedua pada baris (setelah td.h). */
  private function teksSoal(DOMXPath $xp, DOMElement $tr): string
  {
    $tds = $xp->query('./td', $tr);
    if ($tds->length < 2) {
      return '';
    }

    $teks = $this->bersih($tds->item(1)->textContent);

    return $teks === ':' ? '' : $teks;
  }

  /** Label sebuah kontrol = teks setelahnya sampai <br> atau elemen berikutnya. */
  private function labelKontrol(DOMElement $el): string
  {
    $teks = '';
    for ($n = $el->nextSibling; $n !== null; $n = $n->nextSibling) {
      if ($n->nodeType === XML_ELEMENT_NODE) {
        $nama = strtolower($n->nodeName);
        if (in_array($nama, ['br', 'input', 'select', 'textarea'], true)) {
          break;
        }
        // <span class="hl">f16-01</span> hanya penanda kode, dilewati
        if ($nama === 'span' && str_contains($n->getAttribute('class') ?? '', 'hl')) {
          continue;
        }
        if ($nama === 'span' && str_contains($n->getAttribute('class') ?? '', 'i-info')) {
          continue;
        }
      }
      $teks .= $n->textContent;
    }

    return $this->bersihLabel($teks);
  }

  private function semuaLabelAngka(array $opsi): bool
  {
    foreach ($opsi as $o) {
      if (!preg_match('~^\d+$~', trim($o['label']))) {
        return false;
      }
    }

    return true;
  }

  /**
   * Bangun peta kode -> label dari penanda <span class="hl">KODE</span>.
   *
   * Hanya sel yang TIDAK memuat kontrol input yang dipakai, supaya penanda
   * yang menempel pada opsi (mis. "[1] Ya (f5-04)") tidak ikut terbaca.
   * Satu span bisa memuat dua kode untuk matriks A/B, mis. "(f1761) (f1762)".
   */
  private function petaLabel(DOMXPath $xp): array
  {
    $peta = [];

    foreach ($xp->query('//span[contains(@class,"hl")]') as $span) {
      /** @var DOMElement $span */
      $sel = $span->parentNode;
      if (!$sel instanceof DOMElement) {
        continue;
      }

      // Lewati bila sel yang sama memuat kontrol input.
      if ($xp->query('.//input | .//select | .//textarea', $sel)->length > 0) {
        continue;
      }

      $kode = [];
      if (preg_match_all('~f\d+(?:-\d+)?[a-z]?~i', $span->textContent, $m)) {
        foreach ($m[0] as $k) {
          $kode[] = strtolower(str_replace('-', '', $k));
        }
      }
      if (!$kode) {
        continue;
      }

      // Label = teks sel tanpa teks penanda.
      $label = $this->bersih(str_replace($span->textContent, '', $sel->textContent));
      if ($label === '') {
        continue;
      }

      // Dua kode pada satu sel = matriks A/B.
      if (count($kode) === 2) {
        $peta[$kode[0]] = $label . ' (A: saat lulus)';
        $peta[$kode[1]] = $label . ' (B: diperlukan pekerjaan)';
        continue;
      }

      foreach ($kode as $k) {
        $peta[$k] = $label;
      }
    }

    return $peta;
  }

  private function bersihLabel(string $s): string
  {
    $s = $this->bersih($s);
    // buang penomoran "[1] " di depan label
    return trim(preg_replace('~^\[\s*[^\]]*\]\s*~', '', $s));
  }

  private function bersih(string $s): string
  {
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xc2\xa0", ' ', $s);

    return trim(preg_replace('~\s+~u', ' ', $s));
  }
}
