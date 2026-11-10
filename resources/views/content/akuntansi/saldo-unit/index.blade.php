{{-- Saldo Anggaran Unit (Kas di Unit Kerja) per induk FF.00 + sub-unit, rincian transaksi dimuat saat diklik. Query: sql/saldo_unit. --}}
@extends('layouts/layoutMaster')

@section('title', 'Saldo Anggaran Unit')

@section('page-style')
<style>
  .su-wrap { max-height: 72vh; overflow: auto; }
  .su-tabel { font-size: .8rem; margin: 0; border-collapse: separate; border-spacing: 0; }
  .su-tabel th, .su-tabel td { vertical-align: middle; padding: .35rem .6rem; border-bottom: 1px solid var(--bs-border-color); }
  #tabelSu > :not(caption) > * > * { background-color: var(--bs-card-bg, #fff); }
  .su-tabel thead th { position: sticky; top: 0; z-index: 2; text-align: center; font-size: .72rem; text-transform: uppercase; white-space: nowrap; }
  .su-tabel td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .su-tabel .kode { font-family: var(--bs-font-monospace); font-size: .76rem; white-space: nowrap; }
  .su-tabel tr.induk td { font-weight: 700; box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .08); }
  .su-tabel tr.sub td.nama { padding-left: 1.8rem; }
  .su-tabel tr.sub.tanpa td { color: var(--bs-warning); }
  .su-tabel tr.bisa { cursor: pointer; }
  .su-tabel tr.bisa:hover td { box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .05); }
  .su-tabel tr.induk.bisa:hover td { box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .13); }
  .su-tabel .lipat { display: inline-block; transition: transform .15s; margin-right: .25rem; vertical-align: -1px; }
  .su-tabel tr.terbuka .lipat { transform: rotate(90deg); }
  .su-tabel td.m { color: var(--bs-danger); }
  .su-tabel td.nol { color: var(--bs-secondary-color); }
  .su-tabel tr.trx-row > td { padding: 0 0 .6rem 2.2rem; box-shadow: inset 3px 0 0 rgba(var(--bs-primary-rgb), .5); }
  .su-tabel tfoot td { position: sticky; bottom: 0; z-index: 2; font-weight: 700; border-top: 2px solid var(--bs-border-color); }
  .tbl-trx { width: 100%; font-size: .74rem; margin: 0; }
  .tbl-trx th { font-size: .66rem; text-transform: uppercase; color: var(--bs-secondary-color); padding: .25rem .5rem; border-bottom: 1px solid var(--bs-border-color); white-space: nowrap; }
  .tbl-trx td { padding: .18rem .5rem; border-bottom: 1px dashed var(--bs-border-color); }
  .tbl-trx td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .tbl-trx tr.awal td { font-style: italic; color: var(--bs-secondary-color); }
  .tbl-trx .jk { font-size: .64rem; }
  @media print {
    .layout-menu, .layout-navbar, .content-footer, .no-print, .pg-header .btn { display: none !important; }
    .su-wrap { max-height: none; overflow: visible; }
  }
</style>
@endsection

@php
  $rp = fn ($n) => \App\Services\LaporanUnit::rp($n);
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y');
  $q = array_filter(['tahun' => $tahun, 'awal' => $awal, 'akhir' => $akhir]);
  $kol = ['saldo_awal', 'diterima', 'realisasi', 'disetor', 'lainnya', 'saldo'];
  $tot = array_fill_keys([...$kol, 'jml_transaksi'], 0.0);
  foreach ($grup as $g) {
    foreach (array_keys($tot) as $k) {
      $tot[$k] += $g[$k];
    }
  }
  $adaAwal = $awal !== null;   // kolom saldo awal hanya bila periode dimulai dari tanggal tertentu
  $kolom = $adaAwal ? 9 : 8;
  $jmlUnit = array_sum(array_map(fn ($g) => count($g['unit']), $grup));
  $sel = fn ($n, bool $tebal = false) => ($n < -0.5 ? 'm' : (abs($n) < 0.5 ? 'nol' : '')) . ($tebal ? ' fw-semibold' : '');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-wallet"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Saldo Anggaran Unit</h4>
      <p class="pg-header-sub mb-0">Kas di Unit Kerja per unit induk FF.00 · T.A {{ $tahun }}/{{ $tahun + 1 }} ·
        {{ $awal || $akhir ? ($awal ? $tglId($awal) : 'awal') . ' s.d ' . ($akhir ? $tglId($akhir) : 'akhir') : 'seluruh jurnal T.A' }}</p>
    </div>
    <a class="btn btn-label-success" href="{{ route('akuntansi.saldo-unit.excel', $q) }}"><i class="ti ti-file-spreadsheet me-1"></i>Excel</a>
    <button type="button" class="btn btn-label-secondary" onclick="window.print()"><i class="ti ti-printer me-1"></i>Cetak / PDF</button>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-sitemap"></i><span><strong>{{ count($grup) }}</strong> unit induk · <strong>{{ $jmlUnit }}</strong> sub-unit</span></span>
    <span class="pg-header-chip"><i class="ti ti-receipt"></i><span><strong>{{ number_format($tot['jml_transaksi'], 0, ',', '.') }}</strong> transaksi</span></span>
    <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Total saldo <strong>Rp {{ $rp($tot['saldo']) }}</strong></span></span>
  </div>
</div>

@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card">
  <div class="card-header border-bottom no-print">
    <form method="GET" action="{{ route('akuntansi.saldo-unit.index') }}" class="d-flex flex-wrap align-items-center gap-2" id="formSu">
      <select name="tahun" class="form-select" style="width: auto;" aria-label="Tahun anggaran" id="f-tahun">
        @foreach ($daftarTahun as $t)
          <option value="{{ $t }}" @selected($t === $tahun)>T.A {{ $t }}/{{ $t + 1 }}</option>
        @endforeach
      </select>
      <input type="date" name="awal" value="{{ $awal }}" class="form-control" style="width: auto;" aria-label="Tanggal awal (opsional)" id="f-awal">
      <span class="text-muted">s/d</span>
      <input type="date" name="akhir" value="{{ $akhir }}" class="form-control" style="width: auto;" aria-label="Tanggal akhir (opsional)" id="f-akhir">
      <button type="submit" class="btn btn-primary"><i class="ti ti-filter ti-xs me-1"></i>Tampilkan</button>
      @if ($awal || $akhir)
        <a class="btn btn-label-secondary" href="{{ route('akuntansi.saldo-unit.index', ['tahun' => $tahun]) }}">Seluruh T.A</a>
      @endif
    </form>
    <small class="text-muted d-block mt-2">
      Saldo = {{ $adaAwal ? 'saldo awal + ' : '' }}diterima − realisasi − disetor − kredit lainnya (rekening Kas di Unit Kerja).
      Tanggal dikosongkan = seluruh jurnal T.A, sama dengan v1. Sub-unit dari unit jurnal; “Jurnal tanpa unit” = data lama ber-kode unit 0.
      Klik baris induk atau sub-unit untuk melihat transaksinya.
    </small>
  </div>

  <div class="su-wrap">
    <table class="table su-tabel" id="tabelSu">
      <thead>
        <tr><th>Kode</th><th>Unit Kerja</th><th>Trx</th>@if ($adaAwal)<th>Saldo Awal (Rp)</th>@endif<th>Diterima (Rp)</th><th>Realisasi (Rp)</th><th>Disetor (Rp)</th><th>Kredit Lainnya (Rp)</th><th>Saldo (Rp)</th></tr>
      </thead>
      <tbody>
        @forelse ($grup as $ff => $g)
          @php $ff2 = sprintf('%02d', (int) $ff); @endphp
          <tr class="induk bisa" data-ff="{{ $ff2 }}" title="Klik untuk transaksi seluruh {{ $g['kode_induk'] }}">
            <td class="kode"><i class="ti ti-chevron-right ti-xs lipat"></i>{{ $g['kode_induk'] }}</td>
            <td>{{ $g['nama_induk'] }} <span class="badge bg-label-primary ms-1">{{ count($g['unit']) }} sub-unit</span></td>
            <td class="n">{{ number_format($g['jml_transaksi'], 0, ',', '.') }}</td>
            @if ($adaAwal)<td class="n {{ $sel($g['saldo_awal']) }}">{{ $rp($g['saldo_awal']) }}</td>@endif
            <td class="n {{ $sel($g['diterima']) }}">{{ $rp($g['diterima']) }}</td>
            <td class="n {{ $sel($g['realisasi']) }}">{{ $rp($g['realisasi']) }}</td>
            <td class="n {{ $sel($g['disetor']) }}">{{ $rp($g['disetor']) }}</td>
            <td class="n {{ $sel($g['lainnya']) }}">{{ $rp($g['lainnya']) }}</td>
            <td class="n {{ $sel($g['saldo']) }}">{{ $rp($g['saldo']) }}</td>
          </tr>
          @foreach ($g['unit'] as $u)
            <tr class="sub bisa {{ $u->kode === null ? 'tanpa' : '' }}" data-ff="{{ $ff2 }}" data-uk="{{ $u->id_uk }}" title="Klik untuk transaksi {{ $u->kode ?? 'tanpa unit' }}">
              <td class="kode"><i class="ti ti-chevron-right ti-xs lipat"></i>{{ $u->kode ?? '—' }}</td>
              <td class="nama">{{ $u->nama }}</td>
              <td class="n">{{ number_format($u->jml_transaksi, 0, ',', '.') }}</td>
              @if ($adaAwal)<td class="n {{ $sel($u->saldo_awal) }}">{{ $rp($u->saldo_awal) }}</td>@endif
              <td class="n {{ $sel($u->diterima) }}">{{ $rp($u->diterima) }}</td>
              <td class="n {{ $sel($u->realisasi) }}">{{ $rp($u->realisasi) }}</td>
              <td class="n {{ $sel($u->disetor) }}">{{ $rp($u->disetor) }}</td>
              <td class="n {{ $sel($u->lainnya) }}">{{ $rp($u->lainnya) }}</td>
              <td class="n {{ $sel($u->saldo, true) }}">{{ $rp($u->saldo) }}</td>
            </tr>
          @endforeach
        @empty
          <tr><td colspan="{{ $kolom }}" class="text-center text-muted py-5">Belum ada saldo kas unit pada T.A ini.</td></tr>
        @endforelse
      </tbody>
      @if ($grup)
        <tfoot>
          <tr>
            <td colspan="2" class="text-end">TOTAL</td>
            <td class="n">{{ number_format($tot['jml_transaksi'], 0, ',', '.') }}</td>
            @foreach ($kol as $k)
              @continue($k === 'saldo_awal' && !$adaAwal)
              <td class="n {{ $tot[$k] < -0.5 ? 'm' : '' }}">{{ $rp($tot[$k]) }}</td>
            @endforeach
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Ganti tahun => kosongkan periode (seluruh T.A).
  document.getElementById('f-tahun').addEventListener('change', function () {
    document.getElementById('f-awal').value = '';
    document.getElementById('f-akhir').value = '';
  });

  // ---- Transaksi Kas di Unit Kerja: dimuat hanya saat baris diklik ----------
  // Hasil disimpan sebagai baris DOM; klik ulang hanya membuka/menutup tanpa memuat lagi.
  const urlTrx = {{ Js::from(route('akuntansi.saldo-unit.transaksi')) }};
  const filter = {{ Js::from($q) }};
  const kolom = {{ $kolom }};
  const label = { diterima: ['Diterima', 'success'], realisasi: ['Realisasi', 'primary'], disetor: ['Disetor', 'info'], lainnya: ['Lainnya', 'warning'] };
  const rp = (n, kosong) => {
    if (Math.abs(n) < 0.5) return kosong ? '' : '0';
    const t = Math.round(Math.abs(n)).toLocaleString('id-ID');
    return n < 0 ? '(' + t + ')' : t;
  };
  const tgl = s => { const [y, m, d] = String(s).split('-'); return d + '-' + m + '-' + y; };
  const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

  function tabelTrx(d, banyakUnit) {
    if (!d.baris.length) return '<div class="text-muted py-2">Tidak ada transaksi.</div>';
    let h = '<table class="tbl-trx"><thead><tr><th>Tanggal</th><th>Bukti</th>' + (banyakUnit ? '<th>Unit</th>' : '')
      + '<th>Uraian</th><th>Jenis</th><th class="text-end">Debet</th><th class="text-end">Kredit</th><th class="text-end">Saldo</th></tr></thead><tbody>';
    d.baris.forEach(b => {
      const jk = label[b.jenis_kredit];
      h += '<tr class="' + (b.jenis === 0 ? 'awal' : '') + '"><td class="text-nowrap">' + tgl(b.tanggal) + '</td><td>' + esc(b.bukti) + '</td>'
        + (banyakUnit ? '<td class="font-monospace">' + esc(b.kode_unit) + '</td>' : '')
        + '<td>' + esc(b.uraian) + '</td><td>' + (jk ? '<span class="badge jk bg-label-' + jk[1] + '">' + jk[0] + '</span>' : '') + '</td>'
        + '<td class="n">' + (b.debet === null ? '' : rp(b.debet, true)) + '</td><td class="n">' + (b.kredit === null ? '' : rp(b.kredit, true))
        + '</td><td class="n">' + rp(b.saldo) + '</td></tr>';
    });
    h += '</tbody></table>';
    if (d.terpotong) h += '<div class="text-warning small mt-1">Hanya ' + d.batas.toLocaleString('id-ID') + ' transaksi pertama yang ditampilkan; persempit periode atau gunakan Buku Besar.</div>';
    return h;
  }

  function toggle(tr) {
    let r = tr.nextElementSibling && tr.nextElementSibling.classList.contains('trx-row') ? tr.nextElementSibling : null;
    if (r && r.dataset.gagal) { r.remove(); r = null; tr.classList.remove('terbuka'); }
    const buka = !tr.classList.contains('terbuka');
    tr.classList.toggle('terbuka', buka);
    if (!buka) { if (r) r.classList.add('d-none'); return; }
    if (r) { r.classList.remove('d-none'); return; }
    r = document.createElement('tr');
    r.className = 'trx-row';
    r.innerHTML = '<td colspan="' + kolom + '"><div class="text-muted py-2"><span class="spinner-border spinner-border-sm me-1"></span>Memuat transaksi…</div></td>';
    tr.after(r);
    const satu = tr.dataset.uk !== undefined;
    const q = new URLSearchParams(Object.assign({}, filter, { ff: tr.dataset.ff }, satu ? { id_uk: tr.dataset.uk } : {}));
    fetch(urlTrx + '?' + q, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(x => { if (!x.ok) throw new Error('HTTP ' + x.status); return x.json(); })
      .then(d => { r.firstElementChild.innerHTML = tabelTrx(d, !satu); })
      .catch(e => {
        // Pesan tetap tampil; klik berikutnya memuat ulang.
        r.dataset.gagal = '1';
        r.firstElementChild.innerHTML = '<div class="text-danger py-2">Gagal memuat transaksi (' + esc(e.message) + '). Klik baris untuk mencoba lagi.</div>';
      });
  }
  document.querySelectorAll('#tabelSu tr.bisa').forEach(tr => tr.addEventListener('click', () => toggle(tr)));
});
</script>
@endsection
