{{-- Cetak Laporan Penghasilan Komprehensif (HTML siap print / simpan PDF), A4 landscape, kerangka sama dengan cetak posisi keuangan. --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laporan Penghasilan Komprehensif {{ $tahun }}/{{ $tahun + 1 }} | SIMAKU UM Metro</title>
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
    h3.bagian { margin: 12px 0 0; padding: 4px 6px; font-size: 9pt; text-transform: uppercase; letter-spacing: .03em; background: #ecebfd; }
    h3.bagian.utama { background: #dcdaf9; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.data th, table.data td { border: 1px solid #999; padding: 2px 5px; vertical-align: top; }
    table.data th { background: #f1f1f4; font-size: 7.5pt; text-transform: uppercase; text-align: center; }
    table.data td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; width: 10.5%; }
    table.data td.j { font-weight: 700; }
    table.data td.kode { font-family: "Courier New", monospace; white-space: nowrap; width: 11%; font-size: 8pt; }
    table.data td.ka { text-align: right; }
    table.data td.kosong { text-align: center; color: #777; }
    table.data tr.lv3 td { font-weight: 600; }
    table.data tr.lv6 td { font-size: 7.8pt; color: #444; }
    table.data tr.lv6 td.nama { padding-left: 16px; }
    table.data tr.total td { font-weight: 700; background: #f7f7fa; }
    table.data tr.hasil td { font-weight: 800; background: #ecebfd; }
    thead { display: table-header-group; }
    /* Judul bagian + header tabel tidak boleh tertinggal sendiri di bawah halaman: satu bagian dipindah utuh
       ke halaman berikutnya bila tidak muat (bagian yang lebih panjang dari satu halaman tetap terpotong,
       dengan header tabel diulang). */
    .blok { break-inside: avoid; page-break-inside: avoid; }
    h3.bagian { break-after: avoid; page-break-after: avoid; }
    table.data thead, table.data tbody tr:first-child { break-after: avoid; page-break-after: avoid; }
    tr { page-break-inside: avoid; }
    .ttd { width: 100%; margin-top: 22px; border-collapse: collapse; page-break-inside: avoid; }
    .ttd td { width: 33.3%; text-align: center; vertical-align: top; padding: 0 6px; font-size: 9pt; }
    .ttd p { margin: 0; }
    .ttd .nm { margin-top: 52px; font-weight: 700; text-decoration: underline; }
    @media print {
      body { background: #fff; }
      .bilah { display: none; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
      table.data th, h3.bagian, tr.total td, tr.hasil td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
@php
  $rp = fn ($n) => abs($n) < 0.5 ? '0' : ($n < 0 ? '(' . number_format(abs($n), 0, ',', '.') . ')' : number_format($n, 0, ',', '.'));
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d F Y');
  $kolom = ['tp', 'dp', 'jml', 'tp_lalu', 'dp_lalu', 'jml_lalu'];
  $q = ['awal' => $awal, 'akhir' => $akhir, 'cetak' => 1] + ($mode === 'tanggal' ? ['mode' => 'tanggal'] : []);
  $hasil = fn ($label, $o) => '<tr class="hasil"><td></td><td class="ka">' . e($label) . '</td>'
    . implode('', array_map(fn ($c) => '<td class="n">' . $rp($o->$c) . '</td>', $kolom)) . '</tr>';
@endphp
<div class="bilah">
  <span>Laporan Penghasilan Komprehensif</span>
  <button type="button" class="utama" onclick="window.print()">Cetak / Simpan PDF</button>
  @if ($rinci)
    <a class="biasa" href="{{ route('akuntansi.penghasilan-komprehensif.index', $q) }}">Versi ringkas</a>
  @else
    <a class="biasa" href="{{ route('akuntansi.penghasilan-komprehensif.index', $q + ['rinci' => 1]) }}">Versi rinci</a>
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
    <h2>Laporan Penghasilan Komprehensif</h2>
    <p>Tahun Anggaran {{ $tahun }}/{{ $tahun + 1 }} · Periode {{ $tglId($awal) }} s.d {{ $tglId($akhir) }}</p>
  </div>

  @foreach (['pendapatan', 'biaya'] as $k)
    <div class="blok">
    <h3 class="bagian">{{ $bagian[$k]['judul'] }}</h3>
    @include('content.akuntansi.penghasilan-komprehensif._tabel', ['bg' => $bagian[$k], 'layar' => false])
    </div>
  @endforeach
  <table class="data"><tbody>{!! $hasil($surplus->jml < 0 ? 'DEFISIT' : 'SURPLUS', $surplus) !!}</tbody></table>

  <h3 class="bagian utama" style="break-after: avoid; page-break-after: avoid;">Penghasilan Komprehensif Lain</h3>
  @foreach (['pendapatan_lain', 'biaya_lain'] as $k)
    <div class="blok">
    <h3 class="bagian">{{ $bagian[$k]['judul'] }}</h3>
    @include('content.akuntansi.penghasilan-komprehensif._tabel', ['bg' => $bagian[$k], 'layar' => false])
    </div>
  @endforeach
  <table class="data"><tbody>
    {!! $hasil('PENGHASILAN KOMPREHENSIF LAIN', $lain) !!}
    {!! $hasil('PENGHASILAN KOMPREHENSIF PERIODE BERJALAN', $komprehensif) !!}
  </tbody></table>

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
