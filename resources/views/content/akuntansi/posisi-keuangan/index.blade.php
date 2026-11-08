@extends('layouts/layoutMaster')

@section('title', 'Laporan Posisi Keuangan')

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
  .pk-tabel .kode { font-family: var(--bs-font-monospace); font-size: .76rem; white-space: nowrap; width: 150px; }
  .pk-tabel td.n, .pk-tabel th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 190px; }
  .pk-tabel thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .02em; }
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
  .filter-pk .f-tgl { width: 200px; }
  @media (max-width: 575.98px) { .filter-pk .f-tgl { width: 100%; } }
  @media print {
    .layout-menu, .layout-navbar, .content-footer, .filter-pk, .pg-header .btn, .no-print { display: none !important; }
  }
</style>
@endsection

@php
  $rp = fn ($n) => abs($n) < 0.5 ? '0' : ($n < 0 ? '(' . number_format(abs($n), 0, ',', '.') . ')' : number_format($n, 0, ',', '.'));
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $kewNeto = $bagian['2']['total'] + $bagian['3']['total'];
  $kewNetoAwal = $bagian['2']['total_awal'] + $bagian['3']['total_awal'];
  $tglPendek = fn ($t) => \Illuminate\Support\Carbon::parse($t)->format('d-m-Y');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-building-bank"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Laporan Posisi Keuangan</h4>
      <p class="pg-header-sub mb-0">Laporan Keuangan · T.A {{ $tahun }}/{{ $tahun + 1 }} · {{ $tglId($awal) }} s.d {{ $tglId($akhir) }}</p>
    </div>
    <a href="{{ route('akuntansi.posisi-keuangan.komparasi', ['akhir' => $akhir]) }}" class="btn btn-info"><i class="ti ti-arrows-left-right me-1"></i>Komparasi</a>
    <div class="btn-group">
      <a href="{{ route('akuntansi.posisi-keuangan.cetak', ['awal' => $awal, 'akhir' => $akhir]) }}" target="_blank" rel="noopener" class="btn btn-warning">
        <i class="ti ti-printer me-1"></i>Cetak</a>
      <button type="button" class="btn btn-warning dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="visually-hidden">Pilihan cetak</span></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ route('akuntansi.posisi-keuangan.cetak', ['awal' => $awal, 'akhir' => $akhir]) }}" target="_blank" rel="noopener">
          <i class="ti ti-list ti-xs me-2"></i>Ringkas (akun level 3)</a></li>
        <li><a class="dropdown-item" href="{{ route('akuntansi.posisi-keuangan.cetak', ['awal' => $awal, 'akhir' => $akhir, 'rinci' => 1]) }}" target="_blank" rel="noopener">
          <i class="ti ti-list-details ti-xs me-2"></i>Rinci (dengan akun level 6)</a></li>
      </ul>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Total {{ $bagian['1']['nama'] }} <strong>{{ $rp($bagian['1']['total']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-scale"></i><span>{{ $bagian['2']['nama'] }} + {{ $bagian['3']['nama'] }} <strong>{{ $rp($kewNeto) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti {{ $seimbang ? 'ti-circle-check' : 'ti-alert-triangle' }}"></i>
      <span><strong>{{ $seimbang ? 'Seimbang' : 'Selisih ' . $rp(abs($selisih) >= 0.5 ? $selisih : $selisih_awal) }}</strong></span></span>
  </div>
</div>

<div class="card" id="kartuPk">
  <div class="card-header border-bottom">
    <form method="GET" action="{{ route('akuntansi.posisi-keuangan.index') }}" class="d-flex flex-wrap align-items-center gap-2 filter-pk" id="formPk">
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
        Seluruh periode T.A — Saldo Awal = saldo awal T.A ({{ $tglId($pAwal) }}).
      @else
        Saldo Awal = posisi pada {{ $tglId($awal) }} (saldo awal T.A + mutasi {{ $tglId($pAwal) }} s/d sehari sebelumnya).
      @endif
      Saldo Akhir = posisi s/d {{ $tglId($akhir) }}. Aset neto termasuk surplus/defisit berjalan
      (tanpa pembatasan {{ $rp($surplus['tp']) }}, dengan pembatasan {{ $rp($surplus['dp']) }}). Mutasi tidak termasuk jurnal tutup buku (TB).
      Klik baris kelompok untuk melihat akunnya. Angka dalam kurung = negatif.
    </small>
  </div>

  @foreach ($bagian as $k1 => $bg)
    <div class="pk-judul">{{ $bg['nama'] }}</div>
    <div class="table-responsive">
      <table class="table table-sm pk-tabel">
        <thead><tr><th class="kode">Kode Akun</th><th>Nama Akun</th><th class="n">Saldo Akhir<br><span class="fw-normal text-muted">{{ $tglPendek($akhir) }}</span></th>
          <th class="n">Saldo Awal<br><span class="fw-normal text-muted">{{ $tglPendek($awal) }}</span></th></tr></thead>
        <tbody>
          @forelse ($bg['baris'] as $b)
            @if ($b->lv === 3)
              @php $punyaAnak = $b->id && $bg['baris']->contains(fn ($x) => $x->lv === 6 && $x->induk === $b->id); @endphp
              <tr class="lv3 tertutup {{ $punyaAnak ? '' : 'tanpa-anak' }}" @if ($b->id) data-grup="{{ $b->id }}" @endif>
                <td class="kode">@if ($punyaAnak)<i class="ti ti-chevron-down ti-xs lipat"></i>@endif{{ $b->kode }}</td>
                <td class="nama">{{ $b->nama }}
                  @if (abs($b->surplus) >= 0.5)<span class="ket-surplus">termasuk surplus/defisit berjalan {{ $rp($b->surplus) }}</span>@endif
                </td>
                <td class="n">{{ $rp($b->saldo) }}</td>
                <td class="n">{{ $rp($b->saldo_awal) }}</td>
              </tr>
            @else
              <tr class="lv6 d-none" data-induk="{{ $b->induk }}">
                <td class="kode">{{ $b->kode }}</td>
                <td class="nama"><a href="{{ route('akuntansi.buku-besar.index', ['rek6' => $b->id]) }}" title="Buka buku besar">{{ $b->nama }}</a></td>
                <td class="n">{{ $rp($b->saldo) }}</td>
                <td class="n">{{ $rp($b->saldo_awal) }}</td>
              </tr>
            @endif
          @empty
            <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada saldo.</td></tr>
          @endforelse
          <tr class="total"><td></td><td class="text-end">TOTAL {{ strtoupper($bg['nama']) }}</td><td class="n">{{ $rp($bg['total']) }}</td><td class="n">{{ $rp($bg['total_awal']) }}</td></tr>
        </tbody>
      </table>
    </div>
  @endforeach

  {{-- Baris penutup: Liabilitas + Aset Neto (harus sama dengan Total Aset). --}}
  <div class="table-responsive border-top">
    <table class="table table-sm pk-tabel mb-0">
      <tbody>
        <tr class="total-besar">
          <td class="kode"></td>
          <td class="text-end">TOTAL LIABILITAS + ASET NETO</td>
          <td class="n">{{ $rp($kewNeto) }}</td>
          <td class="n">{{ $rp($kewNetoAwal) }}</td>
        </tr>
        @unless ($seimbang)
          <tr><td></td><td class="text-end text-danger small">Selisih terhadap Total Aset</td>
            <td class="n text-danger">{{ $rp($selisih) }}</td><td class="n text-danger">{{ $rp($selisih_awal) }}</td></tr>
        @endunless
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
  const opsi = { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', monthSelectorType: 'static', locale: localeId, minDate: periode.awal, maxDate: periode.akhir };
  const fpAwal = flatpickr('#f-awal', Object.assign({}, opsi, { maxDate: document.getElementById('f-akhir').value, onChange: (d, s) => fpAkhir.set('minDate', s || periode.awal) }));
  const fpAkhir = flatpickr('#f-akhir', Object.assign({}, opsi, { minDate: document.getElementById('f-awal').value, onChange: (d, s) => fpAwal.set('maxDate', s || periode.akhir) }));
  const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const jepit = t => t < periode.awal ? periode.awal : (t > periode.akhir ? periode.akhir : t);

  // Pilihan cepat (sama dengan Neraca Percobaan); preset aktif = btn-primary.
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
    kartu.querySelectorAll('tr.lv6[data-induk="' + tr.dataset.grup + '"]').forEach(r => r.classList.toggle('d-none', tutup));
  };
  const grup = () => kartu.querySelectorAll('tr.lv3[data-grup]:not(.tanpa-anak)');
  grup().forEach(tr => tr.addEventListener('click', () => lipat(tr, !tr.classList.contains('tertutup'))));
  document.getElementById('bukaSemua').addEventListener('click', () => grup().forEach(tr => lipat(tr, false)));
  document.getElementById('lipatSemua').addEventListener('click', () => grup().forEach(tr => lipat(tr, true)));
});
</script>
@endsection
