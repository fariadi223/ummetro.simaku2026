@extends('layouts/layoutMaster')

@section('title', 'Input Non Anggaran')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .form-bkk .form-label { font-weight: 600; }
  .rinci-baris .no { width: 34px; flex-shrink: 0; padding-top: .55rem; color: var(--bs-secondary-color); font-variant-numeric: tabular-nums; }
  .rinci-baris .biaya { max-width: 220px; }
  .rupiah { text-align: right; font-variant-numeric: tabular-nums; }
  .belum-tunai .list-group-item { padding: .85rem 1.25rem; }
  .belum-tunai .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; }
  .belum-tunai .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .belum-tunai .aksi .btn { transition: transform .15s ease, box-shadow .15s ease; }
  .belum-tunai .aksi .btn:hover { transform: translateY(-1px); box-shadow: 0 .25rem .6rem rgba(0, 0, 0, .12); }
  .belum-tunai .btn-tunai { padding-inline: .8rem; font-weight: 600; }
  .belum-tunai .btn-hapus { width: 30px; height: 30px; }
  /* Desktop: tinggi panel kanan = tinggi form kiri; daftar BKK di-scroll bila lebih panjang. */
  @media (min-width: 992px) {
    .kolom-belum { position: relative; }
    .kolom-belum .belum-tunai { position: absolute; inset: 0 calc(var(--bs-gutter-x) * .5); display: flex; flex-direction: column; }
    .kolom-belum .belum-tunai .list-group { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
  }
  .total-bkk { font-size: 1.35rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--bs-heading-color); }
</style>
@endsection

@php
  $rinciLama = old('rinci', [['uraian' => '', 'biaya' => '']]);
  if (!count($rinciLama)) { $rinciLama = [['uraian' => '', 'biaya' => '']]; }
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $fmt = fn ($v) => ($v === null || $v === '') ? '' : number_format((int) preg_replace('/\D/', '', (string) $v), 0, ',', '.');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-receipt-2"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Input Bukti Kas Keluar</h4>
      <p class="pg-header-sub mb-0">Pengeluaran non anggaran · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    <a href="{{ route('kasir.non-anggaran.index') }}" class="btn btn-warning"><i class="ti ti-list-details me-1"></i>Lihat BKK</a>
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

<div class="row g-4">
<div class="col-lg-8 order-2 order-lg-1">
<form method="POST" action="{{ route('kasir.non-anggaran.store') }}" class="card form-bkk" id="formBkk" novalidate>
  @csrf
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label" for="f-nombkk">Nomor BKK</label>
        <input type="text" id="f-nombkk" class="form-control font-monospace" value="{{ $nomorBkk }}" disabled>
        <div class="form-text">Nomor final ditetapkan saat disimpan.</div>
      </div>
      <div class="col-md-8">
        <label class="form-label" for="f-nomref">Nomor Ref.</label>
        <input type="text" id="f-nomref" name="nomref" maxlength="100" value="{{ old('nomref') }}" placeholder="mis. Surat Tugas 693 (Transfer)"
          class="form-control @error('nomref') is-invalid @enderror">
        @error('nomref')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-12">
        <label class="form-label" for="f-unit">Unit Kerja <span class="text-danger">*</span></label>
        <select id="f-unit" name="id_unit" required data-placeholder="Pilih sub unit kerja"
          class="form-select @error('id_unit') is-invalid @enderror">
          <option value=""></option>
          @foreach ($unitPilihan as $induk => $sub)
            <optgroup label="{{ $induk }}">
              @foreach ($sub as $u)
                <option value="{{ $u->id_unit }}" data-kode="{{ $u->kode }}" @selected((string) old('id_unit') === (string) $u->id_unit)>{{ $u->kode }} · {{ $u->nama }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
        @error('id_unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @unless ($unitAktif)
          <div class="form-text text-warning"><i class="ti ti-alert-triangle ti-xs"></i> Kolom unit belum ada di database; unit belum tersimpan sampai SQL <code>database/sql/2026_11_08_ueu_tbl_bkk_id_unit.sql</code> dijalankan.</div>
        @endunless
      </div>
      <div class="col-md-6">
        <label class="form-label" for="f-terima">Sudah Terima Dari <span class="text-danger">*</span></label>
        <input type="text" id="f-terima" name="terimadari" maxlength="60" value="{{ old('terimadari', 'Universitas Muhammadiyah Metro') }}" required
          class="form-control @error('terimadari') is-invalid @enderror">
        @error('terimadari')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-6">
        <label class="form-label" for="f-penerima">Penerima <span class="text-danger">*</span></label>
        <input type="text" id="f-penerima" name="penerima" maxlength="100" value="{{ old('penerima') }}" required
          class="form-control @error('penerima') is-invalid @enderror">
        @error('penerima')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-12">
        <label class="form-label" for="f-uraian">Uraian Kegiatan (Untuk Pembayaran) <span class="text-danger">*</span></label>
        <textarea id="f-uraian" name="uraian_kegiatan" rows="2" required
          class="form-control @error('uraian_kegiatan') is-invalid @enderror">{{ old('uraian_kegiatan') }}</textarea>
        @error('uraian_kegiatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>

    <hr class="my-4">

    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">Rincian Biaya</h6>
      <button type="button" class="btn btn-sm btn-label-primary" id="tambahRinci"><i class="ti ti-plus ti-xs me-1"></i>Tambah rincian</button>
    </div>
    @error('rinci')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

    <div id="daftarRinci">
      @foreach ($rinciLama as $i => $r)
        <div class="d-flex gap-2 align-items-start mb-2 rinci-baris">
          <span class="no">{{ $loop->iteration }}.</span>
          <div class="flex-grow-1">
            <input type="text" name="rinci[{{ $i }}][uraian]" maxlength="255" value="{{ $r['uraian'] ?? '' }}" placeholder="Uraian rincian"
              class="form-control @error("rinci.$i.uraian") is-invalid @enderror" aria-label="Uraian rincian">
            @error("rinci.$i.uraian")<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="biaya">
            <div class="input-group has-validation">
              <span class="input-group-text">Rp</span>
              <input type="text" inputmode="numeric" name="rinci[{{ $i }}][biaya]" value="{{ $fmt($r['biaya'] ?? '') }}" placeholder="0"
                class="form-control rupiah biaya-rinci @error("rinci.$i.biaya") is-invalid @enderror" aria-label="Biaya rincian">
              @error("rinci.$i.biaya")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
          <button type="button" class="btn btn-icon btn-label-danger hapus-rinci" aria-label="Hapus rincian"><i class="ti ti-trash"></i></button>
        </div>
      @endforeach
    </div>

    <div class="d-flex justify-content-end align-items-baseline gap-3 mt-3">
      <span class="text-muted">Banyaknya</span>
      <span class="total-bkk" id="totalBkk">Rp 0</span>
    </div>

    <hr class="my-4">

    <div class="row g-3">
      <div class="col-sm-6 col-md-4">
        <label class="form-label" for="f-ppn">PPN</label>
        <div class="input-group has-validation">
          <span class="input-group-text">Rp</span>
          <input type="text" inputmode="numeric" id="f-ppn" name="ppn" value="{{ $fmt(old('ppn')) }}" placeholder="0"
            class="form-control rupiah @error('ppn') is-invalid @enderror">
          @error('ppn')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
      <div class="col-sm-6 col-md-4">
        <label class="form-label" for="f-pph">PPH</label>
        <div class="input-group has-validation">
          <span class="input-group-text">Rp</span>
          <input type="text" inputmode="numeric" id="f-pph" name="pph" value="{{ $fmt(old('pph')) }}" placeholder="0"
            class="form-control rupiah @error('pph') is-invalid @enderror">
          @error('pph')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex flex-wrap gap-2">
    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Simpan BKK</button>
    <a href="{{ route('kasir.non-anggaran.index') }}" class="btn btn-success">Lihat BKK</a>
  </div>
</form>
</div>

{{-- BKK yang sudah diinput tetapi belum ditunaikan --}}
<div class="col-lg-4 order-1 order-lg-2 kolom-belum">
  <div class="card belum-tunai">
    <div class="card-header d-flex justify-content-between align-items-start gap-2">
      <div>
        <h5 class="mb-0">BKK Belum Tunai</h5>
        <small class="text-muted">{{ $belumTunaiRingkas->n }} BKK · {{ $rp($belumTunaiRingkas->total) }}</small>
      </div>
      <span class="badge rounded-pill {{ $belumTunaiRingkas->n > 0 ? 'bg-label-warning' : 'bg-label-success' }}">{{ $belumTunaiRingkas->n }}</span>
    </div>
    <ul class="list-group list-group-flush">
      @forelse ($belumTunai as $b)
        <li class="list-group-item">
          <div class="d-flex justify-content-between align-items-center gap-2">
            <span class="nomor fw-semibold text-truncate">{{ $b->no_bkk }}</span>
            <div class="aksi d-flex align-items-center gap-1 flex-shrink-0">
              <form method="POST" action="{{ route('kasir.non-anggaran.tunai', $b->id_bkk) }}" class="form-tunai"
                data-bkk="{{ $b->no_bkk }}" data-uraian="{{ $b->uraian }}" data-nominal="{{ $rp($b->nominal) }}">
                @csrf
                <button type="submit" class="btn btn-sm rounded-pill btn-label-success btn-tunai" data-bs-toggle="tooltip" title="Tandai tunai & cetak BKK">
                  <i class="ti ti-cash ti-xs me-1"></i>Tunai</button>
              </form>
              <form method="POST" action="{{ route('kasir.non-anggaran.destroy', $b->id_bkk) }}" class="form-hapus"
                data-bkk="{{ $b->no_bkk }}" data-uraian="{{ $b->uraian }}" data-nominal="{{ $rp($b->nominal) }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-icon rounded-pill btn-label-danger btn-hapus" data-bs-toggle="tooltip" title="Hapus BKK"
                  aria-label="Hapus BKK {{ $b->no_bkk }}"><i class="ti ti-trash ti-xs"></i></button>
              </form>
            </div>
          </div>
          <div class="small mt-1" style="overflow-wrap: anywhere;">{{ \Illuminate\Support\Str::limit($b->uraian, 90) }}</div>
          <div class="d-flex justify-content-between align-items-center gap-2 mt-1">
            <small class="text-muted text-truncate"><i class="ti ti-user ti-xs"></i> {{ $b->penerima }} · {{ $tglId($b->tgl_bkk) }}</small>
            <span class="rp fw-semibold">{{ $rp($b->nominal) }}</span>
          </div>
        </li>
      @empty
        <li class="list-group-item text-center text-muted py-5"><i class="ti ti-circle-check ti-lg d-block mb-2"></i>Semua BKK sudah tunai.</li>
      @endforelse
    </ul>
    @if ($belumTunaiRingkas->n > $belumTunai->count())
      <div class="card-footer small text-muted">
        Menampilkan {{ $belumTunai->count() }} dari {{ $belumTunaiRingkas->n }} BKK ·
        <a href="{{ route('kasir.non-anggaran.index') }}">lihat semua</a>
      </div>
    @endif
  </div>
</div>
</div>

<template id="templatRinci">
  <div class="d-flex gap-2 align-items-start mb-2 rinci-baris">
    <span class="no"></span>
    <div class="flex-grow-1">
      <input type="text" data-name="uraian" maxlength="255" placeholder="Uraian rincian" class="form-control" aria-label="Uraian rincian">
    </div>
    <div class="biaya">
      <div class="input-group">
        <span class="input-group-text">Rp</span>
        <input type="text" inputmode="numeric" data-name="biaya" placeholder="0" class="form-control rupiah biaya-rinci" aria-label="Biaya rincian">
      </div>
    </div>
    <button type="button" class="btn btn-icon btn-label-danger hapus-rinci" aria-label="Hapus rincian"><i class="ti ti-trash"></i></button>
  </div>
</template>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const daftar = document.getElementById('daftarRinci');
  const total = document.getElementById('totalBkk');
  const angka = v => parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;
  const rupiah = n => n.toLocaleString('id-ID');
  let urut = daftar.children.length;

  const hitung = () => {
    let jml = 0;
    daftar.querySelectorAll('.biaya-rinci').forEach(i => jml += angka(i.value));
    total.textContent = 'Rp ' + rupiah(jml);
    daftar.querySelectorAll('.rinci-baris').forEach((b, k) => {
      b.querySelector('.no').textContent = (k + 1) + '.';
      b.querySelector('.hapus-rinci').disabled = daftar.children.length === 1;
    });
  };

  // Format ribuan saat mengetik pada semua isian rupiah.
  document.getElementById('formBkk').addEventListener('input', e => {
    if (!e.target.classList.contains('rupiah')) return;
    const v = e.target.value.replace(/\D/g, '');
    e.target.value = v === '' ? '' : rupiah(parseInt(v, 10));
    hitung();
  });

  document.getElementById('tambahRinci').addEventListener('click', () => {
    const baris = document.getElementById('templatRinci').content.firstElementChild.cloneNode(true);
    baris.querySelectorAll('[data-name]').forEach(i => i.name = 'rinci[' + urut + '][' + i.dataset.name + ']');
    urut++;
    daftar.appendChild(baris);
    hitung();
    baris.querySelector('input').focus();
  });

  daftar.addEventListener('click', e => {
    const tombol = e.target.closest('.hapus-rinci');
    if (!tombol || daftar.children.length === 1) return;
    tombol.closest('.rinci-baris').remove();
    hitung();
  });

  hitung();

  // Unit kerja: hanya sub unit (kode bukan *.00), cari berdasarkan kode atau nama.
  $('#f-unit').select2({ placeholder: 'Pilih sub unit kerja', allowClear: true, width: '100%' })
    .on('change', function () { this.classList.remove('is-invalid'); });

  // Panel BKK belum tunai: konfirmasi Tunai / Hapus, lalu buka cetak BKK setelah tunai.
  document.querySelectorAll('.belum-tunai [data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  var a = document.getElementById('bukaBkk');
  if (a) { window.open(a.href, '_blank'); }
  const esc = s => $('<div>').text(s ?? '').html();
  const isi = el => 'BKK <b class="font-monospace">' + esc(el.dataset.bkk) + '</b><br>' + esc(el.dataset.uraian)
    + '<div class="fs-4 fw-semibold mt-2">' + esc(el.dataset.nominal) + '</div>';
  const tanya = (f, opsi) => (bootstrap.Tooltip.getInstance(f.querySelector('button[type=submit]'))?.hide(), Swal.fire(Object.assign({
    showCancelButton: true, cancelButtonText: 'Batal', buttonsStyling: false, focusCancel: true
  }, opsi)).then(r => { if (r.isConfirmed) { f.querySelector('button[type=submit]').disabled = true; f.submit(); } }));

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
