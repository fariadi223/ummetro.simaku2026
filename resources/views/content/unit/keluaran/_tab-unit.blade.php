{{-- Tab unit untuk unit induk (FF.00): "Semua Unit" + tiap sub unit; memfilter isi halaman & cetak.
     Parameter: $rute (nama route halaman), $daftarUnit, $unitAktif. Gaya: .filter-chip (simaku.css). --}}
<nav class="filter-chips filter-chips-gulir" aria-label="Pilih unit">
  <a href="{{ route($rute) }}" class="filter-chip {{ $unitAktif ? '' : 'active' }}" @if (!$unitAktif) aria-current="page" @endif>
    <i class="ti ti-sitemap" aria-hidden="true"></i><span>Semua Unit</span><strong>{{ $daftarUnit->count() }}</strong>
  </a>
  @foreach ($daftarUnit as $du)
    <a href="{{ route($rute, ['unit' => $du->kode]) }}" class="filter-chip {{ $unitAktif?->kode === $du->kode ? 'active' : '' }}"
       @if ($unitAktif?->kode === $du->kode) aria-current="page" @endif title="{{ $du->nama }}">
      <span class="font-monospace small opacity-75">{{ $du->kode }}</span><span>{{ $du->nama }}</span>
    </a>
  @endforeach
</nav>
