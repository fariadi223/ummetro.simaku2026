{{-- Bukti Kas Keluar non anggaran (bkk_ct_rinci / bkk_cp v1). --}}
@php
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
  $tgl = \Illuminate\Support\Carbon::parse($bkk->tgl_tunai)->locale('id')->translatedFormat('d F Y');
  $kolom = array_values(array_filter([
    $denganRektor ? $ttd['rektor'] : null, $ttd['wr2'], $ttd['kabag'], $ttd['kasir'],
  ]));
  $kolom[] = ['Metro, ' . $tgl, 'Yang Menerima', $bkk->penerima, '-'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>BKK {{ $bkk->no_bkk }} | SIMAKU UM Metro</title>
  <link rel="shortcut icon" href="{{ asset('themes_v1/favicon.ico') }}">
  <style>
    @page { size: A4 portrait; margin: 11mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #111; background: #eef0f4; }
    .bilah { position: sticky; top: 0; display: flex; gap: 8px; justify-content: center; align-items: center; padding: 10px 16px; background: #2f2b3d; color: #fff; font-size: 13px; }
    .bilah button { border: 0; border-radius: 6px; padding: 7px 16px; font: inherit; cursor: pointer; }
    .bilah .utama { background: #7367f0; color: #fff; }
    .bilah .biasa { background: rgba(255,255,255,.15); color: #fff; }
    .kertas { max-width: 210mm; margin: 16px auto; padding: 11mm; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.12); }
    .kop { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
    .kop .pt { display: flex; align-items: center; gap: 10px; }
    .kop img { width: 58px; height: 58px; }
    .kop .nama { font-weight: 700; font-size: 11.5pt; }
    .kop .alamat { font-size: 8.5pt; font-weight: 700; border-top: 1px solid #111; padding-top: 2px; margin-top: 2px; }
    .kop table td { padding: 1px 4px; vertical-align: top; }
    .kop .nomor { font-family: 'Courier New', monospace; }
    h2 { text-align: center; text-decoration: underline; font-size: 13pt; margin: 18px 0 14px; }
    .isi { border-collapse: collapse; }
    .isi > tbody > tr > td { padding: 4px 6px; vertical-align: top; }
    .isi .lbl { white-space: nowrap; width: 130px; }
    .rinci { border-collapse: collapse; margin-top: 2px; min-width: 360px; }
    .rinci th { text-align: left; font-size: 9pt; padding: 2px 10px 2px 4px; }
    .rinci td { border-top: 1px solid #111; padding: 2px 10px 2px 4px; }
    .rinci td.rp { text-align: right; white-space: nowrap; }
    .ttd { width: 100%; margin-top: 28px; border-collapse: collapse; page-break-inside: avoid; }
    .ttd td { vertical-align: top; padding: 0 6px; font-size: 9pt; }
    .ttd p { margin: 0; }
    .ttd .nm { margin-top: 52px; font-weight: 700; text-decoration: underline; }
    .pajak { width: 100%; margin-top: 26px; border-collapse: collapse; page-break-inside: avoid; }
    .pajak > tbody > tr > td { border: 1px solid #111; }
    .pajak table td { padding: 2px 6px; font-size: 9pt; }
    .pajak .rp { text-align: right; font-variant-numeric: tabular-nums; }
    @media print {
      body { background: #fff; }
      .bilah { display: none; }
      .kertas { margin: 0; padding: 0; max-width: none; box-shadow: none; }
    }
  </style>
</head>
<body>
<div class="bilah">
  <span>Bukti Kas Keluar {{ $bkk->no_bkk }}</span>
  <button type="button" class="utama" onclick="window.print()">Cetak / Simpan PDF</button>
  <button type="button" class="biasa" onclick="window.close()">Tutup</button>
</div>
<div class="kertas">
  @if ($salinan)@include('content.kasir._cetak.watermark')@endif
  <div class="kop">
    <div class="pt">
      <img src="{{ asset('themes_v1/assets/images/logoummetro_3.png') }}" alt="Logo UM Metro">
      <div>
        <div class="nama">UNIVERSITAS MUHAMMADIYAH METRO</div>
        <div class="alamat">Jl. Ki Hajar Dewantara No. 116 Metro Timur Telp 42445-42454</div>
      </div>
    </div>
    <table>
      <tr><td><strong>No. BKK</strong></td><td>:</td><td class="nomor">{{ $bkk->no_bkk }}</td></tr>
      <tr><td><strong>No. Ref</strong></td><td>:</td><td>{{ $bkk->no_ref }}</td></tr>
    </table>
  </div>

  <h2>BUKTI KAS KELUAR</h2>

  <table class="isi">
    <tr><td class="lbl">Sudah terima dari</td><td>:</td><td>{{ $bkk->terima_dari }}</td></tr>
    @if ($unit)
      <tr><td class="lbl">Unit Kerja</td><td>:</td><td>{{ $unit->kode }} · {{ $unit->nama }}</td></tr>
    @endif
    <tr><td class="lbl">Banyaknya Rp</td><td>:</td>
      <td><strong>{{ $rp($bkk->nominal) }}</strong><br>( <em>{{ Helper::terbilang($bkk->nominal) }}</em> )</td></tr>
    <tr><td class="lbl">Untuk Pembayaran</td><td>:</td><td style="white-space: pre-line;">{{ $bkk->uraian }}</td></tr>
    @if ($rinci->isNotEmpty())
      <tr><td class="lbl">Rincian</td><td>:</td>
        <td>
          <table class="rinci">
            <tr><th>No.</th><th>Uraian</th><th style="text-align: right;">Biaya (Rp.)</th></tr>
            @foreach ($rinci as $r)
              <tr><td>{{ $loop->iteration }}</td><td>{{ $r->uraian }}</td><td class="rp">{{ $rp($r->biaya) }}</td></tr>
            @endforeach
          </table>
        </td></tr>
    @endif
  </table>

  <table class="ttd">
    <tr>
      @foreach ($kolom as [$atas, $jabatan, $nama, $nbm])
        <td>
          <p>{{ $atas }}</p>
          <p>{{ $jabatan }}</p>
          <p class="nm">{{ $nama }}</p>
          <p>{{ $nbm }}</p>
        </td>
      @endforeach
    </tr>
  </table>

  <table class="pajak">
    <tr>
      <td style="width: 20%;"></td>
      <td>
        <table>
          <tr><td>PPN</td><td>=</td><td>Rp.</td><td class="rp">{{ $rp($bkk->ppn) }}</td></tr>
          <tr><td>PPH</td><td>=</td><td>Rp.</td><td class="rp">{{ $rp($bkk->pph) }}</td></tr>
          <tr><td></td><td>=</td><td>Rp.</td><td class="rp"><strong>{{ $rp($bkk->ppn + $bkk->pph) }}</strong></td></tr>
        </table>
      </td>
      <td style="width: 20%;"></td>
    </tr>
  </table>
</div>
@unless (request()->boolean('lihat'))
<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
@endunless
</body>
</html>
