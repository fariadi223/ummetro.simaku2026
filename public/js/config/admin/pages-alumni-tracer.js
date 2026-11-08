/**
 * Halaman Isian Tracer satu alumni - Tracer Study UM Metro
 *
 * Satu aksi saja: reset responden, yaitu menghapus isian alumni pada periode
 * yang sedang ditampilkan supaya alumni bisa mengisi ulang dari awal.
 */

'use strict';

$(function () {
  var cfg = window.tracerResetConfig || {};
  var $tombol = $('#btn-reset-responden');

  if (!$tombol.length || !cfg.urlReset) return;

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  function esc(val) {
    if (val === null || val === undefined || val === '') return '';
    return $('<div>').text(String(val)).html();
  }

  function pesanGagal(xhr, bawaan) {
    var r = xhr && xhr.responseJSON;
    if (r && r.messages) return r.messages;
    return bawaan || 'Terjadi kesalahan. Silakan coba lagi.';
  }

  function alert(opsi) {
    return Swal.fire(
      $.extend(
        {
          buttonsStyling: false,
          customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-label-secondary' }
        },
        opsi
      )
    );
  }

  $tombol.on('click', function () {
    var periode = $tombol.data('nama-periode') || 'periode ini';

    alert({
      title: 'Reset isian responden?',
      html:
        'Seluruh jawaban <strong>' + esc(cfg.nama) + '</strong> (' + esc(cfg.npm) + ') ' +
        'pada periode <strong>' + esc(periode) + '</strong> akan dihapus permanen, ' +
        'termasuk ringkasan status dan pendapatannya.' +
        '<br><br><span class="text-muted">Setelah direset, alumni dapat mengisi kuesioner ' +
        'periode tersebut dari awal selama periodenya masih aktif.</span>',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, reset',
      cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }
    }).then(function (hasil) {
      if (!hasil.isConfirmed) return;

      $tombol.prop('disabled', true);

      $.ajax({ url: cfg.urlReset, type: 'DELETE' })
        .done(function (res) {
          alert({
            icon: 'success',
            title: 'Isian direset',
            text: res.messages || 'Isian alumni dihapus.'
          }).then(function () {
            // Isian periode ini sudah tidak ada, jadi halamannya dimuat ulang
            // agar yang tampil bukan data yang baru saja dihapus.
            window.location.reload();
          });
        })
        .fail(function (xhr) {
          $tombol.prop('disabled', false);
          alert({ icon: 'error', title: 'Tidak dapat direset', text: pesanGagal(xhr) });
        });
    });
  });
});
