{{-- Form rincian pendapatan/belanja kegiatan, padanan unit/kegiatan/rinci (rinci_cetak) di v1. --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rincian {{ $keg->uraiankeg }} | SIMAKU UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <style>
    @page { size: A4 portrait; margin: 12mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Public Sans", Arial, Helvetica, sans-serif; font-size: 9.5pt; color: #222; background: #eef0f4; }
    .bilah { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; align-items: center; padding: 10px 16px; background: #2f2b3d; color: #fff; font-size: 13px; }
    .bilah button { border: 0; border-radius: 6px; padding: 7px 16px; font: inherit; cursor: pointer; }
    .bilah .utama { background: #7367f0; color: #fff; }
    .bilah .biasa { background: rgba(255,255,255,.15); color: #fff; }
    .kertas { max-width: 210mm; margin: 16px auto; padding: 12mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
    .kop { display: flex; justify-content: space-between; align-items: flex-end; gap: 14px; padding-bottom: 10px; border-bottom: 3px double #333; }
    .kop .kiri { display: flex; align-items: center; gap: 12px; }
    .kop img { width: 56px; height: 56px; }
    .kop h1 { margin: 0; font-size: 12.5pt; }
    .kop p { margin: 1px 0 0; font-size: 8.5pt; color: #555; }
    .kop .kanan { text-align: right; font-size: 9pt; }
    .kop .kanan h2 { margin: 0 0 4px; font-size: 11pt; }
    table.info { width: 100%; border-collapse: collapse; margin: 12px 0; }
    table.info th { text-align: left; width: 26%; vertical-align: top; padding: 2px 0; font-weight: 600; }
    table.info td { vertical-align: top; padding: 2px 0; white-space: pre-line; }
    table.info td.t { width: 2%; }
    table.rinci { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.rinci th, table.rinci td { border: 1px solid #999; padding: 4px 6px; vertical-align: top; }
    table.rinci th { background: #f1f1f4; font-size: 8.5pt; text-transform: uppercase; }
    table.rinci td.rp { text-align: right; white-space: nowrap; }
    table.rinci td.kode { font-family: "Courier New", monospace; white-space: nowrap; }
    table.rinci tr.kelompok td { background: #ecebfd; font-weight: 700; }
    table.rinci tr.total td { font-weight: 700; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .ttd { margin-top: 28px; display: flex; justify-content: flex-end; }
    .ttd div { text-align: center; min-width: 240px; }
    .ttd .nama { margin-top: 60px; font-weight: 700; text-decoration: underline; }
    @media print {
      body { background: #fff; }
      .bilah { display: none; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
      table.rinci th, tr.kelompok td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
@php
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
  $pendapatan = ($rinci['pendapatan'] ?? collect());
  $belanja = ($rinci['belanja'] ?? collect());
  $selisih = $pendapatan->sum('jumlah') - $belanja->sum('jumlah');
@endphp
<div class="bilah">
  <span>Form rincian kegiatan</span>
  <button type="button" class="utama" onclick="window.print()">Cetak / Simpan PDF</button>
  <button type="button" class="biasa" onclick="window.close()">Tutup</button>
</div>

<div class="kertas">
  <div class="kop">
    <div class="kiri">
      <img src="{{ asset('themes_v1/assets/images/logoummetro_3.png') }}" alt="Logo UM Metro">
      <div>
        <h1>UNIVERSITAS MUHAMMADIYAH METRO</h1>
        <p>Jl. Ki Hajar Dewantara No. 116 Kota Metro</p>
        <p>Telp. (0725) 42445-42454 Fax. (0725) 42445</p>
      </div>
    </div>
    <div class="kanan">
      <h2>FORM RINCIAN PENDAPATAN/BELANJA</h2>
      <div><strong>T.A:</strong> {{ $tahun }}/{{ $tahun + 1 }}</div>
      <div><strong>MAK:</strong> {{ $keg->kode_mak }}</div>
    </div>
  </div>

  <table class="info">
    <tr><th>Sub Unit Kerja</th><td class="t">:</td><td>{{ mb_strtoupper($unit->nama) }}</td></tr>
    <tr><th>Nama Proses Bisnis</th><td class="t">:</td><td>{{ $keg->namamak1 }}</td></tr>
    <tr><th>Nama Kegiatan</th><td class="t">:</td><td>{{ $keg->namamak4 }}</td></tr>
    <tr><th>Uraian Kegiatan</th><td class="t">:</td><td>{{ $keg->uraiankeg }}</td></tr>
    <tr><th>Latar Belakang</th><td class="t">:</td><td>{{ $keg->latblk }}</td></tr>
    <tr><th>Tujuan</th><td class="t">:</td><td>{{ $keg->tujuan }}</td></tr>
    <tr><th>Output</th><td class="t">:</td><td>{{ $keg->lingkup }}</td></tr>
    <tr><th>Jadwal</th><td class="t">:</td><td>{{ $keg->jadwal }}</td></tr>
    <tr><th>Sumber Dana</th><td class="t">:</td><td>{{ $keg->nama_pagu ?: $keg->idsumberdana }}</td></tr>
  </table>

  <table class="rinci">
    <thead>
      <tr><th style="width: 15%;">No. Rek</th><th>Uraian</th><th>Volume</th><th style="width: 13%;">Harga Satuan</th><th style="width: 14%;">Jumlah</th></tr>
    </thead>
    <tbody>
      @foreach (['Pendapatan' => $pendapatan, 'Belanja' => $belanja] as $judul => $daftar)
        <tr class="kelompok"><td colspan="5">Rincian {{ $judul }}</td></tr>
        @forelse ($daftar as $r)
          <tr>
            <td class="kode">{{ $r->kode_rek }}</td>
            <td>{{ $r->namarek6 }}<br><small>{{ $r->uraianrinci }}</small></td>
            <td>{{ $r->volume }}</td>
            <td class="rp">{{ $rp($r->hargasat) }}</td>
            <td class="rp">{{ $rp($r->jumlah) }}</td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align: center; color: #777;">—</td></tr>
        @endforelse
        <tr class="total"><td colspan="4" style="text-align: right;">Total Rincian {{ $judul }}</td><td class="rp">{{ $rp($daftar->sum('jumlah')) }}</td></tr>
      @endforeach
      <tr class="total"><td colspan="4" style="text-align: right;">{{ $selisih >= 0 ? 'Surplus' : 'Defisit' }}</td><td class="rp">{{ $rp(abs($selisih)) }}</td></tr>
    </tbody>
  </table>

  <div class="ttd">
    <div>
      Metro, {{ now()->locale('id')->translatedFormat('d F Y') }}<br>
      {{ $unit->nama }}
      <div class="nama">{{ $unit->nama_pimp }}</div>
      @if ($unit->nip)<div>NIP. {{ $unit->nip }}</div>@endif
    </div>
  </div>
</div>
<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
