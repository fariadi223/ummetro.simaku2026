@extends('layouts/layoutMaster')

@section('title', 'Perubahan Aset Neto')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-style')
<style>
  .pan-tabel { font-size: .84rem; margin: 0; }
  #kartuPan .pan-tabel > :not(caption) > * > * { background-color: var(--bs-card-bg, #fff); }
  .pan-tabel thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .02em; }
  .pan-tabel th.judul { font-size: .8rem; color: var(--bs-primary); }
  .pan-tabel td.n, .pan-tabel th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 210px; }
  .pan-tabel td.ind { padding-left: 2rem; }
  .pan-tabel td.ka { text-align: right; }
  .pan-tabel tr.sub td { font-weight: 700; color: var(--bs-heading-color); box-shadow: inset 0 0 0 999px rgba(var(--bs-secondary-rgb), .06); }
  .pan-tabel tr.total td { font-weight: 700; border-top: 2px solid var(--bs-border-color); }
  .pan-tabel tr.akhir td { font-weight: 800; box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .08); }
  .pan-tabel.grand tr.akhir td { font-size: .92rem; box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .14); }
  .filter-pan .f-tgl { width: 200px; }
  @media (max-width: 575.98px) { .filter-pan .f-tgl { width: 100%; } }
</style>
@endsection

@php
  $rp = fn ($n) => abs($n) < 0.5 ? '0' : ($n < 0 ? '(' . number_format(abs($n), 0, ',', '.') . ')' : number_format($n, 0, ',', '.'));
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $tanggalMode = $mode === 'tanggal';
  $q = ['awal' => $awal, 'akhir' => $akhir, 'cetak' => 1] + ($tanggalMode ? ['mode' => 'tanggal'] : []);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-chart-arrows-vertical"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Laporan Perubahan Aset Neto @if ($tanggalMode)<span class="badge bg-label-light ms-1 align-middle">Report Tanggal</span>@endif</h4>
      <p class="pg-header-sub mb-0">Laporan Keuangan · T.A {{ $tahun }}/{{ $tahun + 1 }} · {{ $tglId($awal) }} s.d {{ $tglId($akhir) }}</p>
    </div>
    @if ($tanggalMode)
      <a href="{{ route('akuntansi.perubahan-aset-neto.index', ['awal' => $awal, 'akhir' => $akhir]) }}" class="btn btn-info"><i class="ti ti-layout-list me-1"></i>Report Tahunan</a>
    @else
      <a href="{{ route('akuntansi.perubahan-aset-neto.index', ['mode' => 'tanggal']) }}" class="btn btn-info"><i class="ti ti-calendar-stats me-1"></i>Report Tanggal</a>
    @endif
    <a href="{{ route('akuntansi.perubahan-aset-neto.index', $q) }}" target="_blank" rel="noopener" class="btn btn-warning"><i class="ti ti-printer me-1"></i>Cetak</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-history"></i><span>Saldo awal <strong>{{ $rp($ini['total']['saldo_awal']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-trending-up"></i><span>Kenaikan <strong>{{ $rp($ini['total']['saldo_akhir'] - $ini['total']['saldo_awal']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Saldo akhir aset neto <strong>{{ $rp($ini['total']['saldo_akhir']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti {{ $seimbang ? 'ti-circle-check' : 'ti-alert-triangle' }}"></i>
      <span><strong>{{ $seimbang ? 'Cocok dengan Posisi Keuangan' : 'Tidak cocok dengan Posisi Keuangan' }}</strong></span></span>
  </div>
</div>

<div class="card" id="kartuPan">
  <div class="card-header border-bottom">
    <form method="GET" action="{{ route('akuntansi.perubahan-aset-neto.index') }}" class="d-flex flex-wrap align-items-center gap-2 filter-pan" id="formPan">
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
    </form>
    <small class="text-muted d-block mt-2">
      @if ($tanggalMode)
        <strong>Report Tanggal</strong> · kolom kiri = perubahan {{ $tglId($awal) }} s/d {{ $tglId($akhir) }} (saldo awal = aset neto per {{ $tglId($awal) }});
        kolom kanan = @if ($sebelumPeriode) perubahan sebelum periode ({{ $tglId($awalLalu) }} s/d {{ $tglId($akhirLalu) }}), saldo akhirnya = saldo awal kolom kiri.
        @else seluruh T.A lalu (tanggal awal = awal T.A). @endif
      @else
        {{ $label['ini'] }}: saldo awal = aset neto per {{ $tglId($awal) }}, perubahan {{ $tglId($awal) }} s/d {{ $tglId($akhir) }}.
        {{ $label['lalu'] }}: seluruh T.A lalu ({{ $tglId($awalLalu) }} s/d {{ $tglId($akhirLalu) }}), tidak ikut filter.
      @endif
      Saldo akhir = aset neto di Laporan Posisi Keuangan. Angka dalam kurung = negatif.
    </small>
  </div>

  @foreach (['tp' => 'Aset Neto Tanpa Pembatasan', 'dp' => 'Aset Neto Dengan Pembatasan'] as $k => $judul)
    <div class="table-responsive {{ $loop->first ? '' : 'border-top' }}">
      @include('content.akuntansi.perubahan-aset-neto._tabel', ['judul' => $judul, 'k' => $k, 'layar' => true])
    </div>
    @if (abs($ini[$k]['selisih']) >= 0.5 || abs($lalu[$k]['selisih']) >= 0.5)
      <div class="alert alert-warning m-3 py-2 small">Selisih dengan Posisi Keuangan: {{ $rp($ini[$k]['selisih']) }} / {{ $rp($lalu[$k]['selisih']) }}</div>
    @endif
  @endforeach
  <div class="table-responsive border-top">
    <table class="table table-sm pan-tabel grand mb-0"><tbody>
      <tr class="akhir"><td class="ka">TOTAL ASET NETO</td><td class="n">{{ $rp($ini['total']['saldo_akhir']) }}</td><td class="n">{{ $rp($lalu['total']['saldo_akhir']) }}</td></tr>
    </tbody></table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const periode = { awal: @json($pAwal), akhir: @json($pAkhir) };
  const form = document.getElementById('formPan');
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
