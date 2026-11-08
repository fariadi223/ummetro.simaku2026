/**
 * Halaman Data Alumni - Tracer Study UM Metro
 *
 * Catatan: tabel alumni memakai NPM (npm_mhs) sebagai primary key, bukan id.
 * Semua aksi baris karena itu memakai NPM.
 */

'use strict';

$(function () {
  var cfg = window.alumniConfig || {};
  var tabel = $('.dt-table-alumni');

  if (!tabel.length) return;

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  // ------------------------------------------------------------------ util

  function esc(val) {
    if (val === null || val === undefined || val === '') return '';
    return $('<div>').text(String(val)).html();
  }

  function tampil(val, kosong) {
    var s = esc(val);
    return s === '' ? (kosong || '-') : s;
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

  // Select2 untuk filter yang daftarnya panjang
  if ($.fn.select2) {
    $('#filter-tahun-lulus').select2({ placeholder: 'Semua Tahun Lulus', allowClear: true, width: '100%' });
    $('#filter-prodi').select2({ placeholder: 'Semua Program Studi', allowClear: true, width: '100%' });
  }

  // ------------------------------------------------------------- datatable

  var dt = tabel.DataTable({
    processing: true,
    serverSide: true,
    searching: true,
    ajax: {
      url: cfg.urlData,
      data: function (d) {
        d.tahun_lulus = $('#filter-tahun-lulus').val();
        d.prodi = $('#filter-prodi').val();
        d.status = $('#filter-status').val();
        d.tanggal_mulai = $('#filter-tanggal-mulai').val();
        d.tanggal_selesai = $('#filter-tanggal-selesai').val();
      },
      error: function (xhr) {
        alert({ icon: 'error', title: 'Gagal memuat data', text: pesanGagal(xhr) });
      }
    },
    columns: [
      { data: null, orderable: false, searchable: false },
      { data: 'nama_mhs' },
      { data: 'nama_prg' },
      { data: 'thnlulus' },
      { data: 'sudah_isi', orderable: false, searchable: false },
      { data: null, orderable: false, searchable: false }
    ],
    order: [[1, 'asc']],
    columnDefs: [
      {
        // Nomor urut mengikuti halaman
        targets: 0,
        className: 'text-center text-muted',
        render: function (data, type, full, meta) {
          return meta.row + 1 + meta.settings._iDisplayStart;
        }
      },
      {
        // Nama + NPM, dengan avatar inisial
        targets: 1,
        responsivePriority: 1,
        render: function (data, type, full) {
          if (type !== 'display') return full.nama_mhs || '';

          var nama = full.nama_mhs || '-';
          var inisial = (String(nama).match(/\b\w/g) || []).slice(0, 2).join('').toUpperCase() || 'A';
          var warna = ['primary', 'success', 'danger', 'warning', 'info', 'secondary'];
          var w = warna[Math.abs(String(nama).charCodeAt(0) || 0) % warna.length];

          return (
            '<div class="d-flex align-items-center">' +
            '  <div class="avatar avatar-sm me-2">' +
            '    <span class="avatar-initial rounded-circle bg-label-' + w + '">' + esc(inisial) + '</span>' +
            '  </div>' +
            '  <div class="d-flex flex-column">' +
            '    <a href="javascript:;" class="text-heading fw-semibold on-alumni-detail" data-npm="' + esc(full.npm_mhs) + '">' +
            tampil(nama) +
            '    </a>' +
            '    <small class="text-muted">' + tampil(full.npm_mhs) + ' NIK ' + tampil(full.nik) + '</small>' +
            '  </div>' +
            '</div>'
          );
        }
      },
      {
        // Fakultas + program studi + telepon
        targets: 2,
        render: function (data, type, full) {
          if (type !== 'display') return full.nama_prg || '';

          // Telepon dibuat tautan tel: supaya bisa langsung dihubungi dari ponsel.
          var tlpn = full.tlpn
            ? '<small class="text-muted"><i class="ti ti-phone ti-xs me-1"></i>' +
              '<a href="tel:' + esc(String(full.tlpn).replace(/[^+0-9]/g, '')) + '" class="text-muted">' +
              esc(full.tlpn) + '</a></small>'
            : '<small class="text-muted"><i class="ti ti-phone ti-xs me-1"></i>-</small>';

          return (
            '<div class="d-flex flex-column">' +
            '  <span class="text-truncate" style="max-width:22rem;">' + tampil(full.nama_prg) + '</span>' +
            tlpn +
            '</div>'
          );
        }
      },
      {
        // Tahun lulus + periode
        targets: 3,
        render: function (data, type, full) {
          if (type !== 'display') return full.thnlulus || '';
          var per = full.periode ? ' <small class="text-muted">/ ' + esc(full.periode) + '</small>' : '';
          return tampil(full.thnlulus) + per;
        }
      },
      {
        // Status pengisian kuesioner
        targets: 4,
        render: function (data, type, full) {
          if (type !== 'display') return full.sudah_isi ? 1 : 0;
          return full.sudah_isi
            ? '<span class="badge bg-label-success">Sudah Mengisi</span>'
            : '<span class="badge bg-label-secondary">Belum Mengisi</span>';
        }
      },
      {
        // Aksi
        targets: -1,
        className: 'text-center',
        render: function (data, type, full) {
          var npm = esc(full.npm_mhs);

          // Tombol isian hanya berguna bila alumni memang sudah mengisi.
          var tombolIsian = full.sudah_isi
            ? '  <a href="' + cfg.urlBase + '/' + encodeURIComponent(full.npm_mhs) + '/tracer"' +
              '     class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-primary"' +
              '     title="Lihat isian tracer"><i class="ti ti-file-text"></i></a>'
            : '  <span class="btn btn-sm btn-icon btn-text-secondary rounded-pill disabled"' +
              '        title="Belum mengisi tracer"><i class="ti ti-file-off"></i></span>';

          return (
            '<div class="d-inline-flex align-items-center">' +
            tombolIsian +
            '  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-info on-alumni-login-as" data-npm="' + npm + '" title="Login sebagai alumni ini">' +
            '    <i class="ti ti-login"></i>' +
            '  </button>' +
            '  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill on-alumni-detail" data-npm="' + npm + '" title="Lihat detail">' +
            '    <i class="ti ti-eye"></i>' +
            '  </button>' +
            '  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill on-alumni-edit" data-npm="' + npm + '" title="Edit">' +
            '    <i class="ti ti-pencil"></i>' +
            '  </button>' +
            '  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill text-danger on-alumni-hapus" data-npm="' + npm + '" title="Hapus">' +
            '    <i class="ti ti-trash"></i>' +
            '  </button>' +
            '</div>'
          );
        }
      }
    ],
    dom:
      't' +
      '<"row mx-2 my-3 align-items-center"' +
      '<"col-12 col-md-5"i>' +
      '<"col-12 col-md-7 d-flex justify-content-center justify-content-md-end align-items-center gap-3"lp>' +
      '>',
    language: {
      sLengthMenu: '_MENU_',
      info: 'Menampilkan _START_ - _END_ dari _TOTAL_ alumni',
      infoEmpty: 'Tidak ada data',
      infoFiltered: '(disaring dari _MAX_ total alumni)',
      zeroRecords: 'Tidak ada alumni yang cocok dengan pencarian',
      processing: 'Memuat…',
      paginate: {
        next: '<i class="ti ti-chevron-right ti-sm"></i>',
        previous: '<i class="ti ti-chevron-left ti-sm"></i>'
      }
    }
  });

  // ---------------------------------------------------------------- search

  var jedaCari = null;
  $('#filter-search').on('input', function () {
    var nilai = $(this).val();
    clearTimeout(jedaCari);
    jedaCari = setTimeout(function () {
      dt.search(nilai).draw();
    }, 400);
  });

  // ---------------------------------------------------------------- filter

  $('#filter-tahun-lulus, #filter-prodi, #filter-status').on('change', function () {
    dt.ajax.reload();
  });

  var pickerTanggal = null;
  if ($('#filter-tanggal').length && typeof flatpickr !== 'undefined') {
    pickerTanggal = flatpickr('#filter-tanggal', {
      mode: 'range',
      dateFormat: 'Y-m-d',
      altInput: true,
      altFormat: 'd M Y',
      locale: {
        firstDayOfWeek: 1,
        rangeSeparator: ' s/d ',
        months: {
          shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
          longhand: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
        }
      },
      onClose: function (tanggal, str, inst) {
        var mulai = tanggal[0] ? inst.formatDate(tanggal[0], 'Y-m-d') : '';
        var selesai = tanggal[1] ? inst.formatDate(tanggal[1], 'Y-m-d') : mulai;
        if (mulai === $('#filter-tanggal-mulai').val() && selesai === $('#filter-tanggal-selesai').val()) return;
        $('#filter-tanggal-mulai').val(mulai);
        $('#filter-tanggal-selesai').val(selesai);
        dt.ajax.reload();
      }
    });
  }

  $('#btn-reset-filter').on('click', function () {
    $('#filter-search').val('');
    $('#filter-status').val('');
    $('#filter-tahun-lulus, #filter-prodi').val('');
    if ($.fn.select2) $('#filter-tahun-lulus, #filter-prodi').trigger('change.select2');
    $('#filter-tanggal-mulai, #filter-tanggal-selesai').val('');
    if (pickerTanggal) pickerTanggal.clear();
    dt.search('').ajax.reload();
  });

  // ---------------------------------------------------------------- detail

  tabel.on('click', '.on-alumni-detail', function () {
    var npm = $(this).data('npm');
    var $body = $('#alumni-detail-body');

    $body.html('<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
    new bootstrap.Modal(document.getElementById('modal-alumni-detail')).show();

    $.get(cfg.urlBase + '/' + encodeURIComponent(npm))
      .done(function (res) {
        var a = res.data || {};
        var baris = [
          ['NPM', a.npm_mhs], ['Nama', a.nama_mhs], ['NIK', a.nik],
          ['Email', a.email], ['Telepon', a.tlpn],
          ['Tempat / Tanggal Lahir', [a.tmpt_lahir, a.tgl_lahir].filter(Boolean).join(', ')],
          ['Alamat', a.alamat_asal],
          ['Fakultas', a.nama_fak], ['Program Studi', a.nama_prg], ['Kode Prodi', a.kode_prg],
          ['Tahun Lulus / Periode', [a.thnlulus, a.periode].filter(Boolean).join(' / ')],
          ['IPK', a.ipk_mhs], ['Total SKS', a.sks_tot],
          ['Tgl. Yudisium', a.tgl_yudisium], ['Tgl. Ujian', a.tgl_ujian],
          ['Judul Skripsi', a.judul_skripsi],
          ['Pembimbing 1', a.nama_dosen_1], ['Pembimbing 2', a.nama_dosen_2]
        ];

        var html = '<div class="row g-3">';
        baris.forEach(function (r) {
          html +=
            '<div class="col-md-6"><small class="text-muted d-block">' + esc(r[0]) + '</small>' +
            '<span class="fw-medium">' + tampil(r[1]) + '</span></div>';
        });
        html += '</div>';
        $body.html(html);
      })
      .fail(function (xhr) {
        $body.html('<div class="alert alert-danger mb-0">' + esc(pesanGagal(xhr, 'Data tidak ditemukan.')) + '</div>');
      });
  });

  // ------------------------------------------------------------------ edit

  var modalEdit = null;

  tabel.on('click', '.on-alumni-edit', function () {
    var npm = $(this).data('npm');

    $('#alumni-edit-loading').removeClass('d-none');
    $('#alumni-edit-fields').addClass('d-none');
    $('#form-alumni-edit').find('.is-invalid').removeClass('is-invalid');

    modalEdit = modalEdit || new bootstrap.Modal(document.getElementById('modal-alumni-edit'));
    modalEdit.show();

    $.get(cfg.urlBase + '/' + encodeURIComponent(npm))
      .done(function (res) {
        var a = res.data || {};

        $('#edit-npm-asli').val(a.npm_mhs);
        $('#edit-npm').val(a.npm_mhs);

        ['nama_mhs', 'email', 'tlpn', 'tmpt_lahir', 'alamat_asal', 'nik',
         'thnlulus', 'periode', 'nama_prg', 'kode_prg', 'nama_fak', 'ipk_mhs'].forEach(function (f) {
          $('#edit-' + f).val(a[f] === null || a[f] === undefined ? '' : a[f]);
        });

        // input type=date butuh format YYYY-MM-DD
        $('#edit-tgl_lahir').val(a.tgl_lahir ? String(a.tgl_lahir).substring(0, 10) : '');

        $('#alumni-edit-loading').addClass('d-none');
        $('#alumni-edit-fields').removeClass('d-none');
      })
      .fail(function (xhr) {
        if (modalEdit) modalEdit.hide();
        alert({ icon: 'error', title: 'Gagal', text: pesanGagal(xhr, 'Data alumni tidak ditemukan.') });
      });
  });

  $('#form-alumni-edit').on('submit', function (e) {
    e.preventDefault();

    var npm = $('#edit-npm-asli').val();
    var $tombol = $('#btn-alumni-simpan');
    var data = {};

    ['nama_mhs', 'email', 'tlpn', 'tgl_lahir', 'tmpt_lahir', 'alamat_asal', 'nik',
     'thnlulus', 'periode', 'nama_prg', 'kode_prg', 'nama_fak', 'ipk_mhs'].forEach(function (f) {
      data[f] = $('#edit-' + f).val();
    });

    $tombol.prop('disabled', true);
    $('#form-alumni-edit').find('.is-invalid').removeClass('is-invalid');

    $.ajax({
      url: cfg.urlBase + '/' + encodeURIComponent(npm),
      type: 'PUT',
      data: data
    })
      .done(function (res) {
        if (modalEdit) modalEdit.hide();
        dt.ajax.reload(null, false);
        alert({ icon: 'success', title: 'Berhasil', text: res.messages || 'Data alumni diperbarui.' });
      })
      .fail(function (xhr) {
        // Tandai field yang gagal validasi
        var errors = xhr.responseJSON && xhr.responseJSON.errors;
        if (errors) {
          Object.keys(errors).forEach(function (f) {
            $('#edit-' + f).addClass('is-invalid');
          });
        }
        alert({ icon: 'error', title: 'Gagal menyimpan', text: pesanGagal(xhr) });
      })
      .always(function () {
        $tombol.prop('disabled', false);
      });
  });

  // -------------------------------------------------------------- login as

  tabel.on('click', '.on-alumni-login-as', function () {
    var npm = $(this).data('npm');
    var baris = dt.row($(this).closest('tr')).data() || {};
    var nama = baris.nama_mhs || npm;

    alert({
      title: 'Login sebagai alumni ini?',
      html:
        'Anda akan masuk ke akun <strong>' + esc(nama) + '</strong> (' + esc(npm) + ') ' +
        'dan melihat aplikasi persis seperti yang dilihat alumni tersebut.' +
        '<br><br><span class="text-muted">Gunakan tombol <strong>Kembali ke akun saya</strong> ' +
        'pada pita kuning di atas halaman untuk kembali sebagai administrator.</span>',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Ya, login as',
      cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' }
    }).then(function (hasil) {
      if (!hasil.isConfirmed) return;

      // Aksi ini mengubah session, jadi dikirim sebagai POST biasa (bukan
      // AJAX) supaya browser langsung pindah ke dashboard alumni.
      var form = $('<form>', {
        method: 'POST',
        action: cfg.urlBase + '/' + encodeURIComponent(npm) + '/login-as'
      }).append(
        $('<input>', {
          type: 'hidden',
          name: '_token',
          value: $('meta[name="csrf-token"]').attr('content')
        })
      );

      $('body').append(form);
      form.trigger('submit');
    });
  });

  // ----------------------------------------------------------------- hapus

  tabel.on('click', '.on-alumni-hapus', function () {
    var npm = $(this).data('npm');
    var baris = dt.row($(this).closest('tr')).data() || {};
    var nama = baris.nama_mhs || npm;

    alert({
      title: 'Hapus data alumni?',
      html: 'Data <strong>' + esc(nama) + '</strong> (' + esc(npm) + ') akan dihapus permanen.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus',
      cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' }
    }).then(function (hasil) {
      if (!hasil.isConfirmed) return;

      $.ajax({ url: cfg.urlBase + '/' + encodeURIComponent(npm), type: 'DELETE' })
        .done(function (res) {
          dt.ajax.reload(null, false);
          alert({ icon: 'success', title: 'Terhapus', text: res.messages || 'Data alumni dihapus.' });
        })
        .fail(function (xhr) {
          alert({ icon: 'error', title: 'Tidak dapat dihapus', text: pesanGagal(xhr) });
        });
    });
  });
});
