{{-- Laporan Buku Besar Unit: unit terpetakan (ueu_map_unit_prodi) dikelompokkan ke induk FF.00. Query: sql/laporan_unit. --}}
@extends('layouts/layoutMaster')

@section('title', ($mode ?? '') === 'aktivitas' ? 'Penghasilan Komprehensif Unit' : 'Laporan Buku Besar Unit')

@section('page-style')
<style>
  .lu-wrap { max-height: 72vh; overflow: auto; }
  .lu-tabel { font-size: .8rem; margin: 0; border-collapse: separate; border-spacing: 0; }
  .lu-tabel th, .lu-tabel td { vertical-align: middle; padding: .35rem .6rem; border-bottom: 1px solid var(--bs-border-color); }
  #tabelLu > :not(caption) > * > *, #tabelTf > :not(caption) > * > * { background-color: var(--bs-card-bg, #fff); }
  .lu-tabel thead th { position: sticky; top: 0; z-index: 2; text-align: center; font-size: .72rem; text-transform: uppercase; white-space: nowrap; }
  .lu-tabel td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .lu-tabel .kode { font-family: var(--bs-font-monospace); font-size: .76rem; white-space: nowrap; }
  .lu-tabel tr.induk td { font-weight: 700; box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .08); }
  .lu-tabel tr.sub td.nama { padding-left: 1.8rem; }
  .lu-tabel tr.bisa { cursor: pointer; }
  .lu-tabel tr.bisa:hover td { box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .05); }
  .lu-tabel tr.induk.bisa:hover td { box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .13); }
  .lu-tabel .lipat { display: inline-block; transition: transform .15s; margin-right: .25rem; vertical-align: -1px; }
  .lu-tabel tr.terbuka .lipat { transform: rotate(90deg); }
  .lu-tabel td.m { color: var(--bs-danger); }
  .lu-tabel tr.rinci > td { padding: 0 0 .6rem 2.2rem; box-shadow: inset 3px 0 0 rgba(var(--bs-primary-rgb), .5); }
  .lu-tabel tfoot td { position: sticky; bottom: 0; z-index: 2; font-weight: 700; border-top: 2px solid var(--bs-border-color); }
  .tbl-rinci { width: 100%; font-size: .76rem; margin: 0; }
  .tbl-rinci th { font-size: .68rem; text-transform: uppercase; color: var(--bs-secondary-color); padding: .25rem .5rem; border-bottom: 1px solid var(--bs-border-color); white-space: nowrap; }
  .tbl-rinci td { padding: .2rem .5rem; border-bottom: 1px dashed var(--bs-border-color); }
  .tbl-rinci td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .tbl-rinci tfoot td { font-weight: 600; border-top: 1px solid var(--bs-border-color); }
  /* Rekening di rincian bisa diklik -> transaksi (dimuat saat diklik) */
  .tbl-rinci tr.rek-tr { cursor: pointer; }
  .tbl-rinci tr.rek-tr:hover td { background: rgba(var(--bs-primary-rgb), .05); }
  .tbl-rinci tr.kas-univ td { font-style: italic; box-shadow: inset 0 0 0 999px rgba(var(--bs-info-rgb), .07); }
  .tbl-rinci tr.rek-tr .lipat { display: inline-block; transition: transform .15s; margin-right: .2rem; vertical-align: -1px; }
  .tbl-rinci tr.rek-tr.terbuka .lipat { transform: rotate(90deg); }
  .tbl-rinci tr.trx-row > td { padding: .1rem 0 .5rem 1.6rem; border-bottom: 0; box-shadow: inset 2px 0 0 rgba(var(--bs-info-rgb), .6); }
  .tbl-trx { width: 100%; font-size: .72rem; margin: 0; }
  .tbl-trx th { font-size: .64rem; text-transform: uppercase; color: var(--bs-secondary-color); padding: .2rem .45rem; border-bottom: 1px solid var(--bs-border-color); white-space: nowrap; }
  .tbl-trx td { padding: .15rem .45rem; border-bottom: 1px dotted var(--bs-border-color); }
  .tbl-trx td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .tbl-trx tr.awal td { font-style: italic; color: var(--bs-secondary-color); }
</style>
@endsection

@php
  $rp = fn ($n) => \App\Services\LaporanUnit::rp($n);
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y');
  $q = ['tahun' => $tahun, 'awal' => $awal, 'akhir' => $akhir, 'mode' => $mode];
  $neraca = $mode === 'neraca';        // hanya rekening kepala 1, 2, 3
  $aktivitas = $mode === 'aktivitas';  // halaman Penghasilan Komprehensif Unit (rekening 4, 5)
  $judul = $aktivitas ? 'Penghasilan Komprehensif Unit' : 'Laporan Buku Besar Unit';
  $kolomNilai = \App\Services\LaporanUnit::kolom($mode);
  $nilai = fn ($x) => \App\Services\LaporanUnit::nilai($mode, $x);
  $kolom = 5 + count($kolomNilai);
  $tot = ['n' => 0, 'd' => 0.0, 'k' => 0.0, 'p' => 0.0, 'b' => 0.0, 'l' => 0.0];
  foreach ($grup as $g) {
    foreach (array_keys($tot) as $k) {
      $tot[$k] += $g[$k];
    }
  }
  $jmlUnit = array_sum(array_map(fn ($g) => count($g['unit']), $grup));
  $tfTot = ['n' => array_sum(array_column($transfer, 'n')), 'nilai' => array_sum(array_column($transfer, 'nilai'))];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti {{ $aktivitas ? 'ti-chart-line' : 'ti-building-community' }}"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">{{ $judul }}</h4>
      <p class="pg-header-sub mb-0">T.A {{ $tahun }}/{{ $tahun + 1 }} · {{ $tglId($awal) }} s.d {{ $tglId($akhir) }} · unit terpetakan per induk FF.00</p>
    </div>
    <a class="btn btn-label-success" href="{{ route('akuntansi.laporan-unit.excel', $q) }}"><i class="ti ti-file-spreadsheet me-1"></i>Excel</a>
    <div class="btn-group">
      <a class="btn btn-label-danger" href="{{ route('akuntansi.laporan-unit.cetak', $q) }}" target="_blank" rel="noopener"><i class="ti ti-file-type-pdf me-1"></i>PDF / Cetak</a>
      <button type="button" class="btn btn-label-danger dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false"><span class="visually-hidden">Pilihan cetak</span></button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ route('akuntansi.laporan-unit.cetak', $q) }}" target="_blank" rel="noopener">Rekap unit</a></li>
        <li><a class="dropdown-item" href="{{ route('akuntansi.laporan-unit.cetak', $q + ['rinci' => 1]) }}" target="_blank" rel="noopener">Rekap + rincian rekening per induk</a></li>
      </ul>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-sitemap"></i><span><strong>{{ count($grup) }}</strong> unit induk · <strong>{{ $jmlUnit }}</strong> unit</span></span>
    <span class="pg-header-chip"><i class="ti ti-receipt"></i><span><strong>{{ number_format($tot['n'], 0, ',', '.') }}</strong> transaksi</span></span>
    @if ($neraca)
      <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Mode <strong>rekening neraca (1, 2, 3)</strong></span></span>
    @elseif ($aktivitas)
      <span class="pg-header-chip"><i class="ti ti-arrow-down-right"></i><span>Beban <strong>{{ $rp($tot['b']) }}</strong></span></span>
      <span class="pg-header-chip"><i class="ti ti-scale"></i><span>Surplus / (Defisit) <strong>{{ $rp($tot['p'] - $tot['b']) }}</strong></span></span>
    @endif
    @unless ($aktivitas)
      <a class="pg-header-chip text-reset" href="#kartuTransfer"><i class="ti ti-transfer-in"></i><span>Transfer Kas Univ <strong>{{ $rp($tfTot['nilai']) }}</strong></span></a>
    @endunless
  </div>
</div>

@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card">
  <div class="card-header border-bottom">
    <form method="GET" action="{{ route($aktivitas ? 'akuntansi.laporan-unit.aktivitas' : 'akuntansi.laporan-unit.index') }}" class="d-flex flex-wrap align-items-center gap-2">
      <select name="tahun" class="form-select" style="width: auto;" aria-label="Tahun anggaran" id="f-tahun">
        @foreach ($daftarTahun as $t)
          <option value="{{ $t }}" @selected($t === $tahun)>T.A {{ $t }}/{{ $t + 1 }}</option>
        @endforeach
      </select>
      <input type="date" name="awal" value="{{ $awal }}" class="form-control" style="width: auto;" aria-label="Tanggal awal" id="f-awal">
      <span class="text-muted">s/d</span>
      <input type="date" name="akhir" value="{{ $akhir }}" class="form-control" style="width: auto;" aria-label="Tanggal akhir" id="f-akhir">
      @unless ($aktivitas)
        <select name="mode" class="form-select" style="width: auto;" aria-label="Mode rekening">
          <option value="semua" @selected(!$neraca)>Semua rekening</option>
          <option value="neraca" @selected($neraca)>Neraca: kas/aset, kewajiban, aset neto (1, 2, 3)</option>
        </select>
      @endunless
      <button type="submit" class="btn btn-primary"><i class="ti ti-filter ti-xs me-1"></i>Tampilkan</button>
    </form>
    <small class="text-muted d-block mt-2">
      Hanya unit yang ada di pemetaan unit → prodi. Sub-unit (mis. 01.02, 01.03) dijumlahkan ke unit induk berkode akhir .00 (01.00).
      @if ($neraca)
        Mode neraca: hanya rekening berkode awal 1 (aset/kas), 2 (kewajiban), 3 (aset neto). Mutasi bersih = debet − kredit.
      @endif
      @if ($aktivitas)
        Susunan ISAK 35: Pendapatan = kredit − debet rekening 4 (kecuali 4.3); Beban = debet − kredit rekening 5 (kecuali 5.4);
        Surplus / (Defisit) = Pendapatan − Beban; Penghasilan komprehensif lain = 4.3 − 5.4; Total = Surplus + komprehensif lain.
        Jurnal penutup (TB) tidak dihitung.
      @else
        1.1.01.01.01.01 Kas Universitas (transfer dari universitas) ikut dihitung di debet/kredit dan saldo unit.
        Pendapatan, beban &amp; surplus/(defisit): <a href="{{ route('akuntansi.laporan-unit.aktivitas', ['tahun' => $tahun, 'awal' => $awal, 'akhir' => $akhir]) }}">Penghasilan Komprehensif Unit</a>.
      @endif
      Klik baris induk atau unit untuk rincian per rekening, lalu klik rekening untuk melihat transaksinya.
      @if ($rekNonaktif)
        <br><i class="ti ti-eye-off ti-xs"></i> Tidak ditampilkan (diatur admin): {{ implode('; ', $rekNonaktif) }}.
      @endif
    </small>
  </div>

  <div class="lu-wrap">
    <table class="table lu-tabel" id="tabelLu">
      <thead>
        <tr><th>Kode</th><th>Unit</th><th>Prodi / Fakultas</th><th>Kode NPM</th><th>Trx</th>
          @foreach ($kolomNilai as [$jdl]) <th>{{ $jdl }} (Rp)</th> @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse ($grup as $ff => $g)
          <tr class="induk bisa" data-ff="{{ $ff }}" title="Klik untuk rincian rekening seluruh unit {{ $g['kode_induk'] }}">
            <td class="kode"><i class="ti ti-chevron-right ti-xs lipat"></i>{{ $g['kode_induk'] }}</td>
            <td colspan="3">{{ $g['nama_induk'] }} <span class="badge bg-label-primary ms-1">{{ count($g['unit']) }} unit</span></td>
            <td class="n">{{ number_format($g['n'], 0, ',', '.') }}</td>
            @foreach ($nilai($g) as $i => $v)
              <td class="n {{ $kolomNilai[$i][1] && $v < 0 ? 'm' : '' }}">{{ $rp($v) }}</td>
            @endforeach
          </tr>
          @foreach ($g['unit'] as $u)
            <tr class="sub bisa" data-unit="{{ $u->id_unit }}" title="Klik untuk rincian rekening {{ $u->kode }}">
              <td class="kode"><i class="ti ti-chevron-right ti-xs lipat"></i>{{ $u->kode }}</td>
              <td class="nama">{{ $u->nama }}</td>
              <td>{{ ($u->jenis === 'fakultas' ? ($u->nama_fakultas ?: 'Fakultas') : trim($u->kode_prodi . ' ' . $u->jenjang_didik . ' ' . $u->nama_prodi)) ?: '—' }}</td>
              <td class="kode">{{ $u->kode_npm ?: '—' }}</td>
              <td class="n">{{ number_format($u->jml_transaksi, 0, ',', '.') }}</td>
              @foreach ($nilai($u) as $i => $v)
              <td class="n {{ $kolomNilai[$i][1] && $v < 0 ? 'm' : '' }}">{{ $rp($v) }}</td>
            @endforeach
            </tr>
          @endforeach
        @empty
          <tr><td colspan="{{ $kolom }}" class="text-center text-muted py-4">Belum ada unit di pemetaan unit → prodi (ueu_map_unit_prodi).</td></tr>
        @endforelse
      </tbody>
      @if ($grup)
        <tfoot>
          <tr>
            <td colspan="4" class="text-end">TOTAL</td>
            <td class="n">{{ number_format($tot['n'], 0, ',', '.') }}</td>
            @foreach ($nilai($tot) as $i => $v)
              <td class="n {{ $kolomNilai[$i][1] && $v < 0 ? 'm' : '' }}">{{ $rp($v) }}</td>
            @endforeach
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>

{{-- Jurnal transfer dari Kas Universitas: kredit 569 (pasangan debet 570) dari Akuntansi > Pencairan.
     Rekening 569 disembunyikan dari tabel di atas, jadi transfer ditampilkan terpisah di sini. --}}
@unless ($aktivitas)
<div class="card mt-4" id="kartuTransfer">
  <div class="card-header border-bottom">
    <h5 class="mb-1"><i class="ti ti-transfer-in me-1"></i>Jurnal Transfer dari Kas Universitas</h5>
    <small class="text-muted">
      Jurnal pencairan: debet 570 Kas di Unit Kerja / kredit 569 Kas Universitas, {{ $tglId($awal) }} s.d {{ $tglId($akhir) }}.
      Hanya unit terpetakan. Klik baris induk atau unit untuk melihat jurnalnya.
    </small>
  </div>
  <div class="lu-wrap">
    <table class="table lu-tabel" id="tabelTf">
      <thead>
        <tr><th>Kode</th><th>Unit</th><th>Jurnal</th><th>Transfer terakhir</th><th>Nilai transfer (Rp)</th></tr>
      </thead>
      <tbody>
        @forelse ($transfer as $ff => $g)
          <tr class="induk bisa" data-ff="{{ $ff }}" title="Klik untuk jurnal transfer seluruh unit {{ $g['kode_induk'] }}">
            <td class="kode"><i class="ti ti-chevron-right ti-xs lipat"></i>{{ $g['kode_induk'] }}</td>
            <td>{{ $g['nama_induk'] }} <span class="badge bg-label-primary ms-1">{{ count($g['unit']) }} unit</span></td>
            <td class="n">{{ number_format($g['n'], 0, ',', '.') }}</td>
            <td></td>
            <td class="n">{{ $rp($g['nilai']) }}</td>
          </tr>
          @foreach ($g['unit'] as $u)
            <tr class="sub bisa" data-unit="{{ $u->id_unit }}" title="Klik untuk jurnal transfer {{ $u->kode }}">
              <td class="kode"><i class="ti ti-chevron-right ti-xs lipat"></i>{{ $u->kode }}</td>
              <td class="nama">{{ $u->nama }}</td>
              <td class="n">{{ number_format($u->jml, 0, ',', '.') }}</td>
              <td class="text-nowrap">{{ $tglId($u->terakhir) }}</td>
              <td class="n">{{ $rp($u->nilai) }}</td>
            </tr>
          @endforeach
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada transfer dari Kas Universitas ke unit terpetakan pada periode ini.</td></tr>
        @endforelse
      </tbody>
      @if ($transfer)
        <tfoot>
          <tr>
            <td colspan="2" class="text-end">TOTAL</td>
            <td class="n">{{ number_format($tfTot['n'], 0, ',', '.') }}</td>
            <td></td>
            <td class="n">{{ $rp($tfTot['nilai']) }}</td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>
@endunless
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Ganti tahun => periode ikut T.A baru (01-09-T s/d 31-08-T+1).
  document.getElementById('f-tahun').addEventListener('change', function () {
    const t = parseInt(this.value, 10);
    document.getElementById('f-awal').value = t + '-09-01';
    document.getElementById('f-akhir').value = (t + 1) + '-08-31';
  });

  // ---- Rincian rekening (AJAX) untuk baris induk / unit ---------------------
  const urlRinci = {{ Js::from(route('akuntansi.laporan-unit.rinci')) }};
  const filter = {{ Js::from(['tahun' => $tahun, 'awal' => $awal, 'akhir' => $akhir, 'mode' => $mode]) }};
  const kolom = {{ $kolom }};
  const rp = (n, kosong) => {
    if (Math.abs(n) < 0.5) return kosong ? '' : '0';
    const t = Math.round(Math.abs(n)).toLocaleString('id-ID');
    return n < 0 ? '(' + t + ')' : t;
  };
  const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

  function tabelRinci(baris, ctx) {
    if (!baris.length) return '<div class="text-muted py-2">Tidak ada transaksi pada periode ini.</div>';
    let d = 0, k = 0;
    // data-ctx: unit/kelompok asal, dipakai saat memuat transaksi rekening.
    let h = '<table class="tbl-rinci" data-ctx="' + encodeURIComponent(JSON.stringify(ctx)) + '"><thead><tr><th>Kode rek.</th><th>Nama rekening</th><th>D/K</th><th class="text-end">Trx</th>'
      + '<th class="text-end">Saldo awal</th><th class="text-end">Debet</th><th class="text-end">Kredit</th><th class="text-end">Saldo akhir</th></tr></thead><tbody>';
    baris.forEach(b => {
      d += b.total_debet; k += b.total_kredit;   // termasuk Kas Universitas (baris kas-univ)
      h += '<tr class="rek-tr' + (b.kas_univ ? ' kas-univ' : '') + '" data-rek="' + b.idrek6 + '" title="Klik untuk melihat transaksi"><td class="text-nowrap font-monospace">'
        + '<i class="ti ti-chevron-right ti-xs lipat"></i>' + esc(b.kode_rek) + '</td><td>' + esc(b.nama_rek)
        + (b.kas_univ ? ' <span class="badge bg-label-info ms-1">transfer dari universitas</span>' : '') + '</td><td class="text-center">' + b.saldo_normal
        + '</td><td class="n">' + b.jml_transaksi.toLocaleString('id-ID') + '</td><td class="n">' + rp(b.saldo_awal) + '</td><td class="n">' + rp(b.total_debet, true)
        + '</td><td class="n">' + rp(b.total_kredit, true) + '</td><td class="n">' + rp(b.saldo_akhir) + '</td></tr>';
    });
    h += '</tbody><tfoot><tr><td colspan="5" class="text-end">Jumlah</td><td class="n">' + rp(d) + '</td><td class="n">' + rp(k) + '</td><td></td></tr></tfoot></table>';
    return h;
  }

  function toggle(tr) {
    let r = tr.nextElementSibling && tr.nextElementSibling.classList.contains('rinci') ? tr.nextElementSibling : null;
    if (r && r.dataset.gagal) { r.remove(); r = null; tr.classList.remove('terbuka'); }
    const buka = !tr.classList.contains('terbuka');
    tr.classList.toggle('terbuka', buka);
    if (!buka) { if (r) r.classList.add('d-none'); return; }
    if (r) { r.classList.remove('d-none'); return; }
    r = document.createElement('tr');
    r.className = 'rinci';
    r.innerHTML = '<td colspan="' + kolom + '"><div class="text-muted py-2"><span class="spinner-border spinner-border-sm me-1"></span>Memuat rincian…</div></td>';
    tr.after(r);
    const ctx = tr.dataset.unit ? { id_unit: tr.dataset.unit } : { ff: tr.dataset.ff };
    const q = new URLSearchParams(Object.assign({}, filter, ctx));
    fetch(urlRinci + '?' + q, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(x => { if (!x.ok) throw new Error('HTTP ' + x.status); return x.json(); })
      .then(d => { r.firstElementChild.innerHTML = tabelRinci(d.baris, ctx); })
      .catch(e => {
        // Pesan tetap tampil; klik berikutnya memuat ulang.
        r.dataset.gagal = '1';
        r.firstElementChild.innerHTML = '<div class="text-danger py-2">Gagal memuat rincian (' + esc(e.message) + '). Klik baris untuk mencoba lagi.</div>';
      });
  }
  document.querySelectorAll('#tabelLu tr.bisa').forEach(tr => tr.addEventListener('click', () => toggle(tr)));

  // ---- Transaksi per rekening: dimuat hanya saat baris rekening diklik -------
  // Hasil disimpan sebagai baris DOM; klik ulang hanya membuka/menutup tanpa memuat lagi.
  const urlTrx = {{ Js::from(route('akuntansi.laporan-unit.transaksi')) }};
  const tglId = s => { const [y, m, d] = String(s).split('-'); return d + '-' + m + '-' + y; };

  function tabelTrx(d, banyakUnit) {
    if (!d.baris.length) return '<div class="text-muted py-1">Tidak ada transaksi.</div>';
    let h = '<table class="tbl-trx"><thead><tr><th>Tanggal</th><th>Bukti</th>' + (banyakUnit ? '<th>Unit</th>' : '')
      + '<th>Uraian</th><th class="text-end">Debet</th><th class="text-end">Kredit</th><th class="text-end">Saldo</th></tr></thead><tbody>';
    d.baris.forEach(b => {
      h += '<tr class="' + (b.jenis === 0 ? 'awal' : '') + '"><td class="text-nowrap">' + tglId(b.tanggal) + '</td><td>' + esc(b.bukti) + '</td>'
        + (banyakUnit ? '<td class="font-monospace">' + esc(b.kode_unit) + '</td>' : '')
        + '<td>' + esc(b.uraian) + '</td><td class="n">' + (b.debet === null ? '' : rp(b.debet, true)) + '</td><td class="n">'
        + (b.kredit === null ? '' : rp(b.kredit, true)) + '</td><td class="n">' + rp(b.saldo) + '</td></tr>';
    });
    h += '</tbody></table>';
    if (d.terpotong) h += '<div class="text-warning small mt-1">Hanya ' + d.batas.toLocaleString('id-ID') + ' transaksi pertama yang ditampilkan; gunakan Buku Besar untuk selengkapnya.</div>';
    return h;
  }

  function toggleTrx(tr) {
    let r = tr.nextElementSibling && tr.nextElementSibling.classList.contains('trx-row') ? tr.nextElementSibling : null;
    if (r && r.dataset.gagal) { r.remove(); r = null; tr.classList.remove('terbuka'); }
    const buka = !tr.classList.contains('terbuka');
    tr.classList.toggle('terbuka', buka);
    if (!buka) { if (r) r.classList.add('d-none'); return; }
    if (r) { r.classList.remove('d-none'); return; }
    const ctx = JSON.parse(decodeURIComponent(tr.closest('table.tbl-rinci').dataset.ctx || '%7B%7D'));
    r = document.createElement('tr');
    r.className = 'trx-row';
    r.innerHTML = '<td colspan="8"><div class="text-muted py-1"><span class="spinner-border spinner-border-sm me-1"></span>Memuat transaksi…</div></td>';
    tr.after(r);
    const q = new URLSearchParams(Object.assign({}, filter, ctx, { idrek6: tr.dataset.rek }));
    fetch(urlTrx + '?' + q, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(x => { if (!x.ok) throw new Error('HTTP ' + x.status); return x.json(); })
      .then(d => { r.firstElementChild.innerHTML = tabelTrx(d, !ctx.id_unit); })
      .catch(e => {
        r.dataset.gagal = '1';
        r.firstElementChild.innerHTML = '<div class="text-danger py-1">Gagal memuat transaksi (' + esc(e.message) + '). Klik rekening untuk mencoba lagi.</div>';
      });
  }
  // ---- Jurnal transfer dari Kas Universitas (AJAX) ---------------------------
  const urlTf = {{ Js::from(route('akuntansi.laporan-unit.transfer')) }};

  function tabelTf(d, banyakUnit) {
    if (!d.baris.length) return '<div class="text-muted py-2">Tidak ada jurnal transfer.</div>';
    let t = 0;
    let h = '<table class="tbl-rinci"><thead><tr><th>Tanggal</th><th>Bukti</th>' + (banyakUnit ? '<th>Unit</th>' : '')
      + '<th>Kegiatan</th><th>Uraian</th><th class="text-end">Nilai</th></tr></thead><tbody>';
    d.baris.forEach(b => {
      t += b.nilai;
      h += '<tr><td class="text-nowrap">' + tglId(b.tanggal) + '</td><td>' + esc(b.bukti) + '</td>'
        + (banyakUnit ? '<td class="font-monospace">' + esc(b.kode_unit) + '</td>' : '')
        + '<td>' + esc(b.kegiatan) + '</td><td>' + esc(b.uraian) + '</td><td class="n">' + rp(b.nilai) + '</td></tr>';
    });
    h += '</tbody><tfoot><tr><td colspan="' + (banyakUnit ? 5 : 4) + '" class="text-end">Jumlah</td><td class="n">' + rp(t) + '</td></tr></tfoot></table>';
    if (d.terpotong) h += '<div class="text-warning small mt-1">Hanya ' + d.batas.toLocaleString('id-ID') + ' jurnal pertama yang ditampilkan; buka per unit untuk selengkapnya.</div>';
    return h;
  }

  function toggleTf(tr) {
    let r = tr.nextElementSibling && tr.nextElementSibling.classList.contains('rinci') ? tr.nextElementSibling : null;
    if (r && r.dataset.gagal) { r.remove(); r = null; tr.classList.remove('terbuka'); }
    const buka = !tr.classList.contains('terbuka');
    tr.classList.toggle('terbuka', buka);
    if (!buka) { if (r) r.classList.add('d-none'); return; }
    if (r) { r.classList.remove('d-none'); return; }
    r = document.createElement('tr');
    r.className = 'rinci';
    r.innerHTML = '<td colspan="5"><div class="text-muted py-2"><span class="spinner-border spinner-border-sm me-1"></span>Memuat jurnal…</div></td>';
    tr.after(r);
    const ctx = tr.dataset.unit ? { id_unit: tr.dataset.unit } : { ff: tr.dataset.ff };
    const q = new URLSearchParams(Object.assign({}, filter, ctx));
    fetch(urlTf + '?' + q, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(x => { if (!x.ok) throw new Error('HTTP ' + x.status); return x.json(); })
      .then(d => { r.firstElementChild.innerHTML = tabelTf(d, !ctx.id_unit); })
      .catch(e => {
        r.dataset.gagal = '1';
        r.firstElementChild.innerHTML = '<div class="text-danger py-2">Gagal memuat jurnal (' + esc(e.message) + '). Klik baris untuk mencoba lagi.</div>';
      });
  }
  document.querySelectorAll('#tabelTf tr.bisa').forEach(tr => tr.addEventListener('click', () => toggleTf(tr)));

  // Delegasi: tabel rincian dibuat dinamis, cukup satu listener di tabel utama.
  document.getElementById('tabelLu').addEventListener('click', function (e) {
    const tr = e.target.closest('tr.rek-tr');
    if (tr && this.contains(tr)) toggleTrx(tr);
  });
});
</script>
@endsection
