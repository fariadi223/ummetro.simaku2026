@extends('layouts/layoutMaster')

@section('title', 'Verifikasi SPP')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .spp-tabel td { vertical-align: top; }
  .spp-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .spp-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
  .spp-tabel tfoot td { font-weight: 700; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $umur = fn ($t) => $t ? (int) \Illuminate\Support\Carbon::parse($t)->startOfDay()->diffInDays(today()) : null;
  $approval = function (?string $v) {
    return match ($v) {
      'Disetujui' => ['bg-label-success', 'ti-check', 'Disetujui'],
      'Ditolak' => ['bg-label-danger', 'ti-x', 'Ditolak'],
      default => ['bg-label-secondary', 'ti-clock', 'Menunggu'],
    };
  };
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-file-search"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Verifikasi SPP</h4>
      <p class="pg-header-sub mb-0">Pembuatan SPM · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-file-search"></i><span><strong>{{ $perluVerifikasi->count() }}</strong> SPP perlu verifikasi · {{ $rp($perluVerifikasi->sum('nominal')) }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-file-time"></i><span><strong>{{ $spm->count() }}</strong> SPM menunggu approval · {{ $rp($spm->sum('nominal')) }}</span></span>
  </div>
</div>

@include('_partials._flash')

{{-- 1. SPP yang sudah diotorisasi pimpinan, belum ber-SPM --}}
<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">Daftar SPP <small class="text-muted fw-normal">(Surat Permintaan Pembayaran)</small></h5>
    <small class="text-muted">Sudah diotorisasi pimpinan unit, menunggu verifikasi · terlama lebih dulu</small>
  </div>
  <div class="table-responsive">
    <table class="table spp-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>Tgl. Penyajian</th><th>No. SPP</th><th>Unit</th><th class="text-end">Nominal</th><th>Status SPM</th><th style="width: 110px;"></th></tr>
      </thead>
      <tbody>
        @forelse ($perluVerifikasi as $s)
          @php
            $hari = $umur($s->tgl_saji);
          @endphp
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td class="text-nowrap">{{ $tglId($s->tgl_saji) }}
              <div class="small {{ $hari > 7 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $hari }} hari</div></td>
            <td class="nomor">{{ $s->no_spp }}</td>
            <td style="white-space: normal; min-width: 170px;"><span class="font-monospace small text-muted">{{ $s->kode_unit }}</span> {{ $s->nama_unit }}
              <div class="small text-muted">{{ $s->kegiatan }} kegiatan</div></td>
            <td class="text-end rp">{{ $rp($s->nominal) }}</td>
            <td><span class="badge bg-label-secondary">{{ $s->status_spm }}</span></td>
            <td class="text-end"><a href="{{ route('adminspm.verifikasi.show', $s->id_spp) }}" class="btn btn-sm btn-primary"><i class="ti ti-file-search ti-xs me-1"></i>Verifikasi</a></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-5"><i class="ti ti-circle-check ti-lg d-block mb-2"></i>Tidak ada SPP yang menunggu verifikasi.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- 2. SPM terbit, menunggu approval BAK & WR II --}}
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">SPM Siap di-Approve</h5>
    <small class="text-muted">Menunggu persetujuan BAK (Kepala Bagian Keuangan) &amp; WR II · SPM yang ditolak dapat dibatalkan</small>
  </div>
  <div class="table-responsive">
    <table class="table spp-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>No. SPM</th><th>No. SPP</th><th>Unit</th><th>BAK</th><th>WR II</th><th class="text-end">Jumlah</th><th style="width: 90px;"></th></tr>
      </thead>
      <tbody>
        @forelse ($spm as $m)
          @php
            [$k1, $i1, $t1] = $approval($m->verifikasi1);
            [$k2, $i2, $t2] = $approval($m->verifikasi2);
          @endphp
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td class="nomor"><a href="{{ route('adminspm.verifikasi.spm', $m->id_spp) }}" target="_blank" rel="noopener" class="fw-semibold">{{ $m->no_spm }}</a>
              <div class="small text-muted font-sans-serif">{{ $tglId($m->tgl_saji) }}</div></td>
            <td class="nomor">{{ $m->no_spp }}</td>
            <td style="white-space: normal; min-width: 160px;"><span class="font-monospace small text-muted">{{ $m->kode_unit }}</span> {{ $m->nama_unit }}</td>
            <td><span class="badge {{ $k1 }}"><i class="ti {{ $i1 }} ti-xs me-1"></i>{{ $t1 }}</span></td>
            <td><span class="badge {{ $k2 }}"><i class="ti {{ $i2 }} ti-xs me-1"></i>{{ $t2 }}</span></td>
            <td class="text-end rp">{{ $rp($m->nominal) }}</td>
            <td class="text-end">
              @if ($m->ditolak)
                <form method="POST" action="{{ route('adminspm.verifikasi.batal', $m->id_spp) }}" class="d-inline form-batal" data-label="{{ $m->no_spm }}">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-label-danger"><i class="ti ti-ban ti-xs me-1"></i>Batal</button>
                </form>
              @endif
            </td>
          </tr>
          @if ($m->ditolak && $m->alasan)
            <tr><td></td><td colspan="7" class="pt-0 border-top-0"><div class="small text-danger"><i class="ti ti-message-exclamation ti-xs me-1"></i><strong>Alasan:</strong> {{ $m->alasan }}</div></td></tr>
          @endif
        @empty
          <tr><td colspan="8" class="text-center text-muted py-5">Tidak ada SPM yang menunggu approval.</td></tr>
        @endforelse
      </tbody>
      @if ($spm->isNotEmpty())
        <tfoot><tr><td colspan="6" class="text-end">Jumlah</td><td class="text-end rp">{{ $rp($spm->sum('nominal')) }}</td><td></td></tr></tfoot>
      @endif
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const esc = s => $('<div>').text(s ?? '').html();
  document.querySelectorAll('.form-batal').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    Swal.fire({ icon: 'warning', title: 'Batalkan SPM?', html: 'SPM <b>' + esc(f.dataset.label) + '</b> dibatalkan dan SPP-nya berstatus ditolak.',
      showCancelButton: true, confirmButtonText: 'Ya, batalkan', cancelButtonText: 'Tidak', buttonsStyling: false,
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' } }).then(r => { if (r.isConfirmed) f.submit(); });
  }));
});
</script>
@endsection
