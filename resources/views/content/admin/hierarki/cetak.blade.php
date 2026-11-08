{{-- Halaman cetak seluruh data berjenjang (COA / MAK). Berdiri sendiri, tanpa layout aplikasi. --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cetak {{ $meta['judul'] }} | SIMAKU UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <style>
    @page { size: A4 portrait; margin: 14mm 12mm 16mm; }

    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Public Sans", Arial, Helvetica, sans-serif; font-size: 10.5pt; color: #222; background: #eef0f4; }

    /* Bilah aksi di layar (tidak ikut tercetak) */
    .bilah { position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; justify-content: center; align-items: center;
             padding: 10px 16px; background: #2f2b3d; color: #fff; font-size: 13px; }
    .bilah button { border: 0; border-radius: 6px; padding: 7px 16px; font: inherit; cursor: pointer; }
    .bilah .utama { background: #7367f0; color: #fff; }
    .bilah .biasa { background: rgba(255,255,255,.15); color: #fff; }

    .kertas { max-width: 210mm; margin: 16px auto; padding: 14mm 12mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }

    .kop { display: flex; align-items: center; gap: 14px; padding-bottom: 10px; border-bottom: 3px double #333; }
    .kop img { width: 64px; height: 64px; }
    .kop h1 { margin: 0; font-size: 15pt; letter-spacing: .02em; }
    .kop p { margin: 2px 0 0; font-size: 9.5pt; color: #555; }

    .judul { text-align: center; margin: 14px 0 4px; }
    .judul h2 { margin: 0; font-size: 13pt; text-transform: uppercase; letter-spacing: .04em; }
    .judul p { margin: 4px 0 0; font-size: 9pt; color: #555; }

    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    thead { display: table-header-group; } /* ulang header di tiap halaman */
    th, td { border: 1px solid #bbb; padding: 4px 6px; vertical-align: top; }
    th { background: #f1f1f4; font-size: 9pt; text-transform: uppercase; letter-spacing: .03em; }
    tr { page-break-inside: avoid; }
    td.no { text-align: right; color: #666; width: 34px; }
    td.kode { font-family: "Courier New", monospace; white-space: nowrap; width: 1%; }
    td.angka { text-align: center; width: 60px; }

    /* Penekanan per level: makin atas makin tebal */
    tr.lv-1 td { background: #e9e7fd; font-weight: 700; }
    tr.lv-2 td { background: #f5f4fe; font-weight: 700; }
    tr.lv-3 td { font-weight: 600; }
    @for ($l = 2; $l <= $levelMax; $l++)
    tr.lv-{{ $l }} td.nama { padding-left: {{ 6 + ($l - 1) * 14 }}px; }
    @endfor

    .ringkas { margin-top: 10px; font-size: 9pt; color: #555; }

    @media print {
      body { background: #fff; }
      .bilah { display: none; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
      tr.lv-1 td, tr.lv-2 td, th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>

<div class="bilah">
  <span>{{ number_format($baris->count(), 0, ',', '.') }} baris siap dicetak</span>
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
    <h2>Daftar {{ $meta['judul'] }}</h2>
    <p>Dicetak {{ now()->locale('id')->translatedFormat('d F Y, H:i') }} oleh {{ auth()->user()->name }}</p>
  </div>

  <table>
    <thead>
      <tr>
        <th>No</th>
        <th>Kode</th>
        <th>Nama {{ ucfirst($meta['istilah']) }}</th>
        @foreach ($tambahan as $def)
          <th>{{ $def['label'] }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach ($baris as $r)
        <tr class="lv-{{ $r->level }}">
          <td class="no">{{ $loop->iteration }}</td>
          <td class="kode">{{ $r->kode_lengkap }}</td>
          <td class="nama">{{ $r->nama }}</td>
          @foreach ($tambahan as $kolom => $def)
            <td class="angka">{{ $r->{$kolom} ?? '' }}</td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>

  <p class="ringkas">
    Jumlah:
    @foreach ($statistik as $l => $n)
      Level {{ $l }} = {{ number_format($n, 0, ',', '.') }}{{ $loop->last ? '.' : ',' }}
    @endforeach
  </p>
</div>

<script>
  // Langsung buka dialog cetak setelah halaman (termasuk logo) selesai dimuat.
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
