@extends('layouts/layoutMaster')

@section('title', 'Otorisasi SPP')

@section('page-style')
<style>
  .spp-tabel td { vertical-align: top; }
  .spp-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .spp-tabel .kode-mak { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary); }
  .spp-tabel tr.total td { font-weight: 700; background: rgba(var(--bs-secondary-rgb), .05); }
  .pernyataan { border-left: 3px solid var(--bs-primary); background: rgba(var(--bs-primary-rgb), .05); padding: .75rem 1rem; border-radius: .375rem; font-size: .9rem; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tgl = \Illuminate\Support\Carbon::parse($ringkas->tgl_saji)->locale('id')->translatedFormat('d F Y');
  $badgeOtorisasi = ['Belum' => ['bg-label-warning', 'Menunggu otorisasi'], 'Sudah' => ['bg-label-success', 'Sudah diotorisasi'], 'Batal' => ['bg-label-danger', 'Ditolak pimpinan']];
  [$kelasOto, $teksOto] = $badgeOtorisasi[$ringkas->otorisasi] ?? ['bg-label-secondary', $ringkas->otorisasi];
  $bukaModal = $errors->setujui->any() ? 'modal-setujui' : ($errors->tolak->any() ? 'modal-tolak' : null);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-file-text"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Surat Permintaan Pembayaran</h4>
      <p class="pg-header-sub mb-0 font-monospace">{{ $nomor }}</p>
    </div>
    <a href="{{ route('unit.otorisasi.cetak', $id) }}" target="_blank" rel="noopener" class="btn btn-light"><i class="ti ti-printer me-1"></i> Cetak</a>
    <a href="{{ route('unit.otorisasi.index') }}" class="btn btn-light"><i class="ti ti-arrow-left me-1"></i> Kembali</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building"></i><span>{{ $ringkas->kode_unit }} · {{ $ringkas->nama_unit }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-calendar"></i><span>Disajikan {{ $tgl }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-cash"></i><span>Diminta <strong>{{ $rp($baris->sum('ajuan')) }}</strong></span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="badge {{ $kelasOto }}">{{ $teksOto }}</span>
      <span class="badge bg-label-secondary">{{ $ringkas->status_spp }}</span>
      @if ($ringkas->alasan && ($ringkas->otorisasi === 'Batal' || $ringkas->status_spp === 'Ditolak'))
        <small class="text-danger"><i class="ti ti-message-exclamation ti-xs"></i> {{ $ringkas->alasan }}</small>
      @endif
    </div>
    @if ($bolehOtorisasi)
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-setujui"><i class="ti ti-check me-1"></i> Setujui SPP</button>
        <button type="button" class="btn btn-label-danger" data-bs-toggle="modal" data-bs-target="#modal-tolak"><i class="ti ti-x me-1"></i> Tolak SPP</button>
      </div>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table spp-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>Kegiatan</th><th class="text-end">Total Anggaran</th><th class="text-end">Prnh. Diterima</th>
          <th class="text-end">Tersedia</th><th class="text-end">Diminta</th><th class="text-end">Sisa</th></tr>
      </thead>
      <tbody>
        @foreach ($baris as $b)
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td style="white-space: normal; min-width: 240px;">
              <div class="kode-mak">{{ $b->kode_mak }} · {{ $b->namamak4 }}</div>
              <div class="fw-semibold">{{ $b->uraiankeg }}</div>
            </td>
            <td class="text-end rp">{{ $rp($b->anggaran) }}</td>
            <td class="text-end rp">{{ $rp($b->diterima) }}</td>
            <td class="text-end rp">{{ $rp($b->tersedia) }}</td>
            <td class="text-end rp fw-semibold">{{ $rp($b->ajuan) }}</td>
            <td class="text-end rp {{ $b->tersedia - $b->ajuan < 0 ? 'text-danger' : '' }}">{{ $rp($b->tersedia - $b->ajuan) }}</td>
          </tr>
        @endforeach
        <tr class="total"><td colspan="5" class="text-end">Total diminta</td><td class="text-end rp">{{ $rp($baris->sum('ajuan')) }}</td><td></td></tr>
      </tbody>
    </table>
  </div>
</div>

@if ($bolehOtorisasi)
  {{-- Setujui: pernyataan + password pimpinan (spp/setujui v1) --}}
  <div class="modal fade" id="modal-setujui" tabindex="-1" aria-labelledby="judul-setujui" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" action="{{ route('unit.otorisasi.setujui', $id) }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="judul-setujui">Otorisasi SPP oleh Pimpinan Unit Kerja</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <div class="pernyataan mb-3">
            Ajuan Surat Permintaan Pembayaran (SPP) kami buat sesuai dengan dokumen perencanaan dan anggaran. Setelah dana kami terima,
            akan kami gunakan sesuai dengan peruntukannya dan akan kami pertanggungjawabkan tepat waktu sesuai dengan aturan yang berlaku.
          </div>
          <label for="password" class="form-label">Masukkan password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required
                 class="form-control @error('password', 'setujui') is-invalid @enderror">
          @error('password', 'setujui')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i> Setujui</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Tolak: komentar wajib (spp/tolak v1) --}}
  <div class="modal fade" id="modal-tolak" tabindex="-1" aria-labelledby="judul-tolak" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" action="{{ route('unit.otorisasi.tolak', $id) }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="judul-tolak">Tolak SPP</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <label for="alasan" class="form-label">Alasan penolakan</label>
          <textarea id="alasan" name="alasan" rows="3" required maxlength="1000"
                    class="form-control @error('alasan', 'tolak') is-invalid @enderror">{{ old('alasan') }}</textarea>
          @error('alasan', 'tolak')<div class="invalid-feedback">{{ $message }}</div>@enderror
          <div class="form-text">Alasan ini ditampilkan ke sub unit. Setelah ditolak, sub unit dapat menghapus dan mengajukan ulang SPP.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger"><i class="ti ti-x me-1"></i> Tolak SPP</button>
        </div>
      </form>
    </div>
  </div>
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const buka = {{ \Illuminate\Support\Js::from($bukaModal) }};
  if (buka) bootstrap.Modal.getOrCreateInstance(document.getElementById(buka)).show();
  ['modal-setujui', 'modal-tolak'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('shown.bs.modal', () => el.querySelector('input:not([type=hidden]), textarea')?.focus());
  });
});
</script>
@endsection
