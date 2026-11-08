{{-- Ringkasan SPM di atas form kasir. Parameter: $spm --}}
@php
  $rpR = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp
<dl class="row mb-0 small">
  <dt class="col-sm-4 text-muted fw-normal">Nama Unit</dt>
  <dd class="col-sm-8">{{ $spm->induk->nama ?? $spm->nama_unit }}
    <div class="text-muted"><span class="font-monospace">{{ $spm->kode_spm }}</span> {{ $spm->sub_unit->nama ?? $spm->nama_unit }}</div></dd>
  <dt class="col-sm-4 text-muted fw-normal">Nomor SPM</dt>
  <dd class="col-sm-8 font-monospace">{{ $spm->no_spm }}</dd>
  <dt class="col-sm-4 text-muted fw-normal">Jumlah</dt>
  <dd class="col-sm-8 fw-semibold" style="font-variant-numeric: tabular-nums;">{{ $rpR($spm->nominal) }}
    <span class="text-muted fw-normal">· {{ $spm->kegiatan }} kegiatan</span></dd>
</dl>
