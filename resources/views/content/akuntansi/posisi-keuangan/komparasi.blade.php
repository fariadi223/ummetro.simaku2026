@extends('layouts/layoutMaster')

@section('title', 'Laporan Posisi Keuangan — Komparasi')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-style')
<style>
  .pk-tabel { font-size: .82rem; margin: 0; }
  .pk-tabel td, .pk-tabel th { vertical-align: middle; }
  #kartuPk .pk-tabel > :not(caption) > * > * { background-color: var(--bs-card-bg, #fff); }
  .pk-tabel thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .02em; }
  .pk-tabel .kode { font-family: var(--bs-font-monospace); font-size: .76rem; white-space: nowrap; width: 150px; }
  .pk-tabel td.n, .pk-tabel th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 170px; }
  .pk-tabel td.naik { color: var(--bs-success); }
  .pk-tabel td.turun { color: var(--bs-danger); }
  .pk-tabel tr.lv3 td { font-weight: 600; cursor: pointer; }
  .pk-tabel tr.lv3 .lipat { display: inline-block; transition: transform .15s; margin-right: .2rem; vertical-align: -1px; }
  .pk-tabel tr.lv3.tertutup .lipat { transform: rotate(-90deg); }
  .pk-tabel tr.lv3.tanpa-anak { cursor: default; }
  .pk-tabel tr.lv6 td { color: var(--bs-secondary-color); font-size: .77rem; }
  .pk-tabel tr.lv6 .nama { padding-left: 2.2rem; }
  .pk-tabel tr.lv6 a { color: inherit; }
  .pk-tabel tr.lv6 a:hover { color: var(--bs-primary); }
  .pk-tabel tr.total td { font-weight: 700; border-top: 2px solid var(--bs-border-color); box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .05); }
  .pk-tabel tr.total-besar td { font-weight: 800; font-size: .9rem; box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .1); }
  .pk-tabel .ket-surplus { font-size: .72rem; color: var(--bs-secondary-color); font-weight: 400; display: block; }
  .pk-judul { font-size: .78rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--bs-heading-color);
    padding: .75rem 1.25rem; border-bottom: 1px solid var(--bs-border-color); background: rgba(var(--bs-secondary-rgb), .06); }
  .filter-pk .f-tgl { width: 220px; }
  @media (max-width: 575.98px) { .filter-pk .f-tgl { width: 100%; } }
</style>
@endsection

@php
  $rp = fn ($n) => abs($n) < 0.5 ? '0' : ($n < 0 ? '(' . number_format(abs($n), 0, ',', '.') . ')' : number_format($n, 0, ',', '.'));
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $tglPendek = fn ($t) => \Illuminate\Support\Carbon::parse($t)->format('d-m-Y');
  $kewNeto = $bagian['2']['total'] + $bagian['3']['total'];
  $kewNetoLalu = $bagian['2']['total_awal'] + $bagian['3']['total_awal'];
  $ubah = fn ($a, $b) => $a - $b;
  $kelasUbah = fn ($d) => abs($d) < 0.5 ? '' : ($d > 0 ? 'naik' : 'turun');
  $kolomIni = $tahun . '/' . ($tahun + 1);
  $kolomLalu = ($tahun - 1) . '/' . $tahun;
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-arrows-left-right"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Laporan Posisi Keuangan — Komparasi</h4>
      <p class="pg-header-sub mb-0">Per {{ $tglId($akhir) }} (T.A {{ $kolomIni }}) dibanding {{ $tglId($tglLalu) }} (T.A {{ $kolomLalu }})</p>
    </div>
    <a href="{{ route('akuntansi.posisi-keuangan.index', ['awal' => $pAwal, 'akhir' => $akhir]) }}" class="btn btn-info"><i class="ti ti-layout-list me-1"></i>Tunggal</a>
    <div class="btn-group">
      <a href="{{ route('akuntansi.posisi-keuangan.komparasi', ['akhir' => $akhir, 'cetak' => 1]) }}" target="_blank" rel="noopener" class="btn btn-warning">
        <i class="ti ti-printer me-1"></i>Cetak</a>
      <button type="button" class="btn btn-warning dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="visually-hidden">Pilihan cetak</span></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ route('akuntansi.posisi-keuangan.komparasi', ['akhir' => $akhir, 'cetak' => 1]) }}" target="_blank" rel="noopener">
          <i class="ti ti-list ti-xs me-2"></i>Ringkas (akun level 3)</a></li>
        <li><a class="dropdown-item" href="{{ route('akuntansi.posisi-keuangan.komparasi', ['akhir' => $akhir, 'cetak' => 1, 'rinci' => 1]) }}" target="_blank" rel="noopener">
          <i class="ti ti-list-details ti-xs me-2"></i>Rinci (dengan akun level 6)</a></li>
      </ul>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Aset {{ $kolomIni }} <strong>{{ $rp($bagian['1']['total']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-history"></i><span>Aset {{ $kolomLalu }} <strong>{{ $rp($bagian['1']['total_awal']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-trending-up"></i><span>Perubahan <strong>{{ $rp($bagian['1']['total'] - $bagian['1']['total_awal']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti {{ $seimbang ? 'ti-circle-check' : 'ti-alert-triangle' }}"></i><span><strong>{{ $seimbang ? 'Seimbang' : 'Tidak seimbang' }}</strong></span></span>
  </div>
</div>

<div class="card" id="kartuPk">
  <div class="card-header border-bottom">
    <form method="GET" action="{{ route('akuntansi.posisi-keuangan.komparasi') }}" class="d-flex flex-wrap align-items-center gap-2 filter-pk" id="formPk">
      <div class="input-group input-group-merge f-tgl">
        <span class="input-group-text">Posisi per</span>
        <input type="text" name="akhir" id="f-akhir" value="{{ $akhir }}" class="form-control" readonly>
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-filter ti-xs me-1"></i>Tampilkan</button>
      <div class="d-flex flex-wrap gap-2" id="preset">
        <button type="button" class="btn btn-label-primary" data-preset="ta"><i class="ti ti-calendar ti-xs me-1"></i>Akhir T.A</button>
        <button type="button" class="btn btn-label-primary" data-preset="bulan-lalu"><i class="ti ti-calendar-minus ti-xs me-1"></i>Akhir bulan lalu</button>
        <button type="button" class="btn btn-label-primary" data-preset="hari-ini"><i class="ti ti-calendar-time ti-xs me-1"></i>Hari ini</button>
      </div>
      <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-sm btn-label-secondary" id="bukaSemua"><i class="ti ti-fold-down ti-xs me-1"></i>Buka semua</button>
        <button type="button" class="btn btn-sm btn-label-secondary" id="lipatSemua"><i class="ti ti-fold ti-xs me-1"></i>Lipat semua</button>
      </div>
    </form>
    <small class="text-muted d-block mt-2">
      Kolom T.A {{ $kolomIni }} = saldo awal T.A + mutasi s/d {{ $tglId($akhir) }} (aset neto termasuk surplus/defisit berjalan
      {{ $rp($surplus['tp'] + $surplus['dp']) }}). Kolom T.A {{ $kolomLalu }} = saldo awal T.A {{ $kolomIni }}, yaitu posisi {{ $tglId($tglLalu) }}.
      Angka dalam kurung = negatif.
    </small>
  </div>

  @foreach ($bagian as $k1 => $bg)
    <div class="pk-judul">{{ $bg['nama'] }}</div>
    <div class="table-responsive">
      <table class="table table-sm pk-tabel">
        <thead><tr><th class="kode">Kode Akun</th><th>Nama Akun</th>
          <th class="n">T.A {{ $kolomIni }}<br><span class="fw-normal text-muted">{{ $tglPendek($akhir) }}</span></th>
          <th class="n">T.A {{ $kolomLalu }}<br><span class="fw-normal text-muted">{{ $tglPendek($tglLalu) }}</span></th>
          <th class="n">Kenaikan / (Penurunan)</th></tr></thead>
        <tbody>
          @forelse ($bg['baris'] as $b)
            @php $d = $ubah($b->saldo, $b->saldo_awal); @endphp
            @if ($b->lv === 3)
              @php $punyaAnak = $b->id && $bg['baris']->contains(fn ($x) => $x->lv === 6 && $x->induk === $b->id); @endphp
              <tr class="lv3 tertutup {{ $punyaAnak ? '' : 'tanpa-anak' }}" @if ($b->id) data-grup="{{ $b->id }}" @endif>
                <td class="kode">@if ($punyaAnak)<i class="ti ti-chevron-down ti-xs lipat"></i>@endif{{ $b->kode }}</td>
                <td class="nama">{{ $b->nama }}
                  @if (abs($b->surplus) >= 0.5)<span class="ket-surplus">termasuk surplus/defisit berjalan {{ $rp($b->surplus) }}</span>@endif
                </td>
            @else
              <tr class="lv6 d-none" data-induk="{{ $b->induk }}">
                <td class="kode">{{ $b->kode }}</td>
                <td class="nama"><a href="{{ route('akuntansi.buku-besar.index', ['rek6' => $b->id]) }}" title="Buka buku besar">{{ $b->nama }}</a></td>
            @endif
                <td class="n">{{ $rp($b->saldo) }}</td>
                <td class="n">{{ $rp($b->saldo_awal) }}</td>
                <td class="n {{ $kelasUbah($d) }}">{{ $rp($d) }}</td>
              </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-3">Tidak ada saldo.</td></tr>
          @endforelse
          <tr class="total"><td></td><td class="text-end">TOTAL {{ strtoupper($bg['nama']) }}</td>
            <td class="n">{{ $rp($bg['total']) }}</td><td class="n">{{ $rp($bg['total_awal']) }}</td>
            <td class="n">{{ $rp($bg['total'] - $bg['total_awal']) }}</td></tr>
        </tbody>
      </table>
    </div>
  @endforeach

  <div class="table-responsive border-top">
    <table class="table table-sm pk-tabel mb-0">
      <tbody>
        <tr class="total-besar">
          <td class="kode"></td><td class="text-end">TOTAL LIABILITAS + ASET NETO</td>
          <td class="n">{{ $rp($kewNeto) }}</td><td class="n">{{ $rp($kewNetoLalu) }}</td><td class="n">{{ $rp($kewNeto - $kewNetoLalu) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const periode = { awal: @json($pAwal), akhir: @json($pAkhir) };
  const form = document.getElementById('formPk');
  const kartu = document.getElementById('kartuPk');
  const localeId = {
    firstDayOfWeek: 1,
    weekdays: { shorthand: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'], longhand: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] },
    months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
      longhand: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] }
  };
  const fp = flatpickr('#f-akhir', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', monthSelectorType: 'static', locale: localeId,
    minDate: periode.awal, maxDate: periode.akhir });
  const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const jepit = t => t < periode.awal ? periode.awal : (t > periode.akhir ? periode.akhir : t);
  const h = new Date();
  const nilai = k => jepit({ 'ta': periode.akhir, 'bulan-lalu': ymd(new Date(h.getFullYear(), h.getMonth(), 0)), 'hari-ini': ymd(h) }[k]);
  const kini = document.getElementById('f-akhir').value;
  document.querySelectorAll('#preset [data-preset]').forEach(b => {
    const aktif = nilai(b.dataset.preset) === kini;
    b.classList.toggle('btn-primary', aktif); b.classList.toggle('btn-label-primary', !aktif);
    b.addEventListener('click', () => { fp.setDate(nilai(b.dataset.preset), false); form.submit(); });
  });

  const lipat = (tr, tutup) => {
    tr.classList.toggle('tertutup', tutup);
    kartu.querySelectorAll('tr.lv6[data-induk="' + tr.dataset.grup + '"]').forEach(r => r.classList.toggle('d-none', tutup));
  };
  const grup = () => kartu.querySelectorAll('tr.lv3[data-grup]:not(.tanpa-anak)');
  grup().forEach(tr => tr.addEventListener('click', () => lipat(tr, !tr.classList.contains('tertutup'))));
  document.getElementById('bukaSemua').addEventListener('click', () => grup().forEach(tr => lipat(tr, false)));
  document.getElementById('lipatSemua').addEventListener('click', () => grup().forEach(tr => lipat(tr, true)));
});
</script>
@endsection
