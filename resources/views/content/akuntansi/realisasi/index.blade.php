@extends('layouts/layoutMaster')

@section('title', 'Realisasi Anggaran')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .real-tabel td { vertical-align: middle; font-size: .8125rem; }
  .real-tabel .rp { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
  .real-tabel .nomor { font-family: var(--bs-font-monospace); font-size: .75rem; white-space: nowrap; }
  .real-tabel tr.awal-spp > td { border-top: 2px solid var(--bs-border-color); }
  .ringkas-jurnal dt { font-weight: 500; color: var(--bs-secondary-color); font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; }
  .ringkas-jurnal dd { margin-bottom: .5rem; }
  .ringkas-jurnal .angka { font-variant-numeric: tabular-nums; font-weight: 600; }
  .rupiah { text-align: right; font-variant-numeric: tabular-nums; }
  .filter-real .form-label { font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; margin-bottom: .25rem; }
</style>
@endsection

@php
  $rp = fn ($n) => ($n < 0 ? '−' : '') . number_format(abs((float) $n), 0, ',', '.');
  $kembali = array_filter(array_merge($f, ['page' => $daftar->currentPage() > 1 ? $daftar->currentPage() : '']), fn ($v) => $v !== '');
  $barisLama = (string) old('_baris', '');
  $kasLama = (string) old('kas', 'unit');
  $nominalLama = old('nominal');
  $nominalLama = ($nominalLama === null || $nominalLama === '') ? '' : number_format((int) preg_replace('/\D/', '', (string) $nominalLama), 0, ',', '.');
  $saldoUnivInt = (int) floor($saldoUniv);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-chart-bar"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Realisasi Anggaran</h4>
      <p class="pg-header-sub mb-0">Jurnal realisasi per rinci anggaran dari SPM yang sudah dicairkan · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-list-details"></i><span><strong>{{ number_format($daftar->total(), 0, ',', '.') }}</strong> rinci</span></span>
    <span class="pg-header-chip"><i class="ti ti-file-invoice"></i><span><strong>{{ $opsiSpm->count() }}</strong> SPM belum dijurnal</span></span>
  </div>
</div>

@include('_partials._flash')

@unless ($terbuka)
  @include('_partials._periode-terkunci', ['apa' => 'jurnal realisasi'])
@endunless

<div class="card">
  <div class="card-header border-bottom">
    <form method="GET" action="{{ route('akuntansi.realisasi.index') }}" class="row g-3 align-items-end filter-real" id="formFilter">
      <div class="col-md-4">
        <label class="form-label" for="f-unit">Unit</label>
        <select id="f-unit" name="unit" class="form-select">
          <option value="">Semua unit</option>
          @foreach ($unitInduk as $ff => $u)
            <option value="{{ $ff }}" @selected($f['unit'] === (string) $ff)>{{ $u->kode }} — {{ $u->nama }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="f-spm">No. SPM belum dijurnal</label>
        <select id="f-spm" name="spm" class="form-select">
          <option value="">Semua SPM</option>
          @foreach ($opsiSpm as $n)
            <option value="{{ $n }}" @selected($f['spm'] === $n)>{{ $n }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="f-spp">No. SPP belum dijurnal</label>
        <select id="f-spp" name="spp" class="form-select">
          <option value="">Semua SPP</option>
          @foreach ($opsiSpp as $n)
            <option value="{{ $n }}" @selected($f['spp'] === $n)>{{ $n }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1"><i class="ti ti-filter ti-xs me-1"></i>Tampilkan</button>
        @if (array_filter($f, fn ($v) => $v !== ''))
          <a href="{{ route('akuntansi.realisasi.index') }}" class="btn btn-label-secondary btn-icon" title="Reset filter"><i class="ti ti-x"></i></a>
        @endif
      </div>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-sm real-tabel mb-0">
      <thead>
        <tr><th style="width: 40px;">No</th><th>No. SPM</th><th>MAK</th><th>Kegiatan</th><th>No. Rek</th><th>Rekening / Uraian</th>
          <th class="text-end">Anggaran</th><th class="text-end">Realisasi</th><th class="text-end">Sisa</th><th>Unit Kerja</th>
          <th class="text-end" style="width: 110px;">Menu</th></tr>
      </thead>
      <tbody>
        @forelse ($daftar as $r)
          @php
            $baru = $loop->first || $daftar[$loop->index - 1]->id_spp !== $r->id_spp;
          @endphp
          <tr @class(['awal-spp' => $baru && !$loop->first])>
            <td class="text-muted">{{ $daftar->firstItem() + $loop->index }}</td>
            <td class="nomor">{{ $r->no_spm }}@if ($baru)<div class="text-muted">{{ $r->no_spp }}</div>@endif</td>
            <td class="nomor">{{ $r->kode_mak }}</td>
            <td>{{ $r->namamak4 }}</td>
            <td class="nomor">{{ $r->kode_rek }}</td>
            <td>{{ $r->namarek6 }}<div class="small text-muted">{{ $r->uraianrinci }}</div></td>
            <td class="rp">{{ $rp($r->anggaran) }}</td>
            <td class="rp {{ $r->realisasi > 0 ? 'fw-semibold' : 'text-muted' }}">{{ $rp($r->realisasi) }}</td>
            <td class="rp {{ $r->sisa < 0 ? 'text-danger' : ($r->sisa == 0 ? 'text-success' : '') }}">{{ $rp($r->sisa) }}</td>
            <td class="small">{{ $r->induk->nama ?? $r->nama_unit }}</td>
            <td class="text-end">
              @if ($r->bisa && !$terbuka)
                {{-- Periode tidak terbuka untuk penjurnalan: modal jurnal tidak bisa dibuka. --}}
                <span class="d-inline-block" tabindex="0" title="Penjurnalan T.A {{ $tahun }}/{{ $tahun + 1 }} ditutup">
                  <button type="button" class="btn btn-sm btn-label-secondary" disabled><i class="ti ti-lock ti-xs me-1"></i>Jurnal</button>
                </span>
              @elseif ($r->bisa)
                <button type="button" class="btn btn-sm btn-primary btn-jurnal" data-bs-toggle="modal" data-bs-target="#modalJurnal"
                  data-action="{{ route('akuntansi.realisasi.store', ['spp' => $r->id_spp, 'rinci' => $r->idrinci]) }}"
                  data-baris="{{ $r->id_spp }}-{{ $r->idrinci }}" data-spm="{{ $r->no_spm }}" data-spp="{{ $r->no_spp }}"
                  data-kegiatan="{{ $r->kode_mak }} {{ $r->namamak4 }}" data-rek="{{ $r->kode_rek }} {{ $r->namarek6 }}"
                  data-uraian="{{ $r->uraianrinci }}" data-unit="{{ $r->induk->nama }}" data-belanja="{{ $r->koderek1 === '5' ? 1 : 0 }}"
                  data-anggaran="{{ (int) round($r->anggaran) }}" data-realisasi="{{ (int) round($r->realisasi) }}"
                  data-sisa="{{ (int) floor($r->sisa) }}" data-saldo-unit="{{ (int) floor($saldoUnit[(int) $r->ff] ?? 0) }}">
                  <i class="ti ti-notebook ti-xs me-1"></i>Jurnal</button>
              @elseif ($r->sisa <= 0)
                <span class="badge bg-label-success">Terealisasi</span>
              @else
                <span class="badge bg-label-secondary">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="11" class="text-center text-muted py-5">Tidak ada data realisasi untuk filter ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($daftar->hasPages())
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
      <small class="text-muted">Menampilkan {{ $daftar->firstItem() }}–{{ $daftar->lastItem() }} dari {{ $daftar->total() }} rinci</small>
      {{ $daftar->onEachSide(1)->links('_partials.pagination') }}
    </div>
  @endif
</div>
<div class="modal fade" id="modalJurnal" tabindex="-1" aria-labelledby="modalJurnalJudul" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <form method="POST" action="" class="modal-content" id="formJurnal" novalidate>
      @csrf
      <input type="hidden" name="_baris" id="j-baris" value="{{ $barisLama }}">
      @foreach ($kembali as $k => $v)
        <input type="hidden" name="f_{{ $k }}" value="{{ $v }}">
      @endforeach
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalJurnalJudul">Jurnal Realisasi Anggaran</h5>
          <small class="text-muted font-monospace" id="j-nomor"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-5">
            <dl class="ringkas-jurnal mb-0">
              <dt>Unit</dt><dd id="j-unit"></dd>
              <dt>Kegiatan</dt><dd id="j-kegiatan"></dd>
              <dt>Rekening</dt><dd><span id="j-rek"></span> <span class="badge ms-1" id="j-jenis"></span></dd>
              <dt>Uraian</dt><dd id="j-uraian"></dd>
              <dt>Anggaran</dt><dd class="angka" id="j-anggaran"></dd>
              <dt>Sudah direalisasi</dt><dd class="angka" id="j-realisasi"></dd>
              <dt>Sisa anggaran</dt><dd class="angka fs-5 mb-0" id="j-sisa"></dd>
            </dl>
          </div>
          <div class="col-md-7">
            <div class="mb-3">
              <label class="form-label fw-semibold">Sumber Kas <span class="text-danger">*</span></label>
              <div class="row g-2">
                @foreach (['unit' => 'Kas di Unit Kerja', 'univ' => 'Kas Universitas'] as $k => $label)
                  <div class="col-6">
                    <div class="form-check custom-option custom-option-basic h-100">
                      <label class="form-check-label custom-option-content" for="kas-{{ $k }}">
                        <input class="form-check-input" type="radio" name="kas" value="{{ $k }}" id="kas-{{ $k }}" @checked($kasLama === $k)>
                        <span class="custom-option-header"><span class="h6 mb-0">{{ $label }}</span></span>
                        <span class="custom-option-body"><small class="text-muted" id="saldo-{{ $k }}"></small></span>
                      </label>
                    </div>
                  </div>
                @endforeach
              </div>
              @error('kas')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3">
              <div class="col-sm-7">
                <label class="form-label fw-semibold" for="j-bukti">No. Bukti / No. Kuitansi <span class="text-danger">*</span></label>
                <input type="text" id="j-bukti" name="bukti" value="{{ old('bukti') }}" maxlength="100" required
                  class="form-control @error('bukti') is-invalid @enderror">
                @error('bukti')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-sm-5">
                <label class="form-label fw-semibold" for="j-tgl">Tanggal <span class="text-danger">*</span></label>
                <input type="date" id="j-tgl" name="tgl_trk" value="{{ old('tgl_trk', today()->toDateString()) }}" required
                  class="form-control @error('tgl_trk') is-invalid @enderror">
                @error('tgl_trk')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold" for="j-nominal">Besar Uang <span class="text-danger">*</span></label>
                <div class="input-group has-validation">
                  <span class="input-group-text">Rp</span>
                  <input type="text" id="j-nominal" name="nominal" value="{{ $nominalLama }}" inputmode="numeric" required
                    class="form-control rupiah @error('nominal') is-invalid @enderror">
                  <button type="button" class="btn btn-outline-primary" id="j-maks">Isi maksimal</button>
                  @error('nominal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text" id="j-info"></div>
              </div>
            </div>
            <div class="small text-muted mt-3" id="j-pola"></div>
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
    $('#f-unit, #f-spm, #f-spp').select2({ width: '100%' });

    // Modal jurnal: satu modal, diisi dari data-* tombol baris.
    const saldoUniv = @json($saldoUnivInt);
    const barisLama = @json($barisLama);
    const modalEl = document.getElementById('modalJurnal');
    const form = document.getElementById('formJurnal');
    const nominal = document.getElementById('j-nominal');
    const info = document.getElementById('j-info');
    const angka = v => parseInt(String(v || '').replace(/\D/g, ''), 10) || 0;
    const rupiah = n => (n < 0 ? '−' : '') + 'Rp ' + Math.abs(n).toLocaleString('id-ID');
    const teks = (id, v) => { document.getElementById(id).textContent = v; };
    let aktif = null;

    const kas = () => (form.querySelector('input[name=kas]:checked') || {}).value || 'unit';
    const maks = () => {
      if (!aktif) return 0;
      const sisa = +aktif.sisa;
      if (aktif.belanja !== '1') return Math.max(0, sisa);
      return Math.max(0, Math.min(sisa, kas() === 'univ' ? saldoUniv : +aktif.saldoUnit));
    };
    const perbarui = () => {
      const m = maks(), n = angka(nominal.value);
      info.className = 'form-text ' + (n > m ? 'text-danger' : 'text-muted');
      info.textContent = n > m ? 'Melebihi batas maksimal ' + rupiah(m) + '.' : 'Maksimal ' + rupiah(m) + '.';
      form.querySelectorAll('.custom-option').forEach(o => o.classList.toggle('checked', o.querySelector('input').checked));
    };
    const isi = (btn, pertahankan) => {
      aktif = btn.dataset;
      form.action = aktif.action;
      document.getElementById('j-baris').value = aktif.baris;
      teks('j-nomor', aktif.spm + ' · ' + aktif.spp);
      teks('j-unit', aktif.unit); teks('j-kegiatan', aktif.kegiatan); teks('j-rek', aktif.rek); teks('j-uraian', aktif.uraian);
      teks('j-anggaran', rupiah(+aktif.anggaran)); teks('j-realisasi', rupiah(+aktif.realisasi)); teks('j-sisa', rupiah(+aktif.sisa));
      const jenis = document.getElementById('j-jenis');
      jenis.textContent = aktif.belanja === '1' ? 'Belanja' : 'Pendapatan';
      jenis.className = 'badge ms-1 ' + (aktif.belanja === '1' ? 'bg-label-warning' : 'bg-label-info');
      teks('saldo-unit', 'Saldo ' + rupiah(+aktif.saldoUnit)); teks('saldo-univ', 'Saldo ' + rupiah(saldoUniv));
      teks('j-pola', aktif.belanja === '1' ? 'Jurnal: debet rekening belanja, kredit kas yang dipilih.' : 'Jurnal: debet kas yang dipilih, kredit rekening pendapatan.');
      if (!pertahankan) {
        // Baris lain: bersihkan isian & pesan error sebelumnya.
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback, .text-danger.small').forEach(el => el.remove());
        document.getElementById('j-bukti').value = ''; nominal.value = '';
        document.getElementById('kas-unit').checked = true;
      }
      document.getElementById('j-simpan').disabled = false;
      perbarui();
    };

    document.querySelectorAll('.btn-jurnal').forEach(b => b.addEventListener('click', function () {
      isi(this, this.dataset.baris === document.getElementById('j-baris').value && barisLama !== '');
    }));
    modalEl.addEventListener('shown.bs.modal', () => document.getElementById('j-bukti').focus());
    nominal.addEventListener('input', function () {
      const v = this.value.replace(/\D/g, '');
      this.value = v === '' ? '' : parseInt(v, 10).toLocaleString('id-ID');
      perbarui();
    });
    form.querySelectorAll('input[name=kas]').forEach(el => el.addEventListener('change', perbarui));
    document.getElementById('j-maks').addEventListener('click', () => { nominal.value = maks().toLocaleString('id-ID'); perbarui(); nominal.focus(); });
    form.addEventListener('submit', () => { document.getElementById('j-simpan').disabled = true; });

    // Gagal validasi: buka lagi modal untuk baris yang sama dengan isian & pesan error.
    if (barisLama) {
      const b = document.querySelector('.btn-jurnal[data-baris="' + barisLama + '"]');
      if (b) { isi(b, true); bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
    }
    // Ganti unit: muat ulang agar pilihan SPM/SPP mengikuti unit tersebut.
    $('#f-unit').on('change', function () {
      $('#f-spm, #f-spp').val('');
      document.getElementById('formFilter').submit();
    });
  });
</script>
@endsection
