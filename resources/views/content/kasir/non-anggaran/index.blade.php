@extends('layouts/layoutMaster')

@section('title', 'Bukti Kas Keluar')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-style')
<style>
  .bkk-tabel > tbody > tr.utama > td { vertical-align: top; }
  .bkk-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .bkk-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
  .bkk-tabel tfoot td { font-weight: 700; }
  .bkk-tabel .buka { width: 28px; height: 28px; padding: 0; display: inline-grid; place-items: center; }
  .bkk-tabel .buka i { transition: transform .15s; }
  .bkk-tabel .buka[aria-expanded="true"] i { transform: rotate(90deg); }
  .bkk-tabel tr.rinci > td { background: var(--bs-tertiary-bg, rgba(115, 103, 240, .04)); }
  .rinci-tabel { font-size: .82rem; margin: 0; }
  .filter-bkk .f-rentang { width: 220px; }
  .filter-bkk .f-tanggal { width: 230px; }
  .filter-bkk .f-cari { width: 280px; }
  @media (max-width: 767.98px) { .filter-bkk > * { width: 100% !important; } }
  .rinci-tabel th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglPendek = fn ($t) => $t ? $t->locale('id')->translatedFormat('d M Y') : '…';
  $namaBulan = fn ($ym) => \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $ym . '-01')->locale('id')->translatedFormat('F Y');
  $presetRentang = ['hari-ini' => 'Hari ini', '7-hari' => '7 hari terakhir', 'bulan-ini' => 'Bulan ini', 'bulan-lalu' => 'Bulan lalu'];
  $labelRentang = $rentang === '' ? '' : ($presetRentang[$rentang] ?? ($rentang === 'khusus' ? 'Rentang khusus' : $namaBulan($rentang)));
  $nilaiTanggal = $dari ? $dari->toDateString() . ($sampai && !$sampai->eq($dari) ? ' to ' . $sampai->toDateString() : '') : '';
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-receipt-2"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Bukti Kas Keluar Non Anggaran</h4>
      <p class="pg-header-sub mb-0">Daftar BKK · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    <a href="{{ route('kasir.non-anggaran.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Input BKK</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-receipt-2"></i><span><strong>{{ number_format($ringkas->n, 0, ',', '.') }}</strong> BKK · {{ $rp($ringkas->total) }}</span></span>
    @if ($ringkas->n - $ringkas->tunai > 0)
      <span class="pg-header-chip"><i class="ti ti-hourglass"></i><span><strong>{{ $ringkas->n - $ringkas->tunai }}</strong> belum tunai</span></span>
    @endif
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
      <h5 class="mb-0">Bukti Kas Keluar</h5>
      <small class="text-muted">Terbaru lebih dulu · klik <i class="ti ti-chevron-right ti-xs"></i> untuk melihat rincian
        @if ($difilter) · <strong>{{ $daftar->total() }}</strong> hasil @endif</small>
    </div>
    <form method="GET" action="{{ route('kasir.non-anggaran.index') }}" class="d-flex flex-wrap gap-2 filter-bkk" role="search" id="filterBkk">
      <div class="f-rentang">
        <select name="rentang" id="f-rentang" class="form-select" aria-label="Rentang tanggal" data-placeholder="Semua tanggal">
          <option value="">Semua tanggal</option>
          <optgroup label="Cepat">
            @foreach ($presetRentang as $k => $l)<option value="{{ $k }}" @selected($rentang === $k)>{{ $l }}</option>@endforeach
            <option value="khusus" @selected($rentang === 'khusus')>Rentang khusus…</option>
          </optgroup>
          @if ($bulan->isNotEmpty())
            <optgroup label="Per bulan">
              @foreach ($bulan as $bln => $n)<option value="{{ $bln }}" data-n="{{ $n }}" @selected($rentang === $bln)>{{ $namaBulan($bln) }}</option>@endforeach
            </optgroup>
          @endif
        </select>
      </div>
      <div class="f-tanggal {{ $rentang === 'khusus' ? '' : 'd-none' }}" id="kotakTanggal">
        <div class="input-group">
          <span class="input-group-text"><i class="ti ti-calendar"></i></span>
          <input type="text" id="f-tanggal" class="form-control" placeholder="Pilih tanggal awal – akhir" value="{{ $nilaiTanggal }}" aria-label="Rentang tanggal khusus" readonly>
        </div>
        <input type="hidden" name="dari" id="f-dari" value="{{ $rentang === 'khusus' ? $dari?->toDateString() : '' }}">
        <input type="hidden" name="sampai" id="f-sampai" value="{{ $rentang === 'khusus' ? $sampai?->toDateString() : '' }}">
      </div>
      <div class="input-group f-cari">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="search" name="cari" value="{{ $cari }}" class="form-control" placeholder="Cari no. BKK / uraian / penerima" aria-label="Cari BKK">
      </div>
      <button type="submit" class="btn btn-primary">Cari</button>
      @if ($difilter)<a href="{{ route('kasir.non-anggaran.index') }}" class="btn btn-label-secondary">Reset</a>@endif
    </form>
  </div>
  @if ($rentang !== '')
    <div class="px-4 pb-3 d-flex flex-wrap align-items-center gap-2 small">
      <span class="badge bg-label-primary"><i class="ti ti-calendar ti-xs me-1"></i>{{ $labelRentang }}</span>
      <span class="text-muted">{{ $tglPendek($dari) }} – {{ $tglPendek($sampai) }} · {{ $daftar->total() }} BKK · {{ $rp($total) }}</span>
    </div>
  @endif
  <div class="table-responsive">
    <table class="table bkk-tabel mb-0">
      <thead>
        <tr><th style="width: 36px;"></th><th style="width: 44px;">No</th><th>No. BKK</th><th>Untuk Pembayaran</th><th class="text-end">Banyaknya</th>
          <th>Tanggal</th><th class="text-end" style="width: 220px;">Menu</th></tr>
      </thead>
      <tbody>
        @forelse ($daftar as $b)
          @php
            $tunai = $b->status_bkk === 'Tunai';
            $rinciB = $rinci[$b->id_bkk] ?? collect();
            $rid = 'rinci-' . $b->id_bkk;
          @endphp
          <tr class="utama">
            <td>
              <button type="button" class="btn btn-sm btn-text-secondary buka" data-bs-toggle="collapse" data-bs-target="#{{ $rid }}"
                aria-expanded="false" aria-controls="{{ $rid }}" aria-label="Rincian BKK {{ $b->no_bkk }}"><i class="ti ti-chevron-right"></i></button>
            </td>
            <td class="text-muted">{{ $daftar->firstItem() + $loop->index }}</td>
            <td class="nomor fw-semibold">{{ $b->no_bkk }}
              @if ($b->no_ref !== '')<div class="small text-muted font-sans-serif text-wrap" style="max-width: 240px;">{{ $b->no_ref }}</div>@endif</td>
            <td style="white-space: normal; min-width: 220px;">{{ $b->uraian }}
              <div class="small text-muted"><i class="ti ti-user ti-xs"></i> {{ $b->penerima }}
                @if (isset($unit[$b->id_bkk])) · <i class="ti ti-building ti-xs"></i> <span class="font-monospace">{{ $unit[$b->id_bkk]->kode }}</span> {{ $unit[$b->id_bkk]->nama }}@endif</div></td>
            <td class="text-end rp">{{ $rp($b->nominal) }}</td>
            <td class="text-nowrap">{{ $tglId($b->tgl_bkk) }}
              <div class="mt-1">
                @if ($tunai)
                  <span class="badge bg-label-success" title="Tunai {{ $tglId($b->tgl_tunai) }}"><i class="ti ti-circle-check ti-xs me-1"></i>Tunai</span>
                @else
                  <span class="badge bg-label-warning"><i class="ti ti-hourglass ti-xs me-1"></i>Belum tunai</span>
                @endif
              </div>
            </td>
            <td class="text-end text-nowrap">
              @if ($tunai)
                <a href="{{ route('kasir.non-anggaran.cetak', [$b->id_bkk, 'lihat' => 1]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-label-primary"
                  data-bs-toggle="tooltip" title="Lihat BKK tanpa mencetak"><i class="ti ti-eye ti-xs me-1"></i>Lihat</a>
                <a href="{{ route('kasir.non-anggaran.cetak', $b->id_bkk) }}" target="_blank" rel="noopener" class="btn btn-sm btn-label-secondary"
                  data-bs-toggle="tooltip" title="Cetak ulang BKK (bertanda COPY)"><i class="ti ti-printer ti-xs me-1"></i>Cetak Copy</a>
              @else
                <form method="POST" action="{{ route('kasir.non-anggaran.tunai', $b->id_bkk) }}" class="d-inline form-tunai"
                  data-bkk="{{ $b->no_bkk }}" data-uraian="{{ $b->uraian }}" data-nominal="{{ $rp($b->nominal) }}">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-success"><i class="ti ti-cash ti-xs me-1"></i>Tunai</button>
                </form>
                <form method="POST" action="{{ route('kasir.non-anggaran.destroy', $b->id_bkk) }}" class="d-inline form-hapus"
                  data-bkk="{{ $b->no_bkk }}" data-uraian="{{ $b->uraian }}" data-nominal="{{ $rp($b->nominal) }}">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-label-danger" aria-label="Hapus BKK {{ $b->no_bkk }}"><i class="ti ti-trash ti-xs"></i></button>
                </form>
              @endif
            </td>
          </tr>
          <tr class="rinci">
            <td colspan="7" class="p-0 border-0">
              <div class="collapse" id="{{ $rid }}">
                <div class="px-4 py-3">
                  <div class="row g-3 small mb-2">
                    <div class="col-sm-6"><span class="text-muted">Sudah terima dari:</span> {{ $b->terima_dari }}</div>
                    <div class="col-sm-6 text-sm-end"><span class="text-muted">PPN</span> {{ $rp($b->ppn) }} · <span class="text-muted">PPH</span> {{ $rp($b->pph) }}</div>
                  </div>
                  @if ($rinciB->isNotEmpty())
                    <table class="table table-sm rinci-tabel">
                      <thead><tr><th style="width: 36px;">No</th><th>Uraian</th><th class="text-end">Biaya</th></tr></thead>
                      <tbody>
                        @foreach ($rinciB as $r)
                          <tr><td>{{ $loop->iteration }}</td><td>{{ $r->uraian !== '' ? $r->uraian : '—' }}</td><td class="text-end rp">{{ $rp($r->biaya) }}</td></tr>
                        @endforeach
                      </tbody>
                    </table>
                  @else
                    <div class="small text-muted">Tanpa rincian.</div>
                  @endif
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-5">
            {{ $difilter ? 'Tidak ada BKK yang cocok dengan filter.' : 'Belum ada BKK pada T.A ini.' }}
          </td></tr>
        @endforelse
      </tbody>
      @if ($daftar->isNotEmpty())
        <tfoot>
          <tr><td colspan="4" class="text-end">Jumlah halaman ini</td><td class="text-end rp">{{ $rp($daftar->sum('nominal')) }}</td><td colspan="2"></td></tr>
          @if ($daftar->hasPages())
            <tr><td colspan="4" class="text-end">Jumlah {{ $difilter ? 'hasil filter' : 'seluruh BKK' }}</td><td class="text-end rp">{{ $rp($total) }}</td><td colspan="2"></td></tr>
          @endif
        </tfoot>
      @endif
    </table>
  </div>
  @if ($daftar->hasPages())
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted">Menampilkan {{ $daftar->firstItem() }}–{{ $daftar->lastItem() }} dari {{ $daftar->total() }} BKK</small>
      {{ $daftar->onEachSide(1)->links('_partials.pagination') }}
    </div>
  @endif
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Filter rentang tanggal: select2 untuk pilihan cepat/bulan, flatpickr untuk rentang khusus.
  const form = document.getElementById('filterBkk');
  const kotak = document.getElementById('kotakTanggal');
  const dari = document.getElementById('f-dari'), sampai = document.getElementById('f-sampai');
  const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const kalender = flatpickr('#f-tanggal', {
    mode: 'range', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', allowInput: false, monthSelectorType: 'static',
    locale: {
      rangeSeparator: ' – ', firstDayOfWeek: 1,
      weekdays: { shorthand: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'], longhand: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] },
      months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        longhand: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] }
    },
    onChange: function (pilih) {
      if (pilih.length === 2) { dari.value = ymd(pilih[0]); sampai.value = ymd(pilih[1]); form.submit(); }
    }
  });

  $('#f-rentang').select2({
    width: '100%', minimumResultsForSearch: 6,
    templateResult: o => o.element && o.element.dataset.n ? $('<span class="d-flex justify-content-between gap-2"><span>' + $('<div>').text(o.text).html()
      + '</span><span class="text-muted small">' + o.element.dataset.n + ' BKK</span></span>') : o.text
  }).on('change', function () {
    if (this.value === 'khusus') {
      kotak.classList.remove('d-none');
      kalender.open();
      return;
    }
    kotak.classList.add('d-none');
    dari.value = ''; sampai.value = '';
    form.submit();
  });

  // Rentang khusus belum lengkap: jangan kirim dari/sampai kosong.
  form.addEventListener('submit', function () {
    if ($('#f-rentang').val() !== 'khusus') { dari.disabled = sampai.disabled = true; }
  });

  var a = document.getElementById('bukaBkk');
  if (a) { window.open(a.href, '_blank'); }

  document.querySelectorAll('.bkk-tabel [data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  const esc = s => $('<div>').text(s ?? '').html();
  const isi = el => 'BKK <b class="font-monospace">' + esc(el.dataset.bkk) + '</b><br>' + esc(el.dataset.uraian)
    + '<div class="fs-4 fw-semibold mt-2">' + esc(el.dataset.nominal) + '</div>';
  const tanya = (f, opsi) => Swal.fire(Object.assign({
    showCancelButton: true, cancelButtonText: 'Batal', buttonsStyling: false, focusCancel: true
  }, opsi)).then(r => { if (r.isConfirmed) { f.querySelector('button[type=submit]').disabled = true; f.submit(); } });

  document.querySelectorAll('.form-tunai').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    tanya(f, {
      icon: 'question', title: 'Tunaikan BKK?',
      html: isi(f) + '<div class="small text-muted mt-2">BKK ditandai tunai, langsung dicetak, dan tidak dapat dihapus lagi.</div>',
      confirmButtonText: '<i class="ti ti-cash me-1"></i>Ya, tunai',
      customClass: { confirmButton: 'btn btn-success me-2', cancelButton: 'btn btn-label-secondary' }
    });
  }));

  document.querySelectorAll('.form-hapus').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    tanya(f, {
      icon: 'warning', title: 'Hapus BKK?',
      html: isi(f) + '<div class="small text-muted mt-2">BKK beserta rinciannya akan dihapus.</div>',
      confirmButtonText: '<i class="ti ti-trash me-1"></i>Ya, hapus',
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' }
    });
  }));
});
</script>
@endsection
