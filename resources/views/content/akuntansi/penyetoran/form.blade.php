@extends('layouts/layoutMaster')

@section('title', 'Setor Kas Unit')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .form-setor .form-label { font-weight: 600; }
  .rupiah { text-align: right; font-variant-numeric: tabular-nums; }
  .saldo-besar { font-size: 1.6rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--bs-heading-color); line-height: 1.1; }
  .riwayat .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  /* fieldset[disabled] tidak menonaktifkan tampilan select2: kunci lewat CSS. */
  .setor-kunci[disabled] { opacity: .6; }
  .setor-kunci[disabled] .select2-container { pointer-events: none; }
</style>
@endsection

@php
  $rp = fn ($n) => ($n < 0 ? '−' : '') . 'Rp ' . number_format(abs((float) $n), 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $nominalLama = old('nominal');
  $nominalLama = ($nominalLama === null || $nominalLama === '') ? '' : number_format((int) preg_replace('/\D/', '', (string) $nominalLama), 0, ',', '.');
  $debetLama = (string) old('debet', \App\Http\Controllers\akuntansi\PenyetoranController::KAS_UNIV);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-building-bank"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Penyetoran Kas Unit</h4>
      <p class="pg-header-sub mb-0"><span class="font-monospace">{{ $unit->kode }}</span> {{ $unit->nama }} · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    <a href="{{ route('akuntansi.penyetoran.index') }}" class="btn btn-label-secondary"><i class="ti ti-arrow-left me-1"></i>Kembali</a>
  </div>
</div>

@include('_partials._flash')

@unless ($terbuka)
  @include('_partials._periode-terkunci', ['apa' => 'setoran kas unit'])
@endunless

<div class="row g-4">
  <div class="col-lg-8">
    <form method="POST" action="{{ route('akuntansi.penyetoran.store', $ff) }}" class="card form-setor" id="formSetor" novalidate>
      @csrf
      {{-- Periode bukan periode berjalan: isian nonaktif (tombol Kembali tetap aktif). --}}
      <fieldset class="setor-kunci" @disabled(!$terbuka)>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="f-bukti">No. Bukti Transaksi / No. Kuitansi <span class="text-danger">*</span></label>
            <input type="text" id="f-bukti" name="bukti" maxlength="100" value="{{ old('bukti') }}" required autofocus
              class="form-control @error('bukti') is-invalid @enderror">
            @error('bukti')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="f-tgl">Tanggal <span class="text-danger">*</span></label>
            <input type="date" id="f-tgl" name="tgl_trk" value="{{ old('tgl_trk', today()->toDateString()) }}" required
              class="form-control @error('tgl_trk') is-invalid @enderror">
            @error('tgl_trk')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="f-nominal">Besar Uang <span class="text-danger">*</span></label>
            <div class="input-group has-validation">
              <span class="input-group-text">Rp</span>
              <input type="text" inputmode="numeric" id="f-nominal" name="nominal" value="{{ $nominalLama }}" placeholder="0" required
                class="form-control rupiah @error('nominal') is-invalid @enderror">
              <button type="button" class="btn btn-label-primary" id="isiSaldo" @disabled($saldo <= 0)>Setor semua</button>
              @error('nominal')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-text" id="infoSisa"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="f-sumber">Sumber Dana <span class="text-danger">*</span></label>
            <select id="f-sumber" name="id_sumber" required class="form-select @error('id_sumber') is-invalid @enderror" data-placeholder="Pilih sumber dana">
              <option value=""></option>
              @foreach ($sumber as $s)
                <option value="{{ $s->id }}" @selected((string) old('id_sumber') === (string) $s->id)>{{ $s->kode }} · {{ $s->nama_penerimaan }}</option>
              @endforeach
            </select>
            @error('id_sumber')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label class="form-label" for="f-debet">Rekening yang di-DEBET <span class="text-danger">*</span></label>
            <select id="f-debet" name="debet" required class="form-select @error('debet') is-invalid @enderror">
              @foreach ($rekening->groupBy('namarek1') as $grup => $isi)
                <optgroup label="{{ $grup }}">
                  @foreach ($isi as $r)
                    @continue($r->idrek6 == \App\Http\Controllers\akuntansi\PenyetoranController::KAS_UNIT)
                    <option value="{{ $r->idrek6 }}" @selected($debetLama === (string) $r->idrek6)>{{ $r->kode }} · {{ $r->namarek6 }}</option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
            @error('debet')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text">Rekening yang di-KREDIT otomatis: <strong>Kas di Unit Kerja</strong>. Umumnya debet ke <strong>Kas Universitas</strong>.</div>
          </div>
        </div>
      </div>
      </fieldset>
      <div class="card-footer d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary" @disabled(!$terbuka)><i class="ti ti-device-floppy me-1"></i>Proses Setoran</button>
        <a href="{{ route('akuntansi.penyetoran.index') }}" class="btn btn-label-secondary">Kembali</a>
      </div>
    </form>
  </div>

  <div class="col-lg-4">
    <div class="card mb-4">
      <div class="card-body">
        <div class="small text-muted text-uppercase mb-1" style="letter-spacing: .04em;">Kas di Unit {{ $unit->nama }}</div>
        <div class="saldo-besar {{ $saldo < 0 ? 'text-danger' : '' }}">{{ $rp($saldo) }}</div>
        <div class="small text-muted mt-1">Saldo T.A {{ $tahun }}/{{ $tahun + 1 }} yang dapat disetorkan</div>
      </div>
    </div>

    <div class="card riwayat">
      <div class="card-header">
        <h6 class="mb-0">Setoran Terakhir</h6>
        <small class="text-muted">10 setoran terbaru unit ini</small>
      </div>
      <ul class="list-group list-group-flush">
        @forelse ($riwayat as $h)
          <li class="list-group-item d-flex justify-content-between gap-2">
            <div style="min-width: 0;">
              <div class="small fw-semibold text-truncate">{{ $h->bukti !== '' ? $h->bukti : 'Tanpa no. bukti' }}</div>
              <div class="small text-muted">{{ $tglId($h->tgl_trk) }}</div>
            </div>
            <span class="rp fw-semibold">{{ $rp($h->kredit) }}</span>
          </li>
        @empty
          <li class="list-group-item text-center text-muted py-4 small">Belum ada setoran pada T.A ini.</li>
        @endforelse
      </ul>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const saldo = {{ (int) round($saldo) }};
  const form = document.getElementById('formSetor');
  const nominal = document.getElementById('f-nominal');
  const info = document.getElementById('infoSisa');
  const angka = v => parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;
  const rupiah = n => 'Rp ' + n.toLocaleString('id-ID');

  const perbarui = () => {
    const n = angka(nominal.value);
    if (!n) { info.textContent = ''; info.className = 'form-text'; return; }
    const sisa = saldo - n;
    info.className = 'form-text ' + (sisa < 0 ? 'text-danger' : 'text-muted');
    info.textContent = sisa < 0 ? 'Melebihi saldo kas unit sebesar ' + rupiah(-sisa) + '.' : 'Sisa kas unit setelah setor: ' + rupiah(sisa);
  };
  nominal.addEventListener('input', function () {
    const v = this.value.replace(/\D/g, '');
    this.value = v === '' ? '' : parseInt(v, 10).toLocaleString('id-ID');
    perbarui();
  });
  document.getElementById('isiSaldo').addEventListener('click', () => { nominal.value = saldo.toLocaleString('id-ID'); perbarui(); nominal.focus(); });
  perbarui();

  $('#f-sumber').select2({ placeholder: 'Pilih sumber dana', allowClear: true, width: '100%' });
  $('#f-debet').select2({ width: '100%' });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const n = angka(nominal.value);
    // Isian wajib belum lengkap: kirim langsung agar server menampilkan pesan validasi.
    if (!n || !document.getElementById('f-bukti').value.trim()) { form.submit(); return; }
    const esc = s => $('<div>').text(s ?? '').html();
    const lebih = n > saldo ? '<div class="alert alert-warning small mt-3 mb-0">Nominal melebihi saldo kas unit.</div>' : '';
    Swal.fire({
      icon: 'question', title: 'Jurnalkan setoran?',
      html: esc(@json($unit->nama)) + '<div class="fs-4 fw-semibold my-2">' + rupiah(n) + '</div>'
        + '<div class="small text-muted">Debet: ' + esc($('#f-debet option:selected').text()) + '<br>Kredit: Kas di Unit Kerja</div>' + lebih,
      showCancelButton: true, cancelButtonText: 'Batal', confirmButtonText: 'Ya, jurnalkan', focusCancel: true, buttonsStyling: false,
      customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-label-secondary' }
    }).then(r => { if (r.isConfirmed) { form.querySelector('button[type=submit]').disabled = true; form.submit(); } });
  });
});
</script>
@endsection
