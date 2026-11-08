@extends('layouts/layoutMaster')

@section('title', 'Ajukan SPP')

@section('page-style')
<style>
  .ringkas { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; }
  .ringkas .label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary); margin-bottom: .2rem; }
  .ringkas .nilai { font-size: 1.15rem; font-weight: 600; font-variant-numeric: tabular-nums; }
  .kode-mak { font-family: var(--bs-font-monospace); font-size: .8rem; color: var(--bs-secondary); }
  #ajuan { font-variant-numeric: tabular-nums; font-size: 1.1rem; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $maks = (int) floor($keg->sisa);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-file-export"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Ajukan SPP</h4>
      <p class="pg-header-sub mb-0">{{ $unit->kode }} · {{ $unit->nama }} · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    <a href="{{ route('unit.spp.index') }}" class="btn btn-light"><i class="ti ti-arrow-left me-1"></i> Kembali</a>
  </div>
</div>

@include('_partials._flash')

<div class="row justify-content-center">
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header">
        <div class="kode-mak">{{ $keg->kode_mak }} · {{ $keg->namamak4 }}</div>
        <h5 class="mb-0 mt-1">{{ $keg->uraiankeg }}</h5>
        @if ($nomorDraf)
          <small class="text-muted">Ditambahkan ke SPP <span class="font-monospace">{{ $nomorDraf }}</span> (belum dikirim)</small>
        @else
          <small class="text-muted">Akan membuat SPP baru</small>
        @endif
      </div>
      <div class="card-body">
        <div class="ringkas mb-4">
          <div><div class="label">Total Anggaran Belanja</div><div class="nilai">{{ $rp($keg->anggaran) }}</div></div>
          <div><div class="label">Sudah Dicairkan</div><div class="nilai">{{ $rp($keg->dicairkan) }}</div></div>
          <div><div class="label">Sisa Anggaran</div><div class="nilai text-primary">{{ $rp($keg->sisa) }}</div></div>
        </div>

        <form method="POST" action="{{ route('unit.spp.store', $keg->idkeg) }}" novalidate>
          @csrf
          <label for="ajuan" class="form-label fw-semibold">Jumlah anggaran yang diajukan</label>
          <div class="input-group has-validation">
            <span class="input-group-text">Rp</span>
            <input type="text" inputmode="numeric" autocomplete="off" id="ajuan" name="ajuan" data-maks="{{ $maks }}"
                   class="form-control text-end @error('ajuan') is-invalid @enderror" value="{{ old('ajuan') }}" placeholder="0" required autofocus>
            <button type="button" class="btn btn-outline-primary" id="isi-maks">Ajukan semua sisa</button>
            @error('ajuan')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="form-text">Maksimal {{ $rp($maks) }}.</div>

          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Tambahkan ke SPP</button>
            <a href="{{ route('unit.spp.index') }}" class="btn btn-label-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('ajuan');
  const format = v => { const d = String(v).replace(/\D/g, ''); return d ? Number(d).toLocaleString('id-ID') : ''; };
  input.value = format(input.value);
  input.addEventListener('input', function () {
    const sisaKanan = input.value.length - input.selectionStart;
    input.value = format(input.value);
    const pos = Math.max(0, input.value.length - sisaKanan);
    input.setSelectionRange(pos, pos);
  });
  document.getElementById('isi-maks').addEventListener('click', function () {
    input.value = format(input.dataset.maks);
    input.focus();
  });
});
</script>
@endsection
