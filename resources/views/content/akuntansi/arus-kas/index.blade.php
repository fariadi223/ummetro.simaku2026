@extends('layouts/layoutMaster')

@section('title', 'Arus Kas')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-style')
<style>
  .ak-tabel { font-size: .84rem; margin: 0; }
  #kartuAk .ak-tabel > :not(caption) > * > * { background-color: var(--bs-card-bg, #fff); }
  .ak-tabel thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .02em; }
  .ak-tabel td.n, .ak-tabel th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 210px; }
  .ak-tabel .kd { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary-color); }
  .ak-tabel tr.ind td.nama { padding-left: 1.4rem; }
  .ak-tabel tr.ind2 td.nama { padding-left: 2.6rem; }
  .ak-tabel tr.ind3 td.nama { padding-left: 3.8rem; color: var(--bs-secondary-color); font-size: .8rem; }
  .ak-tabel tr.ind3 td.n { color: var(--bs-secondary-color); font-size: .8rem; }
  .ak-tabel tr.grp td { font-weight: 600; }
  .ak-tabel td.ket { font-style: italic; color: var(--bs-secondary-color); white-space: normal; }
  .ak-tabel td.kosong { color: var(--bs-secondary-color); }
  .ak-tabel tr.sub td { font-weight: 800; letter-spacing: .03em; color: var(--bs-primary); box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .06); }
  .ak-tabel tr.total td { font-weight: 700; border-top: 2px solid var(--bs-border-color); }
  .ak-tabel tr.akhir td { font-weight: 800; font-size: .92rem; box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .12); }
  .filter-ak .f-tgl { width: 200px; }
  @media (max-width: 575.98px) { .filter-ak .f-tgl { width: 100%; } }
</style>
@endsection

@php
  $rp = fn ($n) => abs($n) < 0.5 ? '0' : ($n < 0 ? '(' . number_format(abs($n), 0, ',', '.') . ')' : number_format($n, 0, ',', '.'));
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $label = ['ini' => 'T.A ' . $tahun . '/' . ($tahun + 1), 'lalu' => 'T.A ' . ($tahun - 1) . '/' . $tahun];
  $q = ['awal' => $awal, 'akhir' => $akhir, 'cetak' => 1];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-arrows-exchange"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Laporan Arus Kas</h4>
      <p class="pg-header-sub mb-0">Laporan Keuangan · metode tidak langsung · T.A {{ $tahun }}/{{ $tahun + 1 }} · {{ $tglId($awal) }} s.d {{ $tglId($akhir) }}</p>
    </div>
    <a href="{{ route('akuntansi.arus-kas.index', $q) }}" target="_blank" rel="noopener" class="btn btn-warning"><i class="ti ti-printer me-1"></i>Cetak</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-briefcase"></i><span>Operasi <strong>{{ $rp($ini['operasi']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-building"></i><span>Investasi <strong>{{ $rp($ini['investasi']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-building-bank"></i><span>Pendanaan <strong>{{ $rp($ini['pendanaan']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-cash"></i><span>Kas akhir <strong>{{ $rp($ini['kas_akhir']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-arrow-big-up-lines"></i><span>Kenaikan bersih <strong>{{ $rp($ini['kenaikan']) }}</strong></span></span>
  </div>
</div>

<div class="card" id="kartuAk">
  <div class="card-header border-bottom">
    <form method="GET" action="{{ route('akuntansi.arus-kas.index') }}" class="d-flex flex-wrap align-items-center gap-2 filter-ak" id="formAk">
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
    </form>
    <small class="text-muted d-block mt-2">
      {{ $label['ini'] }}: perubahan {{ $tglId($awal) }} s/d {{ $tglId($akhir) }}. {{ $label['lalu'] }}: seluruh T.A lalu, tidak ikut filter.
      Kas dan setara kas = Kas, Bank, Surat Berharga (1.1.01–1.1.03). Penyesuaian non-kas: beban non-kas ditambahkan, penghasilan non-kas dikurangkan; perubahan akumulasi penyusutan yang berasal dari pelepasan/koreksi aset ditampilkan satu baris di aktivitas investasi.
      Kas awal & kas akhir = kas di Laporan Posisi Keuangan. Angka dalam kurung = negatif.
    </small>
  </div>
  <div class="table-responsive">
    @include('content.akuntansi.arus-kas._tabel', ['layar' => true])
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const periode = { awal: @json($pAwal), akhir: @json($pAkhir) };
  const form = document.getElementById('formAk');
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
});
</script>
@endsection
