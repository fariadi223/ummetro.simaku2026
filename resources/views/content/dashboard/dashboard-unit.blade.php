@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('page-style')
<style>
  .unit-avatar { width: 64px; height: 64px; border-radius: 16px; display: grid; place-items: center; flex-shrink: 0;
                 font-size: 1.4rem; font-weight: 700; background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .3); }
  .pg-header .sapa { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: rgba(255, 255, 255, .75); }
  .pg-header .ta-besar { font-size: 1.6rem; font-weight: 800; line-height: 1; color: #fff; }

  .profil-tab .nav-link { display: flex; align-items: center; gap: .4rem; }
  .profil-isi { font-size: .95rem; line-height: 1.7; }
  .profil-isi ol { padding-left: 0; margin: 0; list-style: none; counter-reset: poin; }
  .profil-isi ol li { position: relative; padding: .55rem .75rem .55rem 3rem; border-radius: .5rem; counter-increment: poin; }
  .profil-isi ol li:nth-child(odd) { background: rgba(var(--bs-primary-rgb), .04); }
  .profil-isi ol li::before { content: counter(poin); position: absolute; left: .75rem; top: .55rem; width: 1.6rem; height: 1.6rem;
                              border-radius: 50%; display: grid; place-items: center; font-size: .8rem; font-weight: 700;
                              background: rgba(var(--bs-primary-rgb), .14); color: var(--bs-primary); }
  .profil-visi { position: relative; padding: 1.25rem 1.5rem 1.25rem 3.25rem; border-radius: .75rem; font-size: 1.1rem; font-style: italic;
                 background: rgba(var(--bs-primary-rgb), .06); border-left: 4px solid var(--bs-primary); }
  .profil-visi::before { content: "\201C"; position: absolute; left: .9rem; top: .1rem; font-size: 3rem; line-height: 1; color: var(--bs-primary); opacity: .5; }

  .info-ta dt { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary); font-weight: 500; }
  .info-ta dd { margin-bottom: .9rem; }
  .info-ta .progress { height: 6px; }
  .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .sub-tabel td { vertical-align: middle; }
</style>
@endsection

@php
  // Profil v1 berupa teks polos; baris "1. ..." ditampilkan sebagai daftar bernomor.
  $poin = function (?string $teks): array {
    $baris = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $teks)), 'strlen'));
    $bernomor = array_filter($baris, fn ($b) => preg_match('/^\d+\s*[.)]/', $b));
    if (count($baris) > 1 && count($bernomor) >= count($baris) / 2) {
      return ['daftar', array_map(fn ($b) => preg_replace('/^\d+\s*[.)]\s*/', '', $b), $baris)];
    }
    return ['teks', implode("\n", $baris)];
  };
  $rp = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
  $tgl = fn ($c) => $c ? $c->locale('id')->translatedFormat('d F Y') : '—';
  $inisial = collect(preg_split('/\s+/', preg_replace('/[^A-Za-z\s]/', ' ', (string) $unit->nama)))->filter()
    ->reject(fn ($k) => in_array(strtolower($k), ['kepala', 'upt', 'prodi', 'dan', 'pusat'], true))
    ->take(2)->map(fn ($k) => strtoupper($k[0]))->implode('') ?: 'U';
  $totalPagu = $pagu->sum('nominal');
  $totalPakai = $pagu->sum('pakai');
  $persenPakai = $totalPagu > 0 ? $totalPakai / $totalPagu * 100 : 0;
  $bisaInput = ($jadwal['komite_buka'] ?? false) || $klinik;
@endphp

@section('content')
{{-- Profil singkat + T.A --}}
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="unit-avatar">{{ $inisial }}</div>
    <div class="flex-grow-1" style="min-width: 0;">
      <div class="sapa">Selamat datang di SIMAKU</div>
      <h4 class="mb-1">{{ $unit->nama }}</h4>
      <div class="pg-header-sub">
        <span class="font-monospace">{{ $unit->kode }}</span>
        @if ($unit->nama_pimp) · Pimpinan: {{ $unit->nama_pimp }} @endif
      </div>
    </div>
    <div class="text-end">
      <div class="sapa">Tahun Anggaran</div>
      <div class="ta-besar">{{ $tahun }}/{{ $tahun + 1 }}</div>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip">
      <i class="ti {{ $bisaInput ? 'ti-lock-open' : 'ti-lock' }}"></i>
      <span>Input anggaran <strong>{{ $bisaInput ? 'dibuka' : 'ditutup' }}</strong></span>
    </span>
    @if ($subUnit)
      <span class="pg-header-chip"><i class="ti ti-sitemap"></i><span><strong>{{ $subUnit->count() }}</strong> sub unit</span></span>
    @endif
    <span class="pg-header-chip"><i class="ti ti-list-check"></i><span><strong>{{ $subUnit ? $subUnit->sum('kegiatan') : $jumlahKegiatan }}</strong> kegiatan{{ $subUnit ? ' sub unit' : '' }}</span></span>
    @if ($totalPagu)
      <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Pagu <strong>{{ $rp($totalPagu) }}</strong></span></span>
    @endif
  </div>
</div>

<div class="row g-4">
  @if ($subUnit)
  {{-- Unit induk: daftar sub unit (tanpa visi & misi) --}}
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Sub Unit</h5>
        <small class="text-muted">{{ $subUnit->count() }} sub unit · T.A {{ $tahun }}/{{ $tahun + 1 }} · terpakai = nominal yang sudah di-SPP-kan terhadap pagu</small>
      </div>
      <div class="table-responsive">
        <table class="table sub-tabel mb-0">
          <thead>
            <tr><th style="width: 70px;">Kode</th><th>Sub Unit</th><th class="text-end">Kegiatan</th><th class="text-end">Pagu</th><th class="text-end">Belanja</th><th style="width: 150px;">Terpakai (SPP)</th></tr>
          </thead>
          <tbody>
            @forelse ($subUnit as $s)
              @php
                $persen = $s->pagu > 0 ? $s->spp / $s->pagu * 100 : null;
                $warna = $persen === null ? 'secondary' : ($persen > 100 ? 'danger' : ($persen >= 90 ? 'warning' : 'success'));
              @endphp
              <tr>
                <td class="font-monospace small">{{ $s->kode }}</td>
                <td style="white-space: normal; min-width: 200px;">
                  <div class="fw-semibold">{{ $s->nama }}</div>
                  @if ($s->nama_pimp)<small class="text-muted">{{ $s->nama_pimp }}</small>@endif
                </td>
                <td class="text-end">{{ $s->kegiatan }}</td>
                <td class="text-end rp">{{ $s->pagu ? $rp($s->pagu) : '—' }}</td>
                <td class="text-end rp">{{ $rp($s->belanja) }}</td>
                <td>
                  <div class="rp small fw-semibold">{{ $rp($s->spp) }}</div>
                  @if ($persen !== null)
                    <div class="progress my-1" style="height: 6px;"><div class="progress-bar bg-{{ $warna }}" style="width: {{ min($persen, 100) }}%"></div></div>
                    <small class="{{ $persen > 100 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ number_format($persen, 1, ',', '.') }}% dari pagu</small>
                  @else
                    <small class="text-muted">Belum ada pagu</small>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-5">Belum ada sub unit.</td></tr>
            @endforelse
          </tbody>
          @if ($subUnit->count() > 1)
            <tfoot>
              <tr class="fw-semibold">
                <td colspan="2" class="text-end">Total</td>
                <td class="text-end">{{ $subUnit->sum('kegiatan') }}</td>
                <td class="text-end rp">{{ $rp($subUnit->sum('pagu')) }}</td>
                <td class="text-end rp">{{ $rp($subUnit->sum('belanja')) }}</td>
                <td class="rp small">{{ $rp($subUnit->sum('spp')) }}</td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>
  </div>
  @else
  {{-- Visi, misi, tujuan --}}
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header pb-0">
        <h5 class="mb-3">Profil Unit</h5>
        <ul class="nav nav-tabs profil-tab" role="tablist">
          @foreach ($profil as $kunci => [$judul, $ikon, $isi])
            <li class="nav-item">
              <button type="button" class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab"
                      data-bs-target="#profil-{{ $kunci }}" role="tab">
                <i class="ti {{ $ikon }} ti-xs"></i>{{ $judul }}
              </button>
            </li>
          @endforeach
        </ul>
      </div>
      <div class="card-body pt-4">
        <div class="tab-content p-0">
          @foreach ($profil as $kunci => [$judul, $ikon, $isi])
            @php
              [$bentuk, $nilai] = $poin($isi);
            @endphp
            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="profil-{{ $kunci }}" role="tabpanel">
              <div class="profil-isi">
                @if (blank($isi))
                  <div class="text-center text-muted py-5">
                    <i class="ti ti-file-off ti-lg d-block mb-2"></i>
                    {{ $judul }} belum diisi.
                  </div>
                @elseif ($kunci === 'visi' && $bentuk === 'teks')
                  <div class="profil-visi">{{ $nilai }}</div>
                @elseif ($bentuk === 'daftar')
                  <ol>
                    @foreach ($nilai as $item)
                      <li>{{ $item }}</li>
                    @endforeach
                  </ol>
                @else
                  <p class="mb-0" style="white-space: pre-line;">{{ $nilai }}</p>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  @endif

  {{-- Informasi tahun anggaran & pagu --}}
  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">T.A {{ $tahun }}/{{ $tahun + 1 }}</h5>
        @if ($jadwal)
          <span class="badge {{ $jadwal['aktif'] ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $jadwal['aktif'] ? 'Tahun berjalan' : 'Bukan tahun berjalan' }}</span>
        @endif
      </div>
      <div class="card-body">
        @if (!$jadwal)
          <p class="text-muted mb-0">Informasi tahun anggaran {{ $tahun }} belum diatur.</p>
        @else
          <dl class="info-ta mb-0">
            <dt>Masa Anggaran</dt>
            <dd>
              {{ $tgl($jadwal['awal_angg']) }} – {{ $tgl($jadwal['akhir_angg']) }}
              @if ($jadwal['berjalan'] !== null)
                <div class="progress mt-2"><div class="progress-bar" style="width: {{ $jadwal['berjalan'] }}%"></div></div>
                <small class="text-muted">{{ number_format($jadwal['berjalan'], 0) }}% masa anggaran berjalan</small>
              @endif
            </dd>

            <dt>Jadwal Komite (input anggaran)</dt>
            <dd>
              {{ $tgl($jadwal['awal_komite']) }} – {{ $tgl($jadwal['akhir_komite']) }}
              <div class="mt-1">
                @if ($jadwal['komite_buka'])
                  <span class="badge bg-label-success">Dibuka</span>
                  <small class="text-muted">{{ $jadwal['sisa_hari_komite'] == 0 ? 'berakhir hari ini' : 'sisa ' . $jadwal['sisa_hari_komite'] . ' hari' }}</small>
                @else
                  <span class="badge bg-label-secondary">Ditutup</span>
                @endif
              </div>
            </dd>

            <dt>Klinik Anggaran</dt>
            <dd>
              @if ($klinik)
                <span class="badge bg-label-info">Aktif</span>
                <small class="text-muted d-block">Unit tetap dapat menginput anggaran di luar jadwal komite.</small>
              @else
                <span class="badge bg-label-secondary">Tidak aktif</span>
              @endif
            </dd>

            <dt>Penjurnalan</dt>
            <dd class="mb-0">
              <span class="badge {{ $jadwal['jurnal'] ? 'bg-label-info' : 'bg-label-secondary' }}">{{ $jadwal['jurnal'] ? 'Dibuka' : 'Ditutup' }}</span>
            </dd>
          </dl>
        @endif
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Pagu Unit</h5>
      </div>
      <div class="card-body">
        @forelse ($pagu as $p)
          @php
            $persen = $p->nominal > 0 ? $p->pakai / $p->nominal * 100 : 0;
            $warna = $persen > 100 ? 'danger' : ($persen >= 90 ? 'warning' : 'success');
          @endphp
          <div class="{{ $loop->last ? '' : 'mb-4' }}">
            <div class="d-flex justify-content-between align-items-baseline mb-1">
              <span class="fw-semibold">{{ $p->pagu->sumber ?? $p->idpagu }}</span>
              <span class="rp fw-semibold">{{ $rp($p->nominal) }}</span>
            </div>
            <div class="progress mb-1" style="height: 8px;"><div class="progress-bar bg-{{ $warna }}" style="width: {{ min($persen, 100) }}%"></div></div>
            <div class="d-flex justify-content-between small text-muted">
              <span>Direncanakan <span class="rp">{{ $rp($p->pakai) }}</span></span>
              <span class="{{ $persen > 100 ? 'text-danger fw-semibold' : '' }}">{{ number_format($persen, 1, ',', '.') }}%</span>
            </div>
            <div class="small mt-1">
              Sisa: <span class="rp fw-semibold {{ $p->nominal - $p->pakai < 0 ? 'text-danger' : '' }}">{{ $rp($p->nominal - $p->pakai) }}</span>
            </div>
          </div>
        @empty
          <p class="text-muted mb-0">Unit belum mendapat pagu untuk T.A {{ $tahun }}.</p>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
