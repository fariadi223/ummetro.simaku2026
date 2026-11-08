@extends('layouts/layoutMaster')

@section('title', 'Realisasi SPM')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .spm-tabel > tbody > tr.utama > td { vertical-align: top; }
  .spm-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .spm-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
  .spm-tabel tfoot td { font-weight: 700; }
  .spm-tabel .buka { width: 28px; height: 28px; padding: 0; display: inline-grid; place-items: center; }
  .spm-tabel .buka i { transition: transform .15s; }
  .spm-tabel .buka[aria-expanded="true"] i { transform: rotate(90deg); }
  .spm-tabel tr.rinci > td { background: var(--bs-tertiary-bg, rgba(115, 103, 240, .04)); padding-top: 0; }
  .rinci-tabel { font-size: .82rem; margin: 0; }
  .rinci-tabel th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); }
  .filter-status .btn { white-space: nowrap; padding-inline: 1rem; }
  .filter-status .badge { font-size: .75rem; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $tautan = fn (array $q) => route('kasir.spm.index', array_filter(array_merge(['cari' => $cari, 'status' => $status], $q), fn ($v) => $v !== ''));
  $filter = [
    '' => ['Semua', $ringkas['semua']['n']],
    'belum' => ['Belum dibayar', $ringkas['belum']['n']],
    'sudah' => ['Sudah dibayar', $ringkas['sudah']['n']],
  ];
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-cash-banknote"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Realisasi SPM</h4>
      <p class="pg-header-sub mb-0">Pembayaran SPM yang sudah disetujui · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-file-check"></i><span><strong>{{ $ringkas['semua']['n'] }}</strong> SPM disetujui · {{ $rp($ringkas['semua']['nominal']) }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-circle-check"></i><span>Dibayar <strong>{{ $ringkas['sudah']['n'] }}</strong> · {{ $rp($ringkas['sudah']['nominal']) }}</span></span>
    <span class="pg-header-chip"><i class="ti ti-hourglass"></i><span>Belum <strong>{{ $ringkas['belum']['n'] }}</strong> · {{ $rp($ringkas['belum']['nominal']) }}</span></span>
  </div>
</div>

@include('_partials._flash')

@if (session('cetak'))
  <div class="alert alert-primary d-flex flex-wrap align-items-center gap-2" role="alert">
    <i class="ti ti-printer"></i>
    <span class="flex-grow-1">Bukti Kas Keluar (BKK) siap dicetak.</span>
    <a href="{{ session('cetak') }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary" id="bukaBkk"><i class="ti ti-printer ti-xs me-1"></i>Cetak BKK</a>
  </div>
@endif

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <h5 class="mb-0">Data SPM Disetujui</h5>
      <small class="text-muted">
        Terbaru lebih dulu · klik <i class="ti ti-chevron-right ti-xs"></i> untuk melihat rincian kegiatan
        @if ($cari !== '' || $status !== '') · <strong>{{ $jumlah }}</strong> hasil @endif
      </small>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div class="btn-group filter-status" role="group" aria-label="Filter status pembayaran">
        @foreach ($filter as $kunci => [$label, $n])
          <a href="{{ $tautan(['status' => $kunci, 'page' => '']) }}" class="btn {{ $status === $kunci ? 'btn-primary' : 'btn-label-secondary' }}">{{ $label }} <span class="badge bg-white text-dark ms-1">{{ $n }}</span></a>
        @endforeach
      </div>
      <form method="GET" action="{{ route('kasir.spm.index') }}" class="d-flex gap-2" role="search">
        @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
        <div class="input-group" style="max-width: 300px;">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="search" name="cari" value="{{ $cari }}" class="form-control" placeholder="Cari no. SPM / unit" aria-label="Cari SPM">
        </div>
        <button type="submit" class="btn btn-primary">Cari</button>
        @if ($cari !== '')<a href="{{ $tautan(['cari' => '', 'page' => '']) }}" class="btn btn-label-secondary">Reset</a>@endif
      </form>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table spm-tabel mb-0">
      <thead>
        <tr>
          <th style="width: 36px;"></th><th style="width: 44px;">No</th><th>No. SPM</th><th>Nama Unit Kerja</th>
          <th>Tgl. SPM Disetujui</th><th class="text-end">Dibayarkan</th><th>Status</th><th class="text-end" style="width: 190px;">Menu</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($daftar as $m)
          @php
            $rinciId = 'rinci-' . $m->id_spp;
            $bolehParaf = in_array($m->kode_spm, $unitParaf, true);
          @endphp
          <tr class="utama">
            <td>
              <button type="button" class="btn btn-sm btn-text-secondary buka" data-bs-toggle="collapse" data-bs-target="#{{ $rinciId }}"
                aria-expanded="false" aria-controls="{{ $rinciId }}" aria-label="Rincian SPM {{ $m->no_spm }}"><i class="ti ti-chevron-right"></i></button>
            </td>
            <td class="text-muted">{{ $daftar->firstItem() + $loop->index }}</td>
            <td class="nomor fw-semibold">{{ $m->no_spm }}</td>
            <td style="white-space: normal; min-width: 180px;">{{ $m->induk->nama ?? $m->nama_unit }}
              <div class="small text-muted"><span class="font-monospace">{{ $m->kode_spm }}</span> {{ $m->sub_unit->nama ?? $m->nama_unit }}</div></td>
            <td class="text-nowrap">{{ $tglId($m->tgl_setuju) }}</td>
            <td class="text-end rp">{{ $rp($m->nominal) }}
              @if ($m->bayar)<div class="small text-muted mt-1" title="Tanggal bayar">{{ $tglId($m->bayar->tgl_kasir) }}</div>@endif
            </td>
            <td class="text-nowrap">
              @if ($m->bayar)
                @if ($m->metode === 'transfer')
                  <span class="badge bg-label-primary"><i class="ti ti-building-bank ti-xs me-1"></i>Transfer</span>
                @else
                  <span class="badge bg-label-info"><i class="ti ti-cash ti-xs me-1"></i>Tunai</span>
                @endif
              @else
                <span class="badge bg-label-warning"><i class="ti ti-hourglass ti-xs me-1"></i>Belum dibayar</span>
              @endif
            </td>
            <td class="text-end text-nowrap">
              @if ($m->bayar)
                <a href="{{ route('kasir.spm.bkk', $m->id_spp) }}" target="_blank" rel="noopener" class="btn btn-sm btn-label-primary"><i class="ti ti-eye ti-xs me-1"></i>Lihat</a>
              @else
                <form method="POST" action="{{ route('kasir.spm.tunai', $m->id_spp) }}" class="d-inline form-tunai"
                  data-spm="{{ $m->no_spm }}" data-unit="{{ $m->induk->nama ?? $m->nama_unit }}" data-nominal="{{ $rp($m->nominal) }}">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-success"><i class="ti ti-cash ti-xs me-1"></i>Tunai</button>
                </form>
                <a href="{{ route('kasir.spm.transfer', $m->id_spp) }}" class="btn btn-sm btn-warning link-transfer"
                  data-spm="{{ $m->no_spm }}" data-unit="{{ $m->induk->nama ?? $m->nama_unit }}" data-nominal="{{ $rp($m->nominal) }}"><i class="ti ti-building-bank ti-xs me-1"></i>Transfer</a>
              @endif
            </td>
          </tr>
          <tr class="rinci">
            <td colspan="8" class="p-0 border-0">
              <div class="collapse" id="{{ $rinciId }}">
                <div class="px-4 pb-3 pt-1">
                  <table class="table table-sm rinci-tabel">
                    <thead><tr><th style="width: 36px;">No</th><th>Kode Sub Unit</th><th>Nama Sub Unit</th><th>M.A.K</th><th>Nama Kegiatan</th><th class="text-end">Diminta</th></tr></thead>
                    <tbody>
                      @foreach ($m->baris as $b)
                        <tr>
                          <td>{{ $loop->iteration }}</td>
                          <td class="font-monospace">{{ $b->kode_unit }}</td>
                          <td>{{ $b->nama_unit }}</td>
                          <td class="font-monospace text-nowrap">{{ $b->kode_mak }}</td>
                          <td>{{ $b->namamak4 }}<div class="text-muted">{{ $b->uraiankeg }}</div></td>
                          <td class="text-end rp">{{ $rp($b->ajuan) }}</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                  <div class="d-flex flex-wrap justify-content-between gap-2 small text-muted mt-2">
                    <span>
                      @if ($m->bayar)
                        BKK <span class="font-monospace">{{ \App\Services\RealisasiSpm::nomorBkk($m) }}</span>
                        @if ($m->metode === 'transfer') · {{ trim($m->bayar->namabank . ' ' . $m->bayar->norekbank) }} · Ref {{ $m->bayar->nobuktirek }}@endif
                      @endif
                      @if ($m->paraf_nama !== '') · Penerima: <strong>{{ $m->paraf_nama }}</strong>@endif
                    </span>
                    @if ($bolehParaf)
                      <a href="{{ route('kasir.spm.paraf', $m->id_spp) }}"><i class="ti ti-signature ti-xs me-1"></i>Penerima</a>
                    @endif
                  </div>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-5">
            {{ $cari !== '' || $status !== '' ? 'Tidak ada SPM yang cocok dengan filter.' : 'Belum ada SPM yang disetujui pada T.A ini.' }}
          </td></tr>
        @endforelse
      </tbody>
      @if ($daftar->isNotEmpty())
        <tfoot>
          <tr><td colspan="5" class="text-end">Jumlah halaman ini</td><td class="text-end rp">{{ $rp($daftar->sum('nominal')) }}</td><td colspan="2"></td></tr>
          @if ($daftar->hasPages())
            <tr><td colspan="5" class="text-end">Jumlah {{ $cari !== '' || $status !== '' ? 'hasil filter' : 'seluruh SPM' }}</td><td class="text-end rp">{{ $rp($total) }}</td><td colspan="2"></td></tr>
          @endif
        </tfoot>
      @endif
    </table>
  </div>
  @if ($daftar->hasPages())
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted">Menampilkan {{ $daftar->firstItem() }}–{{ $daftar->lastItem() }} dari {{ $daftar->total() }} SPM</small>
      {{ $daftar->onEachSide(1)->links('_partials.pagination') }}
    </div>
  @endif
</div>
@endsection

@section('page-script')
<script>
  // Setelah bayar, coba buka BKK otomatis (seperti v1 yang langsung menampilkan BKK).
  document.addEventListener('DOMContentLoaded', function () {
    var a = document.getElementById('bukaBkk');
    if (a) { window.open(a.href, '_blank'); }

    const esc = s => $('<div>').text(s ?? '').html();
    const isi = el => 'SPM <b class="font-monospace">' + esc(el.dataset.spm) + '</b><br>' + esc(el.dataset.unit)
      + '<div class="fs-4 fw-semibold mt-2">' + esc(el.dataset.nominal) + '</div>';
    const tanya = opsi => Swal.fire(Object.assign({
      icon: 'question', showCancelButton: true, cancelButtonText: 'Batal', reverseButtons: false, buttonsStyling: false,
      focusCancel: true, customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-label-secondary' }
    }, opsi));

    // Tunai: langsung mencatat pembayaran, jadi wajib dikonfirmasi.
    document.querySelectorAll('.form-tunai').forEach(f => f.addEventListener('submit', function (e) {
      e.preventDefault();
      tanya({
        title: 'Bayar secara tunai?', html: isi(f) + '<div class="small text-muted mt-2">Pembayaran dicatat dan BKK langsung dicetak.</div>',
        confirmButtonText: '<i class="ti ti-cash me-1"></i>Ya, bayar tunai',
        customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-label-secondary' }
      }).then(r => { if (r.isConfirmed) { f.querySelector('button[type=submit]').disabled = true; f.submit(); } });
    }));

    // Transfer: konfirmasi sebelum membuka form bukti transfer.
    document.querySelectorAll('.link-transfer').forEach(l => l.addEventListener('click', function (e) {
      e.preventDefault();
      tanya({
        title: 'Bayar melalui transfer?', html: isi(l) + '<div class="small text-muted mt-2">Selanjutnya isi nama bank, nomor rekening, dan no. ref transfer.</div>',
        confirmButtonText: '<i class="ti ti-building-bank me-1"></i>Lanjut ke form transfer',
        customClass: { confirmButton: 'btn btn-warning me-2', cancelButton: 'btn btn-label-secondary' }
      }).then(r => { if (r.isConfirmed) window.location.href = l.href; });
    }));
  });
</script>
@endsection
