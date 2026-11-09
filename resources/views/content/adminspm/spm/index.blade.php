@extends('layouts/layoutMaster')

@section('title', 'Daftar SPM')

@section('page-style')
<style>
  .spm-tabel td { vertical-align: top; }
  .spm-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .spm-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
  .spm-tabel tfoot td { font-weight: 700; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $approval = fn (?string $v) => match ($v) {
    'Disetujui' => ['bg-label-success', 'ti-check', 'Disetujui'],
    'Ditolak' => ['bg-label-danger', 'ti-x', 'Ditolak'],
    default => ['bg-label-secondary', 'ti-clock', 'Menunggu'],
  };
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-file-check"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Daftar Surat Perintah Membayar (SPM)</h4>
      <p class="pg-header-sub mb-0">SPM yang sudah disetujui · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-file-check"></i><span><strong>{{ $semua }}</strong> SPM</span></span>
    <span class="pg-header-chip"><i class="ti ti-cash"></i><span>Total <strong>{{ $rp($totalSemua) }}</strong></span></span>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <h5 class="mb-0">SPM Disetujui</h5>
      <small class="text-muted">
        Nomor SPM terbaru lebih dulu
        @if ($cari !== '') · <strong>{{ $jumlah }}</strong> hasil untuk "{{ $cari }}" @endif
      </small>
    </div>
    @if ($semua > 0)
      <form method="GET" action="{{ route($rute . '.index') }}" class="d-flex gap-2" role="search">
        <div class="input-group input-group-sm" style="max-width: 280px;">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="search" name="cari" value="{{ $cari }}" class="form-control" placeholder="Cari no. SPM / SPP / unit" aria-label="Cari SPM">
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Cari</button>
        @if ($cari !== '')<a href="{{ route($rute . '.index') }}" class="btn btn-sm btn-label-secondary">Reset</a>@endif
      </form>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table spm-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>No. SPM</th><th>No. SPP</th><th>Unit</th><th>Tgl. Penyajian</th><th class="text-end">Ajuan</th>
          <th>BAK</th><th>WR II</th><th style="width: 90px;"></th></tr>
      </thead>
      <tbody>
        @forelse ($daftar as $m)
          @php
            [$k1, $i1, $t1] = $approval($m->verifikasi1);
            [$k2, $i2, $t2] = $approval($m->verifikasi2);
          @endphp
          <tr>
            <td class="text-muted">{{ $daftar->firstItem() + $loop->index }}</td>
            <td class="nomor fw-semibold">{{ $m->no_spm }}</td>
            <td class="nomor">{{ $m->no_spp }}</td>
            <td style="white-space: normal; min-width: 160px;"><span class="font-monospace small text-muted">{{ $m->kode_unit }}</span> {{ $m->nama_unit }}
              <div class="small text-muted">{{ $m->kegiatan }} kegiatan</div></td>
            <td class="text-nowrap">{{ $tglId($m->tgl_saji) }}</td>
            <td class="text-end rp">{{ $rp($m->nominal) }}</td>
            <td><span class="badge {{ $k1 }}"><i class="ti {{ $i1 }} ti-xs me-1"></i>{{ $t1 }}</span></td>
            <td><span class="badge {{ $k2 }}"><i class="ti {{ $i2 }} ti-xs me-1"></i>{{ $t2 }}</span></td>
            <td class="text-end"><a href="{{ route($rute . '.cetak', $m->id_spp) }}" target="_blank" rel="noopener" data-cetak-langsung class="btn btn-sm btn-label-primary"><i class="ti ti-printer ti-xs me-1"></i>Cetak</a></td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-5">
            {{ $cari !== '' ? 'Tidak ada SPM yang cocok dengan "' . $cari . '".' : 'Belum ada SPM yang disetujui pada T.A ini.' }}
          </td></tr>
        @endforelse
      </tbody>
      @if ($daftar->isNotEmpty())
        <tfoot>
          <tr><td colspan="5" class="text-end">Jumlah halaman ini</td><td class="text-end rp">{{ $rp($daftar->sum('nominal')) }}</td><td colspan="3"></td></tr>
          @if ($daftar->hasPages())
            <tr><td colspan="5" class="text-end">Jumlah {{ $cari !== '' ? 'hasil pencarian' : 'seluruh SPM' }}</td><td class="text-end rp">{{ $rp($total) }}</td><td colspan="3"></td></tr>
          @endif
        </tfoot>
      @endif
    </table>
  </div>
  @if ($daftar->hasPages())
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted">Menampilkan {{ $daftar->firstItem() }}–{{ $daftar->lastItem() }} dari {{ $daftar->total() }} SPM</small>
      {{ $daftar->onEachSide(1)->links('_partials.pagination') }}
    </div>
  @endif
</div>
@endsection

@section('page-script')
  @include('content.adminspm.spm._cetak-langsung')
@endsection
