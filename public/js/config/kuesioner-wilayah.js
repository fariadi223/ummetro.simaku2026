/**
 * Dropdown berantai provinsi -> kabupaten/kota pada kuesioner tracer study.
 *
 * Dipakai bersama halaman pengisian alumni dan pratinjau administrator supaya
 * keduanya berperilaku sama. Daftar kabupaten/kota seluruh provinsi dicetak
 * Blade sekali per halaman sebagai <script type="application/json"
 * id="kp-wilayah-kabupaten">, jadi pergantian provinsi tidak memanggil server.
 *
 * Pasangan select dikenali dari atribut yang dicetak Blade:
 *   select[data-wilayah="provinsi"]
 *   select[data-wilayah="kabupaten"][data-wilayah-induk="<kode pertanyaan provinsi>"]
 */

'use strict';

window.kuesionerWilayah = (function () {
  /** Daftar kabupaten/kota per kode provinsi; {} bila halaman tidak memuatnya. */
  function petaKabupaten() {
    var el = document.getElementById('kp-wilayah-kabupaten');

    if (!el) return {};

    try {
      return JSON.parse(el.textContent) || {};
    } catch (e) {
      return {};
    }
  }

  /**
   * Isi ulang pilihan satu select kabupaten mengikuti provinsi terpilih.
   * Pilihan yang sedang aktif dipertahankan bila masih ada di provinsi baru,
   * supaya memuat ulang halaman tidak menghapus jawaban yang sudah tersimpan.
   */
  function isiUlang($kab, kodeProvinsi, peta) {
    var daftar = (kodeProvinsi && peta[kodeProvinsi]) || {};
    var sebelum = String($kab.val() || '');
    var kosong = $kab.find('option[value=""]').first();

    $kab.empty();

    // Baris "— pilih —" milik Blade dipertahankan apa adanya; bila tidak ada
    // (mis. markup berubah), dibuatkan yang baru agar select tetap bisa kosong.
    $kab.append(
      kosong.length
        ? kosong
        : $('<option>', { value: '', text: '— pilih kabupaten/kota —' })
    );

    Object.keys(daftar).forEach(function (kode) {
      $kab.append($('<option>', { value: kode, text: daftar[kode] }));
    });

    var sesudah = daftar[sebelum] ? sebelum : '';

    $kab.val(sesudah);

    // Pemberitahuan hanya dikirim bila nilainya benar-benar berubah, supaya
    // penghitung kemajuan tidak dijalankan tanpa alasan.
    if (sesudah !== sebelum) {
      $kab.trigger('change');
    }
  }

  /**
   * Aktifkan semua pasangan provinsi/kabupaten di dalam $akar.
   *
   * @param {jQuery} $akar form atau pembungkus tempat pertanyaan dicetak
   */
  function pasang($akar) {
    var $kabupaten = $akar.find('select[data-wilayah="kabupaten"]');

    if (!$kabupaten.length) return;

    var peta = petaKabupaten();

    $kabupaten.each(function () {
      var $kab = $(this);
      var induk = $kab.attr('data-wilayah-induk');

      if (!induk) return;

      var $prov = $akar.find('select[data-wilayah="provinsi"][name="' + induk + '"]');

      if (!$prov.length) return;

      $prov.on('change', function () {
        isiUlang($kab, String($prov.val() || ''), peta);
      });

      // Pratinjau administrator mencetak kuesioner tanpa jawaban, sehingga
      // pilihannya masih kosong; sekali jalan di awal membuat keduanya
      // sinkron tanpa menunggu provinsi diubah.
      isiUlang($kab, String($prov.val() || ''), peta);
    });
  }

  return { pasang: pasang };
})();
