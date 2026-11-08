@extends('layouts/layoutMaster')

@section('title', 'Rekap Berbasis Fungsi')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endsection

@section('page-style')
<style>
  .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .fungsi-tabel td { vertical-align: top; }
  .fungsi-tabel td.keg { white-space: normal; min-width: 260px; }
  .fungsi-tabel tr.grup td { background: rgba(var(--bs-primary-rgb), .06); border-top: 2px solid rgba(var(--bs-primary-rgb), .25); }
  .fungsi-tabel tr.subtotal td { font-weight: 600; background: rgba(var(--bs-secondary-rgb), .05); }
  .fungsi-tabel tr.detail td { background: rgba(var(--bs-secondary-rgb), .03); border-top: 0; }
  .fungsi-tabel .kode-mak { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary); }
  .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: .75rem 1.5rem; font-size: .85rem; }
  .detail-grid .label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary); }
  .detail-grid .isi { white-space: pre-line; }
  .legenda { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .85rem; color: var(--bs-body-color); }
  .legenda i { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: .35rem; vertical-align: -1px; }
  .legenda i.garis { height: 3px; width: 16px; border-radius: 2px; vertical-align: 3px; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  // $grafik dari controller (algoritma grafik_bebasis_fungsi v1); nama bidang dirapikan.
  $grafik['kategori'] = array_map(fn ($n) => Helper::judulMak($n), $grafik['kategori']);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-sitemap"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Rekap Berbasis Fungsi</h4>
      <p class="pg-header-sub mb-0">
        @if ($unitAktif)
          {{ $unitAktif->kode }} · {{ $unitAktif->nama }}
        @else
          {{ $unit->kode }} · {{ $unit->nama }}{{ $jumlahUnit ? ' · semua unit ' . $unit->kode_induk . '.*' : '' }}
        @endif
        · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    <a href="{{ route('unit.keluaran.fungsi.cetak', $unitAktif ? ['unit' => $unitAktif->kode] : []) }}" target="_blank" rel="noopener" class="btn btn-light"><i class="ti ti-printer me-1"></i> Print</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    @if ($jumlahUnit && !$unitAktif)
      <span class="pg-header-chip"><i class="ti ti-sitemap"></i><span><strong>{{ $jumlahUnit }}</strong> unit</span></span>
    @endif
    <span class="pg-header-chip"><i class="ti ti-list-check"></i><span><strong>{{ $total['kegiatan'] }}</strong> kegiatan</span></span>
    <span class="pg-header-chip"><i class="ti ti-arrow-down-left"></i><span>Pendapatan <strong>{{ $rp($total['pendapatan']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-arrow-up-right"></i><span>Belanja <strong>{{ $rp($total['belanja']) }}</strong></span></span>
  </div>
</div>

{{-- Grafik (padanan "Tampilan Grafik" v1) --}}
<div class="card mb-4">
  @if ($daftarUnit)
    {{-- Unit induk: tab per unit; memfilter grafik, tabel & cetak --}}
    <div class="card-header border-bottom pb-3">
      @include('content.unit.keluaran._tab-unit', ['rute' => 'unit.keluaran.fungsi'])
    </div>
  @endif
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <h5 class="mb-0">Pendapatan, Belanja &amp; Selisih per Bidang</h5>
      <small class="text-muted">Proses bisnis (MAK level 1) · selisih = belanja − pendapatan</small>
    </div>
    <div class="legenda" aria-hidden="true">
      <span><i style="background: var(--viz-1)"></i>Pendapatan</span>
      <span><i style="background: var(--viz-2)"></i>Belanja</span>
      <span><i class="garis" style="background: var(--viz-3)"></i>Selisih</span>
    </div>
  </div>
  <div class="card-body pt-0"><div id="grafik-fungsi" role="img" aria-label="Grafik batang pendapatan dan belanja serta garis selisih per bidang; rincian angka ada di tabel di bawah"></div></div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table fungsi-tabel mb-0">
      <thead>
        <tr>
          <th style="width: 40px;">No</th>
          <th>Jenis</th>
          <th>Kegiatan</th>
          <th>Jadwal</th>
          <th class="text-end">Pendapatan</th>
          <th class="text-end">Belanja</th>
          <th>Dana</th>
          <th style="width: 40px;"></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($grup as $g)
          @if ($g->kegiatan->isNotEmpty())
            <tr class="grup"><td colspan="8">Proses Bisnis {{ $g->kode }} : <strong>{{ Helper::judulMak($g->nama) }}</strong></td></tr>
            @foreach ($g->kegiatan as $k)
              <tr>
                <td class="text-muted">{{ $loop->iteration }}</td>
                <td>{{ $k->namamak2 }}</td>
                <td class="keg">@if ($jumlahUnit && !$unitAktif)<span class="badge bg-label-secondary mb-1" title="{{ $k->nama_unit }}">{{ $k->kode_unit }} · {{ $k->nama_unit }}</span>@endif<div class="kode-mak">{{ $k->kode_mak }} · {{ $k->namamak4 }}</div><div class="fw-semibold">{{ $k->uraiankeg }}</div></td>
                <td style="white-space: normal; min-width: 110px;">{{ $k->jadwal }}</td>
                <td class="text-end rp">{{ $rp($k->pendapatan) }}</td>
                <td class="text-end rp">{{ $rp($k->belanja) }}</td>
                <td>{{ $k->dana ?: '—' }}</td>
                <td>
                  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" data-bs-toggle="collapse"
                          data-bs-target="#det-{{ $k->idkeg }}" aria-expanded="false" title="Latar belakang, tujuan, output">
                    <i class="ti ti-chevron-down"></i>
                  </button>
                </td>
              </tr>
              <tr class="detail collapse" id="det-{{ $k->idkeg }}">
                <td></td>
                <td colspan="7">
                  <div class="detail-grid py-1">
                    <div><div class="label">Latar Belakang</div><div class="isi">{{ $k->latblk }}</div></div>
                    <div><div class="label">Tujuan</div><div class="isi">{{ $k->tujuan }}</div></div>
                    <div><div class="label">Output</div><div class="isi">{{ $k->lingkup }}</div></div>
                  </div>
                </td>
              </tr>
            @endforeach
            <tr class="subtotal"><td colspan="4" class="text-end">Sub Total</td><td class="text-end rp">{{ $rp($g->pendapatan) }}</td><td class="text-end rp">{{ $rp($g->belanja) }}</td><td colspan="2"></td></tr>
          @endif
        @endforeach
        @if ($total['kegiatan'] === 0)
          <tr><td colspan="8" class="text-center text-muted py-5">Belum ada kegiatan pada T.A {{ $tahun }}.</td></tr>
        @endif
      </tbody>
      @if ($total['kegiatan'] > 0)
        <tfoot>
          <tr class="fw-bold"><td colspan="4" class="text-end">Total</td><td class="text-end rp">{{ $rp($total['pendapatan']) }}</td><td class="text-end rp">{{ $rp($total['belanja']) }}</td><td colspan="2"></td></tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const data = @json($grafik);
  const gelap = document.documentElement.classList.contains('dark-style');
  // Palet tervalidasi (dataviz validator): terang #2a78d6/#eb6834 di #fff; gelap #3987e5/#d95926 di #2f3349.
  // Palet tervalidasi (dataviz validator, 3 slot): terang di #fff, gelap di #2f3349.
  // Aqua (Selisih) di mode terang < 3:1 -> wajib label nilai pada garis (relief), seperti v1.
  const warna = gelap ? ['#3987e5', '#d95926', '#199e70'] : ['#2a78d6', '#eb6834', '#1baf7a'];
  warna.forEach((w, i) => document.documentElement.style.setProperty('--viz-' + (i + 1), w));
  const css = getComputedStyle(document.documentElement);
  const teks = css.getPropertyValue('--bs-secondary-color').trim() || (gelap ? '#b6bee3' : '#6f6b7d');
  const tinta = css.getPropertyValue('--bs-body-color').trim() || (gelap ? '#cfd3ec' : '#5d596c');
  const garis = css.getPropertyValue('--bs-border-color').trim() || (gelap ? '#434968' : '#dbdade');
  const kartu = gelap ? '#2f3349' : '#ffffff';
  const singkat = n => {
    const a = Math.abs(n), t = n < 0 ? '−' : '';
    return a >= 1e9 ? t + (a / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M'
      : a >= 1e6 ? t + (a / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt'
      : a >= 1e3 ? t + (a / 1e3).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb' : t + String(a);
  };

  new ApexCharts(document.querySelector('#grafik-fungsi'), {
    chart: { type: 'line', height: 340, toolbar: { show: false }, fontFamily: 'inherit', parentHeightOffset: 0 },
    series: [
      { name: 'Pendapatan', type: 'column', data: data.pendapatan },
      { name: 'Belanja', type: 'column', data: data.belanja },
      { name: 'Selisih', type: 'line', data: data.selisih },
    ],
    colors: warna,
    plotOptions: { bar: { columnWidth: '42%', borderRadius: 4, borderRadiusApplication: 'end' } },
    // Celah 2px warna kartu antar batang; garis selisih 2px.
    stroke: { width: [2, 2, 2], colors: [kartu, kartu, warna[2]], curve: 'smooth' },
    markers: { size: [0, 0, 5], strokeColors: kartu, strokeWidth: 2, hover: { size: 7 } },
    // Label hanya pada garis Selisih (seperti v1; juga relief kontras aqua).
    dataLabels: {
      enabled: true, enabledOnSeries: [2], formatter: v => singkat(v), offsetY: -10,
      background: { enabled: false },
      style: { fontSize: '11px', fontWeight: 600, colors: [tinta] }
    },
    legend: { show: false },
    grid: { borderColor: garis, strokeDashArray: 4, xaxis: { lines: { show: false } } },
    xaxis: { categories: data.kategori, labels: { style: { colors: teks }, trim: true, maxHeight: 64 }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { style: { colors: teks }, formatter: singkat } },
    tooltip: { shared: true, intersect: false, theme: gelap ? 'dark' : 'light',
      y: { formatter: v => (v < 0 ? '−' : '') + 'Rp ' + Math.abs(Math.round(v)).toLocaleString('id-ID') } },
    states: { hover: { filter: { type: 'darken', value: 0.9 } } }
  }).render();
});
</script>
@endsection
