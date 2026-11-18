@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('page-style')
<style>
  .pg-header .sapa { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: rgba(255, 255, 255, .75); }
  .pg-header .ta-besar { font-size: 1.6rem; font-weight: 800; line-height: 1; color: #fff; }
  .unit-avatar { width: 64px; height: 64px; border-radius: 16px; display: grid; place-items: center; flex-shrink: 0;
                 font-size: 1.6rem; background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .3); color: #fff; }
  .tile .angka { font-size: 1.9rem; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; color: var(--bs-heading-color); }
  .tile .label { font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); }
  .tile .ikon { width: 42px; height: 42px; border-radius: 10px; display: grid; place-items: center; font-size: 1.3rem; }
  .tile.sorot { border-left: 3px solid var(--bs-warning); }
  .antrian td { vertical-align: middle; }
  .antrian .kode { font-family: var(--bs-font-monospace); font-size: .78rem; white-space: nowrap; }
  .progres-review { height: 8px; }
</style>
@endsection

@php
  $tglId = fn ($c) => $c ? $c->locale('id')->translatedFormat('d M Y') : '—';
  $dinilai = $ringkas['disetujui'] + $ringkas['direvisi'];
  $persen = $ringkas['kegiatan'] > 0 ? $dinilai / $ringkas['kegiatan'] * 100 : 0;
  // [kunci, label, ikon, kelas ikon, keterangan, sorot]
  $tile = [
    ['belum', 'Belum dinilai', 'ti-hourglass', 'bg-label-warning', 'Kegiatan yang belum Anda setujui / revisi', true],
    ['ajuan_revisi', 'Ajuan revisi', 'ti-refresh', 'bg-label-info', 'Sudah diperbaiki unit, perlu dinilai ulang', true],
    ['disetujui', 'Disetujui', 'ti-circle-check', 'bg-label-success', 'Kegiatan yang Anda setujui', false],
    ['direvisi', 'Direvisi', 'ti-alert-triangle', 'bg-label-danger', 'Kegiatan yang Anda minta direvisi', false],
  ];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="unit-avatar"><i class="ti ti-user-check"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <div class="sapa">Selamat datang di SIMAKU</div>
      <h4 class="mb-1">{{ $user->name }}</h4>
      <div class="pg-header-sub">{{ $user->roleLabel() }} · Klinik Anggaran Universitas Muhammadiyah Metro</div>
    </div>
    <div class="text-end">
      <div class="sapa">Tahun Anggaran</div>
      <div class="ta-besar">{{ $tahun }}/{{ $tahun + 1 }}</div>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building-community"></i><span><strong>{{ $ringkas['unit'] }}</strong> unit ditetapkan</span></span>
    <span class="pg-header-chip"><i class="ti ti-list-numbers"></i><span><strong>{{ $ringkas['kegiatan'] }}</strong> kegiatan</span></span>
    @if ($komite['akhir'])
      <span class="pg-header-chip"><i class="ti ti-calendar-event"></i>
        <span>Komite {{ $tglId($komite['awal']) }} – {{ $tglId($komite['akhir']) }}
          @if ($komite['sisa_hari'] !== null && $komite['sisa_hari'] >= 0) · sisa <strong>{{ $komite['sisa_hari'] }}</strong> hari @else · ditutup @endif
        </span></span>
    @endif
  </div>
</div>

@if ($ringkas['unit'] === 0)
  <div class="alert alert-info d-flex gap-2"><i class="ti ti-info-circle mt-1"></i>
    <div>Belum ada unit yang ditetapkan kepada Anda untuk T.A {{ $tahun }}/{{ $tahun + 1 }}. Penetapan reviewer diatur oleh admin
      (Setting › Penetapan Review). Anda juga dapat memilih tahun anggaran lain di <a href="{{ route('reviewer.periode') }}">Periode Anggaran</a>.</div>
  </div>
@endif

<div class="row g-4 mb-4">
  @foreach ($tile as [$kunci, $label, $ikon, $kelas, $ket, $sorot])
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100 tile {{ $sorot && $ringkas[$kunci] > 0 ? 'sorot' : '' }}">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="label">{{ $label }}</span>
            <span class="ikon {{ $kelas }}" aria-hidden="true"><i class="ti {{ $ikon }}"></i></span>
          </div>
          <div class="angka">{{ number_format($ringkas[$kunci], 0, ',', '.') }}</div>
          <div class="small text-muted mt-1">{{ $ket }}</div>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h5 class="card-title mb-0">Progres review</h5></div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-1">
          <span class="small text-muted">{{ $dinilai }} dari {{ $ringkas['kegiatan'] }} kegiatan dinilai</span>
          <span class="fw-semibold">{{ number_format($persen, 0, ',', '.') }}%</span>
        </div>
        <div class="progress progres-review mb-4">
          <div class="progress-bar bg-success" style="width: {{ $ringkas['kegiatan'] ? $ringkas['disetujui'] / $ringkas['kegiatan'] * 100 : 0 }}%" title="Disetujui"></div>
          <div class="progress-bar bg-danger" style="width: {{ $ringkas['kegiatan'] ? $ringkas['direvisi'] / $ringkas['kegiatan'] * 100 : 0 }}%" title="Direvisi"></div>
        </div>
        <ul class="list-unstyled mb-0 small">
          <li class="d-flex justify-content-between mb-2"><span><span class="badge badge-dot bg-success me-2"></span>Disetujui</span><strong>{{ $ringkas['disetujui'] }}</strong></li>
          <li class="d-flex justify-content-between mb-2"><span><span class="badge badge-dot bg-danger me-2"></span>Direvisi</span><strong>{{ $ringkas['direvisi'] }}</strong></li>
          <li class="d-flex justify-content-between"><span><span class="badge badge-dot bg-secondary me-2"></span>Belum dinilai</span><strong>{{ $ringkas['belum'] }}</strong></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Unit yang perlu ditinjau</h5>
        <a href="{{ route('reviewer.unit.index') }}" class="btn btn-sm btn-label-primary">Semua unit <i class="ti ti-arrow-right ms-1"></i></a>
      </div>
      <div class="table-responsive">
        <table class="table antrian mb-0">
          <thead>
            <tr><th>Unit</th><th class="text-center">Belum dinilai</th><th class="text-center">Ajuan revisi</th><th></th></tr>
          </thead>
          <tbody>
            @forelse ($antrian as $u)
              <tr>
                <td><div class="kode">{{ $u->kode }}</div><div class="fw-semibold">{{ $u->nama }}</div></td>
                <td class="text-center">{!! $u->belum ? '<span class="badge bg-label-warning">' . $u->belum . '</span>' : '<span class="text-muted">0</span>' !!}</td>
                <td class="text-center">{!! $u->ajuan_revisi ? '<span class="badge bg-label-info">' . $u->ajuan_revisi . '</span>' : '<span class="text-muted">0</span>' !!}</td>
                <td class="text-end"><a href="{{ route('reviewer.kegiatan.index', ['kode' => $u->kode]) }}" class="btn btn-sm btn-primary">Review</a></td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-5">
                {{ $ringkas['unit'] ? 'Semua kegiatan sudah Anda nilai. Terima kasih!' : 'Belum ada unit untuk ditinjau.' }}
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
