@extends('layouts/layoutMaster')

@section('title', 'Otorisasi SPM')

@section('page-style')
<style>
  .spm-tabel td { vertical-align: top; }
  .spm-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .spm-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $status = fn (?string $v) => match ($v) {
    'Disetujui' => ['bg-label-success', 'ti-check', 'Disetujui'],
    'Ditolak' => ['bg-label-danger', 'ti-x', 'Ditolak'],
    default => ['bg-label-secondary', 'ti-clock', 'Menunggu'],
  };
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-signature"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Otorisasi SPM</h4>
      <p class="pg-header-sub mb-0">Persetujuan {{ $lv['nama'] }} · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-bell"></i><span><strong>{{ $jumlahMenunggu }}</strong> SPM menunggu persetujuan Anda</span></span>
    <span class="pg-header-chip"><i class="ti ti-file-time"></i><span><strong>{{ $jumlahSemua }}</strong> SPM dalam proses</span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <h5 class="mb-0">Daftar SPM</h5>
      <small class="text-muted">SPM belum diproses · terlama lebih dulu</small>
    </div>
    <nav class="filter-chips" aria-label="Saring SPM">
      <a href="{{ route('otorisator.spm.index') }}" class="filter-chip {{ $semua ? '' : 'active' }}" @if (!$semua) aria-current="page" @endif>
        <i class="ti ti-bell" aria-hidden="true"></i><span>Menunggu persetujuan Anda</span><strong>{{ $jumlahMenunggu }}</strong>
      </a>
      <a href="{{ route('otorisator.spm.index', ['status' => 'semua']) }}" class="filter-chip {{ $semua ? 'active' : '' }}" @if ($semua) aria-current="page" @endif>
        <i class="ti ti-file-time" aria-hidden="true"></i><span>Semua dalam proses</span><strong>{{ $jumlahSemua }}</strong>
      </a>
    </nav>
  </div>
  <div class="table-responsive">
    <table class="table spm-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>No. SPM</th><th>No. SPP</th><th>Unit</th><th class="text-end">Nominal</th><th>BAK</th><th>WR II</th><th style="width: 120px;"></th></tr>
      </thead>
      <tbody>
        @forelse ($spm as $m)
          @php
            [$k1, $i1, $t1] = $status($m->verifikasi1);
            [$k2, $i2, $t2] = $status($m->verifikasi2);
            $giliran = trim((string) $m->{$lv['kolom']}) === '' && $m->verifikasi1 !== 'Ditolak' && $m->verifikasi2 !== 'Ditolak'
              && ($lv['syarat'] === null || $m->{$lv['syarat']} === 'Disetujui');
          @endphp
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td class="nomor fw-semibold">{{ $m->no_spm }}<div class="small text-muted font-sans-serif fw-normal">Terbit {{ $tglId($m->tgl_terbit) }}</div></td>
            <td class="nomor">{{ $m->no_spp }}<div class="small text-muted font-sans-serif">Disajikan {{ $tglId($m->tgl_saji) }}</div></td>
            <td style="white-space: normal; min-width: 170px;"><span class="font-monospace small text-muted">{{ $m->kode_unit }}</span> {{ $m->nama_unit }}
              <div class="small text-muted">{{ $m->kegiatan }} kegiatan</div></td>
            <td class="text-end rp">{{ $rp($m->nominal) }}</td>
            <td><span class="badge {{ $k1 }}"><i class="ti {{ $i1 }} ti-xs me-1"></i>{{ $t1 }}</span></td>
            <td><span class="badge {{ $k2 }}"><i class="ti {{ $i2 }} ti-xs me-1"></i>{{ $t2 }}</span></td>
            <td class="text-end">
              @if ($giliran)
                <a href="{{ route('otorisator.spm.show', $m->id_spp) }}" class="btn btn-sm btn-primary"><i class="ti ti-signature ti-xs me-1"></i>Otorisasi</a>
              @else
                <a href="{{ route('otorisator.spm.show', $m->id_spp) }}" class="btn btn-sm btn-label-secondary">Tampilkan</a>
              @endif
            </td>
          </tr>
          @if ($m->alasan_tolak && ($m->verifikasi1 === 'Ditolak' || $m->verifikasi2 === 'Ditolak'))
            <tr><td></td><td colspan="7" class="pt-0 border-top-0"><div class="small text-danger"><i class="ti ti-message-exclamation ti-xs me-1"></i><strong>Alasan:</strong> {{ $m->alasan_tolak }}</div></td></tr>
          @endif
        @empty
          <tr><td colspan="8" class="text-center text-muted py-5"><i class="ti ti-circle-check ti-lg d-block mb-2"></i>
            {{ $semua ? 'Tidak ada SPM dalam proses.' : 'Tidak ada SPM yang menunggu persetujuan Anda.' }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
