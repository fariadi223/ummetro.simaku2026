@extends('layouts/layoutMaster')

@section('title', 'Verifikasi SPP')

@section('page-style')
<style>
  .spp-tabel td { vertical-align: top; }
  .spp-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .spp-tabel .kode-mak { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary); }
  .spp-tabel tr.total td { font-weight: 700; background: rgba(var(--bs-secondary-rgb), .05); }
  .ttd { text-align: center; min-width: 240px; }
  .ttd .nama { margin-top: 3rem; font-weight: 700; text-decoration: underline; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tgl = \Illuminate\Support\Carbon::parse($ringkas->tgl_saji)->locale('id')->translatedFormat('d F Y');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-file-text"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Surat Permintaan Pembayaran</h4>
      <p class="pg-header-sub mb-0 font-monospace">{{ $nomor }}</p>
    </div>
    <a href="{{ route('adminspm.verifikasi.index') }}" class="btn btn-light"><i class="ti ti-arrow-left me-1"></i> Kembali</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building"></i><span>{{ $ringkas->kode_unit }} · {{ $ringkas->nama_unit }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-calendar"></i><span>Disajikan {{ $tgl }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-signature"></i><span>Otorisasi pimpinan: {{ $ringkas->otorisasi }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-cash"></i><span>Diminta <strong>{{ $rp($baris->sum('ajuan')) }}</strong></span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap gap-2">
      <span class="badge bg-label-secondary">{{ $ringkas->status_spp }}</span>
      @if ($ringkas->no_spm)<span class="badge bg-label-info font-monospace">{{ $ringkas->no_spm }}</span>@endif
    </div>
    @if ($bolehVerifikasi)
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-setujui"><i class="ti ti-check me-1"></i> Setujui &amp; Buat SPM</button>
        <button type="button" class="btn btn-label-danger" data-bs-toggle="modal" data-bs-target="#modal-tolak"><i class="ti ti-x me-1"></i> Tolak SPP</button>
      </div>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table spp-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>Kegiatan</th><th class="text-end">Total Anggaran</th><th class="text-end">Prnh. Diterima</th>
          <th class="text-end">Tersedia</th><th class="text-end">Diminta</th><th class="text-end">Sisa</th></tr>
      </thead>
      <tbody>
        @foreach ($baris as $b)
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td style="white-space: normal; min-width: 240px;">
              <div class="kode-mak">{{ $b->kode_mak }} · {{ $b->namamak4 }}</div>
              <div class="fw-semibold">{{ $b->uraiankeg }}</div>
            </td>
            <td class="text-end rp">{{ $rp($b->anggaran) }}</td>
            <td class="text-end rp">{{ $rp($b->diterima) }}</td>
            <td class="text-end rp">{{ $rp($b->tersedia) }}</td>
            <td class="text-end rp fw-semibold">{{ $rp($b->ajuan) }}</td>
            <td class="text-end rp {{ $b->tersedia - $b->ajuan < 0 ? 'text-danger fw-semibold' : '' }}">{{ $rp($b->tersedia - $b->ajuan) }}</td>
          </tr>
        @endforeach
        <tr class="total"><td colspan="5" class="text-end">Total diminta</td><td class="text-end rp">{{ $rp($baris->sum('ajuan')) }}</td><td></td></tr>
      </tbody>
    </table>
  </div>
  <div class="card-body d-flex justify-content-end">
    <div class="ttd">
      Metro, {{ $tgl }}<br>{{ $induk->nama ?? '' }}
      <div class="nama">{{ $induk->nama_pimp ?? '' }}</div>
    </div>
  </div>
</div>

@if ($bolehVerifikasi)
  <div class="modal fade" id="modal-setujui" tabindex="-1" aria-labelledby="judul-setujui" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" action="{{ route('adminspm.verifikasi.setujui', $id) }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="judul-setujui">Setujui SPP &amp; buat SPM?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          SPP <b class="font-monospace">{{ $nomor }}</b> senilai <b>{{ $rp($baris->sum('ajuan')) }}</b> disetujui dan diberi nomor SPM baru,
          lalu diteruskan ke BAK &amp; WR II untuk approval.
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i> Setujui</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="modal-tolak" tabindex="-1" aria-labelledby="judul-tolak" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" action="{{ route('adminspm.verifikasi.tolak', $id) }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="judul-tolak">Tolak SPP</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <label for="alasan" class="form-label">Alasan penolakan</label>
          <textarea id="alasan" name="alasan" rows="3" required maxlength="1000"
                    class="form-control @error('alasan', 'tolak') is-invalid @enderror">{{ old('alasan') }}</textarea>
          @error('alasan', 'tolak')<div class="invalid-feedback">{{ $message }}</div>@enderror
          <div class="form-text">Alasan ini ditampilkan ke unit pengaju.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger"><i class="ti ti-x me-1"></i> Tolak SPP</button>
        </div>
      </form>
    </div>
  </div>
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if ({{ $errors->tolak->any() ? 'true' : 'false' }}) bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-tolak')).show();
});
</script>
@endsection
