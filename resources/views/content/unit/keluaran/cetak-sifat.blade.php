{{-- Cetak rekap berbasis sifat (lap_bebasis_sifat v1) --}}
@php
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
  $selisih = $kelompok['pendapatan'][1]->sum('jumlah') - $kelompok['belanja'][1]->sum('jumlah');
@endphp
@include('content.unit.keluaran._cetak.kepala', ['judul' => 'Rekap Berbasis Sifat', 'ukuran' => 'A4 portrait', 'unit' => $unitAktif ?? $unit])
  <table class="data">
    <thead><tr><th>No</th><th>Kode Rekening</th><th>Nama Rekening</th><th>Jumlah</th></tr></thead>
    <tbody>
      @foreach ($kelompok as $kunci => [$judul, $daftar])
        @if ($daftar->isNotEmpty() || in_array($kunci, ['pendapatan', 'belanja'], true))
          <tr class="grup"><td colspan="4">{{ mb_strtoupper($judul) }}</td></tr>
          @foreach ($daftar as $r)
            <tr><td>{{ $loop->iteration }}</td><td style="font-family: 'Courier New', monospace;">{{ $r->kode }}</td><td>{{ $r->namarek6 }}</td><td class="rp">{{ $rp($r->jumlah) }}</td></tr>
          @endforeach
          <tr class="total"><td colspan="3" style="text-align: right;">TOTAL {{ mb_strtoupper($judul) }}</td><td class="rp">{{ $rp($daftar->sum('jumlah')) }}</td></tr>
        @endif
        @if ($kunci === 'belanja')
          <tr class="surplus"><td colspan="3" style="text-align: right;">SURPLUS / DEFISIT</td><td class="rp">{{ $rp($selisih) }}</td></tr>
        @endif
      @endforeach
    </tbody>
  </table>
@include('content.unit.keluaran._cetak.kaki')
