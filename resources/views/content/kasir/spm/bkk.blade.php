{{-- Bukti Kas Keluar SPM (spm_ct / spm_cp v1). --}}
@php
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t, $f = 'd F Y') => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat($f) : '—';
  $jenis = $spm->metode === 'transfer' ? 'Transfer Rek. ' . $spm->bayar->norekbank : 'Pengeluaran Tunai/Cash';
@endphp
@include('content.unit.keluaran._cetak.kepala', ['judul' => 'Bukti Kas Keluar', 'ukuran' => 'A4 portrait', 'unit' => $spm->induk ?? (object) ['nama' => $spm->nama_unit], 'tahun' => $tahun])
  <style>
    .nobkk { text-align: center; margin-top: 2px; font-size: 9.5pt; }
    .nobkk strong { font-family: 'Courier New', monospace; }
    .ttd-tabel { width: 100%; margin-top: 18px; text-align: center; border-collapse: collapse; page-break-inside: avoid; }
    .ttd-tabel td { vertical-align: bottom; padding: 2px 6px; }
    .ttd-tabel .nm { font-weight: 700; text-decoration: underline; padding-top: 58px; }
  </style>
  @if ($salinan)@include('content.kasir._cetak.watermark')@endif

  <div class="nobkk">No: <strong>{{ $nomorBkk }}</strong> · {{ $jenis }}
    @if ($spm->metode === 'transfer' && trim((string) $spm->bayar->namabank) !== '') · {{ $spm->bayar->namabank }}@endif
    @if ($spm->metode === 'transfer' && trim((string) $spm->bayar->nobuktirek) !== '') · Ref {{ $spm->bayar->nobuktirek }}@endif
  </div>

  <table class="data">
    <thead><tr><th>Nomor SPM</th><th>Tanggal Disetujui</th><th>Tahun Anggaran</th><th>Unit Kerja</th></tr></thead>
    <tbody>
      <tr>
        <td style="font-family: 'Courier New', monospace;">{{ $spm->no_spm }}</td>
        <td>{{ $tglId($spm->tgl_setuju, 'd M Y') }}</td>
        <td>{{ $tahun }}/{{ $tahun + 1 }}</td>
        <td>{{ $spm->induk->nama ?? $spm->nama_unit }}</td>
      </tr>
    </tbody>
  </table>

  <table class="data">
    <thead><tr><th style="width: 28px;">No</th><th style="width: 70px;">Kode Sub Unit</th><th>Nama Sub Unit</th><th style="width: 80px;">Kode MAK</th><th>Nama Kegiatan</th><th style="width: 110px;">Anggaran yg Diminta</th></tr></thead>
    <tbody>
      @foreach ($spm->baris as $b)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td style="font-family: 'Courier New', monospace;">{{ $b->kode_unit }}</td>
          <td>{{ $b->nama_unit }}</td>
          <td style="font-family: 'Courier New', monospace; white-space: nowrap;">{{ $b->kode_mak }}</td>
          <td>{{ $b->namamak4 }}</td>
          <td class="rp">{{ $rp($b->ajuan) }}</td>
        </tr>
      @endforeach
      <tr class="total"><td colspan="5" style="text-align: right;">TOTAL</td><td class="rp">{{ $rp($spm->nominal) }}</td></tr>
    </tbody>
  </table>
  <p style="margin: 6px 0 0;">Terbilang: <em>{{ Helper::terbilang($spm->nominal) }}</em></p>

  <table class="ttd-tabel">
    <tr><td colspan="{{ $mengetahui ? 3 : 2 }}" style="text-align: left;">{{ $kota }}, {{ $tglId($spm->bayar->tgl_kasir) }}</td></tr>
    <tr>
      @if ($mengetahui)<td class="nm">{{ $pimpinan }}</td>@endif
      <td class="nm">{{ $namaKasir }}</td>
      <td class="nm">{{ $penerima }}</td>
    </tr>
    <tr>
      @if ($mengetahui)<td>Mengetahui</td>@endif
      <td>Kasir</td>
      <td>Penerima</td>
    </tr>
  </table>
</div>
<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
