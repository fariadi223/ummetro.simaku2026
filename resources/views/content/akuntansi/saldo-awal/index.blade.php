@extends('layouts/layoutMaster')

@section('title', 'Saldo Awal')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .saldo-wrap { max-height: 70vh; overflow: auto; }
  .saldo-awal { border-collapse: separate; border-spacing: 0; font-size: .78rem; margin: 0; }
  .saldo-awal th, .saldo-awal td { white-space: nowrap; padding: .3rem .55rem; border-bottom: 1px solid var(--bs-border-color); }
  /* Latar sel solid (warna kartu). Bootstrap mengisi sel .table dengan --bs-table-bg: transparent, jadi ditimpa
     dengan selector ber-id agar kolom sticky tidak tembus saat tabel digeser. */
  #tabelSaldo { --bs-table-bg: var(--bs-card-bg, #fff); }
  #tabelSaldo > :not(caption) > * > * { background-color: var(--bs-card-bg, #fff); }
  .saldo-awal thead th { position: sticky; top: 0; z-index: 3; text-align: center; font-size: .72rem; text-transform: uppercase; letter-spacing: .02em; }
  /* Tinggi header pasti: baris 1 = 32px, baris 2 = 30px; Kode & Nama (rowspan 2) = 62px, teks di tengah. */
  .saldo-awal thead th { box-sizing: border-box; line-height: 1.2; vertical-align: middle; padding-top: 0; padding-bottom: 0; }
  .saldo-awal thead tr:first-child th { height: 32px; }
  .saldo-awal thead tr:nth-child(2) th { height: 30px; top: 32px; }
  .saldo-awal thead th[rowspan="2"] { height: 62px; white-space: nowrap; }
  /* Kolom tetap (tidak ikut bergeser): kode & nama di kiri, konsolidasi D & K di ujung kanan — lebar tetap agar posisi pas. */
  .saldo-awal .c-kode { position: sticky; left: 0; z-index: 2; width: 110px; min-width: 110px; max-width: 110px; font-family: var(--bs-font-monospace); white-space: normal; }
  .saldo-awal .c-nama { position: sticky; left: 110px; z-index: 2; width: 300px; min-width: 300px; max-width: 300px; white-space: normal; }
  .saldo-awal .c-kd { position: sticky; right: 125px; z-index: 2; width: 125px; min-width: 125px; max-width: 125px; border-left: 2px solid var(--bs-primary); }
  .saldo-awal .c-kk { position: sticky; right: 0; z-index: 2; width: 125px; min-width: 125px; max-width: 125px; }
  .saldo-awal th.c-kons { position: sticky; right: 0; z-index: 4; border-left: 2px solid var(--bs-primary); }
  .saldo-awal thead .c-kode, .saldo-awal thead .c-nama, .saldo-awal thead .c-kd, .saldo-awal thead .c-kk { z-index: 4; }
  /* Warna latar sel tetap memakai bayangan dalam agar tetap solid (tidak tembus saat digeser). */
  .saldo-awal .c-kd, .saldo-awal .c-kk, .saldo-awal th.c-kons { box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .06); }
  .saldo-awal tbody td:nth-child(n+3), .saldo-awal tfoot td:nth-child(n+3) { text-align: right; font-variant-numeric: tabular-nums; min-width: 105px; }
  .saldo-awal tbody td:nth-child(2n+3), .saldo-awal tfoot td:nth-child(2n+3), .saldo-awal .g-sumber { border-left: 1px solid var(--bs-border-color); }
  .saldo-awal td.m { color: var(--bs-danger); }
  .saldo-awal tr.lv1 td { font-weight: 700; font-size: .85rem; box-shadow: inset 0 0 0 999px rgba(var(--bs-secondary-rgb), .1); }
  .saldo-awal tr.lv2 td { font-weight: 700; }
  .saldo-awal tr.lv3 td { font-weight: 600; box-shadow: inset 0 0 0 999px rgba(var(--bs-secondary-rgb), .05); cursor: pointer; }
  .saldo-awal tr.lv1 td.c-kd, .saldo-awal tr.lv1 td.c-kk, .saldo-awal tr.lv3 td.c-kd, .saldo-awal tr.lv3 td.c-kk { box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .1); }
  .saldo-awal tr.lv3 .c-kode { white-space: nowrap; }
  .saldo-awal tr.lv3 .lipat { transition: transform .15s; display: inline-block; vertical-align: -1px; margin-right: .2rem; }
  .saldo-awal tr.lv3.tertutup .lipat { transform: rotate(-90deg); }
  .saldo-awal tr.lv6 .c-nama { padding-left: 1.6rem; }
  .saldo-awal td.isi-saldo { cursor: pointer; }
  .saldo-awal td.isi-saldo:hover { outline: 1px dashed var(--bs-primary); outline-offset: -2px; }
  .saldo-awal td.isi-saldo .ti { font-size: .75rem; color: var(--bs-primary); opacity: 0; float: left; margin-top: 2px; }
  .saldo-awal td.isi-saldo:hover .ti { opacity: 1; }
  .saldo-awal tfoot td { position: sticky; bottom: 0; z-index: 3; font-weight: 700; border-top: 2px solid var(--bs-border-color); }
  .saldo-awal tfoot .c-kode, .saldo-awal tfoot .c-nama, .saldo-awal tfoot .c-kd, .saldo-awal tfoot .c-kk { z-index: 4; }
  .filter-saldo .form-check { margin-bottom: 0; }
  .rupiah { text-align: right; font-variant-numeric: tabular-nums; }
</style>
@endsection

@php
  $rp = fn ($n) => abs($n) < 0.5 ? '0' : (($n < 0 ? '−' : '') . number_format(abs((float) $n), 0, ',', '.'));
  // Sel angka ringkas (tabel bisa ribuan baris × puluhan kolom): nol = sel kosong, negatif = class "m".
  $sel = fn ($n) => abs($n) < 0.5 ? '<td></td>' : ('<td' . ($n < 0 ? ' class="m"' : '') . '>' . ($n < 0 ? '−' : '') . number_format(abs((float) $n), 0, ',', '.') . '</td>');
  $periode = \Illuminate\Support\Carbon::create($tahun, 9, 1)->locale('id')->translatedFormat('d F Y');
  $selisih = $total['d'] - $total['k'];
  $filterQ = array_filter(['saldo' => $semua ? 'all' : null, 'isi' => $hanyaIsi ? '1' : null]);
  $nominalLama = old('nominal');
  $nominalLama = ($nominalLama === null || $nominalLama === '') ? '' : number_format((int) preg_replace('/\D/', '', (string) $nominalLama), 0, ',', '.');
  $lama = ['idrek6' => (string) old('idrek6', ''), 'pencatatan' => (string) old('pencatatan', 'D'), 'nominal' => $nominalLama, 'id_sumber' => (string) old('id_sumber', '')];
  $adaError = $errors->any();
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-scale"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Saldo Awal</h4>
      <p class="pg-header-sub mb-0">Periode {{ $periode }} · per sumber dana &amp; konsolidasi</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-cash"></i><span><strong>{{ $sumber->count() }}</strong> sumber dana</span></span>
    <span class="pg-header-chip"><i class="ti ti-arrow-down-right"></i><span>Total D <strong>Rp {{ $rp($total['d']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-arrow-up-right"></i><span>Total K <strong>Rp {{ $rp($total['k']) }}</strong></span></span>
    @if (abs($selisih) >= 0.5)
      <span class="pg-header-chip text-warning"><i class="ti ti-alert-triangle"></i><span>Selisih D − K <strong>Rp {{ $rp($selisih) }}</strong></span></span>
    @endif
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header border-bottom">
    <form method="GET" action="{{ route('akuntansi.saldo-awal.index') }}" class="d-flex flex-wrap align-items-center gap-3 filter-saldo" id="formFilter">
      <div class="d-flex align-items-center gap-2">
        <label for="f-tahun" class="form-label mb-0 fw-semibold">T.A</label>
        <select id="f-tahun" name="tahun" class="form-select form-select-sm" style="width: 110px;" onchange="this.form.submit()">
          @foreach ($pilihanTahun as $t)<option value="{{ $t }}" @selected((int) $t === $tahun)>{{ $t }}</option>@endforeach
        </select>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="f-semua" name="saldo" value="all" @checked($semua) onchange="this.form.submit()">
        <label class="form-check-label" for="f-semua">Tampilkan pendapatan &amp; biaya (rek 4, 5)</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="f-isi" name="isi" value="1" @checked($hanyaIsi) onchange="this.form.submit()">
        <label class="form-check-label" for="f-isi">Hanya akun bersaldo</label>
      </div>
      <div class="ms-auto d-flex gap-2">
        <button type="button" class="btn btn-sm btn-label-secondary" id="lipatSemua"><i class="ti ti-fold ti-xs me-1"></i>Lipat semua</button>
        <button type="button" class="btn btn-sm btn-label-secondary" id="bukaSemua"><i class="ti ti-fold-down ti-xs me-1"></i>Buka semua</button>
      </div>
    </form>
    <small class="text-muted d-block mt-2">
      @if ($bisaInput)
        Klik sel D/K konsolidasi pada akun untuk menambah saldo awal. Kelompok level 3 tertutup — klik barisnya untuk membuka.
      @else
        T.A {{ $tahun }} hanya bisa dilihat — input saldo awal hanya untuk T.A aktif ({{ $aktif }}).
      @endif
    </small>
  </div>
  <div class="saldo-wrap">
    <table class="table saldo-awal" id="tabelSaldo">
      <thead>
        <tr>
          <th class="c-kode" rowspan="2">Kode Akun</th>
          <th class="c-nama" rowspan="2">Nama Akun</th>
          @foreach ($sumber as $s)<th colspan="2" class="g-sumber" title="{{ $s->kode }}">{{ $s->nama_penerimaan }}</th>@endforeach
          <th colspan="2" class="c-kons">Konsolidasi</th>
        </tr>
        <tr>
          @foreach ($sumber as $s)<th class="g-sumber">D</th><th>K</th>@endforeach
          <th class="c-kd">D</th><th class="c-kk">K</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($baris as $b)
          @if ($b->lv === 1 || $b->lv === 2)
            <tr class="lv{{ $b->lv }}">
              <td class="c-kode">{{ $b->kode }}</td>
              <td class="c-nama">{{ $b->nama }}</td>
              @if ($sumber->isNotEmpty())<td colspan="{{ $sumber->count() * 2 }}"></td>@endif
              <td class="c-kd"></td><td class="c-kk"></td>
            </tr>
          @elseif ($b->lv === 3)
            <tr class="lv3 tertutup" data-grup="{{ $b->id }}">
              <td class="c-kode"><i class="ti ti-chevron-down lipat ti-xs"></i> {{ $b->kode }}</td>
              <td class="c-nama">{{ $b->nama }}</td>
              @foreach ($b->nilai['s'] as [$d, $k]){!! $sel($d) !!}{!! $sel($k) !!}@endforeach
              <td class="c-kd{{ $b->nilai['d'] < 0 ? ' m' : '' }}">{{ $rp($b->nilai['d']) }}</td>
              <td class="c-kk{{ $b->nilai['k'] < 0 ? ' m' : '' }}">{{ $rp($b->nilai['k']) }}</td>
            </tr>
          @else
            <tr class="lv6 d-none" data-induk="{{ $b->induk }}" data-id="{{ $b->id }}" data-nama="{{ $b->kode }} · {{ $b->nama }}">
              <td class="c-kode">{{ $b->kode }}</td>
              <td class="c-nama">{{ $b->nama }}</td>
              @foreach ($b->nilai['s'] as [$d, $k]){!! $sel($d) !!}{!! $sel($k) !!}@endforeach
              @foreach (['D' => 'd', 'K' => 'k'] as $pos => $key)
                <td class="{{ $pos === 'D' ? 'c-kd' : 'c-kk' }}{{ $b->nilai[$key] < 0 ? ' m' : '' }}{{ $bisaInput ? ' isi-saldo' : '' }}"@if ($bisaInput) data-pos="{{ $pos }}"@endif>@if ($bisaInput)<i class="ti ti-plus"></i>@endif{{ $rp($b->nilai[$key]) }}</td>
              @endforeach
            </tr>
          @endif
        @empty
          <tr><td class="c-kode"></td><td class="c-nama text-muted py-4">Tidak ada akun{{ $hanyaIsi ? ' bersaldo' : '' }}.</td>@if ($sumber->isNotEmpty())<td colspan="{{ $sumber->count() * 2 }}"></td>@endif<td class="c-kd"></td><td class="c-kk"></td></tr>
        @endforelse
      </tbody>
      <tfoot>
        <tr>
          <td class="c-kode"></td>
          <td class="c-nama">TOTAL</td>
          @foreach ($total['s'] as [$d, $k])<td>{{ $rp($d) }}</td><td>{{ $rp($k) }}</td>@endforeach
          <td class="c-kd">{{ $rp($total['d']) }}</td><td class="c-kk">{{ $rp($total['k']) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

@if ($bisaInput)
<div class="modal fade" id="modalSaldo" tabindex="-1" aria-labelledby="modalSaldoJudul" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="{{ route('akuntansi.saldo-awal.store') }}" class="modal-content" id="formSaldo" novalidate>
      @csrf
      <input type="hidden" name="idrek6" id="s-rek" value="{{ $lama['idrek6'] }}">
      @if ($semua)<input type="hidden" name="f_saldo" value="all">@endif
      @if ($hanyaIsi)<input type="hidden" name="f_isi" value="1">@endif
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalSaldoJudul">Tambah Saldo Awal</h5>
          <small class="text-muted" id="s-nama"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        @error('idrek6')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
        <div class="mb-3">
          <label class="form-label fw-semibold d-block">Posisi <span class="text-danger">*</span></label>
          <div class="btn-group w-100" role="group" aria-label="Posisi saldo">
            <input type="radio" class="btn-check" name="pencatatan" id="pos-d" value="D" @checked($lama['pencatatan'] === 'D')>
            <label class="btn btn-outline-primary" for="pos-d">Debet</label>
            <input type="radio" class="btn-check" name="pencatatan" id="pos-k" value="K" @checked($lama['pencatatan'] === 'K')>
            <label class="btn btn-outline-primary" for="pos-k">Kredit</label>
          </div>
          @error('pencatatan')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="s-nominal">Nominal <span class="text-danger">*</span></label>
          <div class="input-group has-validation">
            <span class="input-group-text">Rp</span>
            <input type="text" id="s-nominal" name="nominal" value="{{ $lama['nominal'] }}" inputmode="numeric" required
              class="form-control rupiah @error('nominal') is-invalid @enderror">
            @error('nominal')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <div>
          <label class="form-label fw-semibold" for="s-sumber">Sumber Dana <span class="text-danger">*</span></label>
          <select id="s-sumber" name="id_sumber" class="form-select @error('id_sumber') is-invalid @enderror" data-placeholder="Pilih sumber dana">
            <option value=""></option>
            @foreach ($semuaSumber as $s)<option value="{{ $s->id }}" @selected($lama['id_sumber'] === (string) $s->id)>{{ $s->kode }} · {{ $s->nama_penerimaan }}</option>@endforeach
          </select>
          @error('id_sumber')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="small text-muted mt-3">Dicatat sebagai "SALDO AWAL 01-09-{{ $tahun }}" pada T.A {{ $tahun }}.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary" id="s-simpan"><i class="ti ti-device-floppy me-1"></i>Simpan</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const tabel = document.getElementById('tabelSaldo');

  // Lipat / buka kelompok level 3.
  const lipat = (tr, tutup) => {
    tr.classList.toggle('tertutup', tutup);
    tabel.querySelectorAll('tr.lv6[data-induk="' + tr.dataset.grup + '"]').forEach(r => r.classList.toggle('d-none', tutup));
  };
  tabel.querySelectorAll('tr.lv3').forEach(tr => tr.addEventListener('click', () => lipat(tr, !tr.classList.contains('tertutup'))));
  document.getElementById('lipatSemua').addEventListener('click', () => tabel.querySelectorAll('tr.lv3').forEach(tr => lipat(tr, true)));
  document.getElementById('bukaSemua').addEventListener('click', () => tabel.querySelectorAll('tr.lv3').forEach(tr => lipat(tr, false)));

  const modalEl = document.getElementById('modalSaldo');
  if (!modalEl) return;
  const form = document.getElementById('formSaldo');
  const nominal = document.getElementById('s-nominal');
  const lama = @json($lama);
  const adaError = @json($adaError);
  $('#s-sumber').select2({ dropdownParent: $('#modalSaldo'), placeholder: 'Pilih sumber dana', allowClear: true, width: '100%' });

  const buka = (td, pertahankan) => {
    const tr = td.closest('tr');
    document.getElementById('s-rek').value = tr.dataset.id;
    document.getElementById('s-nama').textContent = tr.dataset.nama;
    if (!pertahankan) {
      form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      form.querySelectorAll('.invalid-feedback, .text-danger.small, .alert-danger').forEach(el => el.remove());
      document.getElementById(td.dataset.pos === 'K' ? 'pos-k' : 'pos-d').checked = true;
      nominal.value = '';
    }
    document.getElementById('s-simpan').disabled = false;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };
  tabel.addEventListener('click', function (e) {
    const td = e.target.closest('td.isi-saldo');
    if (td) buka(td, false);
  });
  modalEl.addEventListener('shown.bs.modal', () => nominal.focus());
  nominal.addEventListener('input', function () {
    const v = this.value.replace(/\D/g, '');
    this.value = v === '' ? '' : parseInt(v, 10).toLocaleString('id-ID');
  });
  form.addEventListener('submit', () => { document.getElementById('s-simpan').disabled = true; });

  // Gagal validasi: buka lagi modal untuk akun yang sama.
  if (adaError && lama.idrek6) {
    const td = tabel.querySelector('tr.lv6[data-id="' + lama.idrek6 + '"] td.isi-saldo[data-pos="' + (lama.pencatatan === 'K' ? 'K' : 'D') + '"]');
    if (td) {
      const grup = tabel.querySelector('tr.lv3[data-grup="' + td.closest('tr').dataset.induk + '"]');
      if (grup) lipat(grup, false);
      buka(td, true);
    }
  }
});
</script>
@endsection
