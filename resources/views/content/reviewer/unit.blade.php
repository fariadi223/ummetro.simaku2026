@extends('layouts/layoutMaster')

@section('title', 'Klinik Anggaran Unit')

@section('page-style')
<style>
  .klinik td { vertical-align: middle; }
  .klinik .kode { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
  .klinik .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .klinik .pakai { min-width: 150px; }
  .klinik .progress { height: 5px; }
  .klinik tr.selesai td:first-child { box-shadow: inset 3px 0 0 var(--bs-success); }
  .klinik tr.perlu td:first-child { box-shadow: inset 3px 0 0 var(--bs-warning); }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $angka = fn ($n, $kelas) => $n ? '<span class="badge bg-label-' . $kelas . '">' . $n . '</span>' : '<span class="text-muted">0</span>';
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-stethoscope"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Klinik Anggaran Unit</h4>
      <p class="pg-header-sub mb-0">Daftar unit yang ditetapkan kepada Anda · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building-community"></i><span><strong>{{ $unit->count() }}</strong> unit</span></span>
    <span class="pg-header-chip"><i class="ti ti-list-numbers"></i><span><strong>{{ $total['kegiatan'] }}</strong> kegiatan</span></span>
    <span class="pg-header-chip"><i class="ti ti-hourglass"></i><span><strong>{{ $total['belum'] }}</strong> belum dinilai</span></span>
    @if ($total['ajuan_revisi'])
      <span class="pg-header-chip"><i class="ti ti-refresh"></i><span><strong>{{ $total['ajuan_revisi'] }}</strong> ajuan revisi</span></span>
    @endif
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <h5 class="card-title mb-0">Daftar Unit yang di-Klinik</h5>
    <input type="search" class="form-control form-control-sm" id="cari-unit" placeholder="Cari kode / nama unit…" style="max-width: 260px;">
  </div>
  <div class="table-responsive">
    <table class="table table-hover klinik mb-0">
      <thead>
        <tr>
          <th style="width: 40px;">No</th>
          <th>Unit</th>
          <th class="text-end">Total Anggaran</th>
          <th>Penggunaan</th>
          <th class="text-center">Kegiatan</th>
          <th class="text-center">Disetujui</th>
          <th class="text-center">Direvisi</th>
          <th class="text-center">Ajuan Revisi</th>
          <th class="text-center">Belum</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        @forelse ($unit as $u)
          <tr class="{{ $u->ajuan_revisi || $u->belum ? 'perlu' : ($u->kegiatan ? 'selesai' : '') }}" data-cari="{{ strtolower($u->kode . ' ' . $u->nama) }}">
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td style="min-width: 220px; white-space: normal;"><div class="kode">{{ $u->kode }}</div><div class="fw-semibold">{{ $u->nama }}</div></td>
            <td class="text-end rp">{{ $rp($u->pagu) }}</td>
            <td class="pakai">
              <div class="d-flex justify-content-between small"><span class="rp">{{ $rp($u->belanja) }}</span>
                <strong class="{{ $u->persen > 100 ? 'text-danger' : '' }}">{{ number_format($u->persen, 0, ',', '.') }}%</strong></div>
              <div class="progress mt-1"><div class="progress-bar bg-{{ $u->persen > 100 ? 'danger' : ($u->persen >= 90 ? 'warning' : 'primary') }}" style="width: {{ min($u->persen, 100) }}%"></div></div>
            </td>
            <td class="text-center fw-semibold">{{ $u->kegiatan }}</td>
            <td class="text-center">{!! $angka($u->disetujui, 'success') !!}</td>
            <td class="text-center">{!! $angka($u->direvisi, 'danger') !!}</td>
            <td class="text-center">{!! $angka($u->ajuan_revisi, 'info') !!}</td>
            <td class="text-center">{!! $angka($u->belum, 'warning') !!}</td>
            <td class="text-end"><a href="{{ route('reviewer.kegiatan.index', ['kode' => $u->kode]) }}" class="btn btn-sm btn-primary"><i class="ti ti-clipboard-check me-1"></i>Review</a></td>
          </tr>
        @empty
          <tr><td colspan="10" class="text-center text-muted py-5">
            Belum ada unit yang ditetapkan kepada Anda untuk T.A {{ $tahun }}/{{ $tahun + 1 }}.
            <div class="small mt-1">Penetapan reviewer diatur oleh admin (Setting › Penetapan Review).</div>
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.getElementById('cari-unit')?.addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  document.querySelectorAll('.klinik tbody tr[data-cari]').forEach(tr => { tr.hidden = q !== '' && !tr.dataset.cari.includes(q); });
});
</script>
@endsection
