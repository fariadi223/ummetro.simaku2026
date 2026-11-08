@extends('layouts/layoutMaster')

@section('title', 'Saldo Anggaran Unit')

@section('page-style')
<style>
  .saldo-tabel td { vertical-align: middle; }
  .saldo-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
  .saldo-tabel tfoot td { font-weight: 700; }
</style>
@endsection

@php
  $rp = fn ($n) => ($n < 0 ? '−' : '') . number_format(abs((float) $n), 0, ',', '.');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-wallet"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Saldo Anggaran Unit</h4>
      <p class="pg-header-sub mb-0">Saldo Kas di Unit Kerja per unit induk · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building"></i><span><strong>{{ $daftar->count() }}</strong> unit</span></span>
    <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Total saldo <strong>Rp {{ $rp($total['saldo']) }}</strong></span></span>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Daftar Saldo Masing-masing Unit</h5>
    <small class="text-muted">Diterima = debet Kas di Unit Kerja (terutama pencairan SPM) · Saldo = diterima − realisasi − disetor − lainnya</small>
  </div>
  <div class="table-responsive">
    <table class="table saldo-tabel mb-0">
      <thead>
        <tr><th style="width: 44px;">No</th><th>Unit Kerja</th><th class="text-end">Diterima</th><th class="text-end">Realisasi</th>
          <th class="text-end">Disetor</th><th class="text-end">Kredit Lainnya</th><th class="text-end">Saldo</th></tr>
      </thead>
      <tbody>
        @forelse ($daftar as $r)
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td>
              <span class="font-monospace small text-muted">{{ $r->unit->kode }}</span> {{ $r->unit->nama }}
              @if (abs($r->tanpa_unit) >= 1)
                <div class="small text-warning"><i class="ti ti-alert-triangle ti-xs"></i>
                  Termasuk Rp {{ $rp($r->tanpa_unit) }} dari jurnal tanpa unit (data lama, kode unit 0)</div>
              @endif
            </td>
            <td class="rp">{{ $rp($r->diterima) }}</td>
            <td class="rp {{ $r->realisasi ? '' : 'text-muted' }}">{{ $rp($r->realisasi) }}</td>
            <td class="rp {{ $r->disetor ? '' : 'text-muted' }}">{{ $rp($r->disetor) }}</td>
            <td class="rp {{ $r->lainnya ? '' : 'text-muted' }}">{{ $rp($r->lainnya) }}</td>
            <td class="rp fw-semibold {{ $r->saldo < 0 ? 'text-danger' : ($r->saldo == 0 ? 'text-muted' : '') }}">{{ $rp($r->saldo) }}</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-5">Belum ada saldo kas unit pada T.A ini.</td></tr>
        @endforelse
      </tbody>
      @if ($daftar->isNotEmpty())
        <tfoot>
          <tr><td colspan="2" class="text-end">TOTAL</td>
            @foreach (['diterima', 'realisasi', 'disetor', 'lainnya', 'saldo'] as $k)<td class="rp">{{ $rp($total[$k]) }}</td>@endforeach
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>
@endsection
