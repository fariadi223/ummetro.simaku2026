@extends('layouts/layoutMaster')

@section('title', 'Pagu Anggaran')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .stat-pagu .card-body { padding: 1rem 1.25rem; }
  .stat-pagu .label { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary); }
  .stat-pagu .nilai { font-size: 1.15rem; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .pakai { min-width: 190px; }
  .pakai .progress { height: 6px; }
  .pagu-unit td.nama { white-space: normal; min-width: 200px; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
  // Warna bilah penggunaan: hijau < 90%, kuning 90-100%, merah > 100%.
  $warna = fn ($p) => $p > 100 ? 'danger' : ($p >= 90 ? 'warning' : 'success');
  $persen = fn ($pakai, $pagu) => $pagu > 0 ? $pakai / $pagu * 100 : ($pakai > 0 ? 999 : 0);
  $pakaiTotal = $persen($ringkasan['ajuan'], $ringkasan['pagu']);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-moneybag"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Pagu Anggaran T.A {{ $tahun }}/{{ $tahun + 1 }}</h4>
      <p class="pg-header-sub mb-0">Batas anggaran per sumber pagu dan pembagiannya ke unit kerja.</p>
    </div>
    <a href="{{ route('admin.periode.index') }}" class="pg-header-chip text-white text-decoration-none" title="Ganti tahun anggaran">
      <i class="ti ti-calendar-event"></i><span>Ganti T.A</span>
    </a>
  </div>
</div>

@include('_partials._flash')

@if ($janggal)
  <div class="alert alert-warning d-flex gap-2">
    <i class="ti ti-alert-triangle mt-1"></i>
    <div>{{ $janggal }} baris pagu unit T.A {{ $tahun }} menunjuk sumber pagu milik tahun lain (ditandai <i class="ti ti-alert-triangle text-warning"></i> di tabel).</div>
  </div>
@endif

{{-- Ringkasan (sama seperti tabel bawah halaman Pagu v1) --}}
<div class="row g-3 mb-4 stat-pagu">
  @php
    $kartu = [
      ['Total Pagu', $ringkasan['pagu'], 'ti-wallet', 'primary'],
      ['Total Pendapatan', $ringkasan['pendapatan'], 'ti-arrow-down-left', 'success'],
      ['Total Belanja', $ringkasan['belanja'], 'ti-arrow-up-right', 'danger'],
      ['Ajuan Mengurangi Pagu', $ringkasan['ajuan'], 'ti-receipt', 'warning'],
      ['Sisa Pagu', $ringkasan['sisa'], 'ti-pig-money', $ringkasan['sisa'] < 0 ? 'danger' : 'info'],
    ];
  @endphp
  @foreach ($kartu as [$label, $nilai, $ikon, $w])
    <div class="col-sm-6 col-xl">
      <div class="card h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="avatar flex-shrink-0"><span class="avatar-initial rounded bg-label-{{ $w }}"><i class="ti {{ $ikon }}"></i></span></span>
          <div style="min-width: 0;">
            <div class="label">{{ $label }}</div>
            <div class="nilai {{ $nilai < 0 ? 'text-danger' : '' }}">{{ $rp($nilai) }}</div>
          </div>
        </div>
      </div>
    </div>
  @endforeach
</div>

{{-- Sumber pagu --}}
<div class="card mb-4">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <div>
      <h5 class="mb-0">Sumber Pagu</h5>
      <small class="text-muted">Pagu terpakai {{ number_format(min($pakaiTotal, 999), 1, ',', '.') }}% (ajuan dibanding total pagu)</small>
    </div>
    @if ($kodeBerikut)
      <button type="button" class="btn btn-primary" id="btn-tambah-sumber"><i class="ti ti-plus me-1"></i> Tambah Sumber Pagu</button>
    @endif
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th style="width: 90px;">Kode</th>
          <th>Sumber Pagu</th>
          <th class="text-end">Nominal</th>
          <th class="text-end">Dibagi ke Unit</th>
          <th>Penggunaan</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($sumber as $s)
          @php
            $p = $persen($s->pakai, $s->jumlah);
          @endphp
          <tr>
            <td><span class="badge bg-label-primary font-monospace">{{ $s->id_pagu }}</span></td>
            <td class="fw-semibold">{{ $s->sumber }}</td>
            <td class="text-end rp">{{ $rp($s->jumlah) }}</td>
            <td class="text-end rp">
              {{ $rp($s->teralokasi) }}
              @if ($s->teralokasi > $s->jumlah)
                <i class="ti ti-alert-triangle text-danger ms-1" data-bs-toggle="tooltip" title="Pembagian ke unit melebihi nominal pagu"></i>
              @endif
            </td>
            <td class="pakai">
              <div class="d-flex justify-content-between small"><span class="rp">{{ $rp($s->pakai) }}</span><span>{{ number_format(min($p, 999), 0, ',', '.') }}%</span></div>
              <div class="progress"><div class="progress-bar bg-{{ $warna($p) }}" style="width: {{ min($p, 100) }}%"></div></div>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill btn-ubah-sumber" title="Ubah"
                      data-url="{{ route('admin.pagu.sumber.update', $s) }}" data-kode="{{ $s->id_pagu }}"
                      data-sumber="{{ $s->sumber }}" data-jumlah="{{ (int) $s->jumlah }}">
                <i class="ti ti-pencil"></i>
              </button>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">Belum ada sumber pagu untuk T.A {{ $tahun }}.</td></tr>
        @endforelse
      </tbody>
      @if ($sumber->count() > 1)
        <tfoot>
          <tr class="fw-semibold">
            <td colspan="2">Total</td>
            <td class="text-end rp">{{ $rp($ringkasan['pagu']) }}</td>
            <td class="text-end rp">{{ $rp($sumber->sum('teralokasi')) }}</td>
            <td class="rp">{{ $rp($sumber->sum('pakai')) }}</td>
            <td></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>

{{-- Pagu unit --}}
<div class="card">
  <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
    <div>
      <h5 class="mb-0">Pagu Unit Kerja</h5>
      <small class="text-muted">{{ $paguUnit->count() }} unit · penggunaan = belanja kegiatan unit yang memakai sumber pagu tsb.</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <div class="input-group input-group-merge" style="width: 240px;">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="search" id="pu-cari" class="form-control" placeholder="Cari unit...">
      </div>
      <select id="pu-filter" class="form-select" style="width: auto;">
        <option value="">Semua</option>
        <option value="lebih">Melebihi pagu</option>
        <option value="nol">Pagu Rp 0</option>
      </select>
      @if ($sumber->isNotEmpty())
        <button type="button" class="btn btn-label-primary" id="btn-tambah-unit"><i class="ti ti-plus me-1"></i> Tambah Unit</button>
      @endif
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover pagu-unit mb-0">
      <thead>
        <tr>
          <th style="width: 90px;">Kode</th>
          <th>Nama Unit</th>
          <th>Sumber</th>
          <th class="text-end">Pagu</th>
          <th>Penggunaan</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($paguUnit as $pu)
          @php
            $p = $persen($pu->pakai, $pu->nominal);
            $kondisi = $pu->pakai > $pu->nominal ? 'lebih' : ((int) $pu->nominal === 0 ? 'nol' : '');
          @endphp
          <tr class="baris-pu" data-cari="{{ mb_strtolower(($pu->unitKerja->kode ?? '') . ' ' . ($pu->unitKerja->nama ?? '')) }}" data-kondisi="{{ $kondisi }}">
            <td><span class="font-monospace">{{ $pu->unitKerja->kode ?? '?' }}</span></td>
            <td class="nama">{{ $pu->unitKerja->nama ?? 'Unit #' . $pu->idunit . ' (tidak ada)' }}</td>
            <td>
              <span class="badge bg-label-secondary font-monospace">{{ $pu->idpagu }}</span>
              @if ($pu->pagu && (string) $pu->pagu->tahun !== (string) $tahun)
                <i class="ti ti-alert-triangle text-warning" data-bs-toggle="tooltip" title="Sumber pagu {{ $pu->idpagu }} milik T.A {{ $pu->pagu->tahun }}"></i>
              @endif
            </td>
            <td class="text-end rp">{{ $rp($pu->nominal) }}</td>
            <td class="pakai">
              <div class="d-flex justify-content-between small">
                <span class="rp">{{ $rp($pu->pakai) }}</span>
                <span class="{{ $p > 100 ? 'text-danger fw-semibold' : '' }}">{{ $pu->nominal > 0 ? number_format($p, 0, ',', '.') . '%' : '—' }}</span>
              </div>
              <div class="progress"><div class="progress-bar bg-{{ $warna($p) }}" style="width: {{ min($p, 100) }}%"></div></div>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill btn-ubah-unit" title="Ubah nominal"
                      data-url="{{ route('admin.pagu.unit.update', $pu) }}"
                      data-label="{{ ($pu->unitKerja->kode ?? '') . ' - ' . ($pu->unitKerja->nama ?? '') }} ({{ $pu->idpagu }})"
                      data-nominal="{{ (int) $pu->nominal }}" data-pakai="{{ $pu->pakai }}">
                <i class="ti ti-pencil"></i>
              </button>
            </td>
          </tr>
        @endforeach
        <tr id="pu-kosong" class="{{ $paguUnit->isEmpty() ? '' : 'd-none' }}">
          <td colspan="6" class="text-center text-muted py-4">Tidak ada pagu unit yang cocok.</td>
        </tr>
      </tbody>
      @if ($paguUnit->isNotEmpty())
        <tfoot>
          <tr class="fw-semibold">
            <td colspan="3">Total</td>
            <td class="text-end rp">{{ $rp($paguUnit->sum('nominal')) }}</td>
            <td class="rp">{{ $rp($paguUnit->sum('pakai')) }}</td>
            <td></td>
          </tr>
        </tfoot>
      @endif
    </table>
  </div>
</div>

{{-- Modal sumber pagu --}}
<div class="modal fade" id="modal-sumber" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="{{ route('admin.pagu.sumber.store') }}" class="modal-content" id="form-sumber">
      @csrf
      <input type="hidden" name="_method" value="POST" id="form-sumber-method">
      <input type="hidden" name="_form" value="sumber">
      <input type="hidden" name="_form_action" id="form-sumber-action" value="{{ route('admin.pagu.sumber.store') }}">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-sumber-judul">Tambah Sumber Pagu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Kode / Tahun</label>
          <div class="form-control-plaintext font-monospace" id="sumber-kode">{{ $kodeBerikut }} · T.A {{ $tahun }}</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="f-sumber">Sumber Pagu</label>
          <input type="text" id="f-sumber" name="sumber" maxlength="50" class="form-control @error('sumber') is-invalid @enderror"
                 value="{{ old('sumber') }}" placeholder="Mis. SPP Mahasiswa" required>
          @error('sumber')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label" for="f-jumlah">Nominal Pagu</label>
          <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="text" inputmode="numeric" id="f-jumlah" name="jumlah" class="form-control text-end angka @error('jumlah') is-invalid @enderror"
                   value="{{ old('jumlah', 0) }}" required>
            @error('jumlah')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal ubah nominal pagu unit --}}
<div class="modal fade" id="modal-unit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="#" class="modal-content" id="form-unit">
      @csrf
      @method('PUT')
      <input type="hidden" name="_form" value="unit">
      <input type="hidden" name="_form_action" id="form-unit-action" value="{{ old('_form_action') }}">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Ubah Pagu Unit</h5>
          <small class="text-muted" id="unit-label"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="f-nominal">Nominal Pagu</label>
        <div class="input-group">
          <span class="input-group-text">Rp</span>
          <input type="text" inputmode="numeric" id="f-nominal" name="nominal" class="form-control text-end angka @error('nominal') is-invalid @enderror"
                 value="{{ old('nominal', 0) }}" required>
          @error('nominal')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted d-block mt-2" id="unit-pakai"></small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal tambah unit ke pagu --}}
<div class="modal fade" id="modal-tambah-unit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="{{ route('admin.pagu.unit.store') }}" class="modal-content">
      @csrf
      <input type="hidden" name="_form" value="tambah-unit">
      <div class="modal-header">
        <h5 class="modal-title">Tambah Unit ke Pagu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="t-idpagu">Sumber Pagu</label>
          <select id="t-idpagu" name="idpagu" class="form-select @error('idpagu') is-invalid @enderror">
            @foreach ($sumber as $s)
              <option value="{{ $s->id_pagu }}" @selected(old('idpagu') === $s->id_pagu)>{{ $s->id_pagu }} — {{ $s->sumber }}</option>
            @endforeach
          </select>
          @error('idpagu')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
          <label class="form-label" for="t-idunit">Unit</label>
          <select id="t-idunit" name="idunit" class="form-select @error('idunit') is-invalid @enderror">
            @foreach ($units as $u)
              <option value="{{ $u->id_unit }}" @selected((int) old('idunit') === $u->id_unit)>{{ $u->kode }} — {{ $u->nama }}</option>
            @endforeach
          </select>
          @error('idunit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          <small class="text-muted">Unit yang sudah memiliki pagu dari sumber terpilih tidak dapat dipilih.</small>
        </div>
        <div>
          <label class="form-label" for="t-nominal">Nominal Pagu</label>
          <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="text" inputmode="numeric" id="t-nominal" name="nominal" class="form-control text-end angka" value="{{ old('nominal', 0) }}" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Tambah</button>
      </div>
    </form>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const ribuan = n => (Number(String(n).replace(/\D/g, '')) || 0).toLocaleString('id-ID');
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
  document.querySelectorAll('input.angka').forEach(function (el) {
    el.value = ribuan(el.value);
    el.addEventListener('input', () => { el.value = ribuan(el.value); });
  });

  // ---- Cari & filter pagu unit ----------------------------------------------
  const cari = document.getElementById('pu-cari'), filter = document.getElementById('pu-filter');
  function saring() {
    const k = cari.value.trim().toLowerCase(); let ada = 0;
    document.querySelectorAll('tr.baris-pu').forEach(function (tr) {
      const tampil = (!k || tr.dataset.cari.includes(k)) && (!filter.value || tr.dataset.kondisi === filter.value);
      tr.classList.toggle('d-none', !tampil); if (tampil) ada++;
    });
    document.getElementById('pu-kosong').classList.toggle('d-none', ada > 0);
  }
  cari.addEventListener('input', saring); filter.addEventListener('change', saring);

  // ---- Sumber pagu ------------------------------------------------------------
  const mSumber = new bootstrap.Modal(document.getElementById('modal-sumber'));
  const fSumber = document.getElementById('form-sumber');
  const urlSumberBaru = @json(route('admin.pagu.sumber.store'));
  @php
    $labelBaru = ($kodeBerikut ?? '') . ' · T.A ' . $tahun;
  @endphp
  const labelBaru = @json($labelBaru);
  function bukaSumber(url, metode, judul, kode, data) {
    fSumber.action = url;
    document.getElementById('form-sumber-action').value = url;
    document.getElementById('form-sumber-method').value = metode;
    document.getElementById('modal-sumber-judul').textContent = judul;
    document.getElementById('sumber-kode').textContent = kode;
    if (data) {
      document.getElementById('f-sumber').value = data.sumber;
      document.getElementById('f-jumlah').value = ribuan(data.jumlah);
      fSumber.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }
    mSumber.show();
  }
  document.getElementById('btn-tambah-sumber')?.addEventListener('click', () =>
    bukaSumber(urlSumberBaru, 'POST', 'Tambah Sumber Pagu', labelBaru, { sumber: '', jumlah: 0 }));
  document.querySelectorAll('.btn-ubah-sumber').forEach(btn => btn.addEventListener('click', () =>
    bukaSumber(btn.dataset.url, 'PUT', 'Ubah Sumber Pagu', btn.dataset.kode + ' · T.A ' + @json($tahun), { sumber: btn.dataset.sumber, jumlah: btn.dataset.jumlah })));

  // ---- Ubah pagu unit -----------------------------------------------------------
  const mUnit = new bootstrap.Modal(document.getElementById('modal-unit'));
  const fUnit = document.getElementById('form-unit');
  function bukaUnit(url, label, nominal, pakai) {
    fUnit.action = url;
    document.getElementById('form-unit-action').value = url;
    document.getElementById('unit-label').textContent = label || '';
    if (nominal !== null) {
      document.getElementById('f-nominal').value = ribuan(nominal);
      fUnit.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }
    document.getElementById('unit-pakai').textContent = pakai ? 'Sudah dipakai belanja kegiatan: Rp ' + ribuan(pakai) : '';
    mUnit.show();
  }
  document.querySelectorAll('.btn-ubah-unit').forEach(btn => btn.addEventListener('click', () =>
    bukaUnit(btn.dataset.url, btn.dataset.label, btn.dataset.nominal, Number(btn.dataset.pakai))));

  // ---- Tambah unit: sembunyikan unit yang sudah punya pagu dari sumber terpilih ----
  const sudah = @json($sudah->keys());
  const $unit = $('#t-idunit'), selPagu = document.getElementById('t-idpagu');
  const mTambah = document.getElementById('modal-tambah-unit');
  function segarkanUnit() {
    $unit.find('option').each(function () {
      this.disabled = sudah.includes(this.value + '|' + selPagu.value);
    });
    if ($unit.find('option:selected').prop('disabled')) $unit.val($unit.find('option:not(:disabled)').first().val());
    $unit.trigger('change.select2');
  }
  if (mTambah) {
    const modalTambah = new bootstrap.Modal(mTambah);
    $unit.select2({ dropdownParent: $(mTambah), width: '100%' });
    selPagu.addEventListener('change', segarkanUnit);
    document.getElementById('btn-tambah-unit')?.addEventListener('click', () => { segarkanUnit(); modalTambah.show(); });
    @if ($errors->any() && old('_form') === 'tambah-unit')
      segarkanUnit(); modalTambah.show();
    @endif
  }

  // ---- Buka kembali modal yang gagal validasi -------------------------------------
  @if ($errors->any() && old('_form') === 'sumber')
    @php
      $aksiLama = (string) old('_form_action');
      $aksiLama = str_starts_with($aksiLama, url('/') . '/') ? $aksiLama : route('admin.pagu.sumber.store');
      $metodeLama = old('_method') === 'PUT' ? 'PUT' : 'POST';
      $judulLama = $metodeLama === 'PUT' ? 'Ubah Sumber Pagu' : 'Tambah Sumber Pagu';
    @endphp
    bukaSumber(@json($aksiLama), @json($metodeLama), @json($judulLama), '', null);
  @elseif ($errors->any() && old('_form') === 'unit' && str_starts_with((string) old('_form_action'), url('/') . '/'))
    bukaUnit(@json(old('_form_action')), '', null, 0);
  @endif
});
</script>
@endsection
