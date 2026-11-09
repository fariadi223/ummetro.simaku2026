{{-- Satu kelompok aset neto. Parameter: $judul, $k ('tp'|'dp'|'total'), $ini, $lalu, $rp, $label (['ini','lalu']), $layar --}}
@php
  $pan = \App\Services\PerubahanAsetNeto::class;
  // saldo_awal (total) dipisah: bagian surplus akumulasian = saldo_awal_akum, bagian PKL = pkl_awal.
  $akumulasi = ['saldo_awal_akum' => $pan::BARIS['saldo_awal']] + collect($pan::BARIS)->except('saldo_awal')->all();
  $tampil = fn ($c) => $c !== 'langsung' || abs($ini[$k][$c]) >= 0.5 || abs($lalu[$k][$c]) >= 0.5;
@endphp
<table class="{{ $layar ? 'table table-sm pan-tabel' : 'data' }}">
  <thead>
    <tr><th class="judul">{{ $judul }}</th><th class="n">{{ $label['ini'] }}</th><th class="n">{{ $label['lalu'] }}</th></tr>
  </thead>
  <tbody>
    <tr class="sub"><td colspan="3">Surplus / (Defisit) Akumulasian</td></tr>
    @foreach ($akumulasi as $c => $nama)
      @continue(!$tampil($c))
      <tr><td class="ind">{{ $nama }}</td><td class="n">{{ $rp($ini[$k][$c]) }}</td><td class="n">{{ $rp($lalu[$k][$c]) }}</td></tr>
    @endforeach
    <tr class="total"><td class="ka">Saldo Akhir Surplus / (Defisit) Akumulasian</td><td class="n">{{ $rp($ini[$k]['akumulasian']) }}</td><td class="n">{{ $rp($lalu[$k]['akumulasian']) }}</td></tr>
    <tr class="sub"><td colspan="3">Penghasilan Komprehensif Lain</td></tr>
    @foreach ($pan::BARIS_PKL as $c => $nama)
      <tr><td class="ind">{{ $nama }}</td><td class="n">{{ $rp($ini[$k][$c]) }}</td><td class="n">{{ $rp($lalu[$k][$c]) }}</td></tr>
    @endforeach
    <tr class="total"><td class="ka">Saldo Akhir Penghasilan Komprehensif Lain</td><td class="n">{{ $rp($ini[$k]['pkl_akhir']) }}</td><td class="n">{{ $rp($lalu[$k]['pkl_akhir']) }}</td></tr>
    <tr class="akhir"><td class="ka">SALDO AKHIR {{ mb_strtoupper($judul) }}</td><td class="n">{{ $rp($ini[$k]['saldo_akhir']) }}</td><td class="n">{{ $rp($lalu[$k]['saldo_akhir']) }}</td></tr>
  </tbody>
</table>
