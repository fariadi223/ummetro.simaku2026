@extends('layouts/layoutMaster')

@section('title', 'Kode Sumber Dana')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .sd-tabel td.nama { white-space: normal; min-width: 240px; }
  .sd-kode { display: inline-block; min-width: 3.4rem; padding: .15rem .5rem; border-radius: .4rem; text-align: center;
             font-weight: 700; background: rgba(var(--bs-primary-rgb), .1); color: var(--bs-primary); }
  .pilih-kelompok .form-check-input { display: none; }
  .pilih-kelompok label { display: block; height: 100%; padding: .85rem 1rem; border: 1px solid var(--bs-border-color);
                          border-radius: .6rem; cursor: pointer; transition: all .15s ease; }
  .pilih-kelompok label:hover { border-color: rgba(var(--bs-primary-rgb), .5); }
  .pilih-kelompok .form-check-input:checked + label { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .06);
                                                       box-shadow: 0 0 0 1px var(--bs-primary); }
  .pilih-kelompok small { display: block; color: var(--bs-secondary); margin-top: .2rem; }
</style>
@endsection

@php
  $urlSimpan = route('admin.sumberdana.store');
  $warna = ['Pembatasan' => 'warning', 'Tanpa Pembatasan' => 'success'];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-building-bank"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Daftar Kode Sumber Dana</h4>
      <p class="pg-header-sub mb-0">Sumber penerimaan dana yang dipakai pada jurnal dan laporan aset neto.</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-list-numbers"></i><span><strong>{{ $statistik['total'] }}</strong> sumber dana</span></span>
    <span class="pg-header-chip"><i class="ti ti-lock"></i><span><strong>{{ $statistik['pembatasan'] }}</strong> dengan pembatasan</span></span>
    <span class="pg-header-chip"><i class="ti ti-lock-open"></i><span><strong>{{ $statistik['tanpa'] }}</strong> tanpa pembatasan</span></span>
    <span class="pg-header-chip"><i class="ti ti-notebook"></i><span><strong>{{ $statistik['dipakai'] }}</strong> dipakai jurnal</span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div class="input-group input-group-merge" style="width: 260px;">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="search" id="sd-cari" class="form-control" placeholder="Cari kode atau nama...">
      </div>
      <div class="btn-group" role="group" aria-label="Filter kelompok" id="sd-filter">
        <button type="button" class="btn btn-outline-primary active" data-kelompok="">Semua</button>
        <button type="button" class="btn btn-outline-primary" data-kelompok="Pembatasan">Dengan Pembatasan</button>
        <button type="button" class="btn btn-outline-primary" data-kelompok="Tanpa Pembatasan">Tanpa Pembatasan</button>
      </div>
    </div>
    <button type="button" class="btn btn-primary" id="btn-tambah">
      <i class="ti ti-plus me-1"></i> Tambah Sumber Dana
    </button>
  </div>

  <div class="table-responsive text-nowrap">
    <table class="table table-hover sd-tabel mb-0">
      <thead>
        <tr>
          <th style="width: 50px;">No</th>
          <th style="width: 110px;">Kode</th>
          <th>Nama Penerimaan</th>
          <th>Kelompok</th>
          <th class="text-end" title="Jumlah baris jurnal (transaksi + saldo awal) yang memakai sumber dana ini">Dipakai Jurnal</th>
          <th class="text-end" style="width: 110px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($daftar as $s)
          <tr class="baris-sd" data-cari="{{ mb_strtolower($s->kode . ' ' . $s->nama_penerimaan) }}" data-kelompok="{{ $s->kelompok }}">
            <td class="text-muted no-urut">{{ $loop->iteration }}</td>
            <td><span class="sd-kode font-monospace">{{ $s->kode }}</span></td>
            <td class="nama">{{ $s->nama_penerimaan }}</td>
            <td><span class="badge bg-label-{{ $warna[$s->kelompok] ?? 'secondary' }}">{{ $s->label_kelompok }}</span></td>
            <td class="text-end">
              @if ($s->dipakai)
                <span class="fw-semibold">{{ number_format($s->dipakai, 0, ',', '.') }}</span> <small class="text-muted">baris</small>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill btn-ubah" title="Ubah"
                        data-url="{{ route('admin.sumberdana.update', $s) }}" data-dipakai="{{ $s->dipakai }}"
                        data-sd="{{ json_encode($s->only(['kode', 'nama_penerimaan', 'kelompok'])) }}">
                  <i class="ti ti-pencil"></i>
                </button>
                @if ($s->dipakai)
                  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" disabled
                          title="Sudah dipakai jurnal, tidak dapat dihapus">
                    <i class="ti ti-trash-off"></i>
                  </button>
                @else
                  <form method="POST" action="{{ route('admin.sumberdana.destroy', $s) }}" class="form-hapus"
                        data-label="{{ $s->kode }} - {{ $s->nama_penerimaan }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Hapus">
                      <i class="ti ti-trash"></i>
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @endforeach
        <tr id="baris-kosong" class="{{ $daftar->isEmpty() ? '' : 'd-none' }}">
          <td colspan="6" class="text-center text-muted py-5">Tidak ada sumber dana yang cocok.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

{{-- Modal tambah / ubah --}}
<div class="modal fade" id="modal-sd" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="{{ $urlSimpan }}" class="modal-content" id="form-sd">
      @csrf
      <input type="hidden" name="_method" value="POST" id="form-sd-method">
      <input type="hidden" name="_form_action" value="{{ $urlSimpan }}" id="form-sd-action">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-sd-judul">Tambah Sumber Dana</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-sm-4">
            <label class="form-label" for="f-kode">Kode</label>
            <input type="text" id="f-kode" name="kode" maxlength="20" class="form-control font-monospace @error('kode') is-invalid @enderror"
                   value="{{ old('kode') }}" required>
            @error('kode')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-8">
            <label class="form-label" for="f-nama">Nama Penerimaan</label>
            <input type="text" id="f-nama" name="nama_penerimaan" maxlength="255" class="form-control @error('nama_penerimaan') is-invalid @enderror"
                   value="{{ old('nama_penerimaan') }}" required>
            @error('nama_penerimaan')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label class="form-label d-block">Kelompok</label>
            <div class="row g-2 pilih-kelompok">
              <div class="col-6">
                <input class="form-check-input" type="radio" name="kelompok" id="f-kel-p" value="Pembatasan" @checked(old('kelompok', 'Pembatasan') === 'Pembatasan')>
                <label for="f-kel-p"><i class="ti ti-lock me-1"></i><b>Dengan Pembatasan</b>
                  <small>Penggunaan dana dibatasi untuk keperluan tertentu.</small></label>
              </div>
              <div class="col-6">
                <input class="form-check-input" type="radio" name="kelompok" id="f-kel-t" value="Tanpa Pembatasan" @checked(old('kelompok') === 'Tanpa Pembatasan')>
                <label for="f-kel-t"><i class="ti ti-lock-open me-1"></i><b>Tanpa Pembatasan</b>
                  <small>Dana dapat dipakai untuk operasional umum.</small></label>
              </div>
            </div>
            @error('kelompok')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            <div class="alert alert-warning small mt-3 mb-0 d-none" id="peringatan-kelompok">
              <i class="ti ti-alert-triangle me-1"></i>
              Sumber dana ini sudah dipakai <b id="jumlah-pakai"></b> baris jurnal. Mengubah kelompok akan
              memindahkan saldonya di laporan aset neto.
            </div>
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

  // ---- Cari & filter kelompok --------------------------------------------------
  const cari = document.getElementById('sd-cari');
  let kelompok = '';
  function saring() {
    const k = cari.value.trim().toLowerCase();
    let no = 0;
    document.querySelectorAll('tr.baris-sd').forEach(function (tr) {
      const tampil = (!k || tr.dataset.cari.includes(k)) && (!kelompok || tr.dataset.kelompok === kelompok);
      tr.classList.toggle('d-none', !tampil);
      if (tampil) tr.querySelector('.no-urut').textContent = ++no;
    });
    document.getElementById('baris-kosong').classList.toggle('d-none', no > 0);
  }
  cari.addEventListener('input', saring);
  document.querySelectorAll('#sd-filter button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#sd-filter button').forEach(b => b.classList.toggle('active', b === btn));
      kelompok = btn.dataset.kelompok;
      saring();
    });
  });

  // ---- Tambah / ubah -----------------------------------------------------------
  const modal = new bootstrap.Modal(document.getElementById('modal-sd'));
  const form = document.getElementById('form-sd');
  const urlSimpan = @json($urlSimpan);
  const kodeBerikut = @json($kodeBerikut);
  const peringatan = document.getElementById('peringatan-kelompok');
  let kelompokAwal = null, dipakai = 0;

  function cekKelompok() {
    const dipilih = form.querySelector('input[name="kelompok"]:checked')?.value;
    peringatan.classList.toggle('d-none', !(dipakai > 0 && kelompokAwal && dipilih !== kelompokAwal));
  }
  form.querySelectorAll('input[name="kelompok"]').forEach(r => r.addEventListener('change', cekKelompok));

  function bukaForm(url, metode, judul, data, jumlahPakai, bersihkan) {
    form.action = url;
    document.getElementById('form-sd-action').value = url;
    document.getElementById('form-sd-method').value = metode;
    document.getElementById('modal-sd-judul').textContent = judul;
    if (data) {
      document.getElementById('f-kode').value = data.kode || '';
      document.getElementById('f-nama').value = data.nama_penerimaan || '';
      const r = form.querySelector('input[name="kelompok"][value="' + (data.kelompok || 'Pembatasan') + '"]');
      if (r) r.checked = true;
    }
    kelompokAwal = metode === 'PUT' && data ? data.kelompok : null;
    dipakai = jumlahPakai || 0;
    document.getElementById('jumlah-pakai').textContent = dipakai.toLocaleString('id-ID');
    cekKelompok();
    if (bersihkan) form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    modal.show();
  }

  document.getElementById('btn-tambah').addEventListener('click', () =>
    bukaForm(urlSimpan, 'POST', 'Tambah Sumber Dana', { kode: kodeBerikut, kelompok: 'Pembatasan' }, 0, true));
  document.querySelectorAll('.btn-ubah').forEach(btn => btn.addEventListener('click', () =>
    bukaForm(btn.dataset.url, 'PUT', 'Ubah Sumber Dana', JSON.parse(btn.dataset.sd), Number(btn.dataset.dipakai), true)));

  @if ($errors->any())
    @php
      $aksiLama = (string) old('_form_action');
      $aksiLama = str_starts_with($aksiLama, url('/') . '/') ? $aksiLama : $urlSimpan;
      $ubah = old('_method') === 'PUT';
    @endphp
    bukaForm(@json($aksiLama), @json($ubah ? 'PUT' : 'POST'), @json($ubah ? 'Ubah Sumber Dana' : 'Tambah Sumber Dana'), null, 0, false);
  @endif

  // ---- Hapus -------------------------------------------------------------------
  document.querySelectorAll('.form-hapus').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      Swal.fire({
        title: 'Hapus sumber dana?',
        html: '<b>' + esc(f.dataset.label) + '</b> akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
      }).then(r => { if (r.isConfirmed) f.submit(); });
    });
  });
});
</script>
@endsection
