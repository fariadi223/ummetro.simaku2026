@extends('layouts/layoutMaster')

@section('title', 'Penerima SPM')

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-signature"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Input Penerima SPM</h4>
      <p class="pg-header-sub mb-0">Nama penerima yang tercetak pada BKK · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-8 col-xl-7">
    <form method="POST" action="{{ route('kasir.spm.paraf.simpan', $spm->id_spp) }}" class="card">
      @csrf
      <div class="card-body">
        @include('content.kasir.spm._ringkas', ['spm' => $spm])
        <hr>
        <div class="mb-3">
          <label class="form-label" for="f-jbtn">Nama Jabatan</label>
          <input type="text" id="f-jbtn" name="nm_jbtn" maxlength="100" value="{{ old('nm_jbtn', $spm->paraf_jabatan) }}"
            class="form-control @error('nm_jbtn') is-invalid @enderror">
          @error('nm_jbtn')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-0">
          <label class="form-label" for="f-pimp">Nama Penerima <span class="text-danger">*</span></label>
          <input type="text" id="f-pimp" name="nm_pimp" maxlength="100" value="{{ old('nm_pimp', $spm->paraf_nama) }}" required
            class="form-control @error('nm_pimp') is-invalid @enderror">
          @error('nm_pimp')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy ti-xs me-1"></i>Simpan</button>
        <a href="{{ route('kasir.spm.index') }}" class="btn btn-label-secondary">Lihat SPM</a>
      </div>
    </form>
  </div>
</div>
@endsection
