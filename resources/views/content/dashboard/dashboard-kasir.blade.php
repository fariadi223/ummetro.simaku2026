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
  .progres-bayar { height: 8px; }
  .antrian td { vertical-align: top; }
  .antrian .nomor { font-family: var(--bs-font-monospace); font-size: .78rem; white-space: nowrap; }
  .antrian .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $totalN = $ringkas['belum']['n'] + $ringkas['dibayar']['n'];
  $totalRp = $ringkas['belum']['nominal'] + $ringkas['dibayar']['nominal'];
  $persen = $totalRp > 0 ? $ringkas['dibayar']['nominal'] / $totalRp * 100 : 0;
  // [kunci, label, ikon, kelas ikon, keterangan, sorot]
  $tile = [
    ['belum', 'Siap dibayar', 'ti-cash-banknote', 'bg-label-warning', 'SPM disetujui, belum dibayarkan', true],
    ['dibayar', 'Sudah dibayar', 'ti-circle-check', 'bg-label-success', 'Total pembayaran T.A ini', false],
    ['tunai', 'Tunai', 'ti-cash', 'bg-label-info', 'Dibayar tunai', false],
    ['transfer', 'Transfer', 'ti-building-bank', 'bg-label-primary', 'Dibayar melalui transfer bank', false],
  ];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="unit-avatar"><i class="ti ti-wallet"></i></div>
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
    @if ($ringkas['belum']['n'] > 0)
      <span class="pg-header-chip"><i class="ti ti-bell"></i><span><strong>{{ $ringkas['belum']['n'] }}</strong> SPM menunggu pembayaran</span></span>
    @endif
  </div>
</div>

{{-- Ringkasan pembayaran SPM --}}
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

{{-- Realisasi pembayaran terhadap seluruh SPM disetujui --}}
<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
      <div>
        <h6 class="mb-0">Realisasi Pembayaran SPM</h6>
        <small class="text-muted">{{ $ringkas['dibayar']['n'] }} dari {{ $totalN }} SPM disetujui sudah dibayar</small>
      </div>
      <div class="text-end">
        <span class="fw-semibold">{{ number_format($persen, 1, ',', '.') }}%</span>
        <small class="text-muted d-block">{{ $rp($ringkas['dibayar']['nominal']) }} / {{ $rp($totalRp) }}</small>
      </div>
    </div>
    <div class="progress progres-bayar" role="progressbar" aria-label="Realisasi pembayaran" aria-valuenow="{{ round($persen) }}" aria-valuemin="0" aria-valuemax="100">
      <div class="progress-bar bg-success" style="width: {{ $persen }}%"></div>
    </div>
    <div class="small text-muted mt-2">
      <i class="ti ti-calendar ti-xs"></i> Bulan ini: <strong>{{ $ringkas['bulan_ini']['n'] }}</strong> SPM · {{ $rp($ringkas['bulan_ini']['nominal']) }}
    </div>
  </div>
</div>

<div class="row g-4">
  {{-- SPM disetujui yang belum dibayar (terlama dulu) --}}
  <div class="col-xl-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-start gap-2">
        <div>
          <h5 class="mb-0">Antrian Pembayaran SPM</h5>
          <small class="text-muted">Disetujui WR II, belum dibayar · terlama lebih dulu · {{ $ringkas['belum']['n'] }} SPM</small>
        </div>
        <a href="{{ route('kasir.spm.index', ['status' => 'belum']) }}" class="btn btn-sm btn-label-primary">Lihat semua</a>
      </div>
      <div class="table-responsive">
        <table class="table antrian mb-0">
          <thead><tr><th>No. SPM</th><th>Unit</th><th class="text-end">Nominal</th><th class="text-end">Menunggu</th></tr></thead>
          <tbody>
            @forelse ($antrian as $m)
              @php
                $hari = $m->tgl_setuju ? (int) \Illuminate\Support\Carbon::parse($m->tgl_setuju)->startOfDay()->diffInDays(today()) : null;
              @endphp
              <tr>
                <td class="nomor"><a href="{{ route('kasir.spm.index', ['cari' => $m->no_spm]) }}">{{ $m->no_spm }}</a><div class="small text-muted font-sans-serif">Disetujui {{ $tglId($m->tgl_setuju) }}</div></td>
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
              <tr><td colspan="4" class="text-center text-muted py-5"><i class="ti ti-circle-check ti-lg d-block mb-2"></i>Semua SPM disetujui sudah dibayar.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($ringkas['belum']['n'] > $antrian->count())
        <div class="card-footer small text-muted">Menampilkan {{ $antrian->count() }} dari {{ $ringkas['belum']['n'] }} SPM.</div>
      @endif
    </div>
  </div>

  {{-- Pembayaran terakhir --}}
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Pembayaran Terakhir</h5>
        <small class="text-muted">SPM yang terakhir dibayarkan kasir</small>
      </div>
      <ul class="list-group list-group-flush">
        @forelse ($terakhir as $m)
          <li class="list-group-item">
            <div class="d-flex justify-content-between gap-2">
              <div style="min-width: 0;">
                <div class="font-monospace small text-truncate">{{ $m->no_spm }}</div>
                <div class="small text-muted text-truncate">{{ $m->nama_unit }}</div>
              </div>
              <div class="text-end text-nowrap">
                <div class="fw-semibold" style="font-variant-numeric: tabular-nums;">{{ $rp($m->nominal) }}</div>
                <div class="small text-muted">{{ $tglId($m->bayar->tgl_kasir) }}</div>
              </div>
            </div>
            <div class="mt-1">
              @if ($m->metode === 'transfer')
                <span class="badge bg-label-primary"><i class="ti ti-building-bank ti-xs me-1"></i>Transfer</span>
                <span class="small text-muted">{{ trim($m->bayar->namabank . ' ' . $m->bayar->norekbank) }}</span>
              @else
                <span class="badge bg-label-info"><i class="ti ti-cash ti-xs me-1"></i>Tunai</span>
              @endif
            </div>
          </li>
        @empty
          <li class="list-group-item text-center text-muted py-5">Belum ada pembayaran pada T.A ini.</li>
        @endforelse
      </ul>
    </div>
  </div>
</div>
@endsection
