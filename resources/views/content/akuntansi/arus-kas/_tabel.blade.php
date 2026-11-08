{{-- Isi laporan arus kas (dipakai halaman & cetak). Parameter: $bagian, $ini, $lalu, $rp, $label, $layar --}}
@php
  $baris = fn ($nama, $a, $b, $kelas = '') => '<tr class="' . $kelas . '"><td class="nama">' . e($nama) . '</td><td class="n">' . $rp($a) . '</td><td class="n">' . $rp($b) . '</td></tr>';
  $rinci = function ($daftar, $kelas = 'ind2') use ($rp) {
    $h = '';
    foreach ($daftar as $r) {
      $h .= '<tr class="' . $kelas . '"><td class="nama"><span class="kd">' . e($r->kode) . '</span> ' . e($r->nama) . '</td><td class="n">' . $rp($r->ini) . '</td><td class="n">' . $rp($r->lalu) . '</td></tr>';
    }

    return $h ?: '<tr class="' . $kelas . '"><td class="nama kosong">—</td><td class="n">0</td><td class="n">0</td></tr>';
  };
@endphp
<table class="{{ $layar ? 'table table-sm ak-tabel' : 'data' }}">
  <thead><tr><th></th><th class="n">{{ $label['ini'] }}</th><th class="n">{{ $label['lalu'] }}</th></tr></thead>
  <tbody>
    <tr class="sub"><td colspan="3">ARUS KAS DARI AKTIVITAS OPERASI</td></tr>
    {!! $baris('Kenaikan/(penurunan) penghasilan komprehensif periode berjalan', $ini['komprehensif'], $lalu['komprehensif'], 'ind') !!}
    <tr class="ind"><td colspan="3" class="nama ket">Penyesuaian kenaikan/(penurunan) aset bersih terhadap kas bersih diperoleh dari (digunakan untuk) aktivitas operasi:</td></tr>
    {!! $rinci($bagian['penyusutan']) !!}
    {!! $baris('Aset neto sebelum perubahan aktivitas operasi', $ini['komprehensif'] + $ini['penyusutan'], $lalu['komprehensif'] + $lalu['penyusutan'], 'ind total') !!}
    <tr class="ind"><td colspan="3" class="nama ket">Perubahan aset lancar dan liabilitas jangka pendek</td></tr>
    {!! $baris('Penurunan/(Kenaikan) Aset lancar:', $ini['operasi_aset'], $lalu['operasi_aset'], 'ind2 grp') !!}
    {!! $rinci($bagian['operasi_aset'], 'ind3') !!}
    {!! $baris('Kenaikan/(Penurunan) Liabilitas Jangka Pendek:', $ini['operasi_liabilitas'], $lalu['operasi_liabilitas'], 'ind2 grp') !!}
    {!! $rinci($bagian['operasi_liabilitas'], 'ind3') !!}
    {!! $baris('Arus kas bersih diperoleh dari/ digunakan untuk aktivitas operasi', $ini['operasi'], $lalu['operasi'], 'total') !!}

    <tr class="sub"><td colspan="3">ARUS KAS DARI AKTIVITAS INVESTASI</td></tr>
    {!! $baris('Penurunan/(Kenaikan) Aset Tidak lancar:', $ini['investasi'], $lalu['investasi'], 'ind grp') !!}
    {!! $rinci($bagian['investasi']) !!}
    {!! $baris('Arus kas bersih diperoleh dari/ digunakan untuk aktivitas investasi', $ini['investasi'], $lalu['investasi'], 'total') !!}

    <tr class="sub"><td colspan="3">ARUS KAS DARI AKTIVITAS PENDANAAN</td></tr>
    {!! $baris('Kenaikan/(Penurunan) Liabilitas Jangka Panjang:', $ini['liabilitas_panjang'], $lalu['liabilitas_panjang'], 'ind grp') !!}
    {!! $rinci($bagian['pendanaan']) !!}
    @if (abs($ini['langsung']) >= 0.5 || abs($lalu['langsung']) >= 0.5)
      {!! $baris('Penyesuaian langsung aset neto', $ini['langsung'], $lalu['langsung'], 'ind grp') !!}
    @endif
    {!! $baris('Arus kas bersih diperoleh dari/ digunakan untuk aktivitas pendanaan', $ini['pendanaan'], $lalu['pendanaan'], 'total') !!}

    <tr class="sub"><td colspan="3">KAS DAN SETARA KAS</td></tr>
    {!! $baris('Kenaikan (penurunan) bersih kas dan setara kas', $ini['kenaikan'], $lalu['kenaikan'], 'ind') !!}
    {!! $baris('Kas dan setara kas pada awal periode', $ini['kas_awal'], $lalu['kas_awal'], 'ind') !!}
    {!! $baris('KAS DAN SETARA KAS PADA AKHIR PERIODE', $ini['kas_akhir'], $lalu['kas_akhir'], 'akhir') !!}
  </tbody>
</table>
