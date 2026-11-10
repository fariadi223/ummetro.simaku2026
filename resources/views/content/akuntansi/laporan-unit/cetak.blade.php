{{-- Cetak Laporan Keuangan Unit (HTML siap print / "Simpan sebagai PDF"); ?rinci=1 menyertakan rincian rekening per induk. --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan Keuangan Unit {{ $tahun }}/{{ $tahun + 1 }} | SIMAKU UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <style>
    @page { size: A4 landscape; margin: 10mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Public Sans", Arial, Helvetica, sans-serif; font-size: 8.5pt; color: #222; background: #eef0f4; }
    .bilah { position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; justify-content: center; align-items: center; padding: 10px 16px; background: #2f2b3d; color: #fff; font-size: 13px; }
    .bilah button, .bilah a { border: 0; border-radius: 6px; padding: 7px 16px; font: inherit; cursor: pointer; text-decoration: none; }
    .bilah .utama { background: #7367f0; color: #fff; }
    .bilah .biasa { background: rgba(255,255,255,.15); color: #fff; }
    .kertas { max-width: 297mm; margin: 16px auto; padding: 10mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
    .kop { display: flex; align-items: center; gap: 12px; padding-bottom: 8px; border-bottom: 3px double #333; }
    .kop img { width: 52px; height: 52px; }
    .kop h1 { margin: 0; font-size: 12pt; }
    .kop p { margin: 1px 0 0; font-size: 8pt; color: #555; }
    .judul { text-align: center; margin: 10px 0 4px; }
    .judul h2 { margin: 0; font-size: 12pt; text-transform: uppercase; letter-spacing: .04em; }
    .judul p { margin: 3px 0 0; font-size: 9pt; }
    h3 { font-size: 10pt; margin: 16px 0 4px; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.data th, table.data td { border: 1px solid #999; padding: 2px 5px; vertical-align: top; }
    table.data th { background: #f1f1f4; font-size: 7.5pt; text-transform: uppercase; }
    table.data td.rp { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    table.data td.kode { font-family: "Courier New", monospace; white-space: nowrap; font-size: 8pt; }
    table.data tr.induk td { background: #ecebfd; font-weight: 700; }
    table.data tr.sub td.nama { padding-left: 16px; }
    table.data tr.total td { font-weight: 800; background: #ecebfd; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .ttd { width: 100%; margin-top: 22px; border-collapse: collapse; page-break-inside: avoid; }
    .ttd td { width: 33.3%; text-align: center; vertical-align: top; padding: 0 6px; font-size: 9pt; }
    .ttd p { margin: 0; }
    .ttd .nm { margin-top: 52px; font-weight: 700; text-decoration: underline; }
    @media print {
      body { background: #fff; }
      .bilah { display: none; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
      table.data th, tr.induk td, tr.total td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
@php
  $rp = fn ($n) => \App\Services\LaporanUnit::rp($n);
  $rpk = fn ($n) => \App\Services\LaporanUnit::rp($n, true);
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $tot = ['n' => 0, 'd' => 0.0, 'k' => 0.0, 'p' => 0.0, 'b' => 0.0];
  foreach ($grup as $g) {
    foreach (array_keys($tot) as $k) {
      $tot[$k] += $g[$k];
    }
  }
  $q = ['tahun' => $tahun, 'awal' => $awal, 'akhir' => $akhir, 'mode' => $mode];
  $neraca = $mode === 'neraca';
@endphp
<div class="bilah">
  <span>Laporan Keuangan Unit</span>
  <button type="button" class="utama" onclick="window.print()">Cetak / Simpan PDF</button>
  @if ($rinci)
    <a class="biasa" href="{{ route('akuntansi.laporan-unit.cetak', $q) }}">Tanpa rincian rekening</a>
  @else
    <a class="biasa" href="{{ route('akuntansi.laporan-unit.cetak', $q + ['rinci' => 1]) }}">Dengan rincian rekening</a>
  @endif
  <button type="button" class="biasa" onclick="window.close()">Tutup</button>
</div>

<div class="kertas">
  <div class="kop">
    <img src="{{ asset('themes_v1/assets/images/logoummetro_3.png') }}" alt="Logo UM Metro">
    <div>
      <h1>UNIVERSITAS MUHAMMADIYAH METRO</h1>
      <p>Jl. Ki Hajar Dewantara No. 116 Kota Metro · Telp. (0725) 42445-42454 Fax. (0725) 42445</p>
    </div>
  </div>

  <div class="judul">
    <h2>Laporan Keuangan Unit</h2>
    <p>Tahun Anggaran {{ $tahun }}/{{ $tahun + 1 }} · {{ $tglId($awal) }} s.d {{ $tglId($akhir) }}</p>
    @if ($neraca)<p>Rekening neraca: kepala 1 (aset/kas), 2 (kewajiban), 3 (aset neto)</p>@endif
  </div>

  <table class="data">
    <thead><tr><th>Kode</th><th>Unit</th><th>Prodi</th><th>NPM</th><th>Trx</th><th>Debet (Rp)</th><th>Kredit (Rp)</th>@if ($neraca)<th>Mutasi Bersih D−K (Rp)</th>@else<th>Pendapatan (Rp)</th><th>Beban (Rp)</th><th>Surplus/(Defisit) (Rp)</th>@endif</tr></thead>
    <tbody>
      @forelse ($grup as $g)
        <tr class="induk">
          <td class="kode">{{ $g['kode_induk'] }}</td><td colspan="3">{{ $g['nama_induk'] }}</td>
          <td class="rp">{{ number_format($g['n'], 0, ',', '.') }}</td><td class="rp">{{ $rp($g['d']) }}</td><td class="rp">{{ $rp($g['k']) }}</td>
          @if ($neraca)<td class="rp">{{ $rp($g['d'] - $g['k']) }}</td>@else<td class="rp">{{ $rp($g['p']) }}</td><td class="rp">{{ $rp($g['b']) }}</td><td class="rp">{{ $rp($g['p'] - $g['b']) }}</td>@endif
        </tr>
        @foreach ($g['unit'] as $u)
          <tr class="sub">
            <td class="kode">{{ $u->kode }}</td><td class="nama">{{ $u->nama }}</td>
            <td>{{ ($u->jenis === 'fakultas' ? ($u->nama_fakultas ?: 'Fakultas') : trim($u->kode_prodi . ' ' . $u->jenjang_didik . ' ' . $u->nama_prodi)) }}</td><td class="kode">{{ $u->kode_npm }}</td>
            <td class="rp">{{ number_format($u->jml_transaksi, 0, ',', '.') }}</td><td class="rp">{{ $rp($u->total_debet) }}</td><td class="rp">{{ $rp($u->total_kredit) }}</td>
            @if ($neraca)<td class="rp">{{ $rp($u->total_debet - $u->total_kredit) }}</td>@else<td class="rp">{{ $rp($u->pendapatan) }}</td><td class="rp">{{ $rp($u->beban) }}</td><td class="rp">{{ $rp($u->surplus) }}</td>@endif
          </tr>
        @endforeach
      @empty
        <tr><td colspan="{{ $neraca ? 8 : 10 }}" style="text-align: center; color: #777;">Belum ada unit terpetakan.</td></tr>
      @endforelse
      <tr class="total">
        <td colspan="4" style="text-align: right;">TOTAL</td>
        <td class="rp">{{ number_format($tot['n'], 0, ',', '.') }}</td><td class="rp">{{ $rp($tot['d']) }}</td><td class="rp">{{ $rp($tot['k']) }}</td>
        @if ($neraca)<td class="rp">{{ $rp($tot['d'] - $tot['k']) }}</td>@else<td class="rp">{{ $rp($tot['p']) }}</td><td class="rp">{{ $rp($tot['b']) }}</td><td class="rp">{{ $rp($tot['p'] - $tot['b']) }}</td>@endif
      </tr>
    </tbody>
  </table>

  @foreach ($rinci as $ff => $rek)
    <h3>Rincian rekening {{ $grup[$ff]['kode_induk'] }} — {{ $grup[$ff]['nama_induk'] }}</h3>
    <table class="data">
      <thead><tr><th>Kode Rek.</th><th>Nama Rekening</th><th>D/K</th><th>Saldo Awal (Rp)</th><th>Debet (Rp)</th><th>Kredit (Rp)</th><th>Saldo Akhir (Rp)</th></tr></thead>
      <tbody>
        @forelse ($rek as $b)
          <tr>
            <td class="kode">{{ $b->kode_rek }}</td><td>{{ $b->nama_rek }}</td><td style="text-align: center;">{{ $b->saldo_normal }}</td>
            <td class="rp">{{ $rp($b->saldo_awal) }}</td><td class="rp">{{ $rpk($b->total_debet) }}</td><td class="rp">{{ $rpk($b->total_kredit) }}</td><td class="rp">{{ $rp($b->saldo_akhir) }}</td>
          </tr>
        @empty
          <tr><td colspan="7" style="text-align: center; color: #777;">Tidak ada transaksi.</td></tr>
        @endforelse
      </tbody>
    </table>
  @endforeach

  @if (!empty($ttd))
    <table class="ttd">
      <tr><td colspan="{{ count($ttd) }}" style="text-align: right; padding-bottom: 8px;">Metro, {{ $tglId(now()) }}</td></tr>
      <tr>
        @foreach ($ttd as [$ket, $jabatan, $nama, $nbm])
          <td><p>{{ $ket }}</p><p>{{ $jabatan }}</p><p class="nm">{{ $nama }}</p><p>{{ $nbm }}</p></td>
        @endforeach
      </tr>
    </table>
  @endif
</div>
<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
