{{-- Kerangka halaman cetak keluaran unit. Parameter: $judul, $ukuran ('A4 portrait'|'A4 landscape'), $unit, $tahun --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $judul }} | SIMAKU UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <style>
    @page { size: {{ $ukuran }}; margin: 11mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Public Sans", Arial, Helvetica, sans-serif; font-size: 9pt; color: #222; background: #eef0f4; }
    .bilah { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; align-items: center; padding: 10px 16px; background: #2f2b3d; color: #fff; font-size: 13px; }
    .bilah button { border: 0; border-radius: 6px; padding: 7px 16px; font: inherit; cursor: pointer; }
    .bilah .utama { background: #7367f0; color: #fff; }
    .bilah .biasa { background: rgba(255,255,255,.15); color: #fff; }
    .kertas { max-width: {{ str_contains($ukuran, 'landscape') ? '297mm' : '210mm' }}; margin: 16px auto; padding: 11mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
    .kop { display: flex; align-items: center; gap: 12px; padding-bottom: 8px; border-bottom: 3px double #333; }
    .kop img { width: 54px; height: 54px; }
    .kop h1 { margin: 0; font-size: 12.5pt; }
    .kop p { margin: 1px 0 0; font-size: 8.5pt; color: #555; }
    .judul { text-align: center; margin: 10px 0 2px; }
    .judul h2 { margin: 0; font-size: 12pt; text-transform: uppercase; letter-spacing: .03em; }
    .judul p { margin: 3px 0 0; font-size: 9pt; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data th, table.data td { border: 1px solid #999; padding: 3px 5px; vertical-align: top; }
    table.data th { background: #f1f1f4; font-size: 8pt; text-transform: uppercase; }
    table.data td.rp { text-align: right; white-space: nowrap; }
    table.data td.pre { white-space: pre-line; font-size: 8pt; }
    table.data tr.grup td { background: #ecebfd; font-weight: 700; }
    table.data tr.total td { font-weight: 700; }
    table.data tr.surplus td { font-weight: 700; background: #fff8d6; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .ttd { margin-top: 24px; display: flex; justify-content: flex-end; page-break-inside: avoid; }
    .ttd div { text-align: center; min-width: 240px; }
    .ttd .nama { margin-top: 56px; font-weight: 700; text-decoration: underline; }
    @media print {
      body { background: #fff; }
      .bilah { display: none; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
      table.data th, tr.grup td, tr.surplus td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
<div class="bilah">
  <span>{{ $judul }}</span>
  <button type="button" class="utama" onclick="window.print()">Cetak / Simpan PDF</button>
  <button type="button" class="biasa" onclick="window.close()">Tutup</button>
</div>
<div class="kertas">
  <div class="kop">
    <img src="{{ asset('themes_v1/assets/images/logoummetro_3.png') }}" alt="Logo UM Metro">
    <div>
      <h1>UNIVERSITAS MUHAMMADIYAH METRO</h1>
      <p>Jl. Ki Hajar Dewantara No. 116 Kota Metro · Telp. (0725) 42445-42454</p>
    </div>
  </div>
  <div class="judul">
    <h2>{{ $judul }}</h2>
    <p>{{ mb_strtoupper($unit->nama) }} · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
  </div>
