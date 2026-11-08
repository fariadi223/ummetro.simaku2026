@extends('layouts/layoutMaster')

@section('title', 'Master Biaya')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .biaya-tabel td.item { white-space: normal; min-width: 260px; }
  .biaya-tabel td.ket { white-space: normal; max-width: 260px; color: var(--bs-secondary); font-size: .85rem; }
  .biaya-tabel td.rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .jenis-tab .nav-link { padding: .35rem .8rem; font-size: .85rem; }
  .jenis-tab .nav-link .badge { margin-left: .3rem; }
  /* Baris bawah DataTables: "Tampilkan n baris" sejajar paging */
  #tabel-biaya_wrapper .dataTables_length label { display: flex; align-items: center; gap: .4rem; margin: 0; white-space: nowrap; }
  #tabel-biaya_wrapper .dataTables_length select { width: auto; }
  #tabel-biaya_wrapper .dataTables_paginate { margin: 0; }
  #tabel-biaya_wrapper .dataTables_paginate .pagination { margin: 0; }
</style>
@endsection

@php
  $urlSimpan = route('admin.biaya.store');
  $warnaJenis = ['0' => 'secondary', 'atk' => 'info', 'honor' => 'success', 'jasa' => 'primary', 'pembangunan' => 'warning', 'perdin' => 'danger'];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-receipt-2"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Daftar Master Biaya</h4>
      <p class="pg-header-sub mb-0">Standar biaya minimum &amp; maksimum sebagai acuan penyusunan rincian anggaran.</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-list-details"></i><span><strong>{{ number_format($statistik['total'], 0, ',', '.') }}</strong> item</span></span>
    <span class="pg-header-chip"><i class="ti ti-category"></i><span><strong>{{ $statistik['jenis'] }}</strong> jenis</span></span>
    <span class="pg-header-chip"><i class="ti ti-circle-dashed"></i><span><strong>{{ $statistik['tanpa_nilai'] }}</strong> tanpa nilai</span></span>
    <span class="pg-header-chip"><i class="ti ti-alert-triangle"></i><span><strong>{{ $statistik['janggal'] }}</strong> min &gt; maks</span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header pb-0">
    <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-3">
      <div class="input-group input-group-merge" style="width: 300px;">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="search" id="biaya-cari" class="form-control" placeholder="Cari uraian atau keterangan...">
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.biaya.cetak') }}" target="_blank" rel="noopener" class="btn btn-label-info" id="btn-cetak">
          <i class="ti ti-printer me-1"></i> Cetak
        </a>
        <button type="button" class="btn btn-primary" id="btn-tambah">
          <i class="ti ti-plus me-1"></i> Tambah Item
        </button>
      </div>
    </div>
    <ul class="nav nav-pills jenis-tab flex-wrap gap-1" id="biaya-jenis">
      <li class="nav-item"><button type="button" class="nav-link active" data-jenis="">Semua <span class="badge bg-label-secondary">{{ $statistik['total'] }}</span></button></li>
      @foreach ($jenis as $kode => $label)
        <li class="nav-item">
          <button type="button" class="nav-link" data-jenis="{{ $kode }}">{{ $label }}
            <span class="badge bg-label-secondary">{{ $perJenis[$kode] ?? 0 }}</span></button>
        </li>
      @endforeach
    </ul>
  </div>

  <div class="card-datatable table-responsive">
    <table class="table table-hover biaya-tabel" id="tabel-biaya">
      <thead>
        <tr>
          <th>Uraian Item</th>
          <th class="text-end">Standar Biaya</th>
          <th>Satuan / Keterangan</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($daftar as $b)
          <tr data-jenis="{{ $b->kode_jenis }}">
            <td class="item">
              <div>
                {{ $b->item }}
                @if ($b->tidak_konsisten)
                  <i class="ti ti-alert-triangle text-warning ms-1" data-bs-toggle="tooltip" title="Standar minimum lebih besar dari maksimum"></i>
                @endif
                @if (isset($terpetakan[$b->id]))
                  <i class="ti ti-link text-info ms-1" data-bs-toggle="tooltip" title="Dipetakan ke rekening"></i>
                @endif
              </div>
              <span class="badge bg-label-{{ $warnaJenis[$b->kode_jenis] ?? 'secondary' }} mt-1">{{ $b->label_jenis }}</span>
            </td>
            <td class="text-end rp" data-order="{{ $b->nilai_urut }}">
              @if ($teks = $b->rentang())
                {{ $teks }}
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td class="ket">{{ $b->ket }}</td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill btn-ubah" title="Ubah"
                      data-url="{{ route('admin.biaya.update', $b) }}"
                      data-biaya="{{ json_encode(['item' => $b->item, 'jenis' => $b->kode_jenis, 'stmin' => (int) $b->stmin, 'stmak' => (int) $b->stmak, 'ket' => $b->ket]) }}">
                <i class="ti ti-pencil"></i>
              </button>
              <form method="POST" action="{{ route('admin.biaya.destroy', $b) }}" class="form-hapus d-inline" data-label="{{ $b->item }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Hapus">
                  <i class="ti ti-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- Modal tambah / ubah --}}
<div class="modal fade" id="modal-biaya" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form method="POST" action="{{ $urlSimpan }}" class="modal-content" id="form-biaya">
      @csrf
      <input type="hidden" name="_method" value="POST" id="form-biaya-method">
      <input type="hidden" name="_form_action" value="{{ $urlSimpan }}" id="form-biaya-action">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-biaya-judul">Tambah Item Biaya</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="f-item">Uraian Item</label>
            <textarea id="f-item" name="item" rows="2" maxlength="262" class="form-control @error('item') is-invalid @enderror" required>{{ old('item') }}</textarea>
            @error('item')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-4">
            <label class="form-label" for="f-jenis">Jenis</label>
            <select id="f-jenis" name="jenis" class="form-select @error('jenis') is-invalid @enderror">
              @foreach ($jenis as $kode => $label)
                <option value="{{ $kode }}" @selected((string) old('jenis', '0') === (string) $kode)>{{ $label }}</option>
              @endforeach
            </select>
            @error('jenis')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-4">
            <label class="form-label" for="f-stmin">Standar Minimum</label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="text" inputmode="numeric" id="f-stmin" name="stmin" class="form-control text-end angka @error('stmin') is-invalid @enderror"
                     value="{{ old('stmin', 0) }}">
              @error('stmin')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
          <div class="col-sm-4">
            <label class="form-label" for="f-stmak">Standar Maksimum</label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="text" inputmode="numeric" id="f-stmak" name="stmak" class="form-control text-end angka @error('stmak') is-invalid @enderror"
                     value="{{ old('stmak', 0) }}">
              @error('stmak')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <small class="text-muted">Isi 0 bila tanpa batas atas.</small>
          </div>
          <div class="col-12">
            <label class="form-label" for="f-ket">Satuan / Keterangan</label>
            <input type="text" id="f-ket" name="ket" maxlength="104" class="form-control @error('ket') is-invalid @enderror"
                   value="{{ old('ket') }}" placeholder="Mis. Per Kotak, Per Orang/Hari, SK Rektor No. ...">
            @error('ket')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const esc = s => $('<div>').text(s ?? '').html();
  const ribuan = n => (Number(String(n).replace(/\D/g, '')) || 0).toLocaleString('id-ID');

  // ---- DataTables ---------------------------------------------------------------
  let jenisAktif = '';
  $.fn.dataTable.ext.search.push(function (settings, data, idx) {
    if (settings.nTable.id !== 'tabel-biaya' || !jenisAktif) return true;
    return settings.aoData[idx].nTr.dataset.jenis === jenisAktif;
  });

  const tabel = $('#tabel-biaya').DataTable({
    order: [[0, 'asc']],
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100, 500],
    columnDefs: [{ targets: [3], orderable: false, searchable: false }],
    // Tabel di atas; di bawah: info di kiri, "Tampilkan n baris" + paging di kanan.
    // Kotak cari bawaan (f) tidak dipakai: pencarian lewat #biaya-cari.
    dom: 't<"row mx-1 my-2 align-items-center"<"col-md-5"i><"col-md-7 d-flex flex-wrap justify-content-md-end align-items-center gap-3"lp>>',
    language: {
      lengthMenu: 'Tampilkan _MENU_ baris',
      info: '_START_–_END_ dari _TOTAL_ item',
      infoEmpty: 'Tidak ada item',
      infoFiltered: '(disaring dari _MAX_)',
      zeroRecords: 'Tidak ada item yang cocok',
      paginate: { previous: '‹', next: '›' }
    },
    drawCallback: function () {
      document.querySelectorAll('#tabel-biaya [data-bs-toggle="tooltip"]').forEach(el => bootstrap.Tooltip.getOrCreateInstance(el));
    }
  });

  document.getElementById('biaya-cari').addEventListener('input', e => tabel.search(e.target.value).draw());

  const btnCetak = document.getElementById('btn-cetak');
  const urlCetak = btnCetak.href;
  document.querySelectorAll('#biaya-jenis .nav-link').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#biaya-jenis .nav-link').forEach(b => b.classList.toggle('active', b === btn));
      jenisAktif = btn.dataset.jenis;
      btnCetak.href = jenisAktif ? urlCetak + '?jenis=' + encodeURIComponent(jenisAktif) : urlCetak;
      tabel.draw();
    });
  });

  // ---- Format ribuan pada input Rp -----------------------------------------------
  document.querySelectorAll('input.angka').forEach(function (el) {
    el.value = ribuan(el.value);
    el.addEventListener('input', () => { el.value = ribuan(el.value); });
  });

  // ---- Tambah / ubah --------------------------------------------------------------
  const modal = new bootstrap.Modal(document.getElementById('modal-biaya'));
  const form = document.getElementById('form-biaya');
  const urlSimpan = @json($urlSimpan);

  function bukaForm(url, metode, judul, data, bersihkan) {
    form.action = url;
    document.getElementById('form-biaya-action').value = url;
    document.getElementById('form-biaya-method').value = metode;
    document.getElementById('modal-biaya-judul').textContent = judul;
    if (data) {
      document.getElementById('f-item').value = data.item || '';
      document.getElementById('f-jenis').value = data.jenis || '0';
      document.getElementById('f-stmin').value = ribuan(data.stmin || 0);
      document.getElementById('f-stmak').value = ribuan(data.stmak || 0);
      document.getElementById('f-ket').value = data.ket || '';
    }
    if (bersihkan) form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    modal.show();
  }

  document.getElementById('btn-tambah').addEventListener('click', () =>
    bukaForm(urlSimpan, 'POST', 'Tambah Item Biaya', { jenis: jenisAktif || '0', stmin: 0, stmak: 0 }, true));

  // Delegasi: DataTables hanya memasang baris halaman aktif ke DOM.
  $('#tabel-biaya tbody').on('click', '.btn-ubah', function () {
    bukaForm(this.dataset.url, 'PUT', 'Ubah Item Biaya', JSON.parse(this.dataset.biaya), true);
  });

  $('#tabel-biaya tbody').on('submit', '.form-hapus', function (e) {
    e.preventDefault();
    const f = this;
    Swal.fire({
      title: 'Hapus item biaya?',
      html: '<b>' + esc(f.dataset.label) + '</b> akan dihapus permanen.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus',
      cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
      buttonsStyling: false
    }).then(r => { if (r.isConfirmed) f.submit(); });
  });

  @if ($errors->any())
    @php
      $aksiLama = (string) old('_form_action');
      $aksiLama = str_starts_with($aksiLama, url('/') . '/') ? $aksiLama : $urlSimpan;
      $ubah = old('_method') === 'PUT';
    @endphp
    bukaForm(@json($aksiLama), @json($ubah ? 'PUT' : 'POST'), @json($ubah ? 'Ubah Item Biaya' : 'Tambah Item Biaya'), null, false);
  @endif
});
</script>
@endsection
