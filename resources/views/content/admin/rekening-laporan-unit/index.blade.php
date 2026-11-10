{{-- Admin > Rekening Laporan Unit: aktif/nonaktifkan rekening yang tampil di Laporan Keuangan Unit. --}}
@extends('layouts/layoutMaster')

@section('title', 'Rekening Laporan Unit')

@section('page-style')
<style>
  .rl-tabel td { vertical-align: middle; }
  .rl-tabel .kode { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
  .rl-tabel tr.nonaktif td { color: var(--bs-secondary-color); }
  .rl-tabel tr.nonaktif td.nama { text-decoration: line-through; }
  .rl-tabel .form-switch .form-check-input { width: 2.4em; height: 1.25em; cursor: pointer; }
</style>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-eye-off"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Rekening Laporan Unit</h4>
      <p class="pg-header-sub mb-0">Atur rekening yang ditampilkan di Laporan Keuangan Unit (akuntansi). Rekening nonaktif tidak dihitung di rekap, rincian, maupun transaksi.</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-list"></i><span><strong>{{ count($daftar) }}</strong> rekening dipakai unit terpetakan</span></span>
    <span class="pg-header-chip"><i class="ti ti-eye-off"></i><span><strong>{{ $jmlNonaktif }}</strong> disembunyikan</span></span>
  </div>
</div>

@include('_partials._flash')
@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center">
    <div class="input-group input-group-merge" style="width: 280px;">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="search" id="rl-cari" class="form-control" placeholder="Cari kode atau nama rekening...">
    </div>
    <div class="btn-group" role="group" aria-label="Filter status" id="rl-filter">
      <button type="button" class="btn btn-outline-primary active" data-status="">Semua</button>
      <button type="button" class="btn btn-outline-primary" data-status="1">Tampil</button>
      <button type="button" class="btn btn-outline-primary" data-status="0">Disembunyikan</button>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover rl-tabel mb-0">
      <thead><tr><th>Kode rekening</th><th>Nama rekening</th><th class="text-end">Trx unit</th><th>Keterangan</th><th class="text-center">Tampil di laporan</th></tr></thead>
      <tbody>
        @forelse ($daftar as $r)
          <tr class="{{ $r->aktif ? '' : 'nonaktif' }}" data-status="{{ $r->aktif ? 1 : 0 }}" data-cari="{{ strtolower($r->kode . ' ' . $r->namarek6) }}">
            <td class="kode">{{ $r->kode }}</td>
            <td class="nama">{{ $r->namarek6 }}</td>
            <td class="text-end">{{ number_format($r->jml_trx, 0, ',', '.') }}</td>
            <td><small class="text-muted">{{ $r->keterangan }}@if ($r->diubah_oleh) · {{ $r->diubah_oleh }}@endif</small></td>
            <td class="text-center">
              <form method="POST" action="{{ route('admin.rekening-laporan-unit.atur', $r->idrek6) }}" class="d-inline form-atur" data-label="{{ $r->kode }} {{ $r->namarek6 }}">
                @csrf
                <input type="hidden" name="aktif" value="{{ $r->aktif ? 0 : 1 }}">
                <input type="hidden" name="keterangan" value="">
                <div class="form-check form-switch d-inline-block mb-0">
                  <input class="form-check-input" type="checkbox" role="switch" @checked($r->aktif)
                    aria-label="Tampilkan {{ $r->kode }} di laporan unit" onchange="this.form.requestSubmit()">
                </div>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">Belum ada transaksi dari unit terpetakan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Saat menyembunyikan, minta keterangan singkat (opsional).
  document.querySelectorAll('.form-atur').forEach(f => f.addEventListener('submit', function (e) {
    if (f.elements.aktif.value === '0') {
      const ket = prompt('Sembunyikan ' + f.dataset.label + ' dari Laporan Keuangan Unit?\nKeterangan (opsional):', '');
      if (ket === null) { e.preventDefault(); f.querySelector('.form-check-input').checked = true; return; }
      f.elements.keterangan.value = ket;
    }
  }));

  // Cari & filter di sisi browser.
  let status = '';
  const cari = document.getElementById('rl-cari');
  const saring = () => {
    const q = cari.value.trim().toLowerCase();
    document.querySelectorAll('.rl-tabel tbody tr[data-status]').forEach(tr => {
      tr.classList.toggle('d-none', (status !== '' && tr.dataset.status !== status) || (q !== '' && !tr.dataset.cari.includes(q)));
    });
  };
  cari.addEventListener('input', saring);
  document.querySelectorAll('#rl-filter [data-status]').forEach(b => b.addEventListener('click', function () {
    document.querySelectorAll('#rl-filter .btn').forEach(x => x.classList.toggle('active', x === b));
    status = b.dataset.status;
    saring();
  }));
});
</script>
@endsection
