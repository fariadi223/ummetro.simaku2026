{{-- Tabel satu bagian laporan penghasilan komprehensif. Parameter: $bg (judul, baris, total), $layar (bool), $rp, $rinci --}}
<table class="{{ $layar ? 'table table-sm pk-tabel' : 'data' }}">
  <thead>
    <tr>
      <th rowspan="2" class="kode">Kode Akun</th><th rowspan="2">Nama Akun</th>
      <th colspan="3" class="grp">{{ $label['ini'] }}</th>
      <th colspan="3" class="grp">{{ $label['lalu'] }}</th>
    </tr>
    <tr>
      <th class="n">Tanpa Pembatasan</th><th class="n">Dengan Pembatasan</th><th class="n">Jumlah</th>
      <th class="n">Tanpa Pembatasan</th><th class="n">Dengan Pembatasan</th><th class="n">Jumlah</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($bg['baris'] as $b)
      @continue(!$layar && $b->lv === 6 && !$rinci)
      @if ($b->lv === 3)
        <tr class="lv3 {{ $layar ? 'tertutup' : '' }}" data-grup="{{ $b->id }}">
          <td class="kode">@if ($layar)<i class="ti ti-chevron-down ti-xs lipat"></i>@endif{{ $b->kode }}</td>
          <td class="nama">{{ $b->nama }}</td>
      @else
        <tr class="lv6 {{ $layar ? 'd-none' : '' }}" data-induk="{{ $b->induk }}">
          <td class="kode">{{ $b->kode }}</td>
          <td class="nama">@if ($layar)<a href="{{ route('akuntansi.buku-besar.index', ['rek6' => $b->id]) }}" title="Buka buku besar">{{ $b->nama }}</a>@else{{ $b->nama }}@endif</td>
      @endif
          <td class="n">{{ $rp($b->tp) }}</td><td class="n">{{ $rp($b->dp) }}</td><td class="n j">{{ $rp($b->jml) }}</td>
          <td class="n">{{ $rp($b->tp_lalu) }}</td><td class="n">{{ $rp($b->dp_lalu) }}</td><td class="n j">{{ $rp($b->jml_lalu) }}</td>
        </tr>
    @empty
      <tr><td colspan="8" class="kosong">Tidak ada saldo.</td></tr>
    @endforelse
    <tr class="total"><td></td><td class="ka">TOTAL {{ mb_strtoupper($bg['judul']) }}</td>
      @foreach (['tp', 'dp', 'jml', 'tp_lalu', 'dp_lalu', 'jml_lalu'] as $c)<td class="n">{{ $rp($bg['total']->$c) }}</td>@endforeach
    </tr>
  </tbody>
</table>
