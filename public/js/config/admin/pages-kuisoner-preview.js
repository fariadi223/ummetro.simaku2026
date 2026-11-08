/**
 * Pratinjau Kuisoner Alumni - Tracer Study UM Metro
 *
 * Menjalankan percabangan (tampil_jika) di sisi browser, memakai atribut
 * data-pemicu / data-pemicu-nilai yang dicetak Blade.
 */

'use strict';

$(function () {
  var $soal = $('.kp-soal');

  if (!$soal.length) return;

  // ---------------------------------------------------------- percabangan

  /** Nilai terpilih sebuah pertanyaan, berdasarkan kode. */
  function nilaiTerpilih(kode) {
    var $kontrol = $('[name="' + kode + '"], [name="' + kode + '[]"]');

    if (!$kontrol.length) return [];

    var tipe = ($kontrol.attr('type') || '').toLowerCase();

    if (tipe === 'radio' || tipe === 'checkbox') {
      return $kontrol.filter(':checked').map(function () { return String(this.value); }).get();
    }

    var v = $kontrol.val();

    return v === null || v === '' ? [] : [String(v)];
  }

  /** Nilai opsi pemicu sebuah pertanyaan; boleh lebih dari satu. */
  function nilaiPemicu($s) {
    var raw = $s.attr('data-pemicu-nilai');

    if (!raw) return [];

    try {
      var v = JSON.parse(raw);
      return (Array.isArray(v) ? v : [v]).map(String);
    } catch (e) {
      return [String(raw)];
    }
  }

  /**
   * Terapkan aturan tampil. Dijalankan berulang karena satu pertanyaan
   * bersyarat bisa menjadi pemicu pertanyaan lain (rantai percabangan).
   */
  function terapkan() {
    var berubah = true;
    var putaran = 0;

    while (berubah && putaran < 10) {
      berubah = false;
      putaran++;

      $soal.each(function () {
        var $s = $(this);
        var pemicu = $s.data('pemicu');

        if (!pemicu) return;

        var perlu = nilaiPemicu($s);
        var $indukPemicu = $('.kp-soal[data-kode="' + pemicu + '"]');

        // Bila pemicunya sendiri sedang tersembunyi, yang ini ikut tersembunyi.
        var pemicuTampil = $indukPemicu.length === 0 || !$indukPemicu.hasClass('kp-tersembunyi');
        var terpilih = nilaiTerpilih(pemicu);

        // Cukup satu opsi pemicu yang cocok ("atau").
        var cocok = pemicuTampil && perlu.some(function (v) {
          return terpilih.indexOf(v) !== -1;
        });
        var sedangSembunyi = $s.hasClass('kp-tersembunyi');

        if (cocok === sedangSembunyi) {
          $s.toggleClass('kp-tersembunyi', !cocok);
          aturWajib($s, cocok);
          if (!cocok) kosongkan($s);
          berubah = true;
        }
      });
    }
  }

  /**
   * required hanya aktif saat pertanyaannya tampil; kalau tidak, browser
   * akan menolak submit karena field tersembunyi yang wajib.
   */
  function aturWajib($s, tampil) {
    $s.find('[data-wajib="1"]').prop('required', tampil);
  }

  /** Bersihkan jawaban pertanyaan yang kembali tersembunyi. */
  function kosongkan($s) {
    $s.find('input[type=radio], input[type=checkbox]').prop('checked', false);
    $s.find('input[type=text], input[type=number], input[type=date], textarea').val('');
    $s.find('select').val('');
  }

  // Tandai field wajib sebelum atribut required dilepas, agar bisa dipulihkan.
  $soal.find('[required]').attr('data-wajib', '1');

  if (window.kuesionerWilayah) window.kuesionerWilayah.pasang($(document));

  $(document).on('change input', '.kp-soal :input', terapkan);
  terapkan();

  // -------------------------------------------------------- tampilkan kode

  $('#btn-toggle-kode').on('click', function () {
    var $badge = $('.kp-badge-kode');
    var tampil = $badge.first().hasClass('d-none');

    $badge.toggleClass('d-none', !tampil);
    $(this).html(
      tampil
        ? '<i class="ti ti-code-off ti-xs me-1"></i>Sembunyikan kode'
        : '<i class="ti ti-code ti-xs me-1"></i>Tampilkan kode'
    );
  });

  // Pratinjau tidak menyimpan apa pun.
  $('form').on('submit', function (e) { e.preventDefault(); });
});
