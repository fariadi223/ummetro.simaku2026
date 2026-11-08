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
  .tile.sorot { border-left: 3px solid var(--bs-warning); }
  .antrian td { vertical-align: top; }
  .antrian .nomor { font-family: var(--bs-font-monospace); font-size: .78rem; white-space: nowrap; }
  .antrian .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  // [kunci, label, ikon, kelas ikon, keterangan, sorot]; tile pertama = antrian otorisator yang login.
  $saya = $peran === 'wr2' ? 'wr2' : 'bak';
  $namaSaya = $peran === 'wr2' ? 'WR II' : 'BAK';
  $tile = $peran === 'wr2'
    ? [
        ['wr2', 'Menunggu persetujuan Anda', 'ti-signature', 'bg-label-warning', 'Sudah disetujui BAK, menunggu WR II', true],
        ['bak', 'Menunggu BAK', 'ti-hourglass', 'bg-label-info', 'Belum disetujui BAK', false],
      ]
    : [
        ['bak', 'Menunggu persetujuan Anda', 'ti-signature', 'bg-label-warning', 'SPM menunggu persetujuan BAK', true],
        ['wr2', 'Menunggu WR II', 'ti-hourglass', 'bg-label-info', 'Sudah disetujui BAK, menunggu WR II', false],
      ];
  $tile[] = ['ditolak', 'Ditolak', 'ti-file-x', 'bg-label-danger', 'Ditolak BAK / WR II, menunggu dibatalkan', false];
  $tile[] = ['disetujui', 'SPM disetujui', 'ti-file-check', 'bg-label-success', 'Disetujui BAK & WR II, siap dibayar', false];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="unit-avatar"><i class="ti ti-signature"></i></div>
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
    @if ($ringkas[$saya]['n'] > 0)
      <span class="pg-header-chip"><i class="ti ti-bell"></i><span><strong>{{ $ringkas[$saya]['n'] }}</strong> SPM menunggu persetujuan Anda</span></span>
    @endif
  </div>
</div>

{{-- Ringkasan persetujuan SPM --}}
<div class="row g-4 mb-4">
  @foreach ($tile as [$kunci, $label, $ikon, $kelas, $ket, $sorot])
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100 tile {{ $sorot && $ringkas[$kunci]['n'] > 0 ? 'sorot' : '' }}">
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

{{-- SPM menunggu persetujuan otorisator yang login (terlama dulu) --}}
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-start gap-2">
    <div>
      <h5 class="mb-0">Antrian Persetujuan SPM</h5>
      <small class="text-muted">Menunggu persetujuan {{ $namaSaya }} · terlama lebih dulu · {{ $ringkas[$saya]['n'] }} SPM</small>
    </div>
    @if ($bisaBuka)
      <a href="{{ route('otorisator.spm.index') }}" class="btn btn-sm btn-label-primary">Lihat semua</a>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table antrian mb-0">
      <thead><tr><th>No. SPM</th><th>No. SPP</th><th>Unit</th><th class="text-end">Nominal</th><th class="text-end">Menunggu</th></tr></thead>
      <tbody>
        @forelse ($antrian as $m)
          @php
            $hari = $m->tgl_terbit ? (int) $m->tgl_terbit->diffInDays(today()) : null;
          @endphp
          <tr>
            <td class="nomor">@if ($bisaBuka)<a href="{{ route('otorisator.spm.show', $m->id_spp) }}">{{ $m->no_spm }}</a>@else{{ $m->no_spm }}@endif<div class="small text-muted font-sans-serif">Terbit {{ $tglId($m->tgl_terbit) }}</div></td>
            <td class="nomor">{{ $m->no_spp }}</td>
            <td style="white-space: normal; min-width: 170px;"><span class="font-monospace small text-muted">{{ $m->kode_unit }}</span> {{ $m->nama_unit }}
              <div class="small text-muted">{{ $m->kegiatan }} kegiatan</div></td>
            <td class="text-end rp">{{ $rp($m->nominal) }}</td>
            <td class="text-end text-nowrap">
              @if ($hari !== null)
                <span class="{{ $hari > 3 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $hari }} hari</span>
                @if ($hari > 3)<i class="ti ti-alert-triangle ti-xs text-danger" title="Lebih dari 3 hari" aria-label="Lebih dari 3 hari"></i>@endif
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-5"><i class="ti ti-circle-check ti-lg d-block mb-2"></i>Tidak ada SPM yang menunggu persetujuan Anda.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($ringkas[$saya]['n'] > $antrian->count())
    <div class="card-footer small text-muted">Menampilkan {{ $antrian->count() }} dari {{ $ringkas[$saya]['n'] }} SPM.</div>
  @endif
</div>
@endsection
