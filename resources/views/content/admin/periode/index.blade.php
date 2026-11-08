@extends('layouts/layoutMaster')

@section('title', 'Periode Anggaran')

@section('page-style')
<style>
  .ta-pilih { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
  .ta-pilih .form-select { width: auto; min-width: 180px; }
  .periode-tabel tr.dipilih td { background: rgba(var(--bs-primary-rgb), .06); }
  .periode-tabel .rentang { white-space: nowrap; }
  .periode-tabel .rentang small { display: block; color: var(--bs-secondary); }
</style>
@endsection

@php
  $tgl = fn ($c) => $c ? $c->locale('id')->translatedFormat('d M Y') : '—';
  $dariSampai = fn ($a, $b) => $tgl($a) . ' – ' . $tgl($b);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-calendar-cog"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Periode Anggaran</h4>
      <p class="pg-header-sub mb-0">Pilih tahun anggaran yang sedang dikerjakan dan atur jadwal penganggaran &amp; komite.</p>
    </div>
    <span class="pg-header-chip">
      <i class="ti ti-calendar-event"></i>
      <span>Sedang dikerjakan: <strong>T.A {{ $periode }}/{{ $periode + 1 }}</strong></span>
    </span>
  </div>

  <form method="POST" action="{{ route('admin.periode.pilih') }}" class="ta-pilih mt-3">
    @csrf
    <label for="pilih-tahun" class="mb-0">Pilih T.A</label>
    <select id="pilih-tahun" name="tahun" class="form-select">
      @foreach ($daftar as $i)
        <option value="{{ $i->th_angg }}" @selected($i->th_angg == $periode)>{{ $i->th_angg }}/{{ $i->th_angg + 1 }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-light"><i class="ti ti-check me-1"></i> Proses</button>
  </form>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header">
    <h5 class="mb-0">Jadwal Penganggaran</h5>
    <small class="text-muted">Unit dapat menginput anggaran selama jadwal komite belum berakhir (atau klinik anggaran unit aktif).</small>
  </div>
  <div class="table-responsive">
    <table class="table table-hover periode-tabel mb-0">
      <thead>
        <tr>
          <th>Tahun Anggaran</th>
          <th>Masa Anggaran</th>
          <th>Jadwal Komite</th>
          <th>Status</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($daftar as $i)
          <tr class="{{ $i->th_angg == $periode ? 'dipilih' : '' }}">
            <td>
              <span class="fw-semibold">{{ $i->th_angg }}/{{ $i->th_angg + 1 }}</span>
              @if ($i->th_angg == $periode)
                <span class="badge bg-primary ms-1">Dikerjakan</span>
              @endif
            </td>
            <td class="rentang">{{ $dariSampai($i->tgl['awal_angg'], $i->tgl['akhir_angg']) }}</td>
            <td class="rentang">
              {{ $dariSampai($i->tgl['awal_komite'], $i->tgl['akhir_komite']) }}
              <small>{{ $i->komite_buka ? 'Komite masih dibuka' : 'Komite sudah ditutup' }}</small>
            </td>
            <td>
              <span class="badge {{ $i->aktif === 'enable' ? 'bg-label-success' : 'bg-label-secondary' }}"
                    title="Tahun anggaran berjalan (dipakai saat login)">{{ $i->aktif === 'enable' ? 'Aktif' : 'Tidak aktif' }}</span>
              <span class="badge {{ $i->penjurnalan === 'open' ? 'bg-label-info' : 'bg-label-secondary' }}"
                    title="Penjurnalan untuk akuntansi, kasir, dan SPM">Jurnal {{ $i->penjurnalan === 'open' ? 'dibuka' : 'ditutup' }}</span>
            </td>
            <td class="text-end text-nowrap">
              @if ($i->th_angg != $periode)
                <form method="POST" action="{{ route('admin.periode.pilih') }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="tahun" value="{{ $i->th_angg }}">
                  <button type="submit" class="btn btn-sm btn-text-primary" title="Kerjakan T.A ini">
                    <i class="ti ti-player-play ti-xs me-1"></i>Pilih
                  </button>
                </form>
              @endif
              <button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill btn-jadwal" title="Ubah jadwal"
                      data-url="{{ route('admin.periode.jadwal', $i) }}"
                      data-ta="{{ $i->th_angg }}/{{ $i->th_angg + 1 }}"
                      data-jadwal="{{ json_encode(collect($i->tgl)->map(fn ($c) => $c?->format('Y-m-d'))) }}">
                <i class="ti ti-calendar-time"></i>
              </button>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- Modal ubah jadwal --}}
<div class="modal fade" id="modal-jadwal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="#" class="modal-content" id="form-jadwal">
      @csrf
      @method('PUT')
      <input type="hidden" name="_form_action" id="form-jadwal-action" value="{{ old('_form_action') }}">
      <input type="hidden" name="_ta" id="form-jadwal-ta" value="{{ old('_ta') }}">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Ubah Jadwal</h5>
          <small class="text-muted">T.A <span id="jadwal-ta">{{ old('_ta') }}</span></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <h6 class="mb-2">Masa Anggaran</h6>
        <div class="row g-3 mb-4">
          @foreach (['awal_angg' => 'Tanggal awal', 'akhir_angg' => 'Tanggal akhir'] as $k => $l)
            <div class="col-6">
              <label class="form-label" for="f-{{ $k }}">{{ $l }}</label>
              <input type="date" id="f-{{ $k }}" name="{{ $k }}" class="form-control @error($k) is-invalid @enderror" value="{{ old($k) }}" required>
              @error($k)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          @endforeach
        </div>
        <h6 class="mb-2">Jadwal Komite <small class="text-muted fw-normal">(batas unit menginput anggaran)</small></h6>
        <div class="row g-3">
          @foreach (['awal_komite' => 'Tanggal awal', 'akhir_komite' => 'Tanggal akhir'] as $k => $l)
            <div class="col-6">
              <label class="form-label" for="f-{{ $k }}">{{ $l }}</label>
              <input type="date" id="f-{{ $k }}" name="{{ $k }}" class="form-control @error($k) is-invalid @enderror" value="{{ old($k) }}" required>
              @error($k)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          @endforeach
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = new bootstrap.Modal(document.getElementById('modal-jadwal'));
  const form = document.getElementById('form-jadwal');

  function buka(url, ta, jadwal) {
    form.action = url;
    document.getElementById('form-jadwal-action').value = url;
    document.getElementById('form-jadwal-ta').value = ta;
    document.getElementById('jadwal-ta').textContent = ta;
    if (jadwal) {
      Object.keys(jadwal).forEach(k => { const el = document.getElementById('f-' + k); if (el) el.value = jadwal[k] || ''; });
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }
    modal.show();
  }

  document.querySelectorAll('.btn-jadwal').forEach(btn => btn.addEventListener('click', () =>
    buka(btn.dataset.url, btn.dataset.ta, JSON.parse(btn.dataset.jadwal))));

  @if ($errors->any() && str_starts_with((string) old('_form_action'), url('/') . '/'))
    buka(@json(old('_form_action')), @json(old('_ta')), null);
  @endif
});
</script>
@endsection
