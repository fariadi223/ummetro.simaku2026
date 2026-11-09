@extends('layouts/layoutMaster')

@section('title', 'Input Jurnal Umum')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-style')
<style>
  /* fieldset[disabled] tidak menonaktifkan tampilan select2 & altInput flatpickr: kunci lewat CSS. */
  .ju-kunci[disabled] { opacity: .6; }
  .ju-kunci[disabled] .select2-container, .ju-kunci[disabled] .btn { pointer-events: none; }
  .form-ju .form-label { font-weight: 600; }
  .rupiah { text-align: right; font-variant-numeric: tabular-nums; }
  .tabel-ju td { vertical-align: middle; }
  .tabel-ju .kol-rek { min-width: 320px; }
  .tabel-ju .kol-n { width: 190px; min-width: 150px; }
  .tabel-ju tfoot td { font-weight: 700; font-variant-numeric: tabular-nums; }
  .riwayat-ju .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .riwayat-ju .list-group { max-height: 75vh; overflow-y: auto; }
  .riwayat-ju table td { padding-top: .1rem; padding-bottom: .1rem; }
  .tabel-ju tr.salah td { background: rgba(var(--bs-danger-rgb), .06); }
</style>
@endsection

@php
  $fmt = fn ($v) => ($v = (int) preg_replace('/\D/', '', (string) $v)) > 0 ? number_format($v, 0, ',', '.') : '';
  $barisLama = collect(old('baris', [[], []]))->map(fn ($b) => [
    'idrek6' => (string) ($b['idrek6'] ?? ''), 'debet' => $fmt($b['debet'] ?? ''), 'kredit' => $fmt($b['kredit'] ?? ''),
  ])->values()->all();
  while (count($barisLama) < 2) { $barisLama[] = ['idrek6' => '', 'debet' => '', 'kredit' => '']; }
  $errBaris = collect($errors->getMessages())->keys()
    ->filter(fn ($k) => str_starts_with($k, 'baris.'))->map(fn ($k) => (int) explode('.', $k)[1])->unique()->values()->all();
  $pesanBaris = collect($errors->getMessages())->filter(fn ($m, $k) => $k === 'baris' || $k === 'seimbang' || str_starts_with($k, 'baris.'))->flatten()->unique()->values();
  $maksBaris = \App\Http\Controllers\akuntansi\JurnalUmumController::MAKS_BARIS;
  $grupRek = $rekening->groupBy('namarek1');
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  $tglId = fn ($t) => \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('l, d F Y');
  $grupMak = $mak->groupBy('namamak1');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-book"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Input Jurnal Umum</h4>
      <p class="pg-header-sub mb-0">Kertas Kerja · Jurnal Umum · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
</div>

@include('_partials._flash')

@unless ($terbuka)
  @include('_partials._periode-terkunci', ['apa' => 'jurnal umum'])
@endunless

<div class="row g-4">
  <div class="col-7">
    <form method="POST" action="{{ route('akuntansi.jurnal-umum.store') }}" class="card form-ju" id="formJu" novalidate>
      @csrf
      {{-- Periode bukan periode berjalan: seluruh isi form (termasuk baris yang ditambah lewat JS) ikut nonaktif. --}}
      <fieldset class="ju-kunci" @disabled(!$terbuka)>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="f-tgl">Tanggal <span class="text-danger">*</span></label>
            <input type="text" id="f-tgl" name="tgl_trk" value="{{ old('tgl_trk', $tglBawaan) }}" required autocomplete="off" placeholder="Pilih tanggal"
              min="{{ $periodeAwal }}" max="{{ $periodeAkhir }}"
              class="form-control @error('tgl_trk') is-invalid @enderror">
            @error('tgl_trk')<div class="invalid-feedback">{{ $message }}</div>@else
              <div class="form-text">Periode T.A {{ $tahun }}/{{ $tahun + 1 }}:
                {{ \Illuminate\Support\Carbon::parse($periodeAwal)->format('d-m-Y') }} s/d {{ \Illuminate\Support\Carbon::parse($periodeAkhir)->format('d-m-Y') }}</div>
            @enderror
          </div>
          <div class="col-md-8">
            <label class="form-label" for="f-bukti">No. Bukti</label>
            <input type="text" id="f-bukti" name="bukti" maxlength="100" value="{{ old('bukti') }}" placeholder="Opsional"
              class="form-control @error('bukti') is-invalid @enderror">
            @error('bukti')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="f-unit">Unit <span class="text-danger">*</span></label>
            <select id="f-unit" name="id_uk" required class="form-select @error('id_uk') is-invalid @enderror" data-placeholder="Pilih unit">
              <option value=""></option>
              @foreach ($unit as $u)
                <option value="{{ $u->id_unit }}" @selected((string) old('id_uk') === (string) $u->id_unit)>{{ $u->kode }} · {{ $u->nama }}</option>
              @endforeach
            </select>
            @error('id_uk')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <label class="form-label" for="f-mak">Mata Anggaran</label>
            <select id="f-mak" name="id_mak" class="form-select @error('id_mak') is-invalid @enderror" data-placeholder="Opsional">
              <option value=""></option>
              @foreach ($grupMak as $grup => $isi)
                <optgroup label="{{ $grup }}">
                  @foreach ($isi as $m)
                    <option value="{{ $m->idmak4 }}" @selected((string) old('id_mak') === (string) $m->idmak4)>{{ $m->kode }} · {{ $m->namamak4 }}</option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
            @error('id_mak')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
          <div class="col-md-6">
            <label class="form-label" for="f-uraian">Uraian <span class="text-danger">*</span></label>
            <input type="text" id="f-uraian" name="uraian" maxlength="500" value="{{ old('uraian') }}" required
              class="form-control @error('uraian') is-invalid @enderror">
            @error('uraian')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
      <div class="border-top">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-4 pt-3">
          <div>
            <h6 class="mb-0">Baris Jurnal</h6>
            <small class="text-muted">Satu rekening per baris; isi debet <em>atau</em> kredit. Total debet harus sama dengan total kredit.</small>
          </div>
          <button type="button" class="btn btn-sm btn-label-primary" id="tambahBaris"><i class="ti ti-plus ti-xs me-1"></i>Tambah baris</button>
        </div>
        @if ($pesanBaris->isNotEmpty())
          <div class="alert alert-danger mx-4 mt-3 mb-0 py-2 small">
            @foreach ($pesanBaris as $m)<div>{{ $m }}</div>@endforeach
          </div>
        @endif
        <div class="table-responsive mt-2">
          <table class="table tabel-ju mb-0">
            <thead>
              <tr><th style="width: 44px;">No</th><th class="kol-rek">Rekening</th><th class="kol-n text-end">Debet</th>
                <th class="kol-n text-end">Kredit</th><th style="width: 52px;"></th></tr>
            </thead>
            <tbody id="barisJurnal"></tbody>
            <tfoot>
              <tr><td colspan="2" class="text-end">TOTAL</td><td class="text-end" id="totDebet">Rp 0</td><td class="text-end" id="totKredit">Rp 0</td><td></td></tr>
              <tr><td colspan="2" class="text-end">SELISIH</td><td colspan="2" class="text-center" id="selisih">—</td><td></td></tr>
            </tfoot>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Jurnalkan</button>
        <button type="reset" class="btn btn-label-secondary" id="btnReset">Kosongkan</button>
      </div>
      </fieldset>
    </form>
  </div>

  <div class="col-xl-5">
    <div class="card riwayat-ju">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <h6 class="mb-0">Riwayat Jurnal Umum</h6>
            <small class="text-muted">Hari ini · {{ $tglId($tglRiwayat) }}</small>
          </div>
          <span class="badge bg-label-primary" title="Ditampilkan {{ $riwayat->count() }} terbaru">{{ $riwayatTotal > $riwayat->count() ? $riwayat->count() . ' dari ' . $riwayatTotal : $riwayat->count() }} entri</span>
        </div>
      </div>
      <div class="list-group list-group-flush">
        @forelse ($riwayat as $e)
          <div class="list-group-item">
            <div class="d-flex justify-content-between gap-2">
              <div style="min-width: 0;">
                <div class="fw-semibold small text-truncate" title="{{ $e->uraian }}">{{ $e->uraian }}</div>
                <div class="small text-muted text-truncate">{{ $e->bukti !== '' ? $e->bukti . ' · ' : '' }}{{ $e->unit }}</div>
              </div>
              <div class="text-end">
                <div class="fw-semibold small rp">{{ $rp($e->debet) }}</div>
                @unless ($e->selesai)<span class="badge bg-label-danger">tidak seimbang</span>@endunless
              </div>
            </div>
            <table class="table table-sm table-borderless small mb-0 mt-1">
              @foreach ($e->baris as $b)
                <tr>
                  <td class="ps-0 {{ $b->kredit > 0 ? 'ps-3' : '' }}"><span class="font-monospace text-muted">{{ $b->kode_rek }}</span> {{ $b->namarek6 }}</td>
                  <td class="text-end rp pe-0" style="width: 1%;">{{ $b->debet > 0 ? 'D' : 'K' }} {{ $rp($b->debet > 0 ? $b->debet : $b->kredit) }}</td>
                </tr>
              @endforeach
            </table>
          </div>
        @empty
          <div class="list-group-item text-center text-muted small py-5">Belum ada jurnal umum hari ini.</div>
        @endforelse
      </div>
    </div>
  </div>
</div>

<template id="tplBaris">
  <tr>
    <td class="text-muted no-baris"></td>
    <td class="kol-rek">
      <select class="form-select sel-rek" data-nama="idrek6" data-placeholder="Pilih rekening">
        <option value=""></option>
        @foreach ($grupRek as $grup => $isi)
          <optgroup label="{{ $grup }}">
            @foreach ($isi as $r)
              <option value="{{ $r->idrek6 }}">{{ $r->kode }} · {{ $r->namarek6 }}</option>
            @endforeach
          </optgroup>
        @endforeach
      </select>
    </td>
    <td class="kol-n"><input type="text" inputmode="numeric" class="form-control rupiah in-n" data-nama="debet" placeholder="0"></td>
    <td class="kol-n"><input type="text" inputmode="numeric" class="form-control rupiah in-n" data-nama="kredit" placeholder="0"></td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-icon btn-label-danger hapus-baris" title="Hapus baris"><i class="ti ti-trash"></i></button></td>
  </tr>
</template>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Datepicker tanggal jurnal: hanya tanggal di periode T.A (min/max sama dengan validasi server).
  const elTgl = document.getElementById('f-tgl');
  flatpickr(elTgl, {
    dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', monthSelectorType: 'static',
    minDate: elTgl.getAttribute('min'), maxDate: elTgl.getAttribute('max'), disableMobile: true,
    locale: {
      firstDayOfWeek: 1,
      weekdays: { shorthand: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'], longhand: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] },
      months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
        longhand: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] }
    }
  });

  const form = document.getElementById('formJu');
  const tbody = document.getElementById('barisJurnal');
  const tpl = document.getElementById('tplBaris');
  const barisLama = @json($barisLama);
  const errBaris = @json($errBaris);
  const maksBaris = @json($maksBaris);
  const angka = v => parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;
  const rupiah = n => 'Rp ' + n.toLocaleString('id-ID');
  const esc = s => $('<div>').text(s ?? '').html();

  $('#f-unit, #f-mak, #f-sumber').each(function () {
    $(this).select2({ placeholder: $(this).data('placeholder'), allowClear: true, width: '100%' });
  });

  // Nama input baris[i][...] disusun ulang setiap ada perubahan agar indeks berurutan.
  const susun = () => {
    tbody.querySelectorAll('tr').forEach((tr, i) => {
      tr.querySelector('.no-baris').textContent = i + 1;
      tr.querySelectorAll('[data-nama]').forEach(el => { el.name = 'baris[' + i + '][' + el.dataset.nama + ']'; });
    });
    const n = tbody.children.length;
    tbody.querySelectorAll('.hapus-baris').forEach(b => { b.disabled = n <= 2; });
    document.getElementById('tambahBaris').disabled = n >= maksBaris;
    hitung();
  };
  const hitung = () => {
    let d = 0, k = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      d += angka(tr.querySelector('[data-nama=debet]').value);
      k += angka(tr.querySelector('[data-nama=kredit]').value);
    });
    document.getElementById('totDebet').textContent = rupiah(d);
    document.getElementById('totKredit').textContent = rupiah(k);
    const el = document.getElementById('selisih');
    if (!d && !k) { el.textContent = '—'; el.className = 'text-center text-muted'; }
    else if (d === k) { el.innerHTML = '<span class="badge bg-label-success"><i class="ti ti-check ti-xs me-1"></i>Seimbang</span>'; el.className = 'text-center'; }
    else { el.textContent = rupiah(Math.abs(d - k)) + (d > k ? ' (debet lebih besar)' : ' (kredit lebih besar)'); el.className = 'text-center text-danger'; }
    return { d, k };
  };
  const tambah = (isi, salah) => {
    const tr = tpl.content.firstElementChild.cloneNode(true);
    tbody.appendChild(tr);
    const sel = tr.querySelector('.sel-rek');
    sel.value = (isi && isi.idrek6) || '';
    $(sel).select2({ placeholder: 'Pilih rekening', allowClear: true, width: '100%' });
    if (isi) {
      tr.querySelector('[data-nama=debet]').value = isi.debet || '';
      tr.querySelector('[data-nama=kredit]').value = isi.kredit || '';
    }
    if (salah) tr.classList.add('salah');
    susun();
    return tr;
  };

  tbody.addEventListener('input', function (e) {
    if (!e.target.classList.contains('in-n')) return;
    const v = e.target.value.replace(/\D/g, '');
    e.target.value = v === '' ? '' : parseInt(v, 10).toLocaleString('id-ID');
    // Satu baris hanya debet atau kredit: mengisi satu sisi mengosongkan sisi lain.
    if (v !== '') {
      const lain = e.target.dataset.nama === 'debet' ? 'kredit' : 'debet';
      e.target.closest('tr').querySelector('[data-nama=' + lain + ']').value = '';
    }
    e.target.closest('tr').classList.remove('salah');
    hitung();
  });
  tbody.addEventListener('click', function (e) {
    const b = e.target.closest('.hapus-baris');
    if (!b || tbody.children.length <= 2) return;
    $(b.closest('tr').querySelector('.sel-rek')).select2('destroy');
    b.closest('tr').remove();
    susun();
  });
  document.getElementById('tambahBaris').addEventListener('click', () => {
    const tr = tambah(null, false);
    $(tr.querySelector('.sel-rek')).select2('open');
  });
  barisLama.forEach((b, i) => tambah(b, errBaris.includes(i)));

  document.getElementById('btnReset').addEventListener('click', () => setTimeout(() => {
    $('#f-unit, #f-mak, #f-sumber').val('').trigger('change');
    tbody.querySelectorAll('.sel-rek').forEach(s => $(s).select2('destroy'));
    tbody.innerHTML = '';
    tambah(null, false); tambah(null, false);
  }));

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const { d, k } = hitung();
    // Belum lengkap / tidak seimbang: kirim langsung agar server menampilkan pesan validasi.
    if (!d || d !== k || !document.getElementById('f-uraian').value.trim()) { form.submit(); return; }
    const rinci = Array.from(tbody.querySelectorAll('tr')).map(tr => {
      const t = $(tr.querySelector('.sel-rek')).find('option:selected');
      const db = angka(tr.querySelector('[data-nama=debet]').value), kr = angka(tr.querySelector('[data-nama=kredit]').value);
      return '<tr><td class="text-start">' + esc(t.val() ? t.text() : '—') + '</td><td class="text-end">' + (db ? rupiah(db) : '') + '</td><td class="text-end">' + (kr ? rupiah(kr) : '') + '</td></tr>';
    }).join('');
    Swal.fire({
      icon: 'question', title: 'Jurnalkan?', width: 720,
      html: esc(document.getElementById('f-uraian').value) + '<div class="fs-4 fw-semibold my-2">' + rupiah(d) + '</div>'
        + '<table class="table table-sm small mb-0"><thead><tr><th class="text-start">Rekening</th><th class="text-end">Debet</th><th class="text-end">Kredit</th></tr></thead><tbody>' + rinci + '</tbody></table>',
      showCancelButton: true, cancelButtonText: 'Batal', confirmButtonText: 'Ya, jurnalkan', focusCancel: true, buttonsStyling: false,
      customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-label-secondary' }
    }).then(r => { if (r.isConfirmed) { form.querySelector('button[type=submit]').disabled = true; form.submit(); } });
  });
});
</script>
@endsection
