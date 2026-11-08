{{-- Lembar SPP (spp_detail v1). Penanda tangan: pimpinan unit induk (kode FF.00), seperti v1. --}}
@php
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp
@include('content.unit.keluaran._cetak.kepala', ['judul' => 'Surat Permintaan Pembayaran', 'ukuran' => 'A4 landscape'])
  <table style="width: 100%; margin-top: 8px; font-size: 9pt;">
    <tr>
      <td><strong>Sub Unit Kerja</strong>: {{ $unit->kode }} · {{ $unit->nama }}</td>
      <td style="text-align: right;"><strong>No. SPP</strong>: {{ $nomor }}<br>
        <strong>Tgl. Penyajian</strong>: {{ \Illuminate\Support\Carbon::parse($baris->first()->tgl_saji)->locale('id')->translatedFormat('d F Y') }}</td>
    </tr>
  </table>
  <table class="data">
    <thead>
      <tr><th>No</th><th>Kode MAK</th><th>Nama Kegiatan</th><th>Total Anggaran</th><th>Anggaran Prnh. Diterima</th>
        <th>Anggaran Tersedia</th><th>Anggaran Diminta</th><th>Anggaran Sisa</th></tr>
    </thead>
    <tbody>
      @foreach ($baris as $b)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td style="font-family: 'Courier New', monospace; white-space: nowrap;">{{ $b->kode_mak }}</td>
          <td>{{ $b->namamak4 }}<br><span style="color: #555;">{{ $b->uraiankeg }}</span></td>
          <td class="rp">{{ $rp($b->anggaran) }}</td>
          <td class="rp">{{ $rp($b->diterima) }}</td>
          <td class="rp">{{ $rp($b->tersedia) }}</td>
          <td class="rp">{{ $rp($b->ajuan) }}</td>
          <td class="rp">{{ $rp($b->tersedia - $b->ajuan) }}</td>
        </tr>
      @endforeach
      <tr class="total"><td colspan="6" style="text-align: right;">TOTAL</td><td class="rp">{{ $rp($baris->sum('ajuan')) }}</td><td></td></tr>
    </tbody>
  </table>
  <div class="ttd">
    <div>
      Metro, {{ now()->locale('id')->translatedFormat('d F Y') }}<br>
      {{ $induk->nama }}
      <div class="nama">{{ $induk->nama_pimp }}</div>
      @if ($induk->nip)<div>NIP. {{ $induk->nip }}</div>@endif
    </div>
  </div>
</div>
<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
