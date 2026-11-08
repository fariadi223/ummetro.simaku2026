{{-- Halaman cetak daftar master biaya, dikelompokkan per jenis. --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cetak Master Biaya | SIMAKU UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <style>
    @page { size: A4 portrait; margin: 12mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Public Sans", Arial, Helvetica, sans-serif; font-size: 9.5pt; color: #222; background: #eef0f4; }
    .bilah { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; align-items: center;
             padding: 10px 16px; background: #2f2b3d; color: #fff; font-size: 13px; }
    .bilah button { border: 0; border-radius: 6px; padding: 7px 16px; font: inherit; cursor: pointer; }
    .bilah .utama { background: #7367f0; color: #fff; }
    .bilah .biasa { background: rgba(255,255,255,.15); color: #fff; }
    .kertas { max-width: 210mm; margin: 16px auto; padding: 12mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
    .kop { display: flex; align-items: center; gap: 14px; padding-bottom: 10px; border-bottom: 3px double #333; }
    .kop img { width: 60px; height: 60px; }
    .kop h1 { margin: 0; font-size: 15pt; }
    .kop p { margin: 2px 0 0; font-size: 9.5pt; color: #555; }
    .judul { text-align: center; margin: 12px 0 4px; }
    .judul h2 { margin: 0; font-size: 13pt; text-transform: uppercase; letter-spacing: .04em; }
    .judul p { margin: 4px 0 0; font-size: 9pt; color: #555; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    thead { display: table-header-group; }
    th, td { border: 1px solid #bbb; padding: 3px 6px; vertical-align: top; }
    th { background: #f1f1f4; font-size: 8.5pt; text-transform: uppercase; }
    tr { page-break-inside: avoid; }
    td.c { text-align: center; width: 32px; }
    td.rp { text-align: right; white-space: nowrap; }
    tr.jenis td { background: #ecebfd; font-weight: 700; text-transform: uppercase; }
    @media print {
      body { background: #fff; }
      .bilah { display: none; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
      tr.jenis td, th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
<div class="bilah">
  <span>{{ $grup->flatten()->count() }} item siap dicetak</span>
  <button type="button" class="utama" onclick="window.print()">Cetak / Simpan PDF</button>
  <button type="button" class="biasa" onclick="window.close()">Tutup</button>
</div>

<div class="kertas">
  <div class="kop">
    <img src="{{ asset('themes_v1/assets/images/logoummetro_3.png') }}" alt="Logo UM Metro">
    <div>
      <h1>UNIVERSITAS MUHAMMADIYAH METRO</h1>
      <p>Sistem Informasi Manajemen dan Akuntansi Keuangan (SIMAKU)</p>
    </div>
  </div>

  <div class="judul">
    <h2>Daftar Master Biaya{{ $grup->count() === 1 ? ' — ' . ($jenis[$grup->keys()->first()] ?? '') : '' }}</h2>
    <p>Dicetak {{ now()->locale('id')->translatedFormat('d F Y, H:i') }} oleh {{ auth()->user()->name }}</p>
  </div>

  <table>
    <thead>
      <tr>
        <th>No</th>
        <th>Uraian Item</th>
        <th>Standar Biaya (Rp)</th>
        <th>Satuan / Keterangan</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($grup as $kode => $item)
        <tr class="jenis"><td colspan="5">{{ $jenis[$kode] ?? $kode }} ({{ $item->count() }})</td></tr>
        @foreach ($item as $b)
          <tr>
            <td class="c">{{ $loop->iteration }}</td>
            <td>{{ $b->item }}</td>
            <td class="rp">{{ $b->rentang(false) ?? '-' }}</td>
            <td>{{ $b->ket }}</td>
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>
</div>

<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
