@extends('layouts/layoutMaster')

@section('title', 'Informasi Pagu')

@section('page-style')
<style>
  .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .pagu-kartu .baris { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px dashed var(--bs-border-color); }
  .pagu-kartu .baris:last-child { border-bottom: 0; }
  .pagu-kartu .baris.utama { font-weight: 700; font-size: 1.05rem; }
  .pagu-kartu .progress { height: 10px; }
  .label-kecil { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary); }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  // Unit induk: total dari semua unit FF.*; unit biasa: pagu unit sendiri.
  $semuaPagu = $semuaUnit ? $semuaUnit->flatMap(fn ($u) => $u->pagu) : $pagu;
  $totalPagu = $semuaPagu->sum('nominal');
  $totalAjuan = $semuaPagu->sum('ajuan');
  $persenWarna = function ($ajuan, $nominal) {
    $persen = $nominal > 0 ? $ajuan / $nominal * 100 : 0;
    return [$persen, $persen > 100 ? 'danger' : ($persen >= 90 ? 'warning' : 'success')];
  };
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-wallet"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Informasi Pagu {{ $semuaUnit ? 'Semua Unit' : 'Unit' }}</h4>
      <p class="pg-header-sub mb-0">{{ $unit->kode }} · {{ $unit->nama }} · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    <span class="pg-header-chip"><i class="ti ti-pig-money"></i><span>Sisa pagu <strong>{{ $rp($totalPagu - $totalAjuan) }}</strong></span></span>
  </div>
  @if ($semuaUnit)
    <div class="d-flex flex-wrap gap-2 mt-3">
      <span class="pg-header-chip"><i class="ti ti-sitemap"></i><span><strong>{{ $semuaUnit->count() }}</strong> unit</span></span>
      <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Pagu <strong>{{ $rp($totalPagu) }}</strong></span></span>
      <span class="pg-header-chip"><i class="ti ti-receipt"></i><span>Ajuan <strong>{{ $rp($totalAjuan) }}</strong></span></span>
    </div>
  @endif
</div>

@if ($semuaUnit)
  {{-- Unit induk: pagu semua unit FF.* --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Pagu per Unit</h5>
      <small class="text-muted">Ajuan = belanja − pendapatan kegiatan yang memakai sumber pagu tsb. · sisa = pagu − ajuan</small>
    </div>
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr><th style="width: 70px;">Kode</th><th>Unit</th><th>Sumber Pagu</th><th class="text-end">Pagu</th><th class="text-end">Pendapatan</th>
            <th class="text-end">Belanja</th><th class="text-end">Ajuan</th><th class="text-end">Sisa</th><th style="width: 120px;">Terpakai</th></tr>
        </thead>
        <tbody>
          @foreach ($semuaUnit as $u)
            @forelse ($u->pagu as $p)
              @php
                [$persen, $warna] = $persenWarna($p->ajuan, $p->nominal);
              @endphp
              <tr>
                @if ($loop->first)
                  <td rowspan="{{ $u->pagu->count() }}" class="font-monospace small align-top">{{ $u->kode }}</td>
                  <td rowspan="{{ $u->pagu->count() }}" class="align-top fw-semibold" style="white-space: normal; min-width: 180px;">{{ $u->nama }}</td>
                @endif
                <td>{{ $p->sumber }} <small class="text-muted font-monospace">{{ $p->kode }}</small></td>
                <td class="text-end rp">{{ $rp($p->nominal) }}</td>
                <td class="text-end rp">{{ $rp($p->pendapatan) }}</td>
                <td class="text-end rp">{{ $rp($p->belanja) }}</td>
                <td class="text-end rp">{{ $rp($p->ajuan) }}</td>
                <td class="text-end rp fw-semibold {{ $p->sisa < 0 ? 'text-danger' : '' }}">{{ $rp($p->sisa) }}</td>
                <td>
                  <div class="progress mb-1" style="height: 6px;"><div class="progress-bar bg-{{ $warna }}" style="width: {{ min($persen, 100) }}%"></div></div>
                  <small class="{{ $persen > 100 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ number_format($persen, 1, ',', '.') }}%</small>
                </td>
              </tr>
            @empty
              <tr>
                <td class="font-monospace small">{{ $u->kode }}</td>
                <td class="fw-semibold" style="white-space: normal;">{{ $u->nama }}</td>
                <td colspan="7" class="text-muted small">Belum mendapat pagu T.A {{ $tahun }}</td>
              </tr>
            @endforelse
          @endforeach
        </tbody>
        <tfoot>
          <tr class="fw-semibold">
            <td colspan="3" class="text-end">Total</td>
            <td class="text-end rp">{{ $rp($totalPagu) }}</td>
            <td class="text-end rp">{{ $rp($semuaPagu->sum('pendapatan')) }}</td>
            <td class="text-end rp">{{ $rp($semuaPagu->sum('belanja')) }}</td>
            <td class="text-end rp">{{ $rp($totalAjuan) }}</td>
            <td class="text-end rp {{ $totalPagu - $totalAjuan < 0 ? 'text-danger' : '' }}">{{ $rp($totalPagu - $totalAjuan) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  @include('content.unit.keluaran._per-bidang', ['judulBidang' => 'Seluruh kegiatan semua unit T.A ' . $tahun])
@else

@forelse ($pagu as $p)
  @php
    $persen = $p->nominal > 0 ? $p->ajuan / $p->nominal * 100 : 0;
    $warna = $persen > 100 ? 'danger' : ($persen >= 90 ? 'warning' : 'success');
  @endphp
  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card h-100 pagu-kartu">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h5 class="mb-0">{{ $p->sumber }}</h5>
            <small class="text-muted font-monospace">{{ $p->kode }}</small>
          </div>
          <span class="badge bg-label-{{ $warna }}">{{ number_format($persen, 1, ',', '.') }}% terpakai</span>
        </div>
        <div class="card-body">
          <div class="baris utama"><span>Total Pagu Anggaran</span><span class="rp">{{ $rp($p->nominal) }}</span></div>
          <div class="baris"><span>Total Pendapatan</span><span class="rp">{{ $rp($p->pendapatan) }}</span></div>
          <div class="baris"><span>Total Belanja</span><span class="rp">{{ $rp($p->belanja) }}</span></div>
          <div class="baris"><span>Ajuan Anggaran yang Mengurangi Pagu</span><span class="rp">{{ $rp($p->ajuan) }}</span></div>
          <div class="baris utama"><span>Sisa Pagu Anggaran</span><span class="rp {{ $p->sisa < 0 ? 'text-danger' : 'text-success' }}">{{ $rp($p->sisa) }}</span></div>
          <div class="progress mt-3" role="progressbar" aria-label="Pagu terpakai" aria-valuenow="{{ min($persen, 100) }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar bg-{{ $warna }}" style="width: {{ min($persen, 100) }}%"></div>
          </div>
          <div class="d-flex justify-content-between small text-muted mt-1">
            <span>Ajuan {{ $rp($p->ajuan) }}</span><span>Pagu {{ $rp($p->nominal) }}</span>
          </div>
        </div>
      </div>
    </div>

    @if ($loop->first)
      <div class="col-lg-6">
        @include('content.unit.keluaran._per-bidang', ['judulBidang' => 'Seluruh kegiatan unit T.A ' . $tahun])
      </div>
    @endif
  </div>
@empty
  <div class="card"><div class="card-body text-center text-muted py-5">
    <i class="ti ti-wallet-off ti-lg d-block mb-2"></i>Unit belum mendapat pagu untuk T.A {{ $tahun }}.
  </div></div>
@endforelse
@endif
@endsection
