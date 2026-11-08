/**
 * Pengisian Kuisoner Alumni - Tracer Study UM Metro
 *
 * Aturan percabangan sama dengan pratinjau administrator: memakai atribut
 * data-pemicu / data-pemicu-nilai yang dicetak Blade. Server menghitung ulang
 * aturan yang sama saat menyimpan, jadi JS di sini hanya untuk kenyamanan.
 */

'use strict';

$(function () {
  var $form = $('#form-kuisoner');

  if (!$form.length) return;

  var $soal = $form.find('.kp-soal');

  // ---------------------------------------------------------- percabangan

  /** Nilai terpilih sebuah pertanyaan, berdasarkan kode. */
  function nilaiTerpilih(kode) {
    var $kontrol = $form.find('[name="' + kode + '"], [name="' + kode + '[]"]');

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
        var $indukPemicu = $form.find('.kp-soal[data-kode="' + pemicu + '"]');

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

    hitungKemajuan();
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

  // ------------------------------------------------------------- kemajuan

  /**
   * Hitung pertanyaan tampil yang sudah terjawab. Blok matriks A/B berisi
   * beberapa pertanyaan sekaligus, jadi yang dihitung adalah nama kontrol
   * yang unik, bukan jumlah blok.
   */
  function hitungKemajuan() {
    var total = 0;
    var terjawab = 0;

    $soal.each(function () {
      var $s = $(this);

      if ($s.hasClass('kp-tersembunyi')) return;

      var sudah = {};

      $s.find('input[name], select[name], textarea[name]').each(function () {
        var nama = String(this.name || '').replace(/\[\]$/, '');

        if (!nama || sudah[nama]) return;

        sudah[nama] = true;
        total++;

        if (nilaiTerpilih(nama).length) terjawab++;
      });
    });

    var persen = total === 0 ? 0 : Math.round((terjawab / total) * 100);

    $('#ki-hitung').text(terjawab + ' / ' + total);
    $('#ki-batang').css('width', persen + '%')
      .closest('.progress').attr('aria-valuenow', persen);
  }

  // Tandai field wajib sebelum atribut required dilepas, agar bisa dipulihkan.
  $soal.find('[required]').attr('data-wajib', '1');

  $form.on('change input', ':input', terapkan);
  terapkan();

  // -------------------------------------------------------- lompat ke salah

  $('#ki-ke-salah').on('click', function () {
    var $pertama = $form.find('.kp-salah').first();

    if (!$pertama.length) return;

    $('html, body').animate({ scrollTop: $pertama.offset().top - 90 }, 300);
  });

  var $salah = $form.find('.kp-salah').first();
  if ($salah.length) {
    $('html, body').animate({ scrollTop: $salah.offset().top - 90 }, 300);
  }

  // ---------------------------------------------------------------- kirim

  // Tombol mana yang diklik dicatat eksplisit; document.activeElement tidak
  // bisa diandalkan saat submit dipicu dari tempat lain.
  var aksiTerpilih = null;

  $form.on('click', 'button[type=submit]', function () {
    aksiTerpilih = $(this).val();
  });

  $form.on('submit', function (e) {
    // Simpan draf langsung jalan: tidak perlu konfirmasi, tidak divalidasi.
    if (aksiTerpilih !== 'kirim') return;

    e.preventDefault();

    var $tombol = $('#ki-kirim');

    Swal.fire({
      title: 'Kirim jawaban sekarang?',
      html:
        'Setelah dikirim, jawaban <strong>tidak dapat diubah lagi</strong>.' +
        '<br><br><span class="text-muted">Belum selesai? Pakai <strong>Simpan draf</strong> ' +
        'dan lanjutkan kapan saja.</span>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, kirim',
      cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(function (hasil) {
      if (!hasil.isConfirmed) return;

      $tombol.prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span>Mengirim…');

      // Tombol yang dinonaktifkan tidak ikut terkirim, jadi nilainya
      // dititipkan ke field tersembunyi.
      $form.append($('<input>', { type: 'hidden', name: 'aksi', value: 'kirim' }));

      // submit() bawaan DOM: melewati handler ini, jadi tidak berulang.
      $form[0].submit();
    });
  });
});
