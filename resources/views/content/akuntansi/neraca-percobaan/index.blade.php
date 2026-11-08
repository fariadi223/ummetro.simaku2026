@extends('layouts/layoutMaster')

@section('title', 'Neraca Percobaan')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-style')
<style>
  .np-wrap { max-height: 72vh; overflow: auto; }
  .np-tabel { font-size: .8rem; margin: 0; border-collapse: separate; border-spacing: 0; }
  .np-tabel th, .np-tabel td { white-space: nowrap; vertical-align: middle; padding: .32rem .6rem; border-bottom: 1px solid var(--bs-border-color); }
  #tabelNp > :not(caption) > * > * { background-color: var(--bs-card-bg, #fff); }
  .np-tabel thead th { position: sticky; top: 0; z-index: 2; text-align: center; font-size: .72rem; text-transform: uppercase; letter-spacing: .02em;
    box-sizing: border-box; height: 32px; padding-top: 0; padding-bottom: 0; }
  .np-tabel thead tr:nth-child(2) th { top: 32px; height: 30px; }
  .np-tabel thead th[rowspan="2"] { height: 62px; }
  .np-tabel td.n { text-align: right; font-variant-numeric: tabular-nums; min-width: 120px; }
  .np-tabel td.n:empty::after { content: ''; }
  .np-tabel .g { border-left: 1px solid var(--bs-border-color); }
  .np-tabel .kode { font-family: var(--bs-font-monospace); font-size: .75rem; }
  .np-tabel .nama { white-space: normal; min-width: 260px; }
  .np-tabel tr.lv1 td { font-weight: 700; font-size: .85rem; box-shadow: inset 0 0 0 999px rgba(var(--bs-secondary-rgb), .1); }
  .np-tabel tr.lv2 td { font-weight: 700; }
  .np-tabel tr.lv2 .nama { padding-left: 1.2rem; }
  .np-tabel tr.lv3 td { font-weight: 600; cursor: pointer; }
  .np-tabel tr.lv3 .nama { padding-left: 2rem; }
  .np-tabel tr.lv3 .lipat { display: inline-block; transition: transform .15s; margin-right: .2rem; vertical-align: -1px; }
  .np-tabel tr.lv3.tertutup .lipat { transform: rotate(-90deg); }
  .np-tabel tr.lv6 td { color: var(--bs-secondary-color); font-size: .76rem; }
  .np-tabel tr.lv6 .nama { padding-left: 3.2rem; }
  .np-tabel tr.lv6 a { color: inherit; }
  .np-tabel tr.lv6 a:hover { color: var(--bs-primary); }
  .np-tabel tfoot td { position: sticky; bottom: 0; z-index: 2; font-weight: 700; border-top: 2px solid var(--bs-border-color); }
  .np-tabel td.m { color: var(--bs-danger); }
  .filter-np .f-tgl { width: 200px; }
  @media (max-width: 575.98px) { .filter-np .f-tgl { width: 100%; } }
  @media print {
    .layout-menu, .layout-navbar, .content-footer, .filter-np, .pg-header .btn, .no-print { display: none !important; }
    .np-wrap { max-height: none; overflow: visible; }
    .np-tabel tr.lv6.d-none { display: none !important; }
  }
</style>
@endsection

@php
  $rp = fn ($n) => abs($n) < 0.5 ? '' : number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $seimbangAwal = abs($total['awal_d'] - $total['awal_k']) < 0.5;
  $seimbangMut = abs($total['mut_d'] - $total['mut_k']) < 0.5;
  $seimbangAkhir = abs($total['akhir_d'] - $total['akhir_k']) < 0.5;
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-scale"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Neraca Percobaan</h4>
      <p class="pg-header-sub mb-0">Laporan Keuangan · T.A {{ $tahun }}/{{ $tahun + 1 }} · {{ $tglId($awal) }} s.d {{ $tglId($akhir) }}</p>
    </div>
    <button type="button" class="btn btn-label-light" onclick="window.print()"><i class="ti ti-printer me-1"></i>Cetak</button>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    @foreach ([['Saldo Awal', $seimbangAwal, $total['awal_d'] - $total['awal_k']], ['Mutasi', $seimbangMut, $total['mut_d'] - $total['mut_k']], ['Saldo Akhir', $seimbangAkhir, $total['akhir_d'] - $total['akhir_k']]] as [$label, $ok, $selisih])
      <span class="pg-header-chip"><i class="ti {{ $ok ? 'ti-circle-check' : 'ti-alert-triangle' }}"></i>
        <span>{{ $label }} <strong>{{ $ok ? 'seimbang' : 'selisih ' . number_format($selisih, 0, ',', '.') }}</strong></span></span>
    @endforeach
  </div>
</div>

<div class="card">
  <div class="card-header border-bottom">
    <form method="GET" action="{{ route('akuntansi.neraca-percobaan.index') }}" class="d-flex flex-wrap align-items-center gap-2 filter-np" id="formNp">
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
      <div class="ms-auto d-flex gap-2 no-print">
        <button type="button" class="btn btn-sm btn-label-secondary" id="bukaSemua"><i class="ti ti-fold-down ti-xs me-1"></i>Buka semua</button>
        <button type="button" class="btn btn-sm btn-label-secondary" id="lipatSemua"><i class="ti ti-fold ti-xs me-1"></i>Lipat semua</button>
      </div>
    </form>
    <small class="text-muted d-block mt-2">
      @if ($seluruhTa)
        Seluruh periode T.A — saldo awal dari saldo awal T.A ({{ $tglId($pAwal) }}).
      @else
        Saldo awal per {{ $tglId($awal) }} = saldo awal T.A + mutasi {{ $tglId($pAwal) }} s/d sehari sebelumnya.
      @endif
      Mutasi tidak termasuk jurnal tutup buku (TB). Klik baris kelompok untuk melihat akunnya.
    </small>
  </div>
  <div class="np-wrap">
    <table class="table np-tabel" id="tabelNp">
      <thead>
        <tr>
          <th rowspan="2">Kode Akun</th><th rowspan="2">Nama Akun</th>
          <th colspan="2" class="g">Saldo Awal</th><th colspan="2" class="g">Mutasi Transaksi</th><th colspan="2" class="g">Saldo Akhir</th>
        </tr>
        <tr><th class="g">D</th><th>K</th><th class="g">D</th><th>K</th><th class="g">D</th><th>K</th></tr>
      </thead>
      <tbody>
        @foreach ($baris as $b)
          @if ($b->lv === 6)
            <tr class="lv6 d-none" data-induk="{{ $b->induk }}">
              <td class="kode">{{ $b->kode }}</td>
              <td class="nama"><a href="{{ route('akuntansi.buku-besar.index', ['rek6' => $b->id]) }}" title="Buka buku besar">{{ $b->nama }}</a></td>
          @elseif ($b->lv === 3)
            <tr class="lv3 tertutup" data-grup="{{ $b->id }}">
              <td class="kode"><i class="ti ti-chevron-down ti-xs lipat"></i>{{ $b->kode }}</td>
              <td class="nama">{{ $b->nama }}</td>
          @else
            <tr class="lv{{ $b->lv }}">
              <td class="kode">{{ $b->kode }}</td>
              <td class="nama">{{ $b->nama }}</td>
          @endif
              <td class="n g">{{ $rp($b->awal_d) }}</td><td class="n">{{ $rp($b->awal_k) }}</td>
              <td class="n g">{{ $rp($b->mut_d) }}</td><td class="n">{{ $rp($b->mut_k) }}</td>
              <td class="n g">{{ $rp($b->akhir_d) }}</td><td class="n">{{ $rp($b->akhir_k) }}</td>
            </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="2" class="text-end">TOTAL</td>
          <td class="n g {{ $seimbangAwal ? '' : 'm' }}">{{ number_format($total['awal_d'], 0, ',', '.') }}</td>
          <td class="n {{ $seimbangAwal ? '' : 'm' }}">{{ number_format($total['awal_k'], 0, ',', '.') }}</td>
          <td class="n g {{ $seimbangMut ? '' : 'm' }}">{{ number_format($total['mut_d'], 0, ',', '.') }}</td>
          <td class="n {{ $seimbangMut ? '' : 'm' }}">{{ number_format($total['mut_k'], 0, ',', '.') }}</td>
          <td class="n g {{ $seimbangAkhir ? '' : 'm' }}">{{ number_format($total['akhir_d'], 0, ',', '.') }}</td>
          <td class="n {{ $seimbangAkhir ? '' : 'm' }}">{{ number_format($total['akhir_k'], 0, ',', '.') }}</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const periode = { awal: @json($pAwal), akhir: @json($pAkhir) };
  const form = document.getElementById('formNp');
  const tabel = document.getElementById('tabelNp');
  const localeId = {
    firstDayOfWeek: 1,
    weekdays: { shorthand: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'], longhand: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] },
    months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
      longhand: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] }
  };
  const opsi = { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', monthSelectorType: 'static', locale: localeId, minDate: periode.awal, maxDate: periode.akhir };
  const fpAwal = flatpickr('#f-awal', Object.assign({}, opsi, { maxDate: document.getElementById('f-akhir').value, onChange: (d, s) => fpAkhir.set('minDate', s || periode.awal) }));
  const fpAkhir = flatpickr('#f-akhir', Object.assign({}, opsi, { minDate: document.getElementById('f-awal').value, onChange: (d, s) => fpAwal.set('maxDate', s || periode.akhir) }));

  // Pilihan cepat (Report Tanggal v1 = bulan ini).
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
  // Tandai preset yang sesuai rentang tampil (aktif = btn-primary, seperti tombol Tampilkan).
  const kini = [document.getElementById('f-awal').value, document.getElementById('f-akhir').value].join('|');
  document.querySelectorAll('#preset [data-preset]').forEach(b => {
    const aktif = rentangPreset(b.dataset.preset).join('|') === kini;
    b.classList.toggle('btn-primary', aktif);
    b.classList.toggle('btn-label-primary', !aktif);
  });
  document.getElementById('preset').addEventListener('click', function (e) {
    const b = e.target.closest('[data-preset]');
    if (!b) return;
    const [a, z] = rentangPreset(b.dataset.preset);
    fpAwal.set('maxDate', periode.akhir); fpAkhir.set('minDate', periode.awal);
    fpAwal.setDate(a, false); fpAkhir.setDate(z, false);
    form.submit();
  });

  // Lipat / buka kelompok level 3.
  const lipat = (tr, tutup) => {
    tr.classList.toggle('tertutup', tutup);
    tabel.querySelectorAll('tr.lv6[data-induk="' + tr.dataset.grup + '"]').forEach(r => r.classList.toggle('d-none', tutup));
  };
  tabel.querySelectorAll('tr.lv3').forEach(tr => tr.addEventListener('click', () => lipat(tr, !tr.classList.contains('tertutup'))));
  document.getElementById('bukaSemua').addEventListener('click', () => tabel.querySelectorAll('tr.lv3').forEach(tr => lipat(tr, false)));
  document.getElementById('lipatSemua').addEventListener('click', () => tabel.querySelectorAll('tr.lv3').forEach(tr => lipat(tr, true)));
});
</script>
@endsection
