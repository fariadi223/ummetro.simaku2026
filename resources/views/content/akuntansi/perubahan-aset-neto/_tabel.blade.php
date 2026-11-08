{{-- Satu kelompok aset neto. Parameter: $judul, $k ('tp'|'dp'|'total'), $ini, $lalu, $rp, $label (['ini','lalu']), $layar --}}
@php
  $akumulasi = ['saldo_awal', 'surplus', 'koreksi', 'dibebaskan', 'langsung'];
  $tampil = fn ($c) => $c !== 'langsung' || abs($ini[$k][$c]) >= 0.5 || abs($lalu[$k][$c]) >= 0.5;
@endphp
<table class="{{ $layar ? 'table table-sm pan-tabel' : 'data' }}">
  <thead>
    <tr><th class="judul">{{ $judul }}</th><th class="n">{{ $label['ini'] }}</th><th class="n">{{ $label['lalu'] }}</th></tr>
  </thead>
  <tbody>
    <tr class="sub"><td colspan="3">Surplus / (Defisit) Akumulasian</td></tr>
    @foreach ($akumulasi as $c)
      @continue(!$tampil($c))
      <tr><td class="ind">{{ \App\Services\PerubahanAsetNeto::BARIS[$c] }}</td><td class="n">{{ $rp($ini[$k][$c]) }}</td><td class="n">{{ $rp($lalu[$k][$c]) }}</td></tr>
    @endforeach
    <tr class="total"><td class="ka">Saldo Akhir Surplus / (Defisit) Akumulasian</td><td class="n">{{ $rp($ini[$k]['akumulasian']) }}</td><td class="n">{{ $rp($lalu[$k]['akumulasian']) }}</td></tr>
    <tr class="sub"><td colspan="3">Penghasilan Komprehensif Lain</td></tr>
    <tr><td class="ind">{{ \App\Services\PerubahanAsetNeto::BARIS['pkl'] }}</td><td class="n">{{ $rp($ini[$k]['pkl']) }}</td><td class="n">{{ $rp($lalu[$k]['pkl']) }}</td></tr>
    <tr class="akhir"><td class="ka">SALDO AKHIR {{ mb_strtoupper($judul) }}</td><td class="n">{{ $rp($ini[$k]['saldo_akhir']) }}</td><td class="n">{{ $rp($lalu[$k]['saldo_akhir']) }}</td></tr>
  </tbody>
</table>
