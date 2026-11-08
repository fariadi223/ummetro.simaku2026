@extends('layouts/layoutMaster')

@section('title', 'Kegiatan')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .bidang-tab { gap: .5rem; flex-wrap: wrap; }
  .bidang-tab .nav-link { display: flex; align-items: center; gap: .5rem; text-align: left; white-space: normal; padding: .55rem .9rem; }
  .bidang-tab .nav-link .kode { display: inline-grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: .4rem; font-weight: 700;
                                font-size: .8rem; background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary); flex-shrink: 0; }
  .bidang-tab .nav-link.active .kode { background: rgba(255,255,255,.25); color: #fff; }

  .keg-tabel td { vertical-align: top; }
  .keg-tabel td.utama { min-width: 280px; white-space: normal; }
  .keg-tabel .kode-mak { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary); }
  .keg-tabel .judul { font-weight: 600; }
  .keg-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .keg-tabel tr.baris-keg.setuju td:first-child { box-shadow: inset 3px 0 0 var(--bs-success); }
  .keg-tabel tr.baris-keg.revisi td:first-child { box-shadow: inset 3px 0 0 var(--bs-danger); }
  .keg-tabel tr.baris-keg.dikirim td:first-child { box-shadow: inset 3px 0 0 var(--bs-warning); }
  .keg-tabel tr.detail td { background: rgba(var(--bs-secondary-rgb), .04); border-top: 0; }
  .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem 1.5rem; }
  .detail-grid .label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary); margin-bottom: .2rem; }
  .detail-grid .isi { white-space: pre-line; font-size: .875rem; }
  .catatan { border-left: 3px solid var(--bs-border-color); padding: .4rem .75rem; margin-bottom: .5rem; font-size: .85rem; white-space: pre-line; }
  .catatan.ditolak { border-color: var(--bs-danger); background: rgba(var(--bs-danger-rgb), .05); }
  .catatan.disetujui { border-color: var(--bs-success); background: rgba(var(--bs-success-rgb), .04); }
  .btn-lipat i { transition: transform .15s ease; }
  .btn-lipat[aria-expanded="true"] i { transform: rotate(180deg); }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
  $perluRevisi = $kegiatan->filter(fn ($k) => $k->review->perlu_revisi)->count();
  $belumKirim = $kegiatan->filter(fn ($k) => $k->review->perlu_revisi && $k->review->belum_diperbaiki > 0)->count();
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-list-check"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Proses Bisnis - Kegiatan</h4>
      <p class="pg-header-sub mb-0">{{ $unit->kode }} · {{ $unit->nama }} · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    @if ($komite['dibuka'])
      <a href="{{ route('unit.kegiatan.create', ['mak' => $aktif->kodemak1]) }}" class="btn btn-light">
        <i class="ti ti-plus me-1"></i> Tambah Kegiatan
      </a>
    @endif
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    @if ($komite['dibuka'])
      <span class="pg-header-chip"><i class="ti ti-lock-open"></i>
        <span>Input dibuka{{ $komite['sisa_hari'] > 0 ? ' · sisa ' . $komite['sisa_hari'] . ' hari' : '' }}{{ $komite['klinik'] ? ' · klinik aktif' : '' }}</span></span>
    @else
      <span class="pg-header-chip"><i class="ti ti-lock"></i><span>Jadwal komite ditutup</span></span>
    @endif
    <span class="pg-header-chip"><i class="ti ti-user-check"></i><span><strong>{{ $jumlahReviewer }}</strong> reviewer</span></span>
    <span class="pg-header-chip"><i class="ti ti-list-numbers"></i><span><strong>{{ $kegiatan->count() }}</strong> kegiatan di bidang ini</span></span>
    <span class="pg-header-chip"><i class="ti ti-wallet"></i><span>Anggaran <strong>{{ $rp($totalAnggaran) }}</strong></span></span>
  </div>
</div>

@include('_partials._flash')

@if ($komite['dibuka'] && $komite['sisa_hari'] > 0 && $komite['sisa_hari'] <= 7)
  <div class="alert alert-warning d-flex gap-2"><i class="ti ti-clock mt-1"></i>
    <div><strong>Informasi!</strong> Waktu input anggaran tersisa <strong>{{ $komite['sisa_hari'] }} hari</strong>.</div></div>
@endif
@if ($perluRevisi)
  <div class="alert alert-danger d-flex gap-2"><i class="ti ti-alert-triangle mt-1"></i>
    <div><strong>Informasi!</strong> {{ $perluRevisi }} kegiatan harus diperbaiki, {{ $belumKirim }} di antaranya belum dikirim ulang.
      Perbaiki kegiatan lalu klik <b>Selesai diperbaiki</b>.</div></div>
@endif

<div class="card">
  <div class="card-header pb-0">
    <ul class="nav nav-pills bidang-tab mb-3">
      @foreach ($bidang as $b)
        <li class="nav-item">
          <a href="{{ route('unit.kegiatan.index', ['mak' => $b->kodemak1]) }}" class="nav-link {{ $b->idmak1 === $aktif->idmak1 ? 'active' : '' }}">
            <span class="kode">{{ $b->kodemak1 }}</span>
            <span>{{ Helper::judulMak($b->namamak1) }}</span>
            <span class="badge rounded-pill {{ $b->idmak1 === $aktif->idmak1 ? 'bg-white text-primary' : 'bg-label-secondary' }}">{{ $perBidang[$b->idmak1] ?? 0 }}</span>
          </a>
        </li>
      @endforeach
    </ul>
  </div>

  <div class="table-responsive">
    <table class="table keg-tabel mb-0">
      <thead>
        <tr>
          <th style="width: 40px;">No</th>
          <th>Kegiatan</th>
          <th>Sumber Rujukan</th>
          <th>Jadwal</th>
          <th class="text-end">Anggaran</th>
          <th>Review</th>
          <th class="text-end" style="width: 170px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($kegiatan as $k)
          @php
            $rv = $k->review;
            $kelas = $rv->semua_setuju ? 'setuju' : ($rv->perlu_revisi ? ($rv->belum_diperbaiki > 0 ? 'revisi' : 'dikirim') : '');
          @endphp
          <tr class="baris-keg {{ $kelas }}">
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td class="utama">
              <div class="kode-mak">{{ $k->kode_mak }} · {{ $k->namamak4 }}</div>
              <div class="judul">{{ $k->uraiankeg }}</div>
            </td>
            <td style="min-width: 140px;">
              <span class="fw-semibold">{{ $k->rujukan_nama ?: '—' }}</span>
              @if ($k->indikator)
                <div class="small">Indikator: {{ str_replace(',', ', ', $k->indikator) }}</div>
              @endif
              <div class="small text-muted">{{ $k->nama_pagu ? 'Dana: ' . $k->nama_pagu : '' }}</div>
            </td>
            <td style="min-width: 120px; white-space: normal;">{{ $k->jadwal }}</td>
            <td class="text-end rp">{{ $rp($k->anggaran) }}</td>
            <td style="min-width: 130px;">
              @if ($rv->reviewer === 0)
                <span class="badge bg-label-secondary">Belum ada reviewer</span>
              @elseif ($rv->semua_setuju)
                <span class="badge bg-label-success">Disetujui</span>
              @elseif ($rv->perlu_revisi && $rv->belum_diperbaiki > 0)
                <span class="badge bg-label-danger">Perlu revisi</span>
              @elseif ($rv->perlu_revisi)
                <span class="badge bg-label-warning">Revisi dikirim</span>
              @else
                <span class="badge bg-label-secondary">Menunggu review</span>
              @endif
              <div class="small text-muted mt-1">{{ $rv->disetujui + $rv->ditolak }}/{{ $rv->reviewer }} reviewer</div>
            </td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-lipat" title="Detail"
                      data-bs-toggle="collapse" data-bs-target="#detail-{{ $k->idkeg }}" aria-expanded="false">
                <i class="ti ti-chevron-down"></i>
              </button>
              <a href="{{ route('unit.rinci.index', $k->idkeg) }}" class="btn btn-sm btn-icon btn-text-primary rounded-pill"
                 title="Rincian pendapatan & belanja" data-bs-toggle="tooltip">
                <i class="ti ti-list-details"></i>
              </a>
              @if ($k->boleh_ubah)
                <a href="{{ route('unit.kegiatan.edit', $k->idkeg) }}" class="btn btn-sm btn-icon btn-text-warning rounded-pill" title="Ubah">
                  <i class="ti ti-pencil"></i>
                </a>
                <form method="POST" action="{{ route('unit.kegiatan.destroy', $k->idkeg) }}" class="d-inline form-hapus" data-label="{{ $k->uraiankeg }}">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Hapus"><i class="ti ti-trash"></i></button>
                </form>
              @endif
              @if ($rv->perlu_revisi && $rv->belum_diperbaiki > 0)
                <form method="POST" action="{{ route('unit.kegiatan.selesai', $k->idkeg) }}" class="d-block mt-1 form-selesai">
                  @csrf
                  <button type="submit" class="btn btn-xs btn-warning"><i class="ti ti-send ti-xs me-1"></i>Selesai diperbaiki</button>
                </form>
              @endif
            </td>
          </tr>
          <tr class="detail collapse {{ $rv->perlu_revisi && $rv->belum_diperbaiki > 0 ? 'show' : '' }}" id="detail-{{ $k->idkeg }}">
            <td></td>
            <td colspan="6">
              <div class="detail-grid py-2">
                <div><div class="label">Latar Belakang</div><div class="isi">{{ $k->latblk }}</div></div>
                <div><div class="label">Tujuan</div><div class="isi">{{ $k->tujuan }}</div></div>
                <div><div class="label">Luaran / Output</div><div class="isi">{{ $k->lingkup }}</div></div>
                <div><div class="label">Deskripsi Sumber Rujukan</div><div class="isi">{{ $k->kebijakan_uraian }}</div></div>
              </div>
              @if ($rv->catatan->isNotEmpty())
                <div class="label mt-2 mb-1" style="font-size: .7rem; text-transform: uppercase; color: var(--bs-secondary);">Catatan Reviewer</div>
                @foreach ($rv->catatan as $c)
                  <div class="catatan {{ $c->status }}"><b>{{ $c->oleh }}</b> ({{ $c->status }}): {{ $c->teks }}</div>
                @endforeach
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center text-muted py-5">
              Belum ada kegiatan di bidang ini.
              @if ($komite['dibuka'])
                <div class="mt-2"><a href="{{ route('unit.kegiatan.create', ['mak' => $aktif->kodemak1]) }}" class="btn btn-sm btn-primary"><i class="ti ti-plus me-1"></i>Tambah Kegiatan</a></div>
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
      @if ($kegiatan->isNotEmpty())
        <tfoot>
          <tr class="fw-semibold"><td></td><td colspan="3">Total anggaran bidang ini</td><td class="text-end rp">{{ $rp($totalAnggaran) }}</td><td colspan="2"></td></tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const esc = s => $('<div>').text(s ?? '').html();
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  const tanya = (opsi, f) => Swal.fire(Object.assign({ icon: 'warning', showCancelButton: true, cancelButtonText: 'Batal', buttonsStyling: false,
    customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-label-secondary' } }, opsi)).then(r => { if (r.isConfirmed) f.submit(); });

  document.querySelectorAll('.form-hapus').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    tanya({ title: 'Hapus kegiatan?', html: '<b>' + esc(f.dataset.label) + '</b> beserta seluruh rinciannya akan dihapus.',
      confirmButtonText: 'Ya, hapus', customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' } }, f);
  }));
  document.querySelectorAll('.form-selesai').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    tanya({ icon: 'question', title: 'Kirim perbaikan?', text: 'Apakah kegiatan sudah selesai diperbaiki? Setelah dikirim, kegiatan tidak dapat diubah lagi.',
      confirmButtonText: 'Kirim' }, f);
  }));
});
</script>
@endsection
