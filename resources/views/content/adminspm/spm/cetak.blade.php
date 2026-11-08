{{-- Lembar SPM (spm_cetak v1). --}}
@php
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
  $total = $baris->sum('ajuan');
  $tgl = \Illuminate\Support\Carbon::parse($ringkas->tgl_saji)->locale('id')->translatedFormat('d F Y');
  // v1: WR II memakai "An." + an_nama_pimp bila terisi.
  $wr2Nama = $ttd2 ? ($ttd2->an_nama_pimp ?: $ttd2->nama_pimp) : '';
  $wr2An = $ttd2 && $ttd2->an_nama_pimp ? 'An. ' : '';
@endphp
@include('content.unit.keluaran._cetak.kepala', ['judul' => 'Surat Perintah Membayar', 'ukuran' => 'A4 portrait', 'unit' => $unit ?? $induk, 'tahun' => $tahun])
  <style>
    .info { width: 100%; margin-top: 8px; font-size: 9pt; border-collapse: collapse; }
    .info td { padding: 2px 0; vertical-align: top; }
    .blok { display: flex; gap: 16px; align-items: flex-end; margin-top: 16px; page-break-inside: avoid; }
    .pernyataan { flex: 1 1 58%; border: 2px dotted #a7a7a7; padding: 8px 10px; font-size: 8.5pt; line-height: 1.45; }
    .ttd-blok { flex: 1 1 42%; text-align: center; }
    .ttd-blok img, .setuju img { max-height: 46px; display: block; margin: 4px auto; }
    .spesi { height: 50px; }
    .nm { font-weight: 700; text-decoration: underline; }
    hr.pemisah { border: 0; border-top: 1px solid #999; margin: 16px 0 10px; }
    .perintah p { margin: 3px 0; }
    .setuju { width: 100%; margin-top: 12px; text-align: center; border-collapse: collapse; page-break-inside: avoid; }
    .setuju td { width: 50%; vertical-align: top; padding: 2px 6px; }
  </style>

  <table class="info">
    <tr>
      <td><strong>Sub Unit Kerja</strong><br>{{ $ringkas->kode_unit }} · {{ $ringkas->nama_unit }}</td>
      <td style="text-align: right;"><strong>No. SPP</strong>: {{ $ringkas->no_spp }}<br><strong>Tgl. Penyajian</strong>: {{ $tgl }}</td>
    </tr>
  </table>

  <table class="data">
    <thead><tr><th style="width: 28px;">No</th><th style="width: 90px;">Kode MAK</th><th>Nama Kegiatan</th><th style="width: 110px;">Ajuan</th></tr></thead>
    <tbody>
      @foreach ($baris as $b)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td style="font-family: 'Courier New', monospace; white-space: nowrap;">{{ $b->kode_mak }}</td>
          <td>{{ $b->namamak4 }}<br><span style="color: #555;">{{ $b->uraiankeg }}</span></td>
          <td class="rp">{{ $rp($b->ajuan) }}</td>
        </tr>
      @endforeach
      <tr class="total"><td colspan="3" style="text-align: right;">TOTAL</td><td class="rp">{{ $rp($total) }}</td></tr>
    </tbody>
  </table>

  <div class="blok">
    <div class="pernyataan">
      Ajuan Surat Permintaan Pembayaran (SPP) kami buat sesuai dengan dokumen perencanaan dan anggaran. Setelah dana kami terima,
      akan kami gunakan sesuai dengan peruntukannya dan akan kami pertanggungjawabkan tepat waktu sesuai dengan aturan yang berlaku.
    </div>
    <div class="ttd-blok">
      Metro, {{ $tgl }}<br>{{ $induk->nama ?? '' }}
      @if ($spesimen['induk'])<img src="{{ $spesimen['induk'] }}" alt="Spesimen tanda tangan">@else<div class="spesi"></div>@endif
      <div class="nm">{{ $induk->nama_pimp ?? '' }}</div>
    </div>
  </div>

  <hr class="pemisah">

  <div class="perintah">
    <p>Surat Perintah Membayar (SPM) No: <strong>{{ $noSpm }}</strong></p>
    <p>Bendahara Keuangan dimohon membayarkan uang sejumlah: <strong>Rp {{ $rp($total) }}</strong></p>
    <p>(<em>{{ Helper::terbilang($total) }}</em>)</p>
  </div>

  <table class="setuju">
    <tr><td colspan="2">Menyetujui,</td></tr>
    <tr>
      <td>{{ $ttd1->nama ?? '' }}</td>
      <td>{{ $wr2An }}{{ $ttd2->nama ?? '' }}</td>
    </tr>
    <tr>
      <td>@if ($spesimen['bak'])<img src="{{ $spesimen['bak'] }}" alt="Spesimen BAK">@else<div class="spesi"></div>@endif</td>
      <td>@if ($spesimen['wr2'])<img src="{{ $spesimen['wr2'] }}" alt="Spesimen WR II">@else<div class="spesi"></div>@endif</td>
    </tr>
    <tr>
      <td class="nm">{{ $ttd1->nama_pimp ?? '' }}</td>
      <td class="nm">{{ $wr2Nama }}</td>
    </tr>
  </table>
</div>
<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
