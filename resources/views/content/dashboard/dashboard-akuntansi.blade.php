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
  .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .antrian td { vertical-align: top; }
  .antrian .nomor { font-family: var(--bs-font-monospace); font-size: .78rem; white-space: nowrap; }
  .saldo .nilai { font-size: 1.25rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--bs-heading-color); }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $bulanId = fn ($ym) => \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $ym . '-01')->locale('id')->translatedFormat('F Y');
  $lama = fn ($t) => $t ? (int) \Illuminate\Support\Carbon::parse($t)->startOfDay()->diffInDays(today()) : null;
  $seimbang = (float) $jurnal->debet === (float) $jurnal->kredit;
  $antrianTotal = $ringkas['spm']['n'] + $ringkas['bkk']['n'] + $ringkas['bkm']['n'] + $ringkas['lpj']['n'];
  // [kunci, label, ikon, kelas ikon, keterangan]
  $tile = [
    ['spm', 'SPM belum dijurnal', 'ti-file-dollar', 'bg-label-warning', 'Pencairan anggaran belanja'],
    ['bkk', 'BKK belum dijurnal', 'ti-receipt-2', 'bg-label-danger', 'BKK non-SPM yang sudah tunai'],
    ['bkm', 'BKM belum dijurnal', 'ti-receipt', 'bg-label-info', 'Bukti kas masuk yang sudah tunai'],
    ['lpj', 'LPJ menunggu', 'ti-clipboard-check', 'bg-label-primary', 'Realisasi belanja online belum diverifikasi'],
  ];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="unit-avatar"><i class="ti ti-calculator"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <div class="sapa">Selamat datang di SIMAKU</div>
      <h4 class="mb-1">Halaman Akuntansi</h4>
      <div class="pg-header-sub">{{ $user->roleLabel() }} · {{ $user->name }}</div>
    </div>
    <div class="text-end">
      <div class="sapa">Tahun Anggaran</div>
      <div class="ta-besar">{{ $tahun }}/{{ $tahun + 1 }}</div>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building-bank"></i><span>Sistem Informasi Manajemen dan Akuntansi Keuangan</span></span>
    <span class="pg-header-chip"><i class="ti {{ $penjurnalan ? 'ti-lock-open' : 'ti-lock' }}"></i><span>Penjurnalan <strong>{{ $penjurnalan ? 'dibuka' : 'ditutup' }}</strong></span></span>
    @if ($antrianTotal > 0)
      <span class="pg-header-chip"><i class="ti ti-bell"></i><span><strong>{{ number_format($antrianTotal, 0, ',', '.') }}</strong> dokumen menunggu dijurnal / diverifikasi</span></span>
    @endif
  </div>
</div>

{{-- Antrian penjurnalan --}}
<div class="row g-4 mb-4">
  @foreach ($tile as [$kunci, $label, $ikon, $kelas, $ket])
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100 tile {{ $ringkas[$kunci]['n'] > 0 ? 'sorot' : '' }}">
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

<div class="row g-4 mb-4">
  {{-- Ringkasan jurnal T.A --}}
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-start gap-2">
        <div>
          <h5 class="mb-0">Jurnal T.A {{ $tahun }}/{{ $tahun + 1 }}</h5>
          <small class="text-muted">{{ number_format($jurnal->n, 0, ',', '.') }} baris jurnal · terakhir {{ $tglId($jurnal->terakhir) }}</small>
        </div>
        @if ($jurnal->n > 0)
          <span class="badge {{ $seimbang ? 'bg-label-success' : 'bg-label-danger' }}">
            <i class="ti {{ $seimbang ? 'ti-scale' : 'ti-alert-triangle' }} ti-xs me-1"></i>{{ $seimbang ? 'Seimbang' : 'Tidak seimbang' }}</span>
        @endif
      </div>
      <div class="card-body pb-2">
        <div class="row g-3 mb-1">
          <div class="col-sm-4"><div class="small text-muted">Total debet</div><div class="fw-semibold rp">{{ $rp($jurnal->debet) }}</div></div>
          <div class="col-sm-4"><div class="small text-muted">Total kredit</div><div class="fw-semibold rp">{{ $rp($jurnal->kredit) }}</div></div>
          <div class="col-sm-4"><div class="small text-muted">Selisih</div>
            <div class="fw-semibold rp {{ $seimbang ? '' : 'text-danger' }}">{{ $rp(abs($jurnal->debet - $jurnal->kredit)) }}</div></div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Bulan</th><th class="text-end">Debet</th><th class="text-end">Kredit</th></tr></thead>
          <tbody>
            @forelse ($perBulan as $b)
              <tr>
                <td>{{ $b->bln ? $bulanId($b->bln) : 'Tanpa tanggal' }}</td>
                <td class="text-end rp">{{ $rp($b->debet) }}</td>
                <td class="text-end rp">{{ $rp($b->kredit) }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted py-4">Belum ada jurnal pada T.A ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Saldo kas --}}
  <div class="col-lg-5">
    <div class="card h-100 saldo">
      <div class="card-header">
        <h5 class="mb-0">Saldo Kas</h5>
        <small class="text-muted">Per hari ini · saldo awal T.A + mutasi jurnal (sama dengan Laporan Posisi Keuangan)</small>
      </div>
      <div class="card-body d-flex flex-column gap-3">
        @foreach ([['univ', 'Kas Universitas', 'ti-building-bank', 'bg-label-primary'], ['unit', 'Kas di Unit Kerja', 'ti-building', 'bg-label-info'], ['bank', 'Bank & Deposito', 'ti-credit-card', 'bg-label-warning']] as [$k, $label, $ikon, $kelas])
          <div class="d-flex align-items-center gap-3">
            <span class="tile"><span class="ikon {{ $kelas }}" aria-hidden="true"><i class="ti {{ $ikon }}"></i></span></span>
            <div>
              <div class="small text-muted">{{ $label }}</div>
              <div class="nilai {{ $kas[$k] < 0 ? 'text-danger' : '' }}">{{ $kas[$k] < 0 ? '−' : '' }}{{ $rp(abs($kas[$k])) }}</div>
            </div>
          </div>
        @endforeach
        <div class="d-flex align-items-center gap-3 pt-3 border-top">
          <span class="tile"><span class="ikon bg-label-success" aria-hidden="true"><i class="ti ti-wallet"></i></span></span>
          <div class="flex-grow-1">
            <div class="small text-muted">Total Kas dan Setara Kas</div>
            <div class="nilai {{ $kas['total'] < 0 ? 'text-danger' : '' }}">{{ $kas['total'] < 0 ? '−' : '' }}{{ $rp(abs($kas['total'])) }}</div>
            @php $naik = $kas['total'] - $kas['awal']; @endphp
            <div class="small {{ $naik < 0 ? 'text-danger' : 'text-success' }}">{{ $naik < 0 ? '▼' : '▲' }} {{ $rp(abs($naik)) }} sejak awal T.A</div>
          </div>
        </div>
        <div class="small text-muted mt-auto">
          Rincian per rekening di <a href="{{ route('akuntansi.posisi-keuangan.index') }}">Posisi Keuangan</a> ·
          pergerakan di <a href="{{ route('akuntansi.arus-kas.index') }}">Arus Kas</a>.
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  {{-- SPM belum dijurnal --}}
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">SPM Belum Dijurnal</h5>
        <small class="text-muted">Pencairan anggaran belanja · terlama lebih dulu · {{ $ringkas['spm']['n'] }} SPM</small>
      </div>
      <div class="table-responsive">
        <table class="table antrian mb-0">
          <thead><tr><th>No. SPM</th><th>Unit</th><th class="text-end">Nominal</th><th class="text-end">Menunggu</th></tr></thead>
          <tbody>
            @forelse ($antrianSpm as $m)
              @php $hari = $lama($m->tgl); @endphp
              <tr>
                <td class="nomor">{{ $m->no_spm }}<div class="small text-muted font-sans-serif">{{ $tglId($m->tgl) }}</div></td>
                <td style="white-space: normal; min-width: 150px;"><span class="font-monospace small text-muted">{{ $m->kode_unit }}</span> {{ $m->nama_unit }}
                  <div class="small text-muted">{{ $m->kegiatan }} kegiatan</div></td>
                <td class="text-end rp">{{ $rp($m->nominal) }}</td>
                <td class="text-end text-nowrap">
                  @if ($hari !== null)<span class="{{ $hari > 7 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $hari }} hari</span>@else<span class="text-muted">—</span>@endif
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-5"><i class="ti ti-circle-check ti-lg d-block mb-2"></i>Semua SPM sudah dijurnal.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($ringkas['spm']['n'] > $antrianSpm->count())
        <div class="card-footer small text-muted">Menampilkan {{ $antrianSpm->count() }} dari {{ $ringkas['spm']['n'] }} SPM.</div>
      @endif
    </div>
  </div>

  {{-- BKK non-SPM belum dijurnal --}}
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">BKK Belum Dijurnal</h5>
        <small class="text-muted">Non-SPM, sudah tunai · terlama lebih dulu · {{ number_format($ringkas['bkk']['n'], 0, ',', '.') }} BKK</small>
      </div>
      <div class="table-responsive">
        <table class="table antrian mb-0">
          <thead><tr><th>No. BKK</th><th>Untuk Pembayaran</th><th class="text-end">Nominal</th><th class="text-end">Menunggu</th></tr></thead>
          <tbody>
            @forelse ($antrianBkk as $b)
              @php $hari = $lama($b->tgl_tunai); @endphp
              <tr>
                <td class="nomor">{{ $b->no_bkk }}<div class="small text-muted font-sans-serif">Tunai {{ $tglId($b->tgl_tunai) }}</div></td>
                <td style="white-space: normal; min-width: 170px;">{{ \Illuminate\Support\Str::limit($b->uraian, 80) }}
                  <div class="small text-muted"><i class="ti ti-user ti-xs"></i> {{ $b->penerima }}</div></td>
                <td class="text-end rp">{{ $rp($b->nominal) }}</td>
                <td class="text-end text-nowrap">
                  @if ($hari !== null)<span class="{{ $hari > 7 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $hari }} hari</span>@else<span class="text-muted">—</span>@endif
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted py-5"><i class="ti ti-circle-check ti-lg d-block mb-2"></i>Semua BKK sudah dijurnal.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($ringkas['bkk']['n'] > $antrianBkk->count())
        <div class="card-footer small text-muted">Menampilkan {{ $antrianBkk->count() }} dari {{ number_format($ringkas['bkk']['n'], 0, ',', '.') }} BKK.</div>
      @endif
    </div>
  </div>
</div>
@endsection
