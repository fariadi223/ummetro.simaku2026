{{-- Kertas Kerja > Pemetaan Unit: unit kerja -> prodi (ueu_map_unit_prodi), tambah/ubah lewat modal, hapus dengan konfirmasi. --}}
@extends('layouts/layoutMaster')

@section('title', 'Pemetaan Unit')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@php
  $rp = fn ($n) => \App\Services\LaporanUnit::rp($n);
  $belumProdi = collect($belum)->where('unit_prodi', 1);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-arrows-exchange"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Pemetaan Unit</h4>
      <p class="pg-header-sub mb-0">Unit kerja → prodi. Unit yang terpetakan masuk ke Laporan Keuangan Unit (dikelompokkan ke induk FF.00).</p>
    </div>
    <button type="button" class="btn btn-primary btn-isi"><i class="ti ti-plus me-1"></i>Tambah pemetaan</button>
    <a class="btn btn-label-secondary" href="{{ route('akuntansi.laporan-unit.index') }}"><i class="ti ti-report-analytics me-1"></i>Laporan Unit</a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-link"></i><span><strong>{{ count($pemetaan) }}</strong> unit terpetakan</span></span>
    <span class="pg-header-chip"><i class="ti {{ $belumProdi->isNotEmpty() ? 'ti-alert-triangle' : 'ti-circle-check' }}"></i><span><strong>{{ $belumProdi->count() }}</strong> unit Prodi belum dipetakan</span></span>
    <span class="pg-header-chip"><i class="ti ti-school"></i><span><strong>{{ $jmlProdi }}</strong> prodi di referensi</span></span>
  </div>
</div>

@include('_partials._flash')
@if ($errors->any())
  <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif
@if ($jmlProdi === 0)
  <div class="alert alert-warning">Referensi prodi masih kosong. Jalankan <code>php artisan simaku:sinkron-prodi</code> terlebih dahulu.</div>
@endif
@unless ($adaKodeNpm)
  <div class="alert alert-warning">Kolom <code>kode_npm</code> belum ada. Jalankan <code>sql/laporan_prodi/01a_tambah_kode_npm.sql</code> agar kode NPM bisa disimpan.</div>
@endunless

{{-- ===================== SUDAH TERPETAKAN ===================== --}}
<div class="card mb-4">
  <div class="card-header border-bottom"><h5 class="mb-0">Sudah terpetakan</h5></div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead><tr><th>Kode unit</th><th>Nama unit</th><th>Jenis</th><th>Kode</th><th>Jenjang</th><th>Prodi / Fakultas</th><th>Kode NPM</th><th>Berlaku dari</th><th></th></tr></thead>
      <tbody>
        @forelse ($pemetaan as $m)
          @php
            $fak = $m->jenis === 'fakultas';
            $tujuan = $fak ? $m->nama_fak : $m->nm_lemb;
          @endphp
          <tr class="{{ $tujuan === null || $m->kode === null ? 'table-danger' : '' }}">
            <td class="font-monospace">{{ $m->kode ?? '#' . $m->id_unit }}</td>
            <td>{{ $m->nama ?? '(unit tidak ada di ueu_tbl_unit)' }}</td>
            <td><span class="badge bg-label-{{ $fak ? 'warning' : 'primary' }}">{{ $fak ? 'Fakultas' : 'Prodi' }}</span></td>
            <td class="font-monospace">{{ $fak ? $m->kode_fak : ($m->kode_prodi ?? $m->kode_prg) }}</td>
            <td>@if (!$fak && $m->jenjang_didik)<span class="badge bg-label-info">{{ $m->jenjang_didik }}</span>@else — @endif</td>
            <td>{{ $tujuan ?? ($fak ? '(kode_fak tidak ada di referensi: ' . $m->kode_fak . ')' : '(id_sms tidak ada di referensi: ' . $m->id_sms . ')') }}</td>
            <td class="font-monospace">{{ $m->kode_npm ?? '—' }}</td>
            <td>{{ $m->berlaku_dari ?? 'semua transaksi' }}</td>
            <td class="text-end text-nowrap">
              <button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill btn-isi" title="Ubah"
                data-ubah="1" data-unit="{{ $m->id_unit }}" data-jenis="{{ $m->jenis }}" data-prodi="{{ $m->id_sms }}" data-fak="{{ $m->kode_fak }}"
                data-npm="{{ $m->kode_npm }}" data-berlaku="{{ $m->berlaku_dari }}"><i class="ti ti-pencil"></i></button>
              <form method="POST" action="{{ route('akuntansi.pemetaan-unit.hapus', $m->id_map) }}" class="d-inline form-hapus"
                data-label="{{ ($m->kode ?? $m->id_unit) . ' ' . ($m->nama ?? '') }} → {{ $tujuan ?? ($m->id_sms ?? $m->kode_fak) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Hapus"><i class="ti ti-trash"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-3">Belum ada pemetaan. Klik “Tambah pemetaan”.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ===================== BELUM TERPETAKAN ===================== --}}
<div class="card">
  <div class="card-header border-bottom d-flex flex-wrap align-items-center gap-2">
    <div class="me-auto">
      <h5 class="mb-0">Belum terpetakan</h5>
      <small class="text-muted">Unit “Prodi” ditandai kuning (→ prodi); Dekan / Wakil Dekan / Direktur ditandai biru (→ fakultas). Jumlah transaksi sesuai tahun yang dipilih.</small>
    </div>
    <form method="GET" action="{{ route('akuntansi.pemetaan-unit.index') }}">
      <select name="tahun" class="form-select" onchange="this.form.submit()" aria-label="Tahun">
        <option value="semua" @selected($tahun === null)>Semua tahun</option>
        @foreach ($daftarTahun as $t)
          <option value="{{ $t }}" @selected($t === $tahun)>T.A {{ $t }}/{{ $t + 1 }}</option>
        @endforeach
      </select>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>Kode</th><th>Nama unit</th><th class="text-end">Jml transaksi</th><th class="text-end">Debet (Rp)</th><th class="text-end">Kredit (Rp)</th><th></th></tr></thead>
      <tbody>
        @forelse ($belum as $u)
          @php
            $pimpinan = (bool) preg_match('/(^|\s)(Wakil\s+)?(Dekan|Direktur)/i', (string) $u->nama);
          @endphp
          <tr class="{{ $u->unit_prodi ? 'table-warning' : ($pimpinan ? 'table-info' : '') }}">
            <td class="font-monospace">{{ $u->kode ?? '—' }}</td>
            <td>{{ $u->nama }} @if ($pimpinan)<span class="badge bg-label-warning ms-1">→ fakultas</span>@endif</td>
            <td class="text-end">{{ number_format($u->jml_transaksi, 0, ',', '.') }}</td>
            <td class="text-end">{{ $rp($u->total_debet) }}</td>
            <td class="text-end">{{ $rp($u->total_kredit) }}</td>
            <td class="text-end">
              @if ($u->kode !== null)
                <button type="button" class="btn btn-sm btn-label-primary btn-isi" data-unit="{{ $u->id_unit }}"><i class="ti ti-link ti-xs me-1"></i>Petakan</button>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-3">Semua unit sudah terpetakan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ===================== MODAL TAMBAH / UBAH ===================== --}}
<div class="modal fade" id="modalPemetaan" tabindex="-1" aria-labelledby="judulForm" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="POST" action="{{ route('akuntansi.pemetaan-unit.simpan') }}" class="modal-content">
      @csrf
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="judulForm">Tambah pemetaan</h5>
          <small class="text-muted">Satu unit hanya ke satu prodi atau satu fakultas. Unit pimpinan (Dekan / Wakil Dekan / Direktur) dipetakan ke fakultas.</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body row g-3">
        <div class="col-12">
          <label class="form-label" for="f-unit">Unit kerja</label>
          <select name="id_unit" id="f-unit" class="form-select @error('id_unit') is-invalid @enderror" required>
            <option value="">— pilih unit —</option>
            @foreach ($daftarUnit as $u)
              <option value="{{ $u->id_unit }}" data-pimpinan="{{ $u->pimpinan ? 1 : 0 }}" data-fak="{{ $u->usulan_fak }}" @selected((string) old('id_unit') === (string) $u->id_unit)>
                {{ $u->kode }} · {{ $u->nama }}{{ $u->id_map ? ' — sudah: ' . ($u->jenis === 'fakultas' ? $u->nama_fak : trim($u->kode_prodi . ' ' . $u->jenjang_didik . ' ' . $u->nm_lemb)) : '' }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-12">
          <label class="form-label d-block">Dipetakan ke</label>
          <div class="btn-group" role="group" aria-label="Jenis pemetaan">
            <input type="radio" class="btn-check" name="jenis" id="j-prodi" value="prodi" @checked(old('jenis', 'prodi') === 'prodi')>
            <label class="btn btn-outline-primary" for="j-prodi"><i class="ti ti-school me-1"></i>Prodi</label>
            <input type="radio" class="btn-check" name="jenis" id="j-fak" value="fakultas" @checked(old('jenis') === 'fakultas') @disabled(!$adaFakultas)>
            <label class="btn btn-outline-warning" for="j-fak"><i class="ti ti-building me-1"></i>Fakultas</label>
          </div>
          @unless ($adaFakultas)
            <small class="text-warning d-block mt-1">Pemetaan fakultas belum aktif: jalankan sql/laporan_prodi/01c_pemetaan_fakultas.sql.</small>
          @endunless
        </div>
        <div class="col-12 blok-fak">
          <label class="form-label" for="f-fak">Fakultas</label>
          <select name="kode_fak" id="f-fak" class="form-select @error('kode_fak') is-invalid @enderror">
            <option value="">— pilih fakultas —</option>
            @foreach ($daftarFakultas as $f)
              <option value="{{ $f->kode_fak }}" @selected(old('kode_fak') === $f->kode_fak)>{{ $f->kode_fak }} · {{ $f->nama }}</option>
            @endforeach
          </select>
          @if ($adaFakultas && !$daftarFakultas)
            <small class="text-warning">Referensi fakultas kosong; jalankan <code>php artisan simaku:sinkron-prodi</code>.</small>
          @else
            <small class="text-muted">Diusulkan dari kode fakultas unit induk (FF.00) bila ada.</small>
          @endif
        </div>
        <div class="col-12 blok-prodi">
          <label class="form-label" for="f-prodi">Prodi</label>
          <select name="id_sms" id="f-prodi" class="form-select @error('id_sms') is-invalid @enderror">
            <option value="">— pilih prodi —</option>
            @foreach ($daftarProdi as $p)
              <option value="{{ $p->id_sms }}" data-npm="{{ $p->kode_npm }}" @selected(old('id_sms') === $p->id_sms)>{{ $p->kode_prodi }} · {{ $p->jenjang_didik ? $p->jenjang_didik . ' ' : '' }}{{ $p->nm_lemb }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6 blok-prodi">
          <label class="form-label" for="f-npm">Kode NPM <small class="text-muted">(opsional)</small></label>
          <input type="text" name="kode_npm" id="f-npm" value="{{ old('kode_npm') }}" maxlength="10" class="form-control font-monospace @error('kode_npm') is-invalid @enderror"
            placeholder="mis. 81" @disabled(!$adaKodeNpm)>
          <small class="text-muted">Kode prodi di NPM mahasiswa. Diisi ulang dari <code>tbl_sms.npm_prog</code> saat <code>simaku:sinkron-prodi</code>.</small>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="f-berlaku">Berlaku dari <small class="text-muted">(opsional)</small></label>
          <input type="date" name="berlaku_dari" id="f-berlaku" value="{{ old('berlaku_dari') }}" class="form-control @error('berlaku_dari') is-invalid @enderror">
          <small class="text-muted">Kosong = semua transaksi unit ini ikut dihitung.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Simpan</button>
      </div>
    </form>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const $unit = $('#f-unit'), $prodi = $('#f-prodi'), $fak = $('#f-fak');
  const npm = document.getElementById('f-npm');
  const berlaku = document.getElementById('f-berlaku');
  const elModal = document.getElementById('modalPemetaan');
  const modal = new bootstrap.Modal(elModal);
  // Select2 di dalam modal: dropdownParent = modal agar kotak pencarian bisa diketik.
  [$unit, $prodi, $fak].forEach($el => $el.select2({ width: '100%', dropdownParent: $(elModal) }));

  // Jenis pemetaan: tampilkan isian yang sesuai; isian yang tersembunyi dinonaktifkan agar tidak ikut terkirim.
  const jenis = () => (document.querySelector('input[name="jenis"]:checked') || {}).value || 'prodi';
  function setJenis(j) {
    const r = document.querySelector('input[name="jenis"][value="' + j + '"]');
    if (r && !r.disabled) r.checked = true;
    const fak = jenis() === 'fakultas';
    elModal.querySelectorAll('.blok-fak').forEach(el => el.classList.toggle('d-none', !fak));
    elModal.querySelectorAll('.blok-prodi').forEach(el => el.classList.toggle('d-none', fak));
    $fak.prop('disabled', !fak).prop('required', fak);
    $prodi.prop('disabled', fak).prop('required', !fak);
    npm.disabled = fak || {{ $adaKodeNpm ? 'false' : 'true' }};
  }
  document.querySelectorAll('input[name="jenis"]').forEach(r => r.addEventListener('change', () => setJenis(r.value)));

  // Saat menambah: unit pimpinan (Dekan / Wakil Dekan / Direktur, mis. 01.01) -> fakultas induknya.
  $unit.on('change', function () {
    if (elModal.dataset.mode !== 'tambah') return;
    const o = $unit.find(':selected');
    if (!o.val()) return;
    if (String(o.data('pimpinan')) === '1' && !document.getElementById('j-fak').disabled) {
      setJenis('fakultas');
      if (o.data('fak')) $fak.val(String(o.data('fak'))).trigger('change');
      elModal.dataset.fokus = o.data('fak') ? '' : 'fak';
    } else {
      setJenis('prodi');
    }
  });

  // Pilih prodi -> usulkan kode NPM dari mapping lain bila kolom masih kosong.
  $prodi.on('change', function () {
    const usul = $prodi.find(':selected').data('npm');
    if (!npm.value && usul) npm.value = usul;
  });

  // Tombol Tambah / Petakan / Ubah: isi form lalu buka modal (data-prodi terisi = ubah).
  document.querySelectorAll('.btn-isi').forEach(b => b.addEventListener('click', function () {
    const ubah = !!b.dataset.ubah;
    elModal.dataset.mode = ubah ? 'ubah' : 'tambah';
    npm.value = b.dataset.npm || '';
    berlaku.value = b.dataset.berlaku || '';
    $prodi.val(b.dataset.prodi || '').trigger('change');
    $fak.val(b.dataset.fak || '').trigger('change');
    setJenis(b.dataset.jenis || 'prodi');
    elModal.dataset.fokus = b.dataset.unit ? (ubah ? '' : 'prodi') : 'unit';
    $unit.val(b.dataset.unit || '').trigger('change');   // mode tambah: bisa mengalihkan ke fakultas
    document.getElementById('judulForm').textContent = ubah ? 'Ubah pemetaan' : 'Tambah pemetaan';
    modal.show();
  }));
  // Setelah modal terbuka, langsung buka pilihan yang perlu diisi.
  elModal.addEventListener('shown.bs.modal', function () {
    const f = elModal.dataset.fokus;
    elModal.dataset.fokus = '';
    if (f === 'prodi' && jenis() === 'prodi') $prodi.select2('open');
    else if (f === 'fak' || (f === 'prodi' && jenis() === 'fakultas' && !$fak.val())) $fak.select2('open');
    else if (f === 'unit') $unit.select2('open');
  });

  // Validasi gagal: buka lagi modal dengan isian sebelumnya (old()).
  setJenis(jenis());
  @if ($errors->hasAny(['id_unit', 'jenis', 'id_sms', 'kode_fak', 'kode_npm', 'berlaku_dari']))
    elModal.dataset.mode = 'ubah';   // jangan ubah jenis otomatis saat mengisi ulang old()
    modal.show();
  @endif

  // Konfirmasi hapus.
  const esc = s => $('<div>').text(s ?? '').html();
  document.querySelectorAll('.form-hapus').forEach(f => f.addEventListener('submit', function (e) {
    e.preventDefault();
    Swal.fire({ icon: 'warning', title: 'Hapus pemetaan?', showCancelButton: true, buttonsStyling: false,
      html: '<b>' + esc(f.dataset.label) + '</b><br>Unit ini tidak lagi masuk Laporan Keuangan Unit.',
      confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' } })
      .then(r => { if (r.isConfirmed) f.submit(); });
  }));
});
</script>
@endsection
