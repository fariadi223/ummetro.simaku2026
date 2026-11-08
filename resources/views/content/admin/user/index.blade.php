@extends('layouts/layoutMaster')

@section('title', 'Pengguna')

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
  .user-tabel td.nama { white-space: normal; min-width: 200px; }

  /* Kepala kelompok (xx.00) */
  .user-tabel tr.h-induk td { background: rgba(var(--bs-primary-rgb), .07); border-top: 2px solid rgba(var(--bs-primary-rgb), .25); }
  .user-tabel .kode-induk { display: inline-grid; place-items: center; min-width: 3.4rem; height: 1.7rem; padding: 0 .45rem;
                            border-radius: .45rem; background: var(--bs-primary); color: #fff; font-weight: 700; font-size: .8rem; }
  .user-tabel .btn-lipat { border: 0; background: transparent; padding: 0; width: 1.5rem; color: inherit; cursor: pointer; }
  .user-tabel .btn-lipat i { transition: transform .15s ease; }
  .user-tabel .btn-lipat[aria-expanded="false"] i { transform: rotate(-90deg); }

  /* Sub-unit (xx.01 ...) */
  .user-tabel tr.h-unit td { background: rgba(var(--bs-primary-rgb), .025); }
  .user-tabel .kode-unit { font-weight: 600; color: var(--bs-primary); }

  /* Indentasi + garis cabang pohon */
  .user-tabel tr.lvl-1 > td:first-child { padding-left: 2.6rem; }
  .user-tabel tr.lvl-2 > td:first-child { padding-left: 4.4rem; }
  .user-tabel .cabang { position: relative; }
  .user-tabel .cabang::before { content: ""; position: absolute; left: -1.1rem; top: 50%; width: .8rem;
                                border-top: 1px dashed rgba(var(--bs-secondary-rgb), .6); }
  .user-tabel tr.lvl-1 > td:first-child, .user-tabel tr.lvl-2 > td:first-child { position: relative; }
  .user-tabel tr.lvl-1 > td:first-child::before { content: ""; position: absolute; left: 1.5rem; top: 0; bottom: 0;
                                                  border-left: 1px dashed rgba(var(--bs-secondary-rgb), .6); }
  .user-tabel tr.lvl-2 > td:first-child::before { content: ""; position: absolute; left: 3.3rem; top: 0; bottom: 0;
                                                  border-left: 1px dashed rgba(var(--bs-secondary-rgb), .6); }
  .user-tabel tr.baris-user:hover td { background: rgba(var(--bs-primary-rgb), .04); }
  .user-avatar { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0;
                 font-weight: 700; font-size: .8rem; background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary); }
  .pw-sel { min-width: 120px; }
</style>
@endsection

@php
  $warnaRole = [
    'admin' => 'danger', 'akuntansi' => 'info', 'penerimaan' => 'info', 'kasir' => 'success', 'unit' => 'primary',
    'operator' => 'secondary', 'otorisator1' => 'warning', 'otorisator2' => 'warning', 'review' => 'dark',
    'operatorspm' => 'success', 'op1' => 'secondary', 'op2' => 'secondary',
  ];
  $urlSimpan = route('admin.user.store');
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-users"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Pengguna</h4>
      <p class="pg-header-sub mb-0">Akun login SIMAKU untuk unit, operator, otorisator, kasir, dan pengelola.</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-user"></i><span><strong>{{ $statistik['total'] }}</strong> akun</span></span>
    <span class="pg-header-chip"><i class="ti ti-shield"></i><span><strong>{{ $statistik['peran'] }}</strong> peran</span></span>
    <span class="pg-header-chip"><i class="ti ti-alert-triangle"></i><span><strong>{{ $statistik['bermasalah'] }}</strong> tidak bisa login</span></span>
    <span class="pg-header-chip"><i class="ti ti-lock-open"></i><span><strong>{{ $statistik['lemah'] }}</strong> password lemah</span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div class="input-group input-group-merge" style="width: 260px;">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="search" id="user-cari" class="form-control" placeholder="Cari username, nama, unit...">
      </div>
      <select id="user-role" class="form-select" style="width: auto;">
        <option value="">Semua peran</option>
        @foreach ($labelRole as $nilai => $label)
          <option value="{{ $nilai }}">{{ $label }}</option>
        @endforeach
      </select>
      <select id="user-masalah" class="form-select" style="width: auto;">
        <option value="">Semua kondisi</option>
        <option value="danger">Tidak bisa login</option>
        <option value="warning">Perlu perhatian</option>
      </select>
    </div>
    <button type="button" class="btn btn-primary" id="btn-tambah">
      <i class="ti ti-user-plus me-1"></i> Tambah Pengguna
    </button>
  </div>

  <div class="table-responsive text-nowrap">
    <table class="table user-tabel mb-0">
      <thead>
        <tr>
          <th>Unit / Pengguna</th>
          <th>Peran</th>
          <th>Password</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @php
          $ctx = ['warnaRole' => $warnaRole, 'idSaya' => $idSaya];
        @endphp

        {{-- Akun tingkat universitas (tanpa unit) --}}
        @if ($akunUniversitas->isNotEmpty())
          <tr class="h-induk" data-grup="univ" data-cari="universitas tanpa unit">
            <td colspan="4">
              <button type="button" class="btn-lipat" aria-expanded="true" title="Lipat / buka"><i class="ti ti-chevron-down"></i></button>
              <span class="kode-induk"><i class="ti ti-building-bank"></i></span>
              <span class="fw-semibold">Universitas</span>
              <small class="text-muted ms-1">· tanpa unit · {{ $akunUniversitas->count() }} akun</small>
            </td>
          </tr>
          @foreach ($akunUniversitas as $b)
            @include('content.admin.user._baris', $ctx + ['b' => $b, 'lvl' => 1, 'grup' => 'univ', 'simpul' => 'u0'])
          @endforeach
        @endif

        {{-- Per kelompok unit: xx.00 lalu xx.01, xx.02, ... --}}
        @foreach ($pohon as $g)
          @php
            $kunci = 'g' . $g->kode;
          @endphp
          <tr class="h-induk" data-grup="{{ $kunci }}" data-cari="{{ mb_strtolower($g->kode . ' ' . $g->nama) }}">
            <td colspan="4">
              <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn-lipat" aria-expanded="true" title="Lipat / buka"><i class="ti ti-chevron-down"></i></button>
                <span class="kode-induk font-monospace">{{ $g->induk?->unit->kode ?? $g->kode }}</span>
                <span class="fw-semibold">{{ $g->nama }}</span>
                <small class="text-muted">· {{ $g->anak->count() }} sub-unit · {{ $g->jumlah }} akun</small>
                @if ($g->induk)
                  <button type="button" class="btn btn-xs btn-text-primary ms-auto btn-tambah-unit" data-unit="{{ $g->induk->unit->id_unit }}"
                          title="Tambah akun untuk {{ $g->induk->unit->kode }}">
                    <i class="ti ti-user-plus ti-xs me-1"></i>Akun
                  </button>
                @endif
              </div>
            </td>
          </tr>
          @if ($g->induk)
            @foreach ($g->induk->akun as $b)
              @include('content.admin.user._baris', $ctx + ['b' => $b, 'lvl' => 1, 'grup' => $kunci, 'simpul' => 'u' . $g->induk->unit->id_unit])
            @endforeach
          @endif

          @foreach ($g->anak as $s)
            <tr class="h-unit lvl-1 {{ $loop->last ? 'terakhir' : '' }}" data-grup="{{ $kunci }}" data-simpul="u{{ $s->unit->id_unit }}"
                data-cari="{{ mb_strtolower($s->unit->kode . ' ' . $s->unit->nama) }}">
              <td colspan="4">
                <div class="d-flex align-items-center gap-2 cabang">
                  <span class="font-monospace kode-unit">{{ $s->unit->kode }}</span>
                  <span>{{ $s->unit->nama }}</span>
                  @if ($s->akun->isEmpty())
                    <small class="text-danger">· belum ada akun</small>
                  @else
                    <small class="text-muted">· {{ $s->akun->count() }} akun</small>
                  @endif
                  <button type="button" class="btn btn-xs btn-text-primary ms-auto btn-tambah-unit" data-unit="{{ $s->unit->id_unit }}"
                          title="Tambah akun untuk {{ $s->unit->kode }}">
                    <i class="ti ti-user-plus ti-xs me-1"></i>Akun
                  </button>
                </div>
              </td>
            </tr>
            @foreach ($s->akun as $b)
              @include('content.admin.user._baris', $ctx + ['b' => $b, 'lvl' => 2, 'grup' => $kunci, 'simpul' => 'u' . $s->unit->id_unit])
            @endforeach
          @endforeach
        @endforeach

        {{-- Akun yang unitnya sudah tidak ada --}}
        @if ($akunTanpaUnit->isNotEmpty())
          <tr class="h-induk" data-grup="hilang" data-cari="unit tidak ditemukan">
            <td colspan="4">
              <button type="button" class="btn-lipat" aria-expanded="true" title="Lipat / buka"><i class="ti ti-chevron-down"></i></button>
              <span class="kode-induk bg-warning"><i class="ti ti-alert-triangle"></i></span>
              <span class="fw-semibold">Unit tidak ditemukan</span>
              <small class="text-muted ms-1">· {{ $akunTanpaUnit->count() }} akun</small>
            </td>
          </tr>
          @foreach ($akunTanpaUnit as $b)
            @include('content.admin.user._baris', $ctx + ['b' => $b, 'lvl' => 1, 'grup' => 'hilang', 'simpul' => 'x'])
          @endforeach
        @endif

        <tr id="baris-kosong" class="d-none">
          <td colspan="4" class="text-center text-muted py-5">Tidak ada pengguna yang cocok.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

{{-- Modal tambah / ubah --}}
<div class="modal fade" id="modal-user" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="{{ $urlSimpan }}" class="modal-content" id="form-user" autocomplete="off">
      @csrf
      <input type="hidden" name="_method" value="POST" id="form-user-method">
      <input type="hidden" name="_form_action" value="{{ $urlSimpan }}" id="form-user-action">

      <div class="modal-header">
        <h5 class="modal-title" id="modal-user-judul">Tambah Pengguna</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info small d-none" id="info-akun-role">
          Akun ini adalah akun role khusus (admin dengan password khusus). Wewenangnya harus tetap Administrator
          dan password-nya tidak dapat diganti.
        </div>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label" for="f-wewenang">Wewenang</label>
            <select id="f-wewenang" name="wewenang" class="form-select @error('wewenang') is-invalid @enderror" required>
              @foreach ($wewenangTersedia as $w)
                <option value="{{ $w }}" @selected(old('wewenang', 'unit') === $w)>{{ $labelRole[$w] }}</option>
              @endforeach
            </select>
            @error('wewenang')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label class="form-label" for="f-id_unit">Unit</label>
            <select id="f-id_unit" name="id_unit" class="form-select @error('id_unit') is-invalid @enderror">
              <option value="0">— Universitas (tanpa unit) —</option>
              @foreach ($units as $unit)
                <option value="{{ $unit->id_unit }}" @selected((int) old('id_unit') === $unit->id_unit)>{{ $unit->kode }} — {{ $unit->nama }}</option>
              @endforeach
            </select>
            @error('id_unit')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="f-username">Username</label>
            <input type="text" id="f-username" name="username" maxlength="32" class="form-control font-monospace @error('username') is-invalid @enderror"
                   value="{{ old('username') }}" required>
            @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="f-nama_lengkap">Nama Lengkap</label>
            <input type="text" id="f-nama_lengkap" name="nama_lengkap" maxlength="140" class="form-control @error('nama_lengkap') is-invalid @enderror"
                   value="{{ old('nama_lengkap') }}">
            @error('nama_lengkap')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-6 grup-password">
            <label class="form-label" for="f-password">Password</label>
            <input type="password" id="f-password" name="password" minlength="6" maxlength="32" autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-6 grup-password">
            <label class="form-label" for="f-password_confirmation">Ulangi Password</label>
            <input type="password" id="f-password_confirmation" name="password_confirmation" maxlength="32" autocomplete="new-password" class="form-control">
          </div>
          <div class="col-12 grup-password">
            <small class="text-muted">
              Satu username boleh dipakai beberapa akun (mis. unit &amp; operator) asalkan password-nya berbeda.
            </small>
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

{{-- Modal ganti password --}}
<div class="modal fade" id="modal-password" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="#" class="modal-content" id="form-password" autocomplete="off">
      @csrf
      @method('PUT')
      <input type="hidden" name="_form_action" id="form-password-action">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Ganti Password</h5>
          <small class="text-muted font-monospace" id="password-label"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="p-password">Password Baru</label>
          <div class="input-group">
            <input type="password" id="p-password" name="password" minlength="6" maxlength="32" autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror" required>
            <button type="button" class="btn btn-outline-secondary" id="btn-acak" title="Buat password acak"><i class="ti ti-dice-5"></i></button>
          </div>
          @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div>
          <label class="form-label" for="p-password_confirmation">Ulangi Password Baru</label>
          <input type="password" id="p-password_confirmation" name="password_confirmation" maxlength="32" autocomplete="new-password" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-info">Simpan Password</button>
      </div>
    </form>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const esc = s => $('<div>').text(s ?? '').html();
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

  // ---- Cari, filter & lipat --------------------------------------------------
  const cari = document.getElementById('user-cari');
  const role = document.getElementById('user-role');
  const masalah = document.getElementById('user-masalah');
  const tertutup = new Set();   // kunci kelompok yang sedang dilipat

  function saring() {
    const k = cari.value.trim().toLowerCase();
    const adaFilter = !!(k || role.value || masalah.value);
    const grupTampil = new Set(), simpulTampil = new Set();
    let ada = 0;

    // 1. Akun: cocok dengan kata kunci & filter?
    document.querySelectorAll('tr.baris-user').forEach(function (tr) {
      const cocok = (!k || tr.dataset.cari.includes(k))
        && (!role.value || tr.dataset.role === role.value)
        && (!masalah.value || tr.dataset.masalah === masalah.value);
      tr.dataset.cocok = cocok ? '1' : '0';
      if (cocok) { grupTampil.add(tr.dataset.grup); simpulTampil.add(tr.dataset.simpul); ada++; }
    });

    // 2. Sub-unit: tampil bila tanpa filter, ada akun yang cocok, atau namanya sendiri cocok (hanya kata kunci).
    document.querySelectorAll('tr.h-unit').forEach(function (tr) {
      const cocokNama = k && !role.value && !masalah.value && tr.dataset.cari.includes(k);
      const tampil = !adaFilter || simpulTampil.has(tr.dataset.simpul) || cocokNama;
      tr.dataset.cocok = tampil ? '1' : '0';
      if (tampil) { grupTampil.add(tr.dataset.grup); if (cocokNama) ada++; }
    });

    // 3. Kepala kelompok
    document.querySelectorAll('tr.h-induk').forEach(function (tr) {
      const tampil = !adaFilter || grupTampil.has(tr.dataset.grup);
      tr.classList.toggle('d-none', !tampil);
      const btn = tr.querySelector('.btn-lipat');
      if (btn) btn.setAttribute('aria-expanded', tertutup.has(tr.dataset.grup) ? 'false' : 'true');
    });

    // 4. Terapkan: baris tampil bila cocok dan kelompoknya tidak dilipat.
    document.querySelectorAll('tr.baris-user, tr.h-unit').forEach(function (tr) {
      tr.classList.toggle('d-none', tr.dataset.cocok !== '1' || tertutup.has(tr.dataset.grup));
    });

    document.getElementById('baris-kosong').classList.toggle('d-none', ada > 0 || !adaFilter);
  }
  [cari, role, masalah].forEach(el => el.addEventListener(el === cari ? 'input' : 'change', saring));

  document.querySelectorAll('tr.h-induk').forEach(function (tr) {
    tr.querySelector('.btn-lipat')?.addEventListener('click', function () {
      const g = tr.dataset.grup;
      tertutup.has(g) ? tertutup.delete(g) : tertutup.add(g);
      saring();
    });
  });
  saring();

  // ---- Tambah / ubah ---------------------------------------------------------
  const modalUser = new bootstrap.Modal(document.getElementById('modal-user'));
  const form = document.getElementById('form-user');
  const $unit = $('#f-id_unit');
  $unit.select2({ dropdownParent: $('#modal-user'), width: '100%' });
  const urlSimpan = @json($urlSimpan);

  function bukaForm(url, metode, judul, data, akunRole, bersihkan) {
    form.action = url;
    document.getElementById('form-user-action').value = url;
    document.getElementById('form-user-method').value = metode;
    document.getElementById('modal-user-judul').textContent = judul;
    const baru = metode === 'POST';
    form.querySelectorAll('.grup-password').forEach(el => el.classList.toggle('d-none', !baru));
    document.getElementById('f-password').required = baru;
    document.getElementById('info-akun-role').classList.toggle('d-none', !akunRole);
    if (data) {
      document.getElementById('f-wewenang').value = data.wewenang || 'unit';
      document.getElementById('f-username').value = data.username || '';
      document.getElementById('f-nama_lengkap').value = data.nama_lengkap || '';
      document.getElementById('f-password').value = '';
      document.getElementById('f-password_confirmation').value = '';
      $unit.val(String(data.id_unit ?? 0)).trigger('change');
    }
    if (bersihkan) form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    modalUser.show();
  }

  document.getElementById('btn-tambah').addEventListener('click', () =>
    bukaForm(urlSimpan, 'POST', 'Tambah Pengguna', { wewenang: 'unit', id_unit: 0 }, false, true));
  document.querySelectorAll('.btn-tambah-unit').forEach(btn => btn.addEventListener('click', () =>
    bukaForm(urlSimpan, 'POST', 'Tambah Pengguna', { wewenang: 'unit', id_unit: Number(btn.dataset.unit) }, false, true)));
  document.querySelectorAll('.btn-ubah').forEach(btn => btn.addEventListener('click', () =>
    bukaForm(btn.dataset.url, 'PUT', 'Ubah Pengguna', JSON.parse(btn.dataset.user), btn.dataset.akunRole === '1', true)));

  // ---- Ganti password --------------------------------------------------------
  const modalPw = new bootstrap.Modal(document.getElementById('modal-password'));
  const formPw = document.getElementById('form-password');
  function bukaPassword(url, label) {
    formPw.action = url;
    document.getElementById('form-password-action').value = url;
    document.getElementById('password-label').textContent = label;
    modalPw.show();
  }
  document.querySelectorAll('.btn-password').forEach(btn => btn.addEventListener('click', () => {
    formPw.reset();
    bukaPassword(btn.dataset.url, btn.dataset.label);
  }));
  document.getElementById('btn-acak').addEventListener('click', function () {
    const huruf = 'abcdefghjkmnpqrstuvwxyz23456789';
    const acak = new Uint32Array(10);
    crypto.getRandomValues(acak);
    const pw = Array.from(acak, n => huruf[n % huruf.length]).join('');
    ['p-password', 'p-password_confirmation'].forEach(id => {
      const el = document.getElementById(id);
      el.value = pw;
      el.type = 'text';
    });
  });

  // Buka kembali modal yang gagal validasi.
  @if ($errors->any())
    @php
      $aksiLama = (string) old('_form_action');
      $aksiLama = str_starts_with($aksiLama, url('/') . '/') ? $aksiLama : $urlSimpan;
      $modeLama = str_ends_with($aksiLama, '/password') ? 'password' : (old('_method') === 'PUT' ? 'ubah' : 'tambah');
      $dataLama = [
        'wewenang' => old('wewenang', 'unit'),
        'id_unit' => (int) old('id_unit', 0),
        'username' => old('username'),
        'nama_lengkap' => old('nama_lengkap'),
      ];
    @endphp
    @if ($modeLama === 'password')
      bukaPassword(@json($aksiLama), '');
    @else
      bukaForm(@json($aksiLama), @json($modeLama === 'ubah' ? 'PUT' : 'POST'),
        @json($modeLama === 'ubah' ? 'Ubah Pengguna' : 'Tambah Pengguna'),
        @json($dataLama),
        false, false);
    @endif
  @endif

  // ---- Lihat password --------------------------------------------------------
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  document.querySelectorAll('.btn-lihat').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const teks = btn.parentElement.querySelector('.pw-teks');
      if (btn.dataset.terbuka === '1') {
        teks.textContent = '••••••'; teks.classList.add('text-muted');
        btn.dataset.terbuka = '0'; btn.innerHTML = '<i class="ti ti-eye"></i>';
        return;
      }
      fetch(btn.dataset.url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
        .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(d => {
          teks.textContent = d.password; teks.classList.remove('text-muted');
          btn.dataset.terbuka = '1'; btn.innerHTML = '<i class="ti ti-eye-off"></i>';
        })
        .catch(() => Swal.fire({ icon: 'error', title: 'Gagal memuat password' }));
    });
  });

  // ---- Hapus -----------------------------------------------------------------
  document.querySelectorAll('.form-hapus').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      Swal.fire({
        title: 'Hapus pengguna?',
        html: 'Akun <b>' + esc(f.dataset.label) + '</b> akan dihapus permanen dan tidak bisa login lagi.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
      }).then(r => { if (r.isConfirmed) f.submit(); });
    });
  });
});
</script>
@endsection
