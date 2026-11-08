{{-- Cetak Laporan Perubahan Aset Neto (HTML siap print / simpan PDF), kerangka sama dengan cetak laporan keuangan lain. --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan Perubahan Aset Neto {{ $tahun }}/{{ $tahun + 1 }} | SIMAKU UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <style>
    @page { size: A4 portrait; margin: 12mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Public Sans", Arial, Helvetica, sans-serif; font-size: 9.5pt; color: #222; background: #eef0f4; }
    .bilah { position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; justify-content: center; align-items: center; padding: 10px 16px; background: #2f2b3d; color: #fff; font-size: 13px; }
    .bilah button { border: 0; border-radius: 6px; padding: 7px 16px; font: inherit; cursor: pointer; }
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
    .blok { break-inside: avoid; page-break-inside: avoid; margin-top: 10px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border: 1px solid #999; padding: 3px 6px; vertical-align: top; }
    table.data th { background: #f1f1f4; font-size: 8pt; text-transform: uppercase; }
    table.data th.judul { text-align: left; background: #ecebfd; font-size: 8.5pt; }
    table.data td.n, table.data th.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; width: 26%; }
    table.data td.ind { padding-left: 18px; }
    table.data td.ka { text-align: right; }
    table.data tr.sub td { font-weight: 700; background: #f7f7fa; }
    table.data tr.total td { font-weight: 700; }
    table.data tr.akhir td { font-weight: 800; background: #ecebfd; }
    tr { page-break-inside: avoid; }
    .ttd { width: 100%; margin-top: 26px; border-collapse: collapse; page-break-inside: avoid; }
    .ttd td { width: 33.3%; text-align: center; vertical-align: top; padding: 0 6px; font-size: 9pt; }
    .ttd p { margin: 0; }
    .ttd .nm { margin-top: 56px; font-weight: 700; text-decoration: underline; }
    @media print {
      body { background: #fff; }
      .bilah { display: none; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
      table.data th, tr.sub td, tr.akhir td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
@php
  $rp = fn ($n) => abs($n) < 0.5 ? '0' : ($n < 0 ? '(' . number_format(abs($n), 0, ',', '.') . ')' : number_format($n, 0, ',', '.'));
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $tanggalMode = $mode === 'tanggal';
@endphp
<div class="bilah">
  <span>Laporan Perubahan Aset Neto</span>
  <button type="button" class="utama" onclick="window.print()">Cetak / Simpan PDF</button>
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
    <h2>Laporan Perubahan Aset Neto</h2>
    <p>Tahun Anggaran {{ $tahun }}/{{ $tahun + 1 }} · Periode {{ $tglId($awal) }} s.d {{ $tglId($akhir) }}</p>
  </div>

  @foreach (['tp' => 'Aset Neto Tanpa Pembatasan', 'dp' => 'Aset Neto Dengan Pembatasan'] as $k => $judul)
    <div class="blok">@include('content.akuntansi.perubahan-aset-neto._tabel', ['judul' => $judul, 'k' => $k, 'layar' => false])</div>
  @endforeach
  <div class="blok">
    <table class="data"><tbody>
      <tr class="akhir"><td class="ka">TOTAL ASET NETO</td><td class="n">{{ $rp($ini['total']['saldo_akhir']) }}</td><td class="n">{{ $rp($lalu['total']['saldo_akhir']) }}</td></tr>
    </tbody></table>
  </div>

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
