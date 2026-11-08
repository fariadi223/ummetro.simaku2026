@extends('layouts/layoutMaster')

@section('title', 'Penghasilan Komprehensif')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-style')
<style>
  .pk-tabel { font-size: .8rem; margin: 0; }
  .pk-tabel td, .pk-tabel th { vertical-align: middle; }
  #kartuPk .pk-tabel > :not(caption) > * > * { background-color: var(--bs-card-bg, #fff); }
  .pk-tabel thead th { font-size: .7rem; text-transform: uppercase; letter-spacing: .02em; text-align: center; }
  .pk-tabel thead th.grp { border-bottom: 1px solid var(--bs-border-color); }
  .pk-tabel .kode { font-family: var(--bs-font-monospace); font-size: .75rem; white-space: nowrap; width: 130px; }
  .pk-tabel td.n, .pk-tabel th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 125px; }
  .pk-tabel td.j { font-weight: 600; }
  .pk-tabel td.ka { text-align: right; }
  .pk-tabel td.kosong { text-align: center; color: var(--bs-secondary-color); padding: .8rem; }
  .pk-tabel tr.lv3 td { font-weight: 600; cursor: pointer; }
  .pk-tabel tr.lv3 .lipat { display: inline-block; transition: transform .15s; margin-right: .2rem; vertical-align: -1px; }
  .pk-tabel tr.lv3.tertutup .lipat { transform: rotate(-90deg); }
  .pk-tabel tr.lv6 td { color: var(--bs-secondary-color); font-size: .76rem; }
  .pk-tabel tr.lv6 .nama { padding-left: 2.2rem; }
  .pk-tabel tr.lv6 a { color: inherit; }
  .pk-tabel tr.lv6 a:hover { color: var(--bs-primary); }
  .pk-tabel tr.total td { font-weight: 700; border-top: 2px solid var(--bs-border-color); box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .05); }
  .pk-judul { font-size: .78rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--bs-heading-color);
    padding: .75rem 1.25rem; border-bottom: 1px solid var(--bs-border-color); background: rgba(var(--bs-secondary-rgb), .06); }
  .pk-judul.utama { background: rgba(var(--bs-primary-rgb), .1); color: var(--bs-primary); }
  .pk-hasil td { font-weight: 800; font-size: .86rem; }
  .pk-hasil tr.surplus td { box-shadow: inset 0 0 0 999px rgba(var(--bs-success-rgb), .1); }
  .pk-hasil tr.akhir td { box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .12); }
  .filter-pk .f-tgl { width: 200px; }
  @media (max-width: 575.98px) { .filter-pk .f-tgl { width: 100%; } }
</style>
@endsection

@php
  $rp = fn ($n) => abs($n) < 0.5 ? '0' : ($n < 0 ? '(' . number_format(abs($n), 0, ',', '.') . ')' : number_format($n, 0, ',', '.'));
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $kolom = ['tp', 'dp', 'jml', 'tp_lalu', 'dp_lalu', 'jml_lalu'];
  $namaSurplus = fn ($n) => $n < 0 ? 'DEFISIT' : 'SURPLUS';
  $q = ['awal' => $awal, 'akhir' => $akhir, 'cetak' => 1] + ($mode === 'tanggal' ? ['mode' => 'tanggal'] : []);
  $tanggalMode = $mode === 'tanggal';
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-chart-line"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Laporan Penghasilan Komprehensif @if ($tanggalMode)<span class="badge bg-label-light ms-1 align-middle">Report Tanggal</span>@endif</h4>
      <p class="pg-header-sub mb-0">Laporan Keuangan · T.A {{ $tahun }}/{{ $tahun + 1 }} · {{ $tglId($awal) }} s.d {{ $tglId($akhir) }}</p>
    </div>
    @if ($tanggalMode)
      <a href="{{ route('akuntansi.penghasilan-komprehensif.index', ['awal' => $awal, 'akhir' => $akhir]) }}" class="btn btn-info"><i class="ti ti-layout-list me-1"></i>Report Tahunan</a>
    @else
      <a href="{{ route('akuntansi.penghasilan-komprehensif.index', ['mode' => 'tanggal']) }}" class="btn btn-info"><i class="ti ti-calendar-stats me-1"></i>Report Tanggal</a>
    @endif
    <div class="btn-group">
      <a href="{{ route('akuntansi.penghasilan-komprehensif.index', $q) }}" target="_blank" rel="noopener" class="btn btn-warning"><i class="ti ti-printer me-1"></i>Cetak</a>
      <button type="button" class="btn btn-warning dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false"><span class="visually-hidden">Pilihan cetak</span></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ route('akuntansi.penghasilan-komprehensif.index', $q) }}" target="_blank" rel="noopener"><i class="ti ti-list ti-xs me-2"></i>Ringkas (akun level 3)</a></li>
        <li><a class="dropdown-item" href="{{ route('akuntansi.penghasilan-komprehensif.index', $q + ['rinci' => 1]) }}" target="_blank" rel="noopener"><i class="ti ti-list-details ti-xs me-2"></i>Rinci (dengan akun level 6)</a></li>
      </ul>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-arrow-down-right"></i><span>Penerimaan <strong>{{ $rp($bagian['pendapatan']['total']->jml) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-arrow-up-right"></i><span>Biaya <strong>{{ $rp($bagian['biaya']['total']->jml) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-scale"></i><span>{{ $surplus->jml < 0 ? 'Defisit' : 'Surplus' }} <strong>{{ $rp($surplus->jml) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-chart-line"></i><span>Penghasilan komprehensif <strong>{{ $rp($komprehensif->jml) }}</strong></span></span>
  </div>
</div>

<div class="card" id="kartuPk">
  <div class="card-header border-bottom">
    <form method="GET" action="{{ route('akuntansi.penghasilan-komprehensif.index') }}" class="d-flex flex-wrap align-items-center gap-2 filter-pk" id="formPk">
      @if ($tanggalMode)<input type="hidden" name="mode" value="tanggal">@endif
      <div class="input-group input-group-merge f-tgl">
        <span class="input-group-text">Awal</span>
        <input type="text" name="awal" id="f-awal" value="{{ $awal }}" class="form-control" readonly>
      </div>
      <div class="input-group input-group-merge f-tgl">
        <span class="input-group-text">Akhir</span>
        <input type="text" name="akhir" id="f-akhir" value="{{ $akhir }}" class="form-control" readonly>
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-filter ti-xs me-1"></i>Tampilkan</button>
      <div class="d-flex flex-wrap gap-2" role="group" aria-label="Pilihan cepat" id="preset">
        <button type="button" class="btn btn-label-primary" data-preset="ta"><i class="ti ti-calendar ti-xs me-1"></i>Seluruh T.A</button>
        <button type="button" class="btn btn-label-primary" data-preset="bulan"><i class="ti ti-calendar-event ti-xs me-1"></i>Bulan ini</button>
        <button type="button" class="btn btn-label-primary" data-preset="bulan-lalu"><i class="ti ti-calendar-minus ti-xs me-1"></i>Bulan lalu</button>
        <button type="button" class="btn btn-label-primary" data-preset="hari-ini"><i class="ti ti-calendar-time ti-xs me-1"></i>s/d hari ini</button>
      </div>
      <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-sm btn-label-secondary" id="bukaSemua"><i class="ti ti-fold-down ti-xs me-1"></i>Buka semua</button>
        <button type="button" class="btn btn-sm btn-label-secondary" id="lipatSemua"><i class="ti ti-fold ti-xs me-1"></i>Lipat semua</button>
      </div>
    </form>
    <small class="text-muted d-block mt-2">
      @if ($tanggalMode)
        <strong>Report Tanggal</strong> · kolom kiri = mutasi {{ $tglId($awal) }} s/d {{ $tglId($akhir) }};
        kolom kanan = @if ($sumberLalu === 'sebelum') akumulasi T.A berjalan sebelum periode ({{ $tglId($awalLalu) }} s/d {{ $tglId($akhirLalu) }}).
        @else seluruh T.A lalu (tanggal awal = awal T.A). @endif
        Jumlah kedua kolom = akumulasi s/d {{ $tglId($akhir) }}.
      @else
        T.A {{ $tahun }}/{{ $tahun + 1 }} = mutasi {{ $tglId($awal) }} s/d {{ $tglId($akhir) }} (tanpa jurnal tutup buku).
        T.A {{ $tahun - 1 }}/{{ $tahun }} = seluruh T.A lalu ({{ $tglId($awalLalu) }} s/d {{ $tglId($akhirLalu) }}),
        @if ($sumberLalu === 'snapshot') dari saldo tutup buku, @else dari jurnal (belum tutup buku), @endif tidak ikut filter.
      @endif
      Tanpa / dengan pembatasan menurut kelompok sumber dana. Angka dalam kurung = negatif.
    </small>
  </div>

  @foreach (['pendapatan', 'biaya'] as $k)
    <div class="pk-judul">{{ $bagian[$k]['judul'] }}</div>
    <div class="table-responsive">@include('content.akuntansi.penghasilan-komprehensif._tabel', ['bg' => $bagian[$k], 'layar' => true])</div>
  @endforeach
  <div class="table-responsive border-top">
    <table class="table table-sm pk-tabel pk-hasil mb-0"><tbody>
      <tr class="surplus"><td class="kode"></td><td class="ka">{{ $namaSurplus($surplus->jml) }}</td>@foreach ($kolom as $c)<td class="n">{{ $rp($surplus->$c) }}</td>@endforeach</tr>
    </tbody></table>
  </div>

  <div class="pk-judul utama">Penghasilan Komprehensif Lain</div>
  @foreach (['pendapatan_lain', 'biaya_lain'] as $k)
    <div class="pk-judul">{{ $bagian[$k]['judul'] }}</div>
    <div class="table-responsive">@include('content.akuntansi.penghasilan-komprehensif._tabel', ['bg' => $bagian[$k], 'layar' => true])</div>
  @endforeach
  <div class="table-responsive border-top">
    <table class="table table-sm pk-tabel pk-hasil mb-0"><tbody>
      <tr><td class="kode"></td><td class="ka">PENGHASILAN KOMPREHENSIF LAIN</td>@foreach ($kolom as $c)<td class="n">{{ $rp($lain->$c) }}</td>@endforeach</tr>
      <tr class="akhir"><td class="kode"></td><td class="ka">PENGHASILAN KOMPREHENSIF PERIODE BERJALAN</td>@foreach ($kolom as $c)<td class="n">{{ $rp($komprehensif->$c) }}</td>@endforeach</tr>
    </tbody></table>
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
  const opsi = { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', monthSelectorType: 'static', locale: localeId, minDate: periode.awal, maxDate: periode.akhir };
  const fpAwal = flatpickr('#f-awal', Object.assign({}, opsi, { maxDate: document.getElementById('f-akhir').value, onChange: (d, s) => fpAkhir.set('minDate', s || periode.awal) }));
  const fpAkhir = flatpickr('#f-akhir', Object.assign({}, opsi, { minDate: document.getElementById('f-awal').value, onChange: (d, s) => fpAwal.set('maxDate', s || periode.akhir) }));
  const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const jepit = t => t < periode.awal ? periode.awal : (t > periode.akhir ? periode.akhir : t);
  const h = new Date();
  const rentangPreset = k => {
    const p = {
      'ta': [periode.awal, periode.akhir],
      'bulan': [ymd(new Date(h.getFullYear(), h.getMonth(), 1)), ymd(new Date(h.getFullYear(), h.getMonth() + 1, 0))],
      'bulan-lalu': [ymd(new Date(h.getFullYear(), h.getMonth() - 1, 1)), ymd(new Date(h.getFullYear(), h.getMonth(), 0))],
      'hari-ini': [periode.awal, ymd(h)],
    }[k];
    let a = jepit(p[0]), z = jepit(p[1]);
    if (a > z) a = z;
    return [a, z];
  };
  const kini = [document.getElementById('f-awal').value, document.getElementById('f-akhir').value].join('|');
  document.querySelectorAll('#preset [data-preset]').forEach(b => {
    const aktif = rentangPreset(b.dataset.preset).join('|') === kini;
    b.classList.toggle('btn-primary', aktif); b.classList.toggle('btn-label-primary', !aktif);
  });
  document.getElementById('preset').addEventListener('click', function (e) {
    const b = e.target.closest('[data-preset]');
    if (!b) return;
    const [a, z] = rentangPreset(b.dataset.preset);
    fpAwal.set('maxDate', periode.akhir); fpAkhir.set('minDate', periode.awal);
    fpAwal.setDate(a, false); fpAkhir.setDate(z, false);
    form.submit();
  });

  const lipat = (tr, tutup) => {
    tr.classList.toggle('tertutup', tutup);
    kartu.querySelectorAll('tr.lv6[data-induk="' + tr.dataset.grup + '"]').forEach(r => r.classList.toggle('d-none', tutup));
  };
  const grup = () => kartu.querySelectorAll('tr.lv3[data-grup]');
  grup().forEach(tr => tr.addEventListener('click', () => lipat(tr, !tr.classList.contains('tertutup'))));
  document.getElementById('bukaSemua').addEventListener('click', () => grup().forEach(tr => lipat(tr, false)));
  document.getElementById('lipatSemua').addEventListener('click', () => grup().forEach(tr => lipat(tr, true)));
});
</script>
@endsection
