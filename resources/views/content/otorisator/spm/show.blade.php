@extends('layouts/layoutMaster')

@section('title', 'Otorisasi SPM')

@section('page-style')
<style>
  .spm-tabel td { vertical-align: top; }
  .spm-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .spm-tabel .kode-mak { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary); }
  .spm-tabel tr.total td { font-weight: 700; background: rgba(var(--bs-secondary-rgb), .05); }
  .ttd { text-align: center; }
  .ttd .nama { margin-top: 2.5rem; font-weight: 700; text-decoration: underline; }
  .pernyataan { border: 2px dotted var(--bs-border-color); border-radius: .375rem; padding: .75rem 1rem; font-size: .875rem; color: var(--bs-body-color); }
  .perintah { background: rgba(var(--bs-primary-rgb), .05); border-radius: .5rem; padding: 1rem 1.25rem; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $total = $baris->sum('ajuan');
  $tgl = \Illuminate\Support\Carbon::parse($ringkas->tgl_saji)->locale('id')->translatedFormat('d F Y');
  $status = fn (?string $v) => match ($v) { 'Disetujui' => ['bg-label-success', 'Disetujui'], 'Ditolak' => ['bg-label-danger', 'Ditolak'], default => ['bg-label-secondary', 'Menunggu'] };
  [$k1, $t1] = $status($ringkas->verifikasi1);
  [$k2, $t2] = $status($ringkas->verifikasi2);
  $wr2Nama = $ttd2 ? ($ttd2->an_nama_pimp ?: $ttd2->nama_pimp) : '';
  $wr2An = $ttd2 && $ttd2->an_nama_pimp ? 'An. ' : '';
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-file-certificate"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Surat Perintah Membayar</h4>
      <p class="pg-header-sub mb-0 font-monospace">{{ $noSpm }}</p>
    </div>
    <a href="{{ route('otorisator.spm.index') }}" class="btn btn-light"><i class="ti ti-arrow-left me-1"></i> Kembali</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building"></i><span>{{ $ringkas->kode_unit }} · {{ $ringkas->nama_unit }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-file-text"></i><span>SPP {{ $ringkas->no_spp }} · {{ $tgl }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-cash"></i><span>Nominal <strong>{{ $rp($total) }}</strong></span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card mb-4">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <span class="small text-muted">BAK</span><span class="badge {{ $k1 }}">{{ $t1 }}</span>
      <span class="small text-muted ms-2">WR II</span><span class="badge {{ $k2 }}">{{ $t2 }}</span>
      @if ($ringkas->alasan_tolak && ($ringkas->verifikasi1 === 'Ditolak' || $ringkas->verifikasi2 === 'Ditolak'))
        <small class="text-danger ms-2"><i class="ti ti-message-exclamation ti-xs"></i> {{ $ringkas->alasan_tolak }}</small>
      @endif
    </div>
    @if ($bolehPutus)
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modal-setujui"><i class="ti ti-check me-1"></i> Setujui SPM</button>
        <button type="button" class="btn btn-label-danger" data-bs-toggle="modal" data-bs-target="#modal-tolak"><i class="ti ti-x me-1"></i> Tolak SPM</button>
      </div>
    @endif
  </div>

  {{-- Rincian SPP (spm_approve_one v1) --}}
  <div class="table-responsive">
    <table class="table spm-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>Kegiatan</th><th class="text-end">Anggaran</th><th class="text-end">Prnh. Diterima</th>
          <th class="text-end">Tersedia</th><th class="text-end">Diminta</th><th class="text-end">Sisa</th></tr>
      </thead>
      <tbody>
        @foreach ($baris as $b)
          <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td style="white-space: normal; min-width: 240px;"><div class="kode-mak">{{ $b->kode_mak }} · {{ $b->namamak4 }}</div><div class="fw-semibold">{{ $b->uraiankeg }}</div></td>
            <td class="text-end rp">{{ $rp($b->anggaran) }}</td>
            <td class="text-end rp">{{ $rp($b->diterima) }}</td>
            <td class="text-end rp">{{ $rp($b->tersedia) }}</td>
            <td class="text-end rp fw-semibold">{{ $rp($b->ajuan) }}</td>
            <td class="text-end rp {{ $b->tersedia - $b->ajuan < 0 ? 'text-danger fw-semibold' : '' }}">{{ $rp($b->tersedia - $b->ajuan) }}</td>
          </tr>
        @endforeach
        <tr class="total"><td colspan="5" class="text-end">Total</td><td class="text-end rp">{{ $rp($total) }}</td><td></td></tr>
      </tbody>
    </table>
  </div>

  <div class="card-body">
    <div class="row g-4 align-items-end">
      <div class="col-md-7">
        <div class="pernyataan">Ajuan Surat Permintaan Pembayaran (SPP) kami buat sesuai dengan dokumen perencanaan dan anggaran. Setelah dana kami terima,
          akan kami gunakan sesuai dengan peruntukannya dan akan kami pertanggungjawabkan tepat waktu sesuai dengan aturan yang berlaku.</div>
      </div>
      <div class="col-md-5 ttd">Metro, {{ $tgl }}<br>{{ $induk->nama ?? '' }}<div class="nama">{{ $induk->nama_pimp ?? '' }}</div></div>
    </div>

    <hr class="my-4">

    <div class="perintah mb-4">
      <div>Surat Perintah Membayar (SPM) No: <strong class="font-monospace">{{ $noSpm }}</strong></div>
      <div class="mt-1">Bendahara Keuangan dimohon membayarkan uang sejumlah: <strong>{{ $rp($total) }}</strong></div>
      <div class="mt-1 fst-italic text-muted">({{ Helper::terbilang($total) }})</div>
    </div>

    <div class="row text-center g-3">
      <div class="col-12 small text-muted">Menyetujui,</div>
      <div class="col-6 ttd">{{ $ttd1->nama ?? '' }}<br><span class="badge {{ $k1 }} mt-1">{{ $t1 }}</span><div class="nama">{{ $ttd1->nama_pimp ?? '' }}</div></div>
      <div class="col-6 ttd">{{ $wr2An }}{{ $ttd2->nama ?? '' }}<br><span class="badge {{ $k2 }} mt-1">{{ $t2 }}</span><div class="nama">{{ $wr2Nama }}</div></div>
    </div>
  </div>
</div>

@if ($bolehPutus)
  <div class="modal fade" id="modal-setujui" tabindex="-1" aria-labelledby="judul-setujui" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" action="{{ route('otorisator.spm.setujui', $id) }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="judul-setujui">Setujui SPM?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          SPM <b class="font-monospace">{{ $noSpm }}</b> senilai <b>{{ $rp($total) }}</b> disetujui sebagai {{ $lv['nama'] }}
          @if ($lv['syarat']) dan menjadi SPM final yang siap dibayarkan. @else lalu diteruskan ke WR II. @endif
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i> Setujui</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal fade" id="modal-tolak" tabindex="-1" aria-labelledby="judul-tolak" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form method="POST" action="{{ route('otorisator.spm.tolak', $id) }}" class="modal-content">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="judul-tolak">Tolak SPM</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <label for="alasan" class="form-label">Alasan penolakan</label>
          <textarea id="alasan" name="alasan" rows="3" required maxlength="1000"
                    class="form-control @error('alasan', 'tolak') is-invalid @enderror">{{ old('alasan') }}</textarea>
          @error('alasan', 'tolak')<div class="invalid-feedback">{{ $message }}</div>@enderror
          <div class="form-text">SPM yang ditolak dikembalikan ke Operator SPM untuk dibatalkan.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger"><i class="ti ti-x me-1"></i> Tolak SPM</button>
        </div>
      </form>
    </div>
  </div>
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if ({{ $errors->tolak->any() ? 'true' : 'false' }}) bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-tolak')).show();
});
</script>
@endsection
