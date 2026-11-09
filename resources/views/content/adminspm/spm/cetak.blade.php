{{-- Lembar SPM, tata letak mengikuti dashboard/spm_cetak v1. --}}
@php
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
  $total = $baris->sum('ajuan');
  $tgl = \Illuminate\Support\Carbon::parse($ringkas->tgl_saji)->format('d-m-Y');
  // v1: WR II memakai "An." + an_nama_pimp bila terisi.
  $wr2Nama = $ttd2 ? ($ttd2->an_nama_pimp ?: $ttd2->nama_pimp) : '';
  $wr2An = $ttd2 && $ttd2->an_nama_pimp ? 'An. ' : '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SPM {{ $noSpm }} | SIMAKU UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <style>
    @page { size: A4 portrait; margin: 12mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 12px; line-height: 1.43; color: #373e4a; background: #eef0f4; }
    .kertas { max-width: 210mm; margin: 16px auto; padding: 12mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
    .baris { display: flex; gap: 20px; }
    .kiri { flex: 1 1 0; }
    .kanan { flex: 1 1 0; text-align: right; }
    h4 { margin: 0 0 6px; font-size: 15px; font-weight: 700; color: #303641; }
    hr { border: 0; border-top: 1px solid #ebebeb; margin: 17px 0; }
    .kop td { vertical-align: middle; padding-right: 10px; }
    .kop h4 { white-space: nowrap; }
    .kop img { width: 80px; height: auto; display: block; }
    .kop-baris { align-items: center; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table.data th, table.data td { border: 1px solid #ebebeb; padding: 8px; vertical-align: top; }
    table.data th { font-weight: 700; color: #373e4a; }
    .tengah { text-align: center; }
    .kanan-teks { text-align: right; white-space: nowrap; }
    .pernyataan { display: inline-block; border: 2px dotted #a7a7a7; padding: 10px; margin-top: 40px; }
    .ttd { width: 100%; border-collapse: collapse; }
    .ttd td { text-align: center; padding: 1px 4px; vertical-align: top; }
    .ttd img { max-height: 50px; }
    .spesi { height: 50px; }
    .perintah { padding-top: 40px; }
    .perintah div { margin-bottom: 4px; }
    .blok-ttd { page-break-inside: avoid; }
    @media print {
      body { background: #fff; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
      .hidden-print { display: none !important; }
    }
  </style>
</head>
<body>
<div class="kertas">

  <div class="baris kop-baris">
    <div class="kiri" style="flex-basis: 58%;">
      <table class="kop">
        <tr>
          <td><img src="{{ asset('themes_v1/assets/images/logoummetro_3.png') }}" alt="Logo UM Metro"></td>
          <td>
            <h4>UNIVERSITAS MUHAMMADIYAH METRO</h4>
            <span style="font-size: 12px;">Jl. Ki Hajar Dewantara No. 116 Kota Metro,</span><br>
            <span style="font-size: 12px;">Telp.(0725)42445-42454 fax.(0725)42445</span>
          </td>
        </tr>
      </table>
    </div>
    <div class="kanan" style="flex-basis: 42%;">
      <h4>SURAT PERINTAH MEMBAYAR</h4>
      <span><strong>NO. SPP #:</strong> {{ $ringkas->no_spp }}</span><br>
      <span><strong>Tanggal Penyajian #:</strong> {{ $tgl }}</span>
    </div>
  </div>

  <hr>

  <div class="baris">
    <div class="kiri">
      <span><strong>SUB UNIT KERJA</strong></span><br>
      {{ $unit->nama ?? $ringkas->nama_unit }}<br>
    </div>
  </div>

  <table class="data">
    <thead>
      <tr>
        <th class="tengah">#</th>
        <th style="width: 15%;">Kode MAK</th>
        <th style="width: 60%;">Nama Kegiatan</th>
        <th>Anggaran Yang Diminta</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($baris as $b)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td style="white-space: nowrap;">{{ str_replace('.', ' ', $b->kode_mak) }}</td>
          <td>{{ $b->namamak4 }}</td>
          <td class="kanan-teks">{{ $rp($b->ajuan) }}</td>
        </tr>
      @endforeach
    </tbody>
    <thead>
      <tr>
        <th colspan="3" style="text-align: right;">TOTAL</th>
        <th class="kanan-teks">{{ $rp($total) }}</th>
      </tr>
    </thead>
  </table>

  <div class="baris blok-ttd">
    <div class="kiri" style="flex-basis: 58%;">
      <label class="pernyataan">
        Ajuan Surat Permintaan Pembayaran (SPP) kami buat sesuai dengan dokumen perencanaan dan anggaran. Setelah dana kami terima,
        akan kami gunakan sesuai dengan peruntukannya dan akan kami pertanggungjawabkan tepat waktu sesuai dengan aturan yang berlaku.
      </label>
    </div>
    <div style="flex: 1 1 42%;">
      <table class="ttd">
        <tr><td>Metro, {{ $tgl }}</td></tr>
        <tr><td>{{ $induk->nama ?? '' }}</td></tr>
        <tr><td>@if ($spesimen['induk'])<img src="{{ $spesimen['induk'] }}" alt="Spesimen tanda tangan">@else<div class="spesi"></div>@endif</td></tr>
        <tr><td><u>{{ $induk->nama_pimp ?? '' }}</u></td></tr>
      </table>
    </div>
  </div>

  <hr>

  <div class="baris blok-ttd">
    <div class="kiri perintah">
      <div>Surat Perintah Membayar (SPM) No: <strong>{{ $noSpm }}</strong></div>
      <div>Bendahara Keuangan dimohon membayarkan uang sejumlah : <strong>Rp {{ $rp($total) }}</strong></div>
      <div>(<i>{{ Helper::terbilang($total) }}</i>)</div>
    </div>
    <div style="flex: 1 1 0;">
      <table class="ttd">
        <tr><td colspan="2">Menyetujui,<br><br></td></tr>
        <tr>
          <td style="width: 50%;">{{ $ttd1->nama ?? '' }}</td>
          <td style="width: 50%;">{{ $wr2An }}{{ $ttd2->nama ?? '' }}</td>
        </tr>
        <tr>
          <td>@if ($spesimen['bak'])<img src="{{ $spesimen['bak'] }}" alt="Spesimen BAK">@else<div class="spesi"></div>@endif</td>
          <td>@if ($spesimen['wr2'])<img src="{{ $spesimen['wr2'] }}" alt="Spesimen WR II">@else<div class="spesi"></div>@endif</td>
        </tr>
        <tr>
          <td><u>{{ $ttd1->nama_pimp ?? '' }}</u></td>
          <td><u>{{ $wr2Nama }}</u></td>
        </tr>
      </table>
    </div>
  </div>

  <hr>

</div>
@if (request()->boolean('cetak'))
  {{-- Dari tombol "Cetak" di daftar (iframe tersembunyi, lihat _cetak-langsung): langsung dialog print. --}}
  <script>window.addEventListener('load', function () { window.focus(); window.print(); });</script>
@endif
</body>
</html>
