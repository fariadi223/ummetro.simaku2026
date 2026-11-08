{{-- Cetak Laporan Posisi Keuangan — Komparasi (T.A berjalan vs T.A lalu), kerangka sama dengan cetak tunggal. --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan Posisi Keuangan Komparasi {{ $tahun }}/{{ $tahun + 1 }} | SIMAKU UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <style>
    @page { size: A4 portrait; margin: 12mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Public Sans", Arial, Helvetica, sans-serif; font-size: 9.5pt; color: #222; background: #eef0f4; }
    .bilah { position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; justify-content: center; align-items: center; padding: 10px 16px; background: #2f2b3d; color: #fff; font-size: 13px; }
    .bilah button, .bilah a { border: 0; border-radius: 6px; padding: 7px 16px; font: inherit; cursor: pointer; text-decoration: none; }
    .bilah .utama { background: #7367f0; color: #fff; }
    .bilah .biasa { background: rgba(255,255,255,.15); color: #fff; }
    .kertas { max-width: 210mm; margin: 16px auto; padding: 12mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
    .kop { display: flex; align-items: center; gap: 12px; padding-bottom: 8px; border-bottom: 3px double #333; }
    .kop img { width: 56px; height: 56px; }
    .kop h1 { margin: 0; font-size: 12.5pt; }
    .kop p { margin: 1px 0 0; font-size: 8.5pt; color: #555; }
    .judul { text-align: center; margin: 12px 0 4px; }
    .judul h2 { margin: 0; font-size: 12.5pt; text-transform: uppercase; letter-spacing: .04em; }
    .judul p { margin: 3px 0 0; font-size: 9pt; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.data th, table.data td { border: 1px solid #999; padding: 3px 6px; vertical-align: top; }
    table.data th { background: #f1f1f4; font-size: 8pt; text-transform: uppercase; }
    table.data th small { display: block; font-weight: 400; text-transform: none; color: #555; }
    table.data td.rp { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; width: 17%; }
    table.data td.kode { font-family: "Courier New", monospace; white-space: nowrap; width: 15%; font-size: 8.5pt; }
    table.data tr.bagian td { background: #ecebfd; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
    table.data tr.lv3 td { font-weight: 600; }
    table.data tr.lv6 td { font-size: 8.5pt; color: #444; }
    table.data tr.lv6 td.nama { padding-left: 18px; }
    table.data tr.total td { font-weight: 700; background: #f7f7fa; }
    table.data tr.total-besar td { font-weight: 800; background: #ecebfd; }
    table.data .ket { display: block; font-size: 7.5pt; font-weight: 400; color: #666; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    .ttd { width: 100%; margin-top: 26px; border-collapse: collapse; page-break-inside: avoid; }
    .ttd td { width: 33.3%; text-align: center; vertical-align: top; padding: 0 6px; font-size: 9pt; }
    .ttd p { margin: 0; }
    .ttd .nm { margin-top: 56px; font-weight: 700; text-decoration: underline; }
    @media print {
      body { background: #fff; }
      .bilah { display: none; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
      table.data th, tr.bagian td, tr.total td, tr.total-besar td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
@php
  $rp = fn ($n) => abs($n) < 0.5 ? '0' : ($n < 0 ? '(' . number_format(abs($n), 0, ',', '.') . ')' : number_format($n, 0, ',', '.'));
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $kewNeto = $bagian['2']['total'] + $bagian['3']['total'];
  $kewNetoAwal = $bagian['2']['total_awal'] + $bagian['3']['total_awal'];
  $q = ['akhir' => $akhir, 'cetak' => 1];
  $kolomIni = $tahun . '/' . ($tahun + 1);
  $kolomLalu = ($tahun - 1) . '/' . $tahun;
@endphp
<div class="bilah">
  <span>Laporan Posisi Keuangan — Komparasi</span>
  <button type="button" class="utama" onclick="window.print()">Cetak / Simpan PDF</button>
  @if ($rinci)
    <a class="biasa" href="{{ route('akuntansi.posisi-keuangan.komparasi', $q) }}">Versi ringkas</a>
  @else
    <a class="biasa" href="{{ route('akuntansi.posisi-keuangan.komparasi', $q + ['rinci' => 1]) }}">Versi rinci</a>
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
    <h2>Laporan Posisi Keuangan (Komparasi)</h2>
    <p>Per {{ $tglId($akhir) }} dan {{ $tglId($tglLalu) }}</p>
  </div>

  <table class="data">
    <thead>
      <tr>
        <th>Kode Akun</th><th>Nama Akun</th>
        <th>T.A {{ $kolomIni }}<small>{{ $tglId($akhir) }}</small></th>
        <th>T.A {{ $kolomLalu }}<small>{{ $tglId($tglLalu) }}</small></th>
        <th>Kenaikan / (Penurunan)</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($bagian as $k1 => $bg)
        <tr class="bagian"><td colspan="5">{{ $bg['nama'] }}</td></tr>
        @forelse ($bg['baris'] as $b)
          @continue($b->lv === 6 && !$rinci)
          <tr class="lv{{ $b->lv }}">
            <td class="kode">{{ $b->kode }}</td>
            <td class="nama">{{ $b->nama }}
              @if ($b->lv === 3 && abs($b->surplus) >= 0.5)<span class="ket">termasuk surplus/defisit berjalan {{ $rp($b->surplus) }}</span>@endif
            </td>
            <td class="rp">{{ $rp($b->saldo) }}</td>
            <td class="rp">{{ $rp($b->saldo_awal) }}</td>
            <td class="rp">{{ $rp($b->saldo - $b->saldo_awal) }}</td>
          </tr>
        @empty
          <tr><td colspan="5" style="text-align: center; color: #777;">Tidak ada saldo.</td></tr>
        @endforelse
        <tr class="total"><td></td><td style="text-align: right;">TOTAL {{ mb_strtoupper($bg['nama']) }}</td>
          <td class="rp">{{ $rp($bg['total']) }}</td><td class="rp">{{ $rp($bg['total_awal']) }}</td><td class="rp">{{ $rp($bg['total'] - $bg['total_awal']) }}</td></tr>
      @endforeach
      <tr class="total-besar"><td></td><td style="text-align: right;">TOTAL LIABILITAS + ASET NETO</td>
        <td class="rp">{{ $rp($kewNeto) }}</td><td class="rp">{{ $rp($kewNetoAwal) }}</td><td class="rp">{{ $rp($kewNeto - $kewNetoAwal) }}</td></tr>
    </tbody>
  </table>


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
