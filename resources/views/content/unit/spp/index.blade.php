@extends('layouts/layoutMaster')

@section('title', 'Pengajuan SPP')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .spp-tabel td { vertical-align: top; }
  .spp-tabel td.utama { min-width: 260px; white-space: normal; }
  .spp-tabel .kode-mak { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary); }
  .spp-tabel .judul { font-weight: 600; }
  .spp-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .spp-tabel tr.total td { font-weight: 700; background: rgba(var(--bs-secondary-rgb), .05); }
  .spp-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y');
  $tampil = $kegiatan->where('status', '!=', 'kosong');
  $bisa = $tampil->where('status', 'boleh')->count();
  $badgeStatus = [
    'boleh' => null,
    'diajukan' => ['bg-label-info', 'ti-send'],
    'review' => ['bg-label-secondary', 'ti-user-question'],
    'spj' => ['bg-label-warning', 'ti-file-alert'],
    'kuota' => ['bg-label-danger', 'ti-ban'],
    'lunas' => ['bg-label-success', 'ti-circle-check'],
  ];
  $badgeOtorisasi = ['Belum' => ['bg-label-warning', 'Menunggu'], 'Sudah' => ['bg-label-success', 'Sudah'], 'Batal' => ['bg-label-danger', 'Dibatalkan']];
  $badgeVerifikasi = ['Belum diverifikasi' => 'bg-label-secondary', 'Disetujui' => 'bg-label-success', 'Ditolak' => 'bg-label-danger'];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-send"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Pengajuan SPP</h4>
      <p class="pg-header-sub mb-0">Surat Permintaan Pembayaran · {{ $unit->kode }} · {{ $unit->nama }} · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    @if ($jadwal['aktif'] && !$susun && $bolehTambah)
      <a href="{{ route('unit.spp.index', ['baru' => 1]) }}" class="btn btn-light"><i class="ti ti-plus me-1"></i> Tambah SPP</a>
    @endif
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    @if ($jadwal['aktif'])
      <span class="pg-header-chip"><i class="ti ti-lock-open"></i>
        <span>Dibuka sampai <strong>{{ $jadwal['akhir']->locale('id')->translatedFormat('d F Y') }}</strong> · sisa {{ $jadwal['sisa_hari'] }} hari</span></span>
    @else
      <span class="pg-header-chip"><i class="ti ti-lock"></i><span>Pembuatan SPP ditutup</span></span>
    @endif
    <span class="pg-header-chip"><i class="ti ti-file-alert"></i><span>Belum SPJ <strong>{{ $kuota['belum_spj'] }}</strong> kegiatan</span></span>
    <span class="pg-header-chip"><i class="ti ti-ticket"></i><span>Batas <strong>{{ $kuota['maks'] }}</strong> kegiatan belum SPJ · sisa kuota <strong>{{ max(0, $kuota['sisa']) }}</strong></span></span>
  </div>
</div>

@include('_partials._flash')

@if (!$jadwal['aktif'])
  <div class="alert alert-danger d-flex gap-2"><i class="ti ti-lock mt-1"></i>
    <div><strong>Pembuatan SPP tahun anggaran {{ $tahun }}/{{ $tahun + 1 }} sudah ditutup.</strong>
      @if (!$jadwal['akhir']) Tanggal akhir anggaran belum diatur di Setting &rsaquo; Periode. @endif</div></div>
@elseif ($jadwal['sisa_hari'] < 30)
  <div class="alert alert-warning d-flex gap-2"><i class="ti ti-clock mt-1"></i>
    <div>Pembuatan SPP tahun anggaran {{ $tahun }}/{{ $tahun + 1 }} akan ditutup <strong>{{ $jadwal['sisa_hari'] }} hari lagi</strong>.</div></div>
@endif

{{-- 1. Draf: kegiatan yang akan di-SPP-kan --}}
@if ($draf->isNotEmpty())
  <div class="card mb-4 border-primary">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
      <div>
        <h5 class="mb-0">Kegiatan yang akan di-SPP-kan</h5>
        <small class="text-muted">Nomor <span class="font-monospace">{{ $nomorDraf }}</span> · belum dikirim</small>
      </div>
      @if ($jadwal['aktif'])
        <form method="POST" action="{{ route('unit.spp.proses') }}" class="form-proses" data-jumlah="{{ $draf->count() }}" data-total="{{ $rp($draf->sum('ajuan')) }}">
          @csrf
          <button type="submit" class="btn btn-primary"><i class="ti ti-send me-1"></i> Proses SPP</button>
        </form>
      @endif
    </div>
    <div class="table-responsive">
      <table class="table spp-tabel mb-0">
        <thead><tr><th style="width: 40px;">No</th><th>Kegiatan</th><th class="text-end">Diajukan</th><th style="width: 60px;"></th></tr></thead>
        <tbody>
          @foreach ($draf as $d)
            <tr>
              <td class="text-muted">{{ $loop->iteration }}</td>
              <td class="utama">
                <div class="kode-mak">{{ $d->kode_mak }} · {{ $d->namamak4 }}</div>
                <div class="judul">{{ $d->uraiankeg }}</div>
              </td>
              <td class="text-end rp">{{ $rp($d->ajuan) }}</td>
              <td class="text-end">
                <form method="POST" action="{{ route('unit.spp.item.destroy', $d->id_spp) }}" class="d-inline form-batal" data-label="{{ $d->uraiankeg }}">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Keluarkan dari SPP"><i class="ti ti-trash"></i></button>
                </form>
              </td>
            </tr>
          @endforeach
          <tr class="total"><td colspan="2" class="text-end">Total diajukan</td><td class="text-end rp">{{ $rp($draf->sum('ajuan')) }}</td><td></td></tr>
        </tbody>
      </table>
    </div>
  </div>
@endif

{{-- 2. SPP terkirim yang belum menjadi SPM --}}
@if ($daftar->isNotEmpty())
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Daftar SPP</h5>
      <small class="text-muted">SPP yang sudah dikirim dan belum disetujui menjadi SPM</small>
    </div>
    <div class="table-responsive">
      <table class="table spp-tabel mb-0">
        <thead>
          <tr><th style="width: 40px;">No</th><th>No. SPP</th><th>Tgl. Penyajian</th><th class="text-end">Kegiatan</th><th class="text-end">Nominal</th>
            <th>Otorisasi Pimpinan</th><th>Status SPP</th><th style="width: 90px;"></th></tr>
        </thead>
        <tbody>
          @foreach ($daftar as $s)
            @php
              [$kelasOto, $teksOto] = $badgeOtorisasi[$s->otorisasi] ?? ['bg-label-secondary', $s->otorisasi];
              $tampilAlasan = $s->alasan && ($s->otorisasi === 'Batal' || $s->status_spp === 'Ditolak');
            @endphp
            <tr>
              <td class="text-muted">{{ $loop->iteration }}</td>
              <td class="nomor">{{ $s->no_spp }}</td>
              <td class="text-nowrap">{{ $tglId($s->tgl_saji) }}</td>
              <td class="text-end">{{ $s->kegiatan }}</td>
              <td class="text-end rp">{{ $rp($s->nominal) }}</td>
              <td><span class="badge {{ $kelasOto }}">{{ $teksOto }}</span></td>
              <td><span class="badge {{ $badgeVerifikasi[$s->status_spp] ?? 'bg-label-secondary' }}">{{ $s->status_spp }}</span></td>
              <td class="text-end text-nowrap">
                <a href="{{ route('unit.spp.cetak', $s->id_spp) }}" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-text-primary rounded-pill" title="Tampilkan / cetak"><i class="ti ti-printer"></i></a>
                @if ($s->boleh_hapus)
                  <form method="POST" action="{{ route('unit.spp.destroy', $s->id_spp) }}" class="d-inline form-hapus" data-label="{{ $s->no_spp }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Hapus SPP"><i class="ti ti-trash"></i></button>
                  </form>
                @endif
              </td>
            </tr>
            @if ($tampilAlasan)
              <tr><td></td><td colspan="7" class="pt-0 border-top-0">
                <div class="small text-danger"><i class="ti ti-message-exclamation me-1"></i><strong>Alasan:</strong> {{ $s->alasan }}</div>
              </td></tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

{{-- 3. Daftar MAK: pilih kegiatan yang akan di-SPP-kan --}}
@if ($jadwal['aktif'])
  @if (!$susun)
    <div class="alert alert-info d-flex gap-2"><i class="ti ti-info-circle mt-1"></i>
      <div>Masih ada SPP yang sedang diproses. SPP baru dapat disusun setelah SPP tersebut diproses menjadi SPM.</div></div>
  @else
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Daftar Mata Anggaran Kegiatan (MAK)</h5>
        <small class="text-muted">{{ $bisa }} kegiatan dapat di-SPP-kan · sisa anggaran = belanja − yang sudah dicairkan (SPM disetujui)</small>
      </div>
      <div class="table-responsive">
        <table class="table spp-tabel mb-0">
          <thead>
            <tr><th style="width: 40px;">No</th><th>Kegiatan</th><th class="text-end">Anggaran</th><th class="text-end">Sudah Dicairkan</th>
              <th class="text-end">Sisa</th><th style="width: 150px;"></th></tr>
          </thead>
          <tbody>
            @forelse ($tampil as $k)
              <tr>
                <td class="text-muted">{{ $loop->iteration }}</td>
                <td class="utama">
                  <div class="kode-mak">{{ $k->kode_mak }} · {{ $k->namamak4 }}</div>
                  <div class="judul">{{ $k->uraiankeg }}</div>
                </td>
                <td class="text-end rp">{{ $rp($k->anggaran) }}</td>
                <td class="text-end rp">{{ $rp($k->dicairkan) }}</td>
                <td class="text-end rp">{{ $rp($k->sisa) }}</td>
                <td class="text-end">
                  @if ($k->status === 'boleh')
                    <a href="{{ route('unit.spp.create', $k->idkeg) }}" class="btn btn-sm btn-primary"><i class="ti ti-file-export ti-xs me-1"></i>SPP-kan</a>
                  @else
                    @php [$kelas, $ikon] = $badgeStatus[$k->status]; @endphp
                    <span class="badge badge-center rounded-pill {{ $kelas }}" title="{{ $k->keterangan }}" aria-label="{{ $k->keterangan }}" data-bs-toggle="tooltip"><i class="ti {{ $ikon }} ti-xs"></i></span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">Belum ada kegiatan dengan anggaran belanja pada T.A ini.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const esc = s => $('<div>').text(s ?? '').html();
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  const tanya = (opsi, f) => Swal.fire(Object.assign({ icon: 'warning', showCancelButton: true, cancelButtonText: 'Batal', buttonsStyling: false,
    customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' } }, opsi)).then(r => { if (r.isConfirmed) f.submit(); });

  document.querySelectorAll('.form-batal').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    tanya({ title: 'Keluarkan dari SPP?', html: '<b>' + esc(f.dataset.label) + '</b> akan dikeluarkan dari SPP ini.', confirmButtonText: 'Ya, keluarkan' }, f);
  }));
  document.querySelectorAll('.form-hapus').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    tanya({ title: 'Hapus SPP?', html: 'SPP <b>' + esc(f.dataset.label) + '</b> beserta seluruh kegiatannya akan dihapus.', confirmButtonText: 'Ya, hapus' }, f);
  }));
  document.querySelectorAll('.form-proses').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    tanya({ icon: 'question', title: 'Proses SPP?', html: esc(f.dataset.jumlah) + ' kegiatan senilai <b>' + esc(f.dataset.total) + '</b> akan dikirim untuk otorisasi pimpinan. Setelah dikirim, kegiatan tidak dapat ditambah lagi.',
      confirmButtonText: 'Kirim', customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-label-secondary' } }, f);
  }));
});
</script>
@endsection
