{{-- Cetak rekap berbasis fungsi (lap_bebasis_fungsi v1) --}}
@php
  $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
@endphp
@include('content.unit.keluaran._cetak.kepala', ['judul' => 'Rekap Berbasis Fungsi', 'ukuran' => 'A4 landscape', 'unit' => $unitAktif ?? $unit])
  <table class="data">
    <thead>
      <tr>
        <th>No</th><th>Jns Keg.</th><th>Kegiatan</th><th>Latar Belakang</th><th>Tujuan</th><th>Output</th>
        <th>Jadwal</th><th>Pendapatan</th><th>Belanja</th><th>Dana</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($grup as $g)
        @if ($g->kegiatan->isNotEmpty())
          <tr class="grup"><td colspan="10">PROSES BISNIS {{ $g->kode }} : {{ $g->nama }}</td></tr>
          @foreach ($g->kegiatan as $k)
            <tr>
              <td>{{ $loop->iteration }}</td>
              <td>{{ $k->namamak2 }}</td>
              <td>@if ($jumlahUnit && !$unitAktif)<em>{{ $k->kode_unit }} · {{ $k->nama_unit }}</em><br>@endif<strong>{{ $k->namamak4 }}</strong><br>{{ $k->uraiankeg }}</td>
              <td class="pre">{{ $k->latblk }}</td>
              <td class="pre">{{ $k->tujuan }}</td>
              <td class="pre">{{ $k->lingkup }}</td>
              <td>{{ $k->jadwal }}</td>
              <td class="rp">{{ $rp($k->pendapatan) }}</td>
              <td class="rp">{{ $rp($k->belanja) }}</td>
              <td>{{ $k->dana }}</td>
            </tr>
          @endforeach
          <tr class="total"><td colspan="7" style="text-align: right;">SUB TOTAL</td><td class="rp">{{ $rp($g->pendapatan) }}</td><td class="rp">{{ $rp($g->belanja) }}</td><td></td></tr>
        @endif
      @endforeach
      <tr class="surplus"><td colspan="7" style="text-align: right;">TOTAL</td><td class="rp">{{ $rp($total['pendapatan']) }}</td><td class="rp">{{ $rp($total['belanja']) }}</td><td></td></tr>
    </tbody>
  </table>
@include('content.unit.keluaran._cetak.kaki')
