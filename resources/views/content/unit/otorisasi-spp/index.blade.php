@extends('layouts/layoutMaster')

@section('title', 'Otorisasi SPP')

@section('page-style')
<style>
  .spp-tabel td { vertical-align: top; }
  .spp-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .spp-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
  .spp-tabel tr.detail td { background: rgba(var(--bs-secondary-rgb), .04); border-top: 0; }
  .spp-tabel .kode-mak { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary); }
  .btn-lipat i { transition: transform .15s ease; }
  .btn-lipat[aria-expanded="true"] i { transform: rotate(180deg); }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y');
  $badgeOtorisasi = ['Belum' => ['bg-label-warning', 'Menunggu'], 'Sudah' => ['bg-label-success', 'Sudah'], 'Batal' => ['bg-label-danger', 'Ditolak']];
  $badgeVerifikasi = ['Belum diverifikasi' => 'bg-label-secondary', 'Disetujui' => 'bg-label-success', 'Ditolak' => 'bg-label-danger'];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-signature"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Otorisasi SPP</h4>
      <p class="pg-header-sub mb-0">{{ $unit->kode }} · {{ $unit->nama }} · sub unit {{ $unit->kode_induk }}.* · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-clock"></i><span><strong>{{ $menunggu }}</strong> SPP menunggu otorisasi</span></span>
    <span class="pg-header-chip"><i class="ti ti-file-text"></i><span><strong>{{ $jumlahSpp }}</strong> SPP T.A ini</span></span>
    <span class="pg-header-chip"><i class="ti ti-file-check"></i><span><strong>{{ $spm->count() }}</strong> SPM disetujui</span></span>
  </div>
</div>

@include('_partials._flash')

@if (!$jadwal['aktif'])
  <div class="alert alert-danger d-flex gap-2"><i class="ti ti-lock mt-1"></i>
    <div><strong>Pengajuan anggaran T.A {{ $tahun }}/{{ $tahun + 1 }} sudah ditutup.</strong></div></div>
@elseif ($jadwal['sisa_hari'] < 30)
  <div class="alert alert-info d-flex gap-2"><i class="ti ti-clock mt-1"></i>
    <div>Pengajuan anggaran T.A {{ $tahun }}/{{ $tahun + 1 }} akan ditutup <strong>{{ $jadwal['sisa_hari'] }} hari lagi</strong>.</div></div>
@endif

{{-- Daftar SPP --}}
<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <h5 class="mb-0">Daftar SPP <small class="text-muted fw-normal">(Surat Permintaan Pembayaran)</small></h5>
      <small class="text-muted">SPP yang dikirim sub unit; SPP yang ditolak tidak ditampilkan</small>
    </div>
    <ul class="nav nav-pills" role="tablist">
      <li class="nav-item"><a href="{{ route('unit.otorisasi.index') }}" class="nav-link py-1 {{ $semua ? '' : 'active' }}">Menunggu
        <span class="badge rounded-pill {{ $semua ? 'bg-label-warning' : 'bg-white text-primary' }} ms-1">{{ $menunggu }}</span></a></li>
      <li class="nav-item"><a href="{{ route('unit.otorisasi.index', ['status' => 'semua']) }}" class="nav-link py-1 {{ $semua ? 'active' : '' }}">Semua</a></li>
    </ul>
  </div>
  <div class="table-responsive">
    <table class="table spp-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>Tgl. Penyajian</th><th>No. SPP</th><th>Sub Unit</th><th class="text-end">Nominal</th>
          <th>Otorisasi Pimpinan</th><th>Status SPP</th><th style="width: 130px;"></th></tr>
      </thead>
      <tbody>
        @forelse ($spp as $s)
          @php
            [$kelasOto, $teksOto] = $badgeOtorisasi[$s->otorisasi] ?? ['bg-label-secondary', $s->otorisasi];
          @endphp
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td class="text-nowrap">{{ $tglId($s->tgl_saji) }}</td>
            <td class="nomor">{{ $s->no_spp }}</td>
            <td style="white-space: normal; min-width: 160px;"><span class="font-monospace small text-muted">{{ $s->kode_unit }}</span> {{ $s->nama_unit }}</td>
            <td class="text-end rp">{{ $rp($s->nominal) }}<div class="small text-muted">{{ $s->kegiatan }} kegiatan</div></td>
            <td><span class="badge {{ $kelasOto }}">{{ $teksOto }}</span></td>
            <td>
              <span class="badge {{ $badgeVerifikasi[$s->status_spp] ?? 'bg-label-secondary' }}">{{ $s->status_spp }}</span>
              @if ($s->status_spp === 'Ditolak' && $s->alasan)
                <div class="small text-danger mt-1"><i class="ti ti-message-exclamation ti-xs"></i> {{ $s->alasan }}</div>
              @endif
            </td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-lipat" title="Lihat kegiatan"
                      data-bs-toggle="collapse" data-bs-target="#keg-{{ $s->id_spp }}" aria-expanded="false"><i class="ti ti-chevron-down"></i></button>
              @if ($s->otorisasi === 'Belum')
                <a href="{{ route('unit.otorisasi.show', $s->id_spp) }}" class="btn btn-sm btn-primary"><i class="ti ti-signature ti-xs me-1"></i>Otorisasi</a>
              @else
                <a href="{{ route('unit.otorisasi.show', $s->id_spp) }}" class="btn btn-sm btn-label-secondary">Tampilkan</a>
              @endif
            </td>
          </tr>
          <tr class="detail collapse" id="keg-{{ $s->id_spp }}">
            <td></td>
            <td colspan="7" class="py-2">
              <ul class="list-unstyled mb-0 small">
                @foreach ($s->baris as $b)
                  <li class="d-flex justify-content-between gap-3 py-1 {{ $loop->last ? '' : 'border-bottom' }}">
                    <span><span class="kode-mak">{{ $b->kode_mak }}</span> · {{ $b->namamak4 }} — <span class="fw-semibold">{{ $b->uraiankeg }}</span></span>
                    <span class="rp">{{ $rp($b->ajuan) }}</span>
                  </li>
                @endforeach
              </ul>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-5">
            {{ $semua ? 'Tidak ada data Surat Permintaan Pembayaran (SPP).' : 'Tidak ada SPP yang menunggu otorisasi.' }}
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Daftar SPM --}}
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Daftar SPM <small class="text-muted fw-normal">(Surat Perintah Membayar)</small></h5>
    <small class="text-muted">SPP sub unit yang sudah disetujui menjadi SPM</small>
  </div>
  <div class="table-responsive">
    <table class="table spp-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>Tgl. Penyajian</th><th>No. SPM</th><th>No. SPP</th><th>Sub Unit</th><th class="text-end">Nominal</th><th>Status SPM</th></tr>
      </thead>
      <tbody>
        @forelse ($spm as $s)
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td class="text-nowrap">{{ $tglId($s->tgl_saji) }}</td>
            <td class="nomor">{{ $s->no_spm ?: '—' }}</td>
            <td class="nomor">{{ $s->no_spp }}</td>
            <td style="white-space: normal;"><span class="font-monospace small text-muted">{{ $s->kode_unit }}</span> {{ $s->nama_unit }}</td>
            <td class="text-end rp">{{ $rp($s->nominal) }}</td>
            <td><span class="badge bg-label-success">{{ $s->status_spm }}</span></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada SPM pada T.A ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
