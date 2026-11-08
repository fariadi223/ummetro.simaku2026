@extends('layouts/layoutMaster')

@section('title', 'Rekap Berbasis Sifat')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/apex-charts/apex-charts.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
@endsection

@section('page-style')
<style>
  .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .sifat-tabel tr.grup td { background: rgba(var(--bs-primary-rgb), .06); border-top: 2px solid rgba(var(--bs-primary-rgb), .25); font-weight: 700; }
  .sifat-tabel tr.total td { font-weight: 600; background: rgba(var(--bs-secondary-rgb), .05); }
  .sifat-tabel tr.surplus td { font-weight: 700; background: rgba(var(--bs-warning-rgb), .1); }
  .sifat-tabel .kode { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $totPendapatan = $kelompok['pendapatan'][1]->sum('jumlah');
  $totBelanja = $kelompok['belanja'][1]->sum('jumlah');
  $selisih = $totPendapatan - $totBelanja;
  $grafik = [
    'kategori' => $komposisi->map(fn ($k) => $k->kode . ' ' . $k->nama)->values(),
    // Persen dari controller: blj5.x / total belanja × 100 (algoritma grafik_bebasis_sifat v1).
    'persen' => $komposisi->map(fn ($k) => round($k->persen, 1))->values(),
    'jumlah' => $komposisi->map(fn ($k) => round($k->jumlah))->values(),
  ];
  // Belanja per jenis biaya (rek level 3), sudah urut terbesar dari controller.
  // Lebih dari 12 jenis: sisanya dilipat ke "Lainnya" agar grafik tetap terbaca.
  $batasJenis = 12;
  $jenisTampil = $jenisBiaya->take($batasJenis)->values();
  $jenisSisa = $jenisBiaya->slice($batasJenis);
  if ($jenisSisa->isNotEmpty()) {
    $jenisTampil->push((object) [
      'kode' => '',
      'nama' => 'Lainnya (' . $jenisSisa->count() . ' jenis)',
      'jumlah' => $jenisSisa->sum('jumlah'),
      'persen' => $jenisSisa->sum('persen'),
    ]);
  }
  $grafikJenis = [
    'kategori' => $jenisTampil->map(fn ($j) => trim($j->kode . ' ' . $j->nama))->values(),
    'jumlah' => $jenisTampil->map(fn ($j) => round($j->jumlah))->values(),
    'persen' => $jenisTampil->map(fn ($j) => round($j->persen, 1))->values(),
  ];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-category"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Rekap Berbasis Sifat</h4>
      <p class="pg-header-sub mb-0">
        @if ($unitAktif)
          {{ $unitAktif->kode }} · {{ $unitAktif->nama }}
        @else
          {{ $unit->kode }} · {{ $unit->nama }}{{ $jumlahUnit ? ' · semua unit ' . $unit->kode_induk . '.*' : '' }}
        @endif
        · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    <a href="{{ route('unit.keluaran.sifat.cetak', $unitAktif ? ['unit' => $unitAktif->kode] : []) }}" target="_blank" rel="noopener" class="btn btn-light"><i class="ti ti-printer me-1"></i> Print</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    @if ($jumlahUnit && !$unitAktif)
      <span class="pg-header-chip"><i class="ti ti-sitemap"></i><span><strong>{{ $jumlahUnit }}</strong> unit</span></span>
    @endif
    <span class="pg-header-chip"><i class="ti ti-arrow-down-left"></i><span>Pendapatan <strong>{{ $rp($totPendapatan) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-arrow-up-right"></i><span>Belanja <strong>{{ $rp($totBelanja) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-scale"></i><span>{{ $selisih >= 0 ? 'Surplus' : 'Defisit' }} <strong>{{ $rp(abs($selisih)) }}</strong></span></span>
  </div>
</div>

@if ($daftarUnit)
  {{-- Unit induk: tab per unit; memfilter tabel, grafik & cetak --}}
  <div class="card mb-4"><div class="card-body py-3">
    @include('content.unit.keluaran._tab-unit', ['rute' => 'unit.keluaran.sifat'])
  </div></div>
@endif

<div class="row g-4">
  <div class="col-xl-8">
    <div class="card">
      <div class="table-responsive">
        <table class="table sifat-tabel mb-0">
          <thead>
            <tr><th style="width: 40px;">No</th><th style="width: 160px;">Kode Rekening</th><th>Nama Rekening</th><th class="text-end">Item</th><th class="text-end">Jumlah</th></tr>
          </thead>
          <tbody>
            @foreach ($kelompok as $kunci => [$judul, $daftar])
              {{-- Pembiayaan hanya tampil bila ada datanya (seperti v1) --}}
              @if ($daftar->isNotEmpty() || in_array($kunci, ['pendapatan', 'belanja'], true))
                <tr class="grup"><td colspan="5">{{ mb_strtoupper($judul) }}</td></tr>
                @forelse ($daftar as $r)
                  <tr>
                    <td class="text-muted">{{ $loop->iteration }}</td>
                    <td class="kode">{{ $r->kode }}</td>
                    <td style="white-space: normal;">{{ $r->namarek6 }}</td>
                    <td class="text-end text-muted">{{ $r->item }}</td>
                    <td class="text-end rp">{{ $rp($r->jumlah) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted">—</td></tr>
                @endforelse
                <tr class="total"><td colspan="4" class="text-end">Total {{ $judul }}</td><td class="text-end rp">{{ $rp($daftar->sum('jumlah')) }}</td></tr>
              @endif
              @if ($kunci === 'belanja')
                <tr class="surplus"><td colspan="4" class="text-end">Surplus / Defisit</td><td class="text-end rp">{{ $selisih < 0 ? '−' : '' }}{{ $rp(abs($selisih)) }}</td></tr>
              @endif
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Komposisi belanja (padanan grafik_bebasis_sifat v1) --}}
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Komposisi Belanja</h5>
        <small class="text-muted">Persentase terhadap total belanja {{ $rp($totalBelanja) }}</small>
      </div>
      <div class="card-body pt-0">
        <div id="grafik-sifat" role="img" aria-label="Grafik persentase belanja 5.1 sampai 5.3 terhadap total belanja; angka ada di daftar di bawah"></div>
        <ul class="list-unstyled small mb-0 mt-2">
          @foreach ($komposisi as $k)
            <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
              <span>{{ $k->kode }} {{ $k->nama }}</span>
              <span class="rp text-end">{{ $rp($k->jumlah) }} <span class="text-muted">· {{ number_format($k->persen, 1, ',', '.') }}%</span></span>
            </li>
          @endforeach
        </ul>
        @if ($belanjaLain['jumlah'] > 0)
          <div class="small text-muted mt-2">
            <i class="ti ti-info-circle"></i> Belanja rekening 5.4 ke atas {{ $rp($belanjaLain['jumlah']) }}
            ({{ number_format($belanjaLain['persen'], 1, ',', '.') }}%) tidak termasuk grafik, seperti versi lama.
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Tambahan v2: belanja per jenis biaya (rekening level 3) --}}
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
          <h5 class="mb-0">Belanja per Jenis Biaya</h5>
          <small class="text-muted">Rekening level 3, urut dari yang terbesar · persentase terhadap total belanja</small>
        </div>
        @if ($jenisBiaya->isNotEmpty())
          <button type="button" class="btn btn-sm btn-label-secondary" data-bs-toggle="collapse" data-bs-target="#tabel-jenis" aria-expanded="false" aria-controls="tabel-jenis">
            <i class="ti ti-table me-1"></i> Tabel
          </button>
        @endif
      </div>
      <div class="card-body pt-0">
        @if ($jenisBiaya->isEmpty())
          <div class="text-center text-muted py-4">Belum ada belanja pada T.A ini.</div>
        @else
          <div id="grafik-jenis" role="img" aria-label="Grafik batang belanja per jenis biaya, urut terbesar; angka lengkap ada di tombol Tabel"></div>
          <div class="collapse" id="tabel-jenis">
            <div class="table-responsive mt-2">
              <table class="table table-sm mb-0">
                <thead><tr><th style="width: 110px;">Kode</th><th>Jenis Biaya</th><th class="text-end">Jumlah</th><th class="text-end">%</th></tr></thead>
                <tbody>
                  @foreach ($jenisBiaya as $j)
                    <tr>
                      <td class="kode" style="font-family: var(--bs-font-monospace); font-size: .8rem;">{{ $j->kode }}</td>
                      <td>{{ $j->nama }}</td>
                      <td class="text-end rp">{{ $rp($j->jumlah) }}</td>
                      <td class="text-end rp text-muted">{{ number_format($j->persen, 1, ',', '.') }}%</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const data = @json($grafik);
  const gelap = document.documentElement.classList.contains('dark-style');
  const warna = gelap ? '#3987e5' : '#2a78d6';   // slot 1 palet tervalidasi
  const css = getComputedStyle(document.documentElement);
  const teks = css.getPropertyValue('--bs-secondary-color').trim() || (gelap ? '#b6bee3' : '#6f6b7d');
  const garis = css.getPropertyValue('--bs-border-color').trim() || (gelap ? '#434968' : '#dbdade');
  const tinta = css.getPropertyValue('--bs-body-color').trim() || (gelap ? '#cfd3ec' : '#5d596c');

  new ApexCharts(document.querySelector('#grafik-sifat'), {
    chart: { type: 'bar', height: 70 + data.kategori.length * 52, toolbar: { show: false }, fontFamily: 'inherit', parentHeightOffset: 0 },
    series: [{ name: 'Persentase belanja', data: data.persen }],
    colors: [warna],
    plotOptions: { bar: { horizontal: true, barHeight: '45%', borderRadius: 4, borderRadiusApplication: 'end', dataLabels: { position: 'top' } } },
    dataLabels: { enabled: true, offsetX: 26, style: { colors: [tinta], fontWeight: 500 }, formatter: v => v.toLocaleString('id-ID') + '%' },
    grid: { borderColor: garis, strokeDashArray: 4, yaxis: { lines: { show: false } } },
    xaxis: { categories: data.kategori, max: 100, labels: { style: { colors: teks }, formatter: v => v + '%' }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { style: { colors: teks }, maxWidth: 170 } },
    tooltip: { theme: gelap ? 'dark' : 'light',
      y: { formatter: (v, o) => v.toLocaleString('id-ID') + '% · Rp ' + Math.round(data.jumlah[o.dataPointIndex]).toLocaleString('id-ID'), title: { formatter: () => '' } } },
    legend: { show: false }
  }).render();

  // Belanja per jenis biaya: satu seri, warna sama (slot 1), label nilai di ujung batang.
  const jenis = @json($grafikJenis);
  const elJenis = document.querySelector('#grafik-jenis');
  if (elJenis && jenis.kategori.length) {
    const singkat = n => {
      const a = Math.abs(n);
      return a >= 1e9 ? (a / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M'
        : a >= 1e6 ? (a / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt'
        : a >= 1e3 ? (a / 1e3).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' rb' : String(a);
    };
    new ApexCharts(elJenis, {
      chart: { type: 'bar', height: 60 + jenis.kategori.length * 36, toolbar: { show: false }, fontFamily: 'inherit', parentHeightOffset: 0 },
      series: [{ name: 'Belanja', data: jenis.jumlah }],
      colors: [warna],
      plotOptions: { bar: { horizontal: true, barHeight: '55%', borderRadius: 4, borderRadiusApplication: 'end', dataLabels: { position: 'top' } } },
      dataLabels: { enabled: true, offsetX: 34, style: { colors: [tinta], fontWeight: 500 },
        formatter: (v, o) => jenis.persen[o.dataPointIndex].toLocaleString('id-ID') + '%' },
      grid: { borderColor: garis, strokeDashArray: 4, yaxis: { lines: { show: false } }, padding: { right: 40 } },
      xaxis: { categories: jenis.kategori, labels: { style: { colors: teks }, formatter: v => singkat(Number(v)) }, axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { labels: { style: { colors: teks }, maxWidth: 280 } },
      tooltip: { theme: gelap ? 'dark' : 'light',
        y: { formatter: (v, o) => 'Rp ' + Math.round(v).toLocaleString('id-ID') + ' · ' + jenis.persen[o.dataPointIndex].toLocaleString('id-ID') + '%', title: { formatter: () => '' } } },
      states: { hover: { filter: { type: 'darken', value: 0.9 } } },
      legend: { show: false }
    }).render();
  }
});
</script>
@endsection
