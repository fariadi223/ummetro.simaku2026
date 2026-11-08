@extends('layouts/layoutMaster')

@section('title', $keg ? 'Ubah Kegiatan' : 'Tambah Kegiatan')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .form-keg .form-label { font-weight: 600; }
  .form-keg .bantuan { font-size: .8rem; color: var(--bs-secondary); }
  .form-keg textarea { line-height: 1.6; }
  .bagian-judul { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: var(--bs-secondary); margin: 1.75rem 0 1rem;
                  display: flex; align-items: center; gap: .5rem; }
  .bagian-judul::after { content: ""; flex: 1; border-top: 1px dashed var(--bs-border-color); }
</style>
@endsection

@php
  $nilai = fn ($kolom, $bawaan = '') => old($kolom, $keg ? $keg->{$kolom} : $bawaan);
  $indikatorDipilih = old('indikator', $keg ? $keg->daftar_indikator : []);
  $aksi = $keg ? route('unit.kegiatan.update', $keg->idkeg) : route('unit.kegiatan.store');
  $kembali = route('unit.kegiatan.index', ['mak' => $aktif->kodemak1]);
  $rujukanUraian = $rujukan->pluck('rujukan_uraian', 'id');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti {{ $keg ? 'ti-pencil' : 'ti-plus' }}"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">{{ $keg ? 'Ubah Kegiatan' : 'Tambah Kegiatan' }}</h4>
      <p class="pg-header-sub mb-0">Proses Bisnis {{ $aktif->kodemak1 }} : {{ Helper::judulMak($aktif->namamak1) }} · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    @if ($komite['dibuka'] && $komite['sisa_hari'] > 0)
      <span class="pg-header-chip"><i class="ti ti-clock"></i><span>Waktu tersisa <strong>{{ $komite['sisa_hari'] }} hari</strong></span></span>
    @endif
  </div>
</div>

@if ($errors->any())
  <div class="alert alert-danger">Data yang dimasukkan belum lengkap atau tidak valid. Periksa isian yang ditandai merah.</div>
@endif

<form method="POST" action="{{ $aksi }}" class="card form-keg">
  @csrf
  @if ($keg) @method('PUT') @endif
  <input type="hidden" name="idmak1" value="{{ $aktif->idmak1 }}">

  <div class="card-body">
    <div class="row g-4">
      <div class="col-lg-8">
        <div class="bagian-judul mt-0">Identitas kegiatan</div>

        <div class="mb-3">
          <label class="form-label" for="f-idmak4">Nama Kegiatan (MAK)</label>
          <select id="f-idmak4" name="idmak4" class="form-select @error('idmak4') is-invalid @enderror" data-placeholder="Pilih nama kegiatan">
            <option value=""></option>
            @foreach ($mak4 as $grup => $daftar)
              <optgroup label="{{ $grup }}">
                @foreach ($daftar as $m)
                  <option value="{{ $m->idmak4 }}" @selected((int) $nilai('idmak4') === $m->idmak4)>{{ $m->kode }} — {{ $m->namamak4 }}</option>
                @endforeach
              </optgroup>
            @endforeach
          </select>
          @error('idmak4')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
          <label class="form-label" for="f-uraiankeg">Nama / Judul Kegiatan</label>
          <input type="text" id="f-uraiankeg" name="uraiankeg" maxlength="200" class="form-control @error('uraiankeg') is-invalid @enderror"
                 value="{{ $nilai('uraiankeg') }}" required>
          @error('uraiankeg')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-5">
            <label class="form-label" for="f-rujukan_id">Sumber Rujukan / Kebijakan</label>
            <select id="f-rujukan_id" name="rujukan_id" class="form-select @error('rujukan_id') is-invalid @enderror" required>
              <option value="">Pilih rujukan</option>
              @foreach ($rujukan as $r)
                <option value="{{ $r->id }}" @selected((int) old('rujukan_id', $keg?->kebijakan_id) === $r->id)>{{ $r->rujukan_nama }}</option>
              @endforeach
            </select>
            @error('rujukan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-7">
            <label class="form-label" for="f-rujukan_uraian">Deskripsi Sumber Rujukan</label>
            <textarea id="f-rujukan_uraian" name="rujukan_uraian" rows="2" class="form-control @error('rujukan_uraian') is-invalid @enderror"
                      required>{{ old('rujukan_uraian', $keg?->kebijakan_uraian) }}</textarea>
            @error('rujukan_uraian')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="bagian-judul">Uraian kegiatan</div>

        <div class="mb-3">
          <label class="form-label" for="f-latblk">Latar Belakang</label>
          <textarea id="f-latblk" name="latblk" rows="6" class="form-control @error('latblk') is-invalid @enderror" required>{{ $nilai('latblk') }}</textarea>
          @error('latblk')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
          <label class="form-label" for="f-tujuan">Tujuan</label>
          <textarea id="f-tujuan" name="tujuan" rows="5" class="form-control @error('tujuan') is-invalid @enderror" required>{{ $nilai('tujuan') }}</textarea>
          @error('tujuan')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-0">
          <label class="form-label" for="f-lingkup">Luaran / Output Kegiatan</label>
          <textarea id="f-lingkup" name="lingkup" rows="3" class="form-control @error('lingkup') is-invalid @enderror" required>{{ $nilai('lingkup') }}</textarea>
          @error('lingkup')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="col-lg-4">
        <div class="bagian-judul mt-0">Pelaksanaan</div>

        <div class="mb-3">
          <label class="form-label" for="f-indikator">Indikator</label>
          <select id="f-indikator" name="indikator[]" multiple class="form-select @error('indikator') is-invalid @enderror @error('indikator.*') is-invalid @enderror"
                  data-placeholder="Pilih indikator">
            @foreach ($opsiIndikator as $ind)
              <option value="{{ $ind }}" @selected(in_array($ind, (array) $indikatorDipilih, true))>{{ $ind }}</option>
            @endforeach
          </select>
          <div class="bantuan mt-1">IKU = Indikator Kinerja Utama, IKT = Indikator Kinerja Tambahan.</div>
          @error('indikator.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
          <label class="form-label" for="f-jadwal">Jadwal Kegiatan</label>
          <input type="text" id="f-jadwal" name="jadwal" maxlength="255" class="form-control @error('jadwal') is-invalid @enderror"
                 value="{{ $nilai('jadwal') }}" placeholder="Mis. September 2026 - Agustus 2027" required>
          @error('jadwal')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
          <label class="form-label" for="f-idsumberdana">Sumber Dana</label>
          <select id="f-idsumberdana" name="idsumberdana" class="form-select @error('idsumberdana') is-invalid @enderror" required>
            @forelse ($pagu as $p)
              <option value="{{ $p->id_pagu }}" @selected($nilai('idsumberdana') === $p->id_pagu)>{{ $p->sumber }}</option>
            @empty
              <option value="">Belum ada pagu T.A {{ $tahun }}</option>
            @endforelse
          </select>
          @error('idsumberdana')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        @if ($keg)
          <div class="alert alert-secondary small mb-0">
            Dibuat {{ $keg->created_by }} {{ $keg->created_date }}
            @if ($keg->modified_date)<br>Diubah {{ $keg->modified_by }} {{ $keg->modified_date }}@endif
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="card-footer d-flex justify-content-end gap-2 border-top">
    <a href="{{ $kembali }}" class="btn btn-label-secondary">Kembali</a>
    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan</button>
  </div>
</form>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  $('#f-idmak4').select2({ placeholder: 'Pilih nama kegiatan', allowClear: true, width: '100%' });
  $('#f-indikator').select2({ placeholder: 'Pilih indikator', width: '100%', closeOnSelect: false });

  // Isi deskripsi rujukan otomatis bila masih kosong.
  const uraianRujukan = @json($rujukanUraian);
  const rujukan = document.getElementById('f-rujukan_id'), deskripsi = document.getElementById('f-rujukan_uraian');
  rujukan.addEventListener('change', function () {
    if (!deskripsi.value.trim() && uraianRujukan[rujukan.value]) deskripsi.value = uraianRujukan[rujukan.value];
  });
});
</script>
@endsection
