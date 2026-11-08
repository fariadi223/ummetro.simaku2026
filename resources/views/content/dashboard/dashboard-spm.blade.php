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
  .tile .nominal { font-variant-numeric: tabular-nums; color: var(--bs-body-color); }
  .tile .ikon { width: 42px; height: 42px; border-radius: 10px; display: grid; place-items: center; font-size: 1.3rem; }
  .antrian td { vertical-align: top; }
  .antrian .nomor { font-family: var(--bs-font-monospace); font-size: .78rem; white-space: nowrap; }
  .antrian .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $umur = fn ($t) => $t ? (int) \Illuminate\Support\Carbon::parse($t)->startOfDay()->diffInDays(today()) : null;
  // [kunci, label, ikon, kelas ikon, keterangan]
  $tile = [
    ['verifikasi', 'Menunggu verifikasi', 'ti-file-search', 'bg-label-warning', 'SPP sudah diotorisasi pimpinan, belum ber-SPM'],
    ['spm_proses', 'SPM belum diproses', 'ti-file-time', 'bg-label-info', 'SPM terbit, menunggu persetujuan'],
    ['spm_setuju', 'SPM disetujui', 'ti-file-check', 'bg-label-success', 'Siap dibayarkan kasir'],
    ['ditolak', 'SPP ditolak', 'ti-file-x', 'bg-label-danger', 'Ditolak saat verifikasi'],
  ];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="unit-avatar"><i class="ti ti-file-certificate"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <div class="sapa">Selamat datang di SIMAKU</div>
      <h4 class="mb-1">{{ $unit?->nama_pimp ?: $user->name }}</h4>
      <div class="pg-header-sub">
        {{ $user->roleLabel() }}
        @if ($unit) · {{ $unit->nama }} · <span class="font-monospace">{{ $unit->kode }}</span>@endif
      </div>
    </div>
    <div class="text-end">
      <div class="sapa">Tahun Anggaran</div>
      <div class="ta-besar">{{ $tahun }}/{{ $tahun + 1 }}</div>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building-bank"></i><span>Sistem Informasi Manajemen dan Akuntansi Keuangan</span></span>
  </div>
</div>

{{-- Ringkasan antrian --}}
<div class="row g-4 mb-4">
  @foreach ($tile as [$kunci, $label, $ikon, $kelas, $ket])
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100 tile">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="label">{{ $label }}</span>
            <span class="ikon {{ $kelas }}" aria-hidden="true"><i class="ti {{ $ikon }}"></i></span>
          </div>
          <div class="angka">{{ number_format($ringkas[$kunci]['n'], 0, ',', '.') }}</div>
          <div class="nominal small">{{ $rp($ringkas[$kunci]['nominal']) }}</div>
          <div class="small text-muted mt-1">{{ $ket }}</div>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="row g-4">
  {{-- SPP menunggu verifikasi (terlama dulu) --}}
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-start gap-2">
        <div>
          <h5 class="mb-0">Antrian Verifikasi SPP</h5>
          <small class="text-muted">Terlama lebih dulu · {{ $ringkas['verifikasi']['n'] }} SPP menunggu</small>
        </div>
        <a href="{{ route('adminspm.verifikasi.index') }}" class="btn btn-sm btn-label-primary">Lihat semua</a>
      </div>
      <div class="table-responsive">
        <table class="table antrian mb-0">
          <thead><tr><th>No. SPP</th><th>Unit</th><th class="text-end">Nominal</th><th class="text-end">Menunggu</th></tr></thead>
          <tbody>
            @forelse ($antrianVerifikasi as $s)
              @php
                $hari = $umur($s->tgl_saji);
              @endphp
              <tr>
                <td class="nomor"><a href="{{ route('adminspm.verifikasi.show', $s->id_spp) }}">{{ $s->no_spp }}</a><div class="small text-muted font-sans-serif">{{ $tglId($s->tgl_saji) }}</div></td>
                <td style="white-space: normal; min-width: 160px;"><span class="font-monospace small text-muted">{{ $s->kode_unit }}</span> {{ $s->nama_unit }}
                  <div class="small text-muted">{{ $s->kegiatan }} kegiatan</div></td>
                <td class="text-end rp">{{ $rp($s->nominal) }}</td>
                <td class="text-end text-nowrap">
                  <span class="{{ $hari !== null && $hari > 7 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $hari }} hari</span>
                  @if ($hari !== null && $hari > 7)<i class="ti ti-alert-triangle ti-xs text-danger" title="Lebih dari 7 hari" aria-label="Lebih dari 7 hari"></i>@endif
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-5"><i class="ti ti-circle-check ti-lg d-block mb-2"></i>Tidak ada SPP yang menunggu verifikasi.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($ringkas['verifikasi']['n'] > $antrianVerifikasi->count())
        <div class="card-footer small text-muted">Menampilkan {{ $antrianVerifikasi->count() }} dari {{ $ringkas['verifikasi']['n'] }} SPP.</div>
      @endif
    </div>
  </div>

  {{-- SPM belum diproses --}}
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-start gap-2">
        <div>
          <h5 class="mb-0">SPM Belum Diproses</h5>
          <small class="text-muted">Terlama lebih dulu · {{ $ringkas['spm_proses']['n'] }} SPM</small>
        </div>
        <a href="{{ route('adminspm.verifikasi.index') }}" class="btn btn-sm btn-label-primary">Lihat semua</a>
      </div>
      <div class="table-responsive">
        <table class="table antrian mb-0">
          <thead><tr><th>No. SPM</th><th>Unit</th><th class="text-end">Nominal</th></tr></thead>
          <tbody>
            @forelse ($antrianSpm as $m)
              <tr>
                <td class="nomor">{{ $m->no_spm }}<div class="small text-muted font-sans-serif">{{ $tglId($m->tgl_spm) }}</div></td>
                <td style="white-space: normal;"><span class="font-monospace small text-muted">{{ $m->kode_unit }}</span> {{ $m->nama_unit }}
                  <div class="small text-muted">{{ $m->spp }} SPP</div></td>
                <td class="text-end rp">{{ $rp($m->nominal) }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted py-5"><i class="ti ti-circle-check ti-lg d-block mb-2"></i>Tidak ada SPM yang menunggu.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($ringkas['spm_proses']['n'] > $antrianSpm->count())
        <div class="card-footer small text-muted">Menampilkan {{ $antrianSpm->count() }} dari {{ $ringkas['spm_proses']['n'] }} SPM.</div>
      @endif
    </div>
  </div>
</div>
@endsection
