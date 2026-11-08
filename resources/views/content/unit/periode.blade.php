@extends('layouts/layoutMaster')

@section('title', 'Periode Anggaran')

@section('page-style')
<style>
  .ta-pilihan { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
  .ta-pilihan input { display: none; }
  .ta-kartu { display: block; height: 100%; padding: 1rem 1.1rem; border: 1px solid var(--bs-border-color); border-radius: .75rem;
              cursor: pointer; transition: all .15s ease; background: var(--bs-card-bg); }
  .ta-kartu:hover { border-color: rgba(var(--bs-primary-rgb), .5); transform: translateY(-2px); }
  .ta-pilihan input:checked + .ta-kartu { border-color: var(--bs-primary); box-shadow: 0 0 0 1px var(--bs-primary), 0 8px 20px -12px rgba(var(--bs-primary-rgb), .7);
                                          background: rgba(var(--bs-primary-rgb), .05); }
  .ta-kartu .tahun { font-size: 1.35rem; font-weight: 800; line-height: 1.1; }
  .ta-kartu .masa { font-size: .8rem; color: var(--bs-secondary); }
  .ta-kartu .cek { width: 1.4rem; height: 1.4rem; border-radius: 50%; border: 2px solid var(--bs-border-color); display: grid; place-items: center;
                   color: transparent; flex-shrink: 0; }
  .ta-pilihan input:checked + .ta-kartu .cek { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
</style>
@endsection

@php
  $tgl = fn ($c) => $c ? $c->locale('id')->translatedFormat('d M Y') : '—';
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-calendar-cog"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Periode Anggaran</h4>
      <p class="pg-header-sub mb-0">Pilih tahun anggaran yang ingin dikerjakan. Semua halaman akan menampilkan data tahun tersebut.</p>
    </div>
    <span class="pg-header-chip">
      <i class="ti ti-calendar-event"></i>
      <span>Sedang dikerjakan: <strong>T.A {{ $periode }}/{{ $periode + 1 }}</strong></span>
    </span>
  </div>
</div>

@include('_partials._flash')
@error('tahun')<div class="alert alert-danger">{{ $message }}</div>@enderror

<form method="POST" action="{{ $aksi }}" class="card">
  @csrf
  <div class="card-header">
    <h5 class="mb-0">Pilih Tahun Anggaran</h5>
  </div>
  <div class="card-body">
    <div class="ta-pilihan">
      @foreach ($daftar as $i)
        <label>
          <input type="radio" name="tahun" value="{{ $i->th_angg }}" @checked($i->th_angg == $periode)>
          <span class="ta-kartu">
            <span class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <span>
                <span class="d-block text-muted small">T.A</span>
                <span class="tahun">{{ $i->th_angg }}/{{ $i->th_angg + 1 }}</span>
              </span>
              <span class="cek"><i class="ti ti-check ti-xs"></i></span>
            </span>
            <span class="masa d-block mb-2">{{ $tgl($i->awal) }} – {{ $tgl($i->akhir) }}</span>
            <span class="d-flex flex-wrap gap-1">
              @if ($i->aktif === 'enable')
                <span class="badge bg-label-success">Tahun berjalan</span>
              @endif
              @if ($i->komite_buka)
                <span class="badge bg-label-info">Komite dibuka</span>
              @endif
              @if ($i->th_angg == $periode)
                <span class="badge bg-label-primary">Dikerjakan</span>
              @endif
            </span>
          </span>
        </label>
      @endforeach
    </div>
  </div>
  <div class="card-footer d-flex justify-content-end gap-2 border-top">
    <a href="{{ $batal }}" class="btn btn-label-secondary">Batal</a>
    <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Proses</button>
  </div>
</form>
@endsection
