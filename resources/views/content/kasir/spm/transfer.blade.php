@extends('layouts/layoutMaster')

@section('title', 'Bukti Transfer SPM')

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-building-bank"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Input Bukti Transfer SPM</h4>
      <p class="pg-header-sub mb-0">Pembayaran SPM pagu unit melalui transfer bank · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
</div>

@include('_partials._flash')

<div class="row">
  <div class="col-lg-8 col-xl-7">
    <form method="POST" action="{{ route('kasir.spm.transfer.simpan', $spm->id_spp) }}" class="card">
      @csrf
      <div class="card-body">
        @include('content.kasir.spm._ringkas', ['spm' => $spm])
        <hr>
        <div class="mb-3">
          <label class="form-label" for="f-namabank">Nama Bank Tujuan</label>
          <input type="text" id="f-namabank" name="namabank" maxlength="50" value="{{ old('namabank', 'BRI Syariah') }}"
            class="form-control @error('namabank') is-invalid @enderror">
          @error('namabank')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label" for="f-nomrek">Nomor Rekening <span class="text-danger">*</span></label>
          <input type="text" id="f-nomrek" name="nomrek" maxlength="50" value="{{ old('nomrek') }}" required autofocus
            class="form-control font-monospace @error('nomrek') is-invalid @enderror">
          @error('nomrek')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-0">
          <label class="form-label" for="f-nomref">No. Ref / No. Record <span class="text-danger">*</span></label>
          <input type="text" id="f-nomref" name="nomref" maxlength="50" value="{{ old('nomref') }}" required
            class="form-control font-monospace @error('nomref') is-invalid @enderror">
          @error('nomref')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="ti ti-check ti-xs me-1"></i>Proses</button>
        <a href="{{ route('kasir.spm.index') }}" class="btn btn-label-secondary">Kembali</a>
      </div>
    </form>
  </div>
</div>
@endsection
