@extends('layouts/layoutMaster')

@section('title', 'Penyetoran Kas Unit')

@section('page-style')
<style>
  .setor-tabel td { vertical-align: middle; }
  .setor-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .setor-tabel tfoot td { font-weight: 700; }
</style>
@endsection

@php
  $rp = fn ($n) => ($n < 0 ? '−' : '') . 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-building-bank"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Penyetoran Kas Unit ke Kas Universitas</h4>
      <p class="pg-header-sub mb-0">Saldo Kas di Unit Kerja per unit induk · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building"></i><span><strong>{{ $daftar->count() }}</strong> unit</span></span>
    <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Total kas di unit <strong>{{ $rp($total['saldo']) }}</strong></span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Saldo Kas Unit</h5>
    <small class="text-muted">Diterima = pencairan SPM ke unit (debet) · Disetor = setoran ke kas universitas (kredit)</small>
  </div>
  <div class="table-responsive">
    <table class="table setor-tabel mb-0">
      <thead>
        <tr><th style="width: 44px;">No</th><th>Nama Unit</th><th class="text-end">Diterima</th><th class="text-end">Disetor</th>
          <th class="text-end">Saldo</th><th class="text-end" style="width: 120px;">Menu</th></tr>
      </thead>
      <tbody>
        @forelse ($daftar as $r)
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td><span class="font-monospace small text-muted">{{ $r->unit->kode }}</span> {{ $r->unit->nama }}</td>
            <td class="text-end rp">{{ $rp($r->debet) }}</td>
            <td class="text-end rp">{{ $rp($r->kredit) }}</td>
            <td class="text-end rp fw-semibold {{ $r->saldo < 0 ? 'text-danger' : ($r->saldo == 0 ? 'text-muted' : '') }}">{{ $rp($r->saldo) }}</td>
            <td class="text-end">
              <a href="{{ route('akuntansi.penyetoran.create', $r->ff) }}" class="btn btn-sm {{ $r->saldo > 0 ? 'btn-primary' : 'btn-label-secondary' }}">
                <i class="ti ti-arrow-big-right-lines ti-xs me-1"></i>Setorkan</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-5">Belum ada kas di unit kerja pada T.A ini.</td></tr>
        @endforelse
      </tbody>
      @if ($daftar->isNotEmpty())
        <tfoot>
          <tr><td colspan="2" class="text-end">TOTAL KAS</td><td class="text-end rp">{{ $rp($total['debet']) }}</td>
            <td class="text-end rp">{{ $rp($total['kredit']) }}</td><td class="text-end rp">{{ $rp($total['saldo']) }}</td><td></td></tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>
@endsection
