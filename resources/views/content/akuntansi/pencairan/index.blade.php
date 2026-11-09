@extends('layouts/layoutMaster')

@section('title', 'Pencairan Anggaran Belanja')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .cair-tabel td { vertical-align: middle; }
  .cair-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .cair-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
  .cair-tabel tfoot td { font-weight: 700; }
</style>
@endsection

@php
  $rp = fn ($n) => ($n < 0 ? '−' : '') . 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-transfer-in"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Pencairan Anggaran Belanja</h4>
      <p class="pg-header-sub mb-0">SPM yang sudah dibayar kasir dan belum dijurnal ke unit · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-list-check"></i><span><strong>{{ $daftar->count() }}</strong> kegiatan</span></span>
    <span class="pg-header-chip"><i class="ti ti-cash"></i><span>Total belum dijurnal <strong>{{ $rp($total) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-building-bank"></i><span>Saldo Kas Universitas <strong>{{ $rp($kasUniv) }}</strong></span></span>
  </div>
</div>

@include('_partials._flash')

@unless ($terbuka)
  @include('_partials._periode-terkunci', ['apa' => 'jurnal pencairan'])
@endunless

<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Belum Dijurnal</h5>
    <small class="text-muted">Jurnalkan = debet <strong>Kas di Unit Kerja</strong> (unit induk) · kredit <strong>Kas Universitas</strong></small>
  </div>
  <div class="table-responsive">
    <table class="table cair-tabel mb-0">
      <thead>
        <tr><th style="width: 44px;">No</th><th>No. BKK</th><th>No. SPM</th><th>MAK</th><th>Nama Kegiatan</th>
          <th class="text-end">Realisasi SPM</th><th>Unit Kerja</th><th class="text-end" style="width: 130px;">Menu</th></tr>
      </thead>
      <tbody>
        @forelse ($daftar as $r)
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td class="nomor">{{ $r->no_bkk }}</td>
            <td class="nomor">{{ $r->no_spm }}</td>
            <td class="nomor">{{ $r->kode_mak }}</td>
            <td>{{ $r->namamak4 }}<div class="small text-muted">{{ $r->nama_unit }}</div></td>
            <td class="text-end rp fw-semibold">{{ $rp($r->ajuan) }}</td>
            <td>{{ $r->induk->nama }}</td>
            <td class="text-end">
              @if (!$terbuka)
                {{-- Periode tidak terbuka untuk penjurnalan. --}}
                <span class="d-inline-block" tabindex="0" title="Penjurnalan T.A {{ $tahun }}/{{ $tahun + 1 }} ditutup">
                  <button type="button" class="btn btn-sm btn-label-secondary" disabled><i class="ti ti-lock ti-xs me-1"></i>Jurnalkan</button>
                </span>
              @else
              <form method="POST" action="{{ route('akuntansi.pencairan.jurnal', $r->id_spp) }}" class="d-inline form-jurnal"
                data-spm="{{ $r->no_spm }}" data-unit="{{ $r->induk->nama }}" data-nominal="{{ $rp($r->ajuan) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-notebook ti-xs me-1"></i>Jurnalkan</button>
              </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-5">Tidak ada pencairan yang menunggu dijurnal pada T.A ini.</td></tr>
        @endforelse
      </tbody>
      @if ($daftar->isNotEmpty())
        <tfoot>
          <tr><td colspan="5" class="text-end">TOTAL</td><td class="text-end rp">{{ $rp($total) }}</td><td colspan="2"></td></tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const esc = s => $('<div>').text(s ?? '').html();
    document.querySelectorAll('.form-jurnal').forEach(f => f.addEventListener('submit', function (e) {
      e.preventDefault();
      Swal.fire({
        icon: 'question', title: 'Jurnalkan pencairan ini?',
        html: 'SPM <b class="font-monospace">' + esc(f.dataset.spm) + '</b><br>ke ' + esc(f.dataset.unit)
          + '<div class="fs-4 fw-semibold mt-2">' + esc(f.dataset.nominal) + '</div>'
          + '<div class="small text-muted mt-2">Debet Kas di Unit Kerja, kredit Kas Universitas.</div>',
        showCancelButton: true, confirmButtonText: '<i class="ti ti-notebook me-1"></i>Ya, jurnalkan', cancelButtonText: 'Batal',
        buttonsStyling: false, focusCancel: true,
        customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-label-secondary' }
      }).then(r => { if (r.isConfirmed) { f.querySelector('button[type=submit]').disabled = true; f.submit(); } });
    }));
  });
</script>
@endsection
