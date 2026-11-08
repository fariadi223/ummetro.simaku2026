@extends('layouts/layoutMaster')

@section('title', 'Rincian Kegiatan')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .ringkas .card-body { padding: 1rem 1.25rem; }
  .ringkas .label { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary); }
  .ringkas .nilai { font-size: 1.15rem; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .rinci-tabel td { vertical-align: top; }
  .rinci-tabel td.uraian { white-space: normal; min-width: 220px; }
  .rinci-tabel .kode-rek { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary); }
  .rinci-tab .nav-link .badge { margin-left: .35rem; }
  .info-keg dt { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: rgba(255,255,255,.7); font-weight: 500; }
  .info-keg dd { margin-bottom: .25rem; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $selisih = $pendapatan - $belanja;
  $tabAktif = in_array(request('tab'), ['pendapatan', 'belanja'], true) ? request('tab') : 'belanja';
  $persenPagu = $pagu['nominal'] > 0 ? $pagu['belanja_unit'] / $pagu['nominal'] * 100 : 0;
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-start gap-3">
    <div class="pg-header-icon"><i class="ti ti-list-details"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <div class="pg-header-sub small mb-1">Proses Bisnis {{ $keg->kodemak1 }} · {{ $keg->kode_mak }} · {{ $keg->namamak4 }}</div>
      <h4 class="mb-1">{{ $keg->uraiankeg }}</h4>
      <div class="pg-header-sub">{{ $unit->kode }} · {{ $unit->nama }} · T.A {{ $tahun }}/{{ $tahun + 1 }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('unit.kegiatan.index', ['mak' => $keg->kodemak1]) }}" class="btn btn-light"><i class="ti ti-arrow-left me-1"></i> Kembali</a>
      <a href="{{ route('unit.rinci.cetak', $keg->idkeg) }}" target="_blank" rel="noopener" class="btn btn-light"><i class="ti ti-printer me-1"></i> Print Preview</a>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-calendar"></i><span>{{ $keg->jadwal }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-coin"></i><span>Dana: {{ $keg->nama_pagu ?: $keg->idsumberdana }}</span></span>
    @if ($review->reviewer)
      <span class="pg-header-chip"><i class="ti ti-user-check"></i>
        <span>Review {{ $review->disetujui + $review->ditolak }}/{{ $review->reviewer }}
          · {{ $review->semua_setuju ? 'disetujui' : ($review->perlu_revisi ? ($review->belum_diperbaiki ? 'perlu revisi' : 'revisi dikirim') : 'menunggu') }}</span></span>
    @endif
    <span class="pg-header-chip"><i class="ti {{ $bolehUbah ? 'ti-lock-open' : 'ti-lock' }}"></i>
      <span>{{ $bolehUbah ? 'Dapat diubah' . (($komite['sisa_hari'] ?? 0) > 0 ? ' · sisa ' . $komite['sisa_hari'] . ' hari' : '') : 'Terkunci' }}</span></span>
  </div>
</div>

@include('_partials._flash')

@if ($review->perlu_revisi && $review->catatan->isNotEmpty())
  <div class="alert alert-{{ $review->belum_diperbaiki ? 'danger' : 'warning' }}">
    <strong>Catatan reviewer:</strong>
    @foreach ($review->catatan as $c)
      <div class="mt-1" style="white-space: pre-line;"><b>{{ $c->oleh }}</b> ({{ $c->status }}): {{ $c->teks }}</div>
    @endforeach
  </div>
@endif

{{-- Ringkasan --}}
<div class="row g-3 mb-4 ringkas">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
      <span class="avatar"><span class="avatar-initial rounded bg-label-success"><i class="ti ti-arrow-down-left"></i></span></span>
      <div><div class="label">Pendapatan</div><div class="nilai">{{ $rp($pendapatan) }}</div></div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
      <span class="avatar"><span class="avatar-initial rounded bg-label-danger"><i class="ti ti-arrow-up-right"></i></span></span>
      <div><div class="label">Belanja</div><div class="nilai">{{ $rp($belanja) }}</div></div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
      <span class="avatar"><span class="avatar-initial rounded bg-label-{{ $selisih >= 0 ? 'info' : 'warning' }}"><i class="ti ti-scale"></i></span></span>
      <div><div class="label">{{ $selisih > 0 ? 'Surplus' : ($selisih < 0 ? 'Defisit' : 'Surplus / Defisit') }}</div>
        <div class="nilai {{ $selisih < 0 ? 'text-warning' : '' }}">{{ $rp(abs($selisih)) }}</div></div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100"><div class="card-body">
      <div class="d-flex justify-content-between"><span class="label">Pagu {{ $pagu['sumber'] }}</span><span class="small">{{ number_format($persenPagu, 1, ',', '.') }}%</span></div>
      <div class="nilai">{{ $rp($pagu['nominal']) }}</div>
      <div class="progress mt-1" style="height: 6px;"><div class="progress-bar bg-{{ $persenPagu > 100 ? 'danger' : ($persenPagu >= 90 ? 'warning' : 'success') }}" style="width: {{ min($persenPagu, 100) }}%"></div></div>
      <div class="small text-muted mt-1">Sisa pagu unit: <span class="rp {{ $pagu['sisa'] < 0 ? 'text-danger' : '' }}">{{ $rp($pagu['sisa']) }}</span></div>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <ul class="nav nav-pills rinci-tab" role="tablist">
      @foreach (['pendapatan' => 'Pendapatan', 'belanja' => 'Belanja'] as $kunci => $label)
        <li class="nav-item">
          <button type="button" class="nav-link {{ $tabAktif === $kunci ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $kunci }}">
            {{ $label }} <span class="badge rounded-pill {{ $tabAktif === $kunci ? 'bg-white text-primary' : 'bg-label-secondary' }}">{{ ($rinci[$kunci] ?? collect())->count() }}</span>
          </button>
        </li>
      @endforeach
    </ul>
    @if ($bolehUbah)
      <div class="d-flex gap-2">
        <a href="{{ route('unit.rinci.create', [$keg->idkeg, 'pendapatan']) }}" class="btn btn-label-success"><i class="ti ti-plus me-1"></i> Pendapatan</a>
        <a href="{{ route('unit.rinci.create', [$keg->idkeg, 'belanja']) }}" class="btn btn-danger"><i class="ti ti-plus me-1"></i> Belanja</a>
      </div>
    @endif
  </div>

  <div class="tab-content p-0">
    @foreach (['pendapatan' => 'pendapatan', 'belanja' => 'belanja'] as $kunci => $label)
      @php
        $daftar = $rinci[$kunci] ?? collect();
      @endphp
      <div class="tab-pane fade {{ $tabAktif === $kunci ? 'show active' : '' }}" id="tab-{{ $kunci }}" role="tabpanel">
        <div class="table-responsive">
          <table class="table table-hover rinci-tabel mb-0">
            <thead>
              <tr>
                <th style="width: 40px;">No</th>
                <th>Rekening &amp; Uraian</th>
                <th>Volume</th>
                <th class="text-end">Harga Satuan</th>
                <th class="text-end">Jumlah</th>
                @if ($bolehUbah)<th class="text-end" style="width: 100px;">Aksi</th>@endif
              </tr>
            </thead>
            <tbody>
              @forelse ($daftar as $r)
                <tr>
                  <td class="text-muted">{{ $loop->iteration }}</td>
                  <td class="uraian">
                    <div class="kode-rek">{{ $r->kode_rek }} · {{ $r->namarek6 }}</div>
                    <div class="fw-semibold">{{ $r->uraianrinci }}</div>
                  </td>
                  <td>{{ $r->volume }}</td>
                  <td class="text-end rp">{{ $rp($r->hargasat) }}</td>
                  <td class="text-end rp fw-semibold">{{ $rp($r->jumlah) }}</td>
                  @if ($bolehUbah)
                    <td class="text-end text-nowrap">
                      <a href="{{ route('unit.rinci.edit', [$keg->idkeg, $r->idrinci]) }}" class="btn btn-sm btn-icon btn-text-warning rounded-pill" title="Ubah"><i class="ti ti-pencil"></i></a>
                      <form method="POST" action="{{ route('unit.rinci.destroy', [$keg->idkeg, $r->idrinci]) }}" class="d-inline form-hapus" data-label="{{ $r->uraianrinci }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Hapus"><i class="ti ti-trash"></i></button>
                      </form>
                    </td>
                  @endif
                </tr>
              @empty
                <tr><td colspan="{{ $bolehUbah ? 6 : 5 }}" class="text-center text-muted py-5">Data rincian {{ $label }} kosong.</td></tr>
              @endforelse
            </tbody>
            @if ($daftar->isNotEmpty())
              <tfoot>
                <tr class="fw-semibold">
                  <td></td><td colspan="3" class="text-end">Jumlah {{ $label }}</td>
                  <td class="text-end rp">{{ $rp($daftar->sum('jumlah')) }}</td>
                  @if ($bolehUbah)<td></td>@endif
                </tr>
              </tfoot>
            @endif
          </table>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Surplus (v1): pilihan ditambahkan pada pagu unit atau tidak --}}
  @if ($selisih > 0)
    <div class="card-footer border-top">
      <form method="POST" action="{{ route('unit.rinci.surplus', $keg->idkeg) }}" class="d-flex flex-wrap align-items-center gap-3">
        @csrf
        <div class="fw-semibold"><i class="ti ti-info-circle text-info me-1"></i>Terjadi surplus {{ $rp($selisih) }}. Tambahkan pada pagu unit?</div>
        <div class="form-check mb-0">
          <input class="form-check-input" type="radio" name="opsi" id="opsi-plus" value="plus" @checked($keg->ket !== 'no')>
          <label class="form-check-label" for="opsi-plus">Ya, tambahkan</label>
        </div>
        <div class="form-check mb-0">
          <input class="form-check-input" type="radio" name="opsi" id="opsi-no" value="no" @checked($keg->ket === 'no')>
          <label class="form-check-label" for="opsi-no">Tidak</label>
        </div>
        <button type="submit" class="btn btn-sm btn-primary">Proses</button>
        @if ($keg->ket)
          <small class="text-muted">Tersimpan: {{ $keg->ket === 'plus' ? 'ditambahkan ke pagu' : 'tidak ditambahkan' }}</small>
        @endif
      </form>
    </div>
  @endif
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const esc = s => $('<div>').text(s ?? '').html();
  document.querySelectorAll('.form-hapus').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    Swal.fire({ title: 'Hapus rincian?', html: '<b>' + esc(f.dataset.label) + '</b> akan dihapus.', icon: 'warning',
      showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal', buttonsStyling: false,
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' } }).then(r => { if (r.isConfirmed) f.submit(); });
  }));
});
</script>
@endsection
