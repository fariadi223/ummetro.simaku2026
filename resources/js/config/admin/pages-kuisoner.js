/**
 * Halaman Kelola Kuisoner Dinamis - Tracer Study UM Metro
 *
 * Struktur: periode -> kuesioner -> bagian -> pertanyaan -> opsi
 */

'use strict';

$(function () {
  var cfg = window.kuisonerConfig || {};
  var TIPE_BEROPSI = ['radio', 'checkbox', 'select', 'scale'];

  var kuesionerId = null;
  var pemicuTersedia = [];

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  // ------------------------------------------------------------------- util

  function esc(v) {
    if (v === null || v === undefined || v === '') return '';
    return $('<div>').text(String(v)).html();
  }

  function pesanGagal(xhr, bawaan) {
    var r = xhr && xhr.responseJSON;
    if (r && r.messages) return r.messages;
    if (r && r.errors) {
      return Object.keys(r.errors).map(function (k) { return r.errors[k][0]; }).join(' ');
    }
    return bawaan || 'Terjadi kesalahan. Silakan coba lagi.';
  }

  function alert(opsi) {
    return Swal.fire($.extend({
      buttonsStyling: false,
      customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-label-secondary' }
    }, opsi));
  }

  function sukses(teks) {
    return alert({ icon: 'success', title: 'Berhasil', text: teks, timer: 1600, showConfirmButton: false });
  }

  function modal(id) {
    return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
  }

  var LABEL_TIPE = {
    text: 'Teks', number: 'Angka', date: 'Tanggal', radio: 'Pilihan tunggal',
    checkbox: 'Pilihan ganda', select: 'Dropdown', scale: 'Skala'
  };

  var LABEL_RINGKASAN = {
    status_saat_ini: 'Status saat ini',
    waktu_tunggu_bulan: 'Masa tunggu (bulan)',
    pendapatan: 'Pendapatan',
    kesesuaian_bidang: 'Kesesuaian bidang'
  };

  // ------------------------------------------------- isi dropdown statis

  (cfg.tipe || []).forEach(function (t) {
    $('#soal-tipe').append($('<option>').val(t).text(LABEL_TIPE[t] || t));
  });

  $('#soal-ringkasan').append($('<option>').val('').text('— tidak diringkas —'));
  (cfg.ringkasan || []).forEach(function (r) {
    $('#soal-ringkasan').append($('<option>').val(r).text(LABEL_RINGKASAN[r] || r));
  });

  // --------------------------------------------------------------- periode

  $('#daftar-periode').on('click', '.kz-periode', function (e) {
    if ($(e.target).closest('button').length) return;

    var $kartu = $(this);
    var idKuesioner = $kartu.data('kuesioner');

    if (!idKuesioner) {
      alert({ icon: 'info', title: 'Belum ada kuesioner', text: 'Periode ini belum memiliki kuesioner.' });
      return;
    }

    $('.kz-periode').removeClass('kz-aktif');
    $kartu.addClass('kz-aktif');
    muatStruktur(idKuesioner);
  });

  $('#btn-periode-baru').on('click', function () {
    $('#form-periode')[0].reset();
    $('#periode-tahun').val(new Date().getFullYear());
    modal('modal-periode').show();
  });

  $('#form-periode').on('submit', function (e) {
    e.preventDefault();
    var $btn = $(this).find('button[type=submit]').prop('disabled', true);

    $.post(cfg.urlBase + '/periode', {
      nama: $('#periode-nama').val(),
      tahun_lulusan: $('#periode-tahun').val(),
      tanggal_mulai: $('#periode-mulai').val(),
      tanggal_selesai: $('#periode-selesai').val(),
      judul_kuesioner: $('#periode-judul').val()
    })
      .done(function () {
        modal('modal-periode').hide();
        sukses('Periode dibuat.').then(function () { location.reload(); });
      })
      .fail(function (xhr) {
        alert({ icon: 'error', title: 'Gagal', text: pesanGagal(xhr) });
      })
      .always(function () { $btn.prop('disabled', false); });
  });

  $('#daftar-periode').on('click', '.on-periode-aktifkan', function () {
    var id = $(this).data('id');

    $.ajax({ url: cfg.urlBase + '/periode/' + id + '/aktifkan', type: 'PUT' })
      .done(function (res) { sukses(res.messages).then(function () { location.reload(); }); })
      .fail(function (xhr) { alert({ icon: 'error', title: 'Gagal', text: pesanGagal(xhr) }); });
  });

  $('#daftar-periode').on('click', '.on-periode-salin', function () {
    var tahunBaru = (parseInt($(this).data('tahun'), 10) || new Date().getFullYear()) + 1;

    $('#salin-id').val($(this).data('id'));
    $('#salin-asal').text($(this).data('nama'));
    $('#salin-nama').val('Tracer Study ' + tahunBaru);
    $('#salin-tahun').val(tahunBaru);
    $('#salin-mulai').val(tahunBaru + '-01-01');
    $('#salin-selesai').val(tahunBaru + '-12-31');

    modal('modal-salin').show();
  });

  $('#form-salin').on('submit', function (e) {
    e.preventDefault();

    var id = $('#salin-id').val();
    var $btn = $('#btn-salin-simpan').prop('disabled', true);

    $.post(cfg.urlBase + '/periode/' + id + '/salin', {
      nama: $('#salin-nama').val(),
      tahun_lulusan: $('#salin-tahun').val(),
      tanggal_mulai: $('#salin-mulai').val(),
      tanggal_selesai: $('#salin-selesai').val()
    })
      .done(function (res) {
        modal('modal-salin').hide();
        alert({ icon: 'success', title: 'Periode disalin', text: res.messages })
          .then(function () { location.reload(); });
      })
      .fail(function (xhr) {
        alert({ icon: 'error', title: 'Gagal menyalin', text: pesanGagal(xhr) });
      })
      .always(function () {
        $btn.prop('disabled', false);
      });
  });

  $('#daftar-periode').on('click', '.on-periode-hapus', function () {
    var id = $(this).data('id');
    var nama = $(this).data('nama');

    alert({
      title: 'Hapus periode?',
      html: 'Periode <strong>' + esc(nama) + '</strong> beserta kuesioner, bagian, dan pertanyaannya akan dihapus.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus',
      cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }
    }).then(function (h) {
      if (!h.isConfirmed) return;

      $.ajax({ url: cfg.urlBase + '/periode/' + id, type: 'DELETE' })
        .done(function (res) { sukses(res.messages).then(function () { location.reload(); }); })
        .fail(function (xhr) { alert({ icon: 'error', title: 'Tidak dapat dihapus', text: pesanGagal(xhr) }); });
    });
  });

  // -------------------------------------------------------------- struktur

  function muatStruktur(id) {
    kuesionerId = id;

    $('#struktur-kosong').prop('hidden', true);
    $('#struktur-bagian').empty();
    $('#struktur-loading').prop('hidden', false);
    $('#struktur-aksi').prop('hidden', true);

    $.get(cfg.urlBase + '/' + id + '/struktur')
      .done(function (res) {
        pemicuTersedia = res.pemicu || [];

        $('#struktur-judul').text(res.kuesioner.judul || 'Kuesioner');
        $('#struktur-sub').text(
          (res.kuesioner.periode ? res.kuesioner.periode + ' · ' : '') +
          res.bagian.length + ' bagian · ' +
          res.bagian.reduce(function (n, b) { return n + b.pertanyaan.length; }, 0) + ' pertanyaan'
        );
        $('#struktur-aksi').prop('hidden', false);
        $('#btn-preview').attr('href', cfg.urlBase + '/' + id + '/preview');

        gambarBagian(res.bagian);
      })
      .fail(function (xhr) {
        $('#struktur-kosong').prop('hidden', false).text(pesanGagal(xhr, 'Gagal memuat struktur.'));
      })
      .always(function () {
        $('#struktur-loading').prop('hidden', true);
      });
  }

  function gambarBagian(bagian) {
    var $wrap = $('#struktur-bagian').empty();

    if (!bagian.length) {
      $wrap.append(
        '<div class="alert alert-info mb-0">Kuesioner ini belum punya bagian. ' +
        'Klik tombol <strong>Bagian</strong> untuk menambah.</div>'
      );
      return;
    }

    bagian.forEach(function (b, i) {
      var idAcc = 'bagian-acc-' + b.id;

      var $item = $(
        '<div class="accordion-item kz-bagian" draggable="true" data-bagian="' + b.id + '">' +
        '  <h2 class="accordion-header d-flex align-items-center">' +
        '    <i class="ti ti-grip-vertical kz-grip ms-3"></i>' +
        '    <button class="accordion-button ' + (i === 0 ? '' : 'collapsed') + '" type="button"' +
        '            data-bs-toggle="collapse" data-bs-target="#' + idAcc + '">' +
        '      <span class="fw-semibold">' + esc(b.judul) + '</span>' +
        '      <span class="badge bg-label-secondary ms-2">' + b.pertanyaan.length + '</span>' +
        '    </button>' +
        '  </h2>' +
        '  <div id="' + idAcc + '" class="accordion-collapse collapse ' + (i === 0 ? 'show' : '') + '"' +
        '       data-bs-parent="#struktur-bagian">' +
        '    <div class="accordion-body">' +
        (b.deskripsi ? '<p class="text-muted small">' + esc(b.deskripsi) + '</p>' : '') +
        '      <div class="d-flex flex-wrap gap-2 mb-3">' +
        '        <button type="button" class="btn btn-sm btn-primary on-soal-baru" data-bagian="' + b.id + '">' +
        '          <i class="ti ti-plus me-1"></i>Pertanyaan' +
        '        </button>' +
        '        <button type="button" class="btn btn-sm btn-label-secondary on-bagian-edit"' +
        '                data-id="' + b.id + '" data-judul="' + esc(b.judul) + '"' +
        '                data-deskripsi="' + esc(b.deskripsi || '') + '">' +
        '          <i class="ti ti-pencil me-1"></i>Edit bagian' +
        '        </button>' +
        '        <button type="button" class="btn btn-sm btn-label-danger on-bagian-hapus"' +
        '                data-id="' + b.id + '" data-judul="' + esc(b.judul) + '">' +
        '          <i class="ti ti-trash me-1"></i>Hapus bagian' +
        '        </button>' +
        '        <button type="button" class="btn btn-sm btn-label-secondary on-simpan-urutan-soal"' +
        '                data-bagian="' + b.id + '" hidden>' +
        '          <i class="ti ti-arrows-sort me-1"></i>Simpan urutan' +
        '        </button>' +
        '      </div>' +
        '      <div class="kz-daftar-soal" data-bagian="' + b.id + '"></div>' +
        '    </div>' +
        '  </div>' +
        '</div>'
      );

      var $daftar = $item.find('.kz-daftar-soal');

      if (!b.pertanyaan.length) {
        $daftar.append('<div class="text-muted small">Belum ada pertanyaan di bagian ini.</div>');
      } else {
        b.pertanyaan.forEach(function (p) { $daftar.append(barisSoal(p)); });
      }

      $wrap.append($item);
    });

    aktifkanDragBagian();
    aktifkanDragSoal();
  }

  function barisSoal(p) {
    var lencana = '<span class="badge bg-label-primary kz-kode">' + esc(p.kode) + '</span>' +
      ' <span class="badge bg-label-secondary">' + esc(LABEL_TIPE[p.tipe] || p.tipe) + '</span>';

    if (p.wajib) lencana += ' <span class="badge bg-label-warning">wajib</span>';
    if (p.opsi.length) lencana += ' <span class="badge bg-label-info">' + p.opsi.length + ' opsi</span>';
    if (p.ringkasan_field) {
      lencana += ' <span class="badge bg-label-success">' +
        esc(LABEL_RINGKASAN[p.ringkasan_field] || p.ringkasan_field) + '</span>';
    }
    if (p.pemicu_kode) {
      lencana += ' <span class="badge bg-label-dark"><i class="ti ti-git-branch ti-xs me-1"></i>bila ' +
        esc(p.pemicu_kode) + '</span>';
    }

    return $(
      '<div class="kz-soal d-flex flex-wrap justify-content-between align-items-start gap-2"' +
      '     draggable="true" data-soal="' + p.id + '">' +
      '  <div class="d-flex align-items-start gap-2 flex-grow-1" style="min-width:16rem;">' +
      '    <i class="ti ti-grip-vertical kz-grip mt-1"></i>' +
      '    <div>' +
      '      <div class="mb-1">' + lencana + '</div>' +
      '      <div class="small">' + esc(p.teks) + '</div>' +
      '    </div>' +
      '  </div>' +
      '  <div class="d-flex gap-1 kz-soal-aksi">' +
      '    <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill on-soal-edit"' +
      '            data-id="' + p.id + '" title="Edit"><i class="ti ti-pencil"></i></button>' +
      '    <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-danger on-soal-hapus"' +
      '            data-id="' + p.id + '" data-kode="' + esc(p.kode) + '" title="Hapus"><i class="ti ti-trash"></i></button>' +
      '  </div>' +
      '</div>'
    );
  }

  // ----------------------------------------------------------------- bagian

  $('#btn-bagian-baru').on('click', function () {
    $('#form-bagian')[0].reset();
    $('#bagian-id').val('');
    $('#modal-bagian-judul').text('Bagian Baru');
    modal('modal-bagian').show();
  });

  $('#struktur-bagian').on('click', '.on-bagian-edit', function () {
    $('#bagian-id').val($(this).data('id'));
    $('#bagian-judul').val($(this).data('judul'));
    $('#bagian-deskripsi').val($(this).data('deskripsi'));
    $('#modal-bagian-judul').text('Edit Bagian');
    modal('modal-bagian').show();
  });

  $('#form-bagian').on('submit', function (e) {
    e.preventDefault();

    var id = $('#bagian-id').val();
    var data = { judul: $('#bagian-judul').val(), deskripsi: $('#bagian-deskripsi').val() };
    var req = id
      ? $.ajax({ url: cfg.urlBase + '/bagian/' + id, type: 'PUT', data: data })
      : $.post(cfg.urlBase + '/' + kuesionerId + '/bagian', data);

    req.done(function (res) {
      modal('modal-bagian').hide();
      muatStruktur(kuesionerId);
      sukses(res.messages);
    }).fail(function (xhr) {
      alert({ icon: 'error', title: 'Gagal', text: pesanGagal(xhr) });
    });
  });

  $('#struktur-bagian').on('click', '.on-bagian-hapus', function () {
    var id = $(this).data('id');
    var judul = $(this).data('judul');

    alert({
      title: 'Hapus bagian?',
      html: 'Bagian <strong>' + esc(judul) + '</strong> dan seluruh pertanyaannya akan dihapus.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus',
      cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }
    }).then(function (h) {
      if (!h.isConfirmed) return;

      $.ajax({ url: cfg.urlBase + '/bagian/' + id, type: 'DELETE' })
        .done(function (res) { muatStruktur(kuesionerId); sukses(res.messages); })
        .fail(function (xhr) { alert({ icon: 'error', title: 'Tidak dapat dihapus', text: pesanGagal(xhr) }); });
    });
  });

  // ------------------------------------------------------------- pertanyaan

  function barisOpsi(nilai, label) {
    return $(
      '<div class="kz-opsi-baris">' +
      '  <input type="text" class="form-control kz-opsi-nilai kz-kode" style="max-width:9rem;"' +
      '         placeholder="nilai" maxlength="100" value="' + esc(nilai || '') + '">' +
      '  <input type="text" class="form-control kz-opsi-label" placeholder="label yang dibaca alumni"' +
      '         maxlength="255" value="' + esc(label || '') + '">' +
      '  <button type="button" class="btn btn-sm btn-icon btn-label-danger on-opsi-hapus" title="Hapus opsi">' +
      '    <i class="ti ti-x"></i></button>' +
      '</div>'
    );
  }

  function aturBlokOpsi() {
    var perlu = TIPE_BEROPSI.indexOf($('#soal-tipe').val()) !== -1;
    $('#blok-opsi').prop('hidden', !perlu);

    if (perlu && $('#daftar-opsi').children().length === 0) {
      $('#daftar-opsi').append(barisOpsi('1', ''));
    }
  }

  $('#soal-tipe').on('change', aturBlokOpsi);
  $('#btn-opsi-tambah').on('click', function () {
    var n = $('#daftar-opsi').children().length + 1;
    $('#daftar-opsi').append(barisOpsi(String(n), ''));
  });
  $('#daftar-opsi').on('click', '.on-opsi-hapus', function () {
    $(this).closest('.kz-opsi-baris').remove();
  });

  function isiPemicu(terpilihSoal, terpilihOpsi, soalIni) {
    // terpilihOpsi: array id opsi pemicu
    var $p = $('#soal-pemicu').empty().append($('<option>').val('').text('Selalu tampil'));

    pemicuTersedia.forEach(function (q) {
      if (soalIni && q.id === soalIni) return;
      $p.append($('<option>').val(q.id).text(q.kode + ' — ' + q.teks.substring(0, 60)));
    });

    $p.val(terpilihSoal || '');
    isiPemicuOpsi(terpilihOpsi);
  }

  /**
   * Opsi pemicu dicentang, bukan dipilih dari dropdown, supaya satu
   * pertanyaan bisa dipicu beberapa opsi sekaligus.
   *
   * @param terpilih array id opsi yang sudah tersimpan
   */
  function isiPemicuOpsi(terpilih) {
    var id = parseInt($('#soal-pemicu').val(), 10);
    var $wadah = $('#daftar-pemicu-opsi').empty();
    var dicentang = (terpilih || []).map(String);

    var q = pemicuTersedia.filter(function (x) { return x.id === id; })[0];

    if (!q) {
      $wadah.append($('<span class="text-muted small">').text('Pilih pertanyaan pemicu lebih dulu.'));
      return;
    }

    if (!q.opsi.length) {
      $wadah.append($('<span class="text-muted small">').text('Pertanyaan pemicu ini belum punya opsi.'));
      return;
    }

    q.opsi.forEach(function (op) {
      var idCek = 'pemicu-opsi-' + op.id;

      $wadah.append(
        $('<div class="form-check">').append(
          $('<input>', { type: 'checkbox', class: 'form-check-input kz-pemicu-cek', value: op.id, id: idCek })
            .prop('checked', dicentang.indexOf(String(op.id)) !== -1),
          $('<label>', { class: 'form-check-label', for: idCek }).text(op.nilai + ' — ' + op.label)
        )
      );
    });
  }

  $('#soal-pemicu').on('change', function () { isiPemicuOpsi([]); });

  $('#struktur-bagian').on('click', '.on-soal-baru', function () {
    $('#form-pertanyaan')[0].reset();
    $('#soal-id').val('');
    $('#soal-bagian-id').val($(this).data('bagian'));
    $('#daftar-opsi').empty();
    $('#soal-tipe').val('radio');
    $('#modal-pertanyaan-judul').text('Pertanyaan Baru');
    aturBlokOpsi();
    isiPemicu(null, null, null);
    modal('modal-pertanyaan').show();
  });

  $('#struktur-bagian').on('click', '.on-soal-edit', function () {
    var id = $(this).data('id');

    $.get(cfg.urlBase + '/pertanyaan/' + id).done(function (res) {
      var p = res.data;

      $('#form-pertanyaan')[0].reset();
      $('#soal-id').val(p.id);
      $('#soal-bagian-id').val(p.kuesioner_bagian_id);
      $('#soal-kode').val(p.kode);
      $('#soal-teks').val(p.teks);
      $('#soal-tipe').val(p.tipe);
      $('#soal-wajib').prop('checked', p.wajib);
      $('#soal-ringkasan').val(p.ringkasan_field || '');

      $('#daftar-opsi').empty();
      p.opsi.forEach(function (o) { $('#daftar-opsi').append(barisOpsi(o.nilai, o.label)); });

      $('#modal-pertanyaan-judul').text('Edit Pertanyaan ' + p.kode);
      aturBlokOpsi();
      isiPemicu(p.tampil_jika_pertanyaan_id, p.tampil_jika_opsi_ids, p.id);
      modal('modal-pertanyaan').show();
    }).fail(function (xhr) {
      alert({ icon: 'error', title: 'Gagal', text: pesanGagal(xhr) });
    });
  });

  $('#form-pertanyaan').on('submit', function (e) {
    e.preventDefault();

    var id = $('#soal-id').val();
    var bagianId = $('#soal-bagian-id').val();
    var $btn = $('#btn-soal-simpan').prop('disabled', true);

    var tipe = $('#soal-tipe').val();
    var opsi = [];

    // Baris opsi dibiarkan di DOM saat tipe diganti supaya tidak hilang kalau
    // user balik lagi, jadi hanya dikirim untuk tipe yang memakai opsi.
    if (TIPE_BEROPSI.indexOf(tipe) !== -1) {
      $('#daftar-opsi .kz-opsi-baris').each(function () {
        var nilai = $(this).find('.kz-opsi-nilai').val();
        var label = $(this).find('.kz-opsi-label').val();
        if (String(nilai).trim() !== '' || String(label).trim() !== '') {
          opsi.push({ nilai: nilai, label: label });
        }
      });
    }

    var pemicuOpsi = $('#daftar-pemicu-opsi .kz-pemicu-cek:checked')
      .map(function () { return parseInt(this.value, 10); })
      .get();

    var data = {
      kode: $('#soal-kode').val(),
      teks: $('#soal-teks').val(),
      tipe: tipe,
      wajib: $('#soal-wajib').is(':checked') ? 1 : 0,
      ringkasan_field: $('#soal-ringkasan').val() || null,
      tampil_jika_pertanyaan_id: $('#soal-pemicu').val() || null,
      tampil_jika_opsi_ids: pemicuOpsi,
      opsi: opsi
    };

    var req = id
      ? $.ajax({ url: cfg.urlBase + '/pertanyaan/' + id, type: 'PUT', data: data })
      : $.post(cfg.urlBase + '/bagian/' + bagianId + '/pertanyaan', data);

    req.done(function (res) {
      modal('modal-pertanyaan').hide();
      muatStruktur(kuesionerId);
      sukses(res.messages);
    }).fail(function (xhr) {
      alert({ icon: 'error', title: 'Gagal menyimpan', text: pesanGagal(xhr) });
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  $('#struktur-bagian').on('click', '.on-soal-hapus', function () {
    var id = $(this).data('id');
    var kode = $(this).data('kode');

    alert({
      title: 'Hapus pertanyaan?',
      html: 'Pertanyaan <strong>' + esc(kode) + '</strong> akan dihapus.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus',
      cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }
    }).then(function (h) {
      if (!h.isConfirmed) return;

      $.ajax({ url: cfg.urlBase + '/pertanyaan/' + id, type: 'DELETE' })
        .done(function (res) { muatStruktur(kuesionerId); sukses(res.messages); })
        .fail(function (xhr) { alert({ icon: 'error', title: 'Tidak dapat dihapus', text: pesanGagal(xhr) }); });
    });
  });

  // ------------------------------------------------------------ urut (drag)

  function pasangDrag($wadah, selItem, saatSelesai) {
    var seret = null;

    $wadah.off('dragstart.kz dragover.kz dragleave.kz drop.kz dragend.kz');

    $wadah.on('dragstart.kz', selItem, function (e) {
      seret = this;
      e.originalEvent.dataTransfer.effectAllowed = 'move';
      e.originalEvent.dataTransfer.setData('text/plain', '');
    });

    $wadah.on('dragover.kz', selItem, function (e) {
      e.preventDefault();
      if (this !== seret) $(this).addClass('kz-drag-over');
    });

    $wadah.on('dragleave.kz', selItem, function () {
      $(this).removeClass('kz-drag-over');
    });

    $wadah.on('drop.kz', selItem, function (e) {
      e.preventDefault();
      $(this).removeClass('kz-drag-over');
      if (!seret || this === seret) return;

      var posisiSeret = $(seret).index();
      var posisiTujuan = $(this).index();

      posisiSeret < posisiTujuan ? $(this).after(seret) : $(this).before(seret);
      saatSelesai(this);
    });

    $wadah.on('dragend.kz', selItem, function () {
      $(selItem, $wadah).removeClass('kz-drag-over');
      seret = null;
    });
  }

  function aktifkanDragBagian() {
    pasangDrag($('#struktur-bagian'), '.kz-bagian', function () {
      $('#btn-simpan-urutan-bagian').prop('hidden', false);
    });
  }

  function aktifkanDragSoal() {
    $('.kz-daftar-soal').each(function () {
      var $d = $(this);
      pasangDrag($d, '.kz-soal', function () {
        $d.closest('.accordion-body').find('.on-simpan-urutan-soal').prop('hidden', false);
      });
    });
  }

  $('#btn-simpan-urutan-bagian').on('click', function () {
    var urutan = $('#struktur-bagian .kz-bagian').map(function () { return $(this).data('bagian'); }).get();

    $.ajax({
      url: cfg.urlBase + '/' + kuesionerId + '/bagian/urut',
      type: 'PUT',
      data: { urutan: urutan }
    })
      .done(function (res) { $('#btn-simpan-urutan-bagian').prop('hidden', true); sukses(res.messages); })
      .fail(function (xhr) { alert({ icon: 'error', title: 'Gagal', text: pesanGagal(xhr) }); });
  });

  $('#struktur-bagian').on('click', '.on-simpan-urutan-soal', function () {
    var $btn = $(this);
    var bagianId = $btn.data('bagian');
    var urutan = $btn.closest('.accordion-body')
      .find('.kz-soal')
      .map(function () { return $(this).data('soal'); })
      .get();

    $.ajax({
      url: cfg.urlBase + '/bagian/' + bagianId + '/pertanyaan/urut',
      type: 'PUT',
      data: { urutan: urutan }
    })
      .done(function (res) { $btn.prop('hidden', true); sukses(res.messages); })
      .fail(function (xhr) { alert({ icon: 'error', title: 'Gagal', text: pesanGagal(xhr) }); });
  });

  // Pilih periode pertama secara otomatis
  var $pertama = $('.kz-periode').filter(function () { return $(this).data('kuesioner'); }).first();
  if ($pertama.length) $pertama.trigger('click');
});
