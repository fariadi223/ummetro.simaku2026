@extends('layouts/layoutMaster')

@section('title', 'Jurnal Non Anggaran')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .bkk-tabel td { vertical-align: top; }
  .bkk-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
  .bkk-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .8rem; white-space: nowrap; }
  .filter-bkk .f-tgl { width: 205px; }
  .filter-bkk .f-cari { width: 260px; }
  @media (max-width: 575.98px) { .filter-bkk .f-tgl, .filter-bkk .f-cari { width: 100%; } }
  .rupiah { text-align: right; font-variant-numeric: tabular-nums; }
  .ringkas-bkk dt { font-weight: 500; color: var(--bs-secondary-color); font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; }
  .ringkas-bkk dd { margin-bottom: .5rem; font-variant-numeric: tabular-nums; }
</style>
@endsection

@php
  $rp = fn ($n) => ($n < 0 ? '−' : '') . number_format(abs((float) $n), 0, ',', '.');
  $tglId = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '—';
  $difilter = $cari !== '' || $dari !== '' || $sampai !== '';
  $kembali = array_filter(['cari' => $cari, 'dari' => $dari, 'sampai' => $sampai, 'page' => $daftar->currentPage() > 1 ? (string) $daftar->currentPage() : ''], fn ($v) => $v !== '');
  $bkkLama = (string) old('_bkk', '');
  $nominalLama = old('nominal');
  $nominalLama = ($nominalLama === null || $nominalLama === '') ? '' : number_format((int) preg_replace('/\D/', '', (string) $nominalLama), 0, ',', '.');
  $lama = [
    'tgl_trk' => (string) old('tgl_trk', today()->toDateString()), 'uraian' => (string) old('uraian', ''),
    'bkk_rinci_id' => (string) old('bkk_rinci_id', ''), 'nominal' => $nominalLama, 'debet' => (string) old('debet', ''),
    'kredit' => (string) old('kredit', ''), 'id_uk' => (string) old('id_uk', ''), 'id_mak' => (string) old('id_mak', ''),
    'id_sumber' => (string) old('id_sumber', ''),
  ];
  $grupRek = $rekening->groupBy('namarek1');
  $grupMak = $mak->groupBy('namamak1');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-receipt-2"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Jurnal Non Anggaran</h4>
      <p class="pg-header-sub mb-0">Bukti Kas Keluar non anggaran yang sudah dibayar kasir · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-receipt"></i><span><strong>{{ number_format($daftar->total(), 0, ',', '.') }}</strong> BKK tunai</span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom">
    <div>
      <h5 class="mb-0">Bukti Kas Keluar Non Anggaran</h5>
      <small class="text-muted">Realisasi = total yang sudah dijurnal dengan bukti nomor BKK tersebut</small>
    </div>
    <form method="GET" action="{{ route('akuntansi.jurnal-bkk.index') }}" class="d-flex flex-wrap align-items-center gap-2 filter-bkk" role="search">
      <div class="input-group input-group-merge f-tgl">
        <span class="input-group-text">Dari</span>
        <input type="text" name="dari" id="f-dari" value="{{ $dari }}" class="form-control" placeholder="Pilih tanggal" aria-label="Tanggal dari" readonly>
      </div>
      <div class="input-group input-group-merge f-tgl">
        <span class="input-group-text">Sampai</span>
        <input type="text" name="sampai" id="f-sampai" value="{{ $sampai }}" class="form-control" placeholder="Pilih tanggal" aria-label="Tanggal sampai" readonly>
      </div>
      <div class="input-group f-cari">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="search" name="cari" value="{{ $cari }}" class="form-control" placeholder="No. BKK, uraian, penerima">
      </div>
      <button type="submit" class="btn btn-primary"><i class="ti ti-filter ti-xs me-1"></i>Tampilkan</button>
      @if ($difilter)<a href="{{ route('akuntansi.jurnal-bkk.index') }}" class="btn btn-label-secondary btn-icon" title="Reset filter"><i class="ti ti-x"></i></a>@endif
    </form>
  </div>
  <div class="table-responsive">
    <table class="table bkk-tabel mb-0">
      <thead>
        <tr><th style="width: 44px;">No</th><th>No. BKK</th><th>Untuk Pembayaran</th><th class="text-end">Banyaknya (Rp)</th>
          <th>Tanggal</th><th class="text-end">Realisasi</th><th class="text-end" style="width: 150px;">Menu</th></tr>
      </thead>
      <tbody>
        @forelse ($daftar as $b)
          @php
            $rinciData = $b->rinci->map(fn ($r) => ['id' => $r->id, 'uraian' => $r->uraian, 'biaya' => (int) round($r->biaya), 'realisasi' => (int) round($r->realisasi)])->values()->all();
          @endphp
          <tr>
            <td class="text-muted">{{ $daftar->firstItem() + $loop->index }}</td>
            <td>
              <span class="nomor">{{ $b->no_bkk }}</span>
              @if (trim((string) $b->penerima) !== '')<div class="small text-muted">{{ $b->penerima }}</div>@endif
            </td>
            <td>
              {{ $b->uraian }}
              @if ($b->unit)<div class="small text-muted">{{ $b->unit->kode }} {{ $b->unit->nama }}</div>@endif
            </td>
            <td class="rp">{{ $rp($b->nominal) }}</td>
            <td class="text-nowrap small">{{ $tglId($b->tgl_bkk) }}</td>
            <td class="rp">
              <span class="{{ $b->realisasi > 0 ? 'fw-semibold' : 'text-muted' }}">{{ $rp($b->realisasi) }}</span>
            </td>
            <td class="text-end text-nowrap">
              @if ($b->sisa > 0)
                <button type="button" class="btn btn-sm btn-primary btn-jurnal" data-bs-toggle="modal" data-bs-target="#modalJurnal"
                  data-id="{{ $b->id_bkk }}" data-action="{{ route('akuntansi.jurnal-bkk.store', $b->id_bkk) }}"
                  data-no="{{ $b->no_bkk }}" data-uraian="{{ $b->uraian }}" data-penerima="{{ $b->penerima }}"
                  data-nominal="{{ (int) round($b->nominal) }}" data-realisasi="{{ (int) round($b->realisasi) }}"
                  data-sisa="{{ (int) floor($b->sisa) }}" data-unit="{{ $b->id_unit ?? '' }}" data-rinci="{{ json_encode($rinciData) }}">
                  <i class="ti ti-notebook ti-xs me-1"></i>Jurnalkan</button>
              @endif
              @if ($b->realisasi <= 0)
                <form method="POST" action="{{ route('akuntansi.jurnal-bkk.destroy', $b->id_bkk) }}" class="d-inline form-hapus" data-no="{{ $b->no_bkk }}">
                  @csrf @method('DELETE')
                  @foreach ($kembali as $k => $v)<input type="hidden" name="f_{{ $k }}" value="{{ $v }}">@endforeach
                  <button type="submit" class="btn btn-sm btn-icon btn-label-danger" title="Hapus BKK"><i class="ti ti-trash"></i></button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-5">Tidak ada BKK tunai{{ $difilter ? ' untuk filter ini' : '' }}.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($daftar->hasPages())
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted">Menampilkan {{ $daftar->firstItem() }}–{{ $daftar->lastItem() }} dari {{ $daftar->total() }} BKK</small>
      {{ $daftar->onEachSide(1)->links('_partials.pagination') }}
    </div>
  @endif
</div>

<div class="modal fade" id="modalJurnal" tabindex="-1" aria-labelledby="modalJurnalJudul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <form method="POST" action="" class="modal-content" id="formJurnal" novalidate>
      @csrf
      <input type="hidden" name="_bkk" id="j-bkk" value="{{ $bkkLama }}">
      @foreach ($kembali as $k => $v)<input type="hidden" name="f_{{ $k }}" value="{{ $v }}">@endforeach
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalJurnalJudul">Jurnal BKK</h5>
          <small class="text-muted font-monospace" id="j-no"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-lg-3">
            <dl class="ringkas-bkk mb-0">
              <dt>Penerima</dt><dd id="j-penerima"></dd>
              <dt>Banyaknya</dt><dd class="fw-semibold" id="j-nominal-bkk"></dd>
              <dt>Sudah dijurnal</dt><dd id="j-realisasi"></dd>
              <dt>Sisa</dt><dd class="fw-semibold fs-5 mb-0" id="j-sisa"></dd>
            </dl>
          </div>
          <div class="col-lg-9">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="j-tgl">Tanggal <span class="text-danger">*</span></label>
                <input type="date" id="j-tgl" name="tgl_trk" required class="form-control @error('tgl_trk') is-invalid @enderror">
                @error('tgl_trk')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-8">
                <label class="form-label fw-semibold" for="j-uraian">Uraian <span class="text-danger">*</span></label>
                <input type="text" id="j-uraian" name="uraian" maxlength="500" required class="form-control @error('uraian') is-invalid @enderror">
                @error('uraian')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-7">
                <label class="form-label fw-semibold" for="j-item">Item</label>
                <select id="j-item" name="bkk_rinci_id" class="form-select @error('bkk_rinci_id') is-invalid @enderror"></select>
                @error('bkk_rinci_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Opsional. Bila dipilih, uraian jurnal diberi nama item dan realisasi item bertambah.</div>
              </div>
              <div class="col-md-5">
                <label class="form-label fw-semibold" for="j-nominal">Realisasi <span class="text-danger">*</span></label>
                <div class="input-group has-validation">
                  <span class="input-group-text">Rp</span>
                  <input type="text" id="j-nominal" name="nominal" inputmode="numeric" required class="form-control rupiah @error('nominal') is-invalid @enderror">
                  <button type="button" class="btn btn-outline-primary" id="j-maks">Isi sisa</button>
                  @error('nominal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text" id="j-info"></div>
              </div>
              @foreach (['debet' => 'Rekening yang di-DEBET', 'kredit' => 'Rekening yang di-KREDIT'] as $nama => $label)
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="j-{{ $nama }}">{{ $label }} <span class="text-danger">*</span></label>
                  <select id="j-{{ $nama }}" name="{{ $nama }}" class="form-select sel2 @error($nama) is-invalid @enderror" data-placeholder="Pilih rekening">
                    <option value=""></option>
                    @foreach ($grupRek as $grup => $isi)
                      <optgroup label="{{ $grup }}">
                        @foreach ($isi as $r)<option value="{{ $r->idrek6 }}">{{ $r->kode }} · {{ $r->namarek6 }}</option>@endforeach
                      </optgroup>
                    @endforeach
                  </select>
                  @error($nama)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
              @endforeach
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="j-unit">Unit <span class="text-danger">*</span></label>
                <select id="j-unit" name="id_uk" class="form-select sel2 @error('id_uk') is-invalid @enderror" data-placeholder="Pilih unit">
                  <option value=""></option>
                  @foreach ($subUnit as $u)<option value="{{ $u->id_unit }}">{{ $u->kode }} · {{ $u->nama }}</option>@endforeach
                </select>
                @error('id_uk')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="j-mak">Mata Anggaran</label>
                <select id="j-mak" name="id_mak" class="form-select sel2 @error('id_mak') is-invalid @enderror" data-placeholder="Opsional">
                  <option value=""></option>
                  @foreach ($grupMak as $grup => $isi)
                    <optgroup label="{{ $grup }}">
                      @foreach ($isi as $m)<option value="{{ $m->idmak4 }}">{{ $m->kode }} · {{ $m->namamak4 }}</option>@endforeach
                    </optgroup>
                  @endforeach
                </select>
                @error('id_mak')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" for="j-sumber">Sumber Dana <span class="text-danger">*</span></label>
                <select id="j-sumber" name="id_sumber" class="form-select sel2 @error('id_sumber') is-invalid @enderror" data-placeholder="Pilih sumber dana">
                  <option value=""></option>
                  @foreach ($sumber as $s)<option value="{{ $s->id }}">{{ $s->kode }} · {{ $s->nama_penerimaan }}</option>@endforeach
                </select>
                @error('id_sumber')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" id="j-simpan"><i class="ti ti-device-floppy me-1"></i>Jurnalkan</button>
      </div>
    </form>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Filter tanggal: flatpickr, kirim Y-m-d, tampil "d M Y"; Dari ≤ Sampai saling membatasi.
  const localeId = {
    firstDayOfWeek: 1,
    weekdays: { shorthand: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'], longhand: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] },
    months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
      longhand: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] }
  };
  const opsiTgl = { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', allowInput: false, monthSelectorType: 'static', locale: localeId };
  let fpDari = null;
  const fpSampai = flatpickr('#f-sampai', Object.assign({}, opsiTgl, {
    minDate: document.getElementById('f-dari').value || null,
    onChange: (d, str) => { if (fpDari) fpDari.set('maxDate', str || null); }
  }));
  fpDari = flatpickr('#f-dari', Object.assign({}, opsiTgl, {
    maxDate: document.getElementById('f-sampai').value || null,
    onChange: (d, str) => fpSampai.set('minDate', str || null)
  }));

  const lama = @json($lama);
  const bkkLama = @json($bkkLama);
  const modalEl = document.getElementById('modalJurnal');
  const form = document.getElementById('formJurnal');
  const nominal = document.getElementById('j-nominal');
  const item = document.getElementById('j-item');
  const info = document.getElementById('j-info');
  const angka = v => parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;
  const rupiah = n => (n < 0 ? '−' : '') + 'Rp ' + Math.abs(n).toLocaleString('id-ID');
  const teks = (id, v) => { document.getElementById(id).textContent = v; };
  let aktif = null, rinci = [];

  $('#formJurnal .sel2').each(function () {
    $(this).select2({ dropdownParent: $('#modalJurnal'), placeholder: $(this).data('placeholder'), allowClear: true, width: '100%' });
  });

  const maks = () => {
    if (!aktif) return 0;
    let m = +aktif.sisa;
    const it = rinci.find(r => String(r.id) === item.value);
    if (it) m = Math.min(m, it.biaya - it.realisasi);
    return Math.max(0, m);
  };
  const perbarui = () => {
    const m = maks(), n = angka(nominal.value);
    info.className = 'form-text ' + (n > m ? 'text-danger' : 'text-muted');
    info.textContent = n > m ? 'Melebihi batas maksimal ' + rupiah(m) + '.' : 'Maksimal ' + rupiah(m) + '.';
  };
  const isiNominal = n => { nominal.value = n > 0 ? n.toLocaleString('id-ID') : ''; perbarui(); };

  const buka = (btn, pertahankan) => {
    aktif = btn.dataset;
    rinci = JSON.parse(aktif.rinci || '[]');
    form.action = aktif.action;
    document.getElementById('j-bkk').value = aktif.id;
    teks('j-no', aktif.no); teks('j-penerima', aktif.penerima || '—');
    teks('j-nominal-bkk', rupiah(+aktif.nominal)); teks('j-realisasi', rupiah(+aktif.realisasi)); teks('j-sisa', rupiah(+aktif.sisa));

    item.innerHTML = '<option value="">— Tanpa item —</option>' + rinci.map(r =>
      '<option value="' + r.id + '">' + $('<div>').text(r.uraian).html() + ' — ' + rupiah(r.biaya)
      + (r.realisasi > 0 ? ' (terealisasi ' + rupiah(r.realisasi) + ')' : '') + '</option>').join('');

    if (pertahankan) {
      // Gagal validasi: isian lama dikembalikan.
      document.getElementById('j-tgl').value = lama.tgl_trk;
      document.getElementById('j-uraian').value = lama.uraian;
      item.value = lama.bkk_rinci_id;
      nominal.value = lama.nominal;
      ['debet', 'kredit', 'unit', 'mak', 'sumber'].forEach(k => {
        const key = { unit: 'id_uk', mak: 'id_mak', sumber: 'id_sumber' }[k] || k;
        $('#j-' + k).val(lama[key]).trigger('change');
      });
      perbarui();
    } else {
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
      document.getElementById('j-tgl').value = @json(today()->toDateString());
      document.getElementById('j-uraian').value = aktif.uraian;
      item.value = '';
      $('#j-debet, #j-kredit, #j-mak').val('').trigger('change');
      // Unit diisi otomatis bila kasir sudah mencatat unit pada BKK; sumber dana dipertahankan antar BKK.
      $('#j-unit').val(aktif.unit || '').trigger('change');
      isiNominal(+aktif.sisa);
    }
    document.getElementById('j-simpan').disabled = false;
  };

  document.querySelectorAll('.btn-jurnal').forEach(b => b.addEventListener('click', function () { buka(this, false); }));
  item.addEventListener('change', function () {
    const it = rinci.find(r => String(r.id) === this.value);
    isiNominal(it ? Math.min(+aktif.sisa, it.biaya - it.realisasi) : +aktif.sisa);
  });
  nominal.addEventListener('input', function () {
    const v = this.value.replace(/\D/g, '');
    this.value = v === '' ? '' : parseInt(v, 10).toLocaleString('id-ID');
    perbarui();
  });
  document.getElementById('j-maks').addEventListener('click', () => { isiNominal(maks()); nominal.focus(); });
  form.addEventListener('submit', () => { document.getElementById('j-simpan').disabled = true; });

  if (bkkLama) {
    const b = document.querySelector('.btn-jurnal[data-id="' + bkkLama + '"]');
    if (b) { buka(b, true); bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
  }

  document.querySelectorAll('.form-hapus').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    Swal.fire({
      icon: 'warning', title: 'Hapus BKK ini?', html: '<span class="font-monospace">' + $('<div>').text(f.dataset.no).html() + '</span><div class="small text-muted mt-2">BKK beserta rinciannya dihapus permanen.</div>',
      showCancelButton: true, cancelButtonText: 'Batal', confirmButtonText: 'Ya, hapus', focusCancel: true, buttonsStyling: false,
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' }
    }).then(r => { if (r.isConfirmed) f.submit(); });
  }));
});
</script>
@endsection
