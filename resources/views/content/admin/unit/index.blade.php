@extends('layouts/layoutMaster')

@section('title', 'Unit Kerja')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .unit-stat { display: flex; flex-wrap: wrap; gap: .5rem; }
  .unit-tabel tr.grup td { background: rgba(var(--bs-primary-rgb), .06); border-top: 2px solid rgba(var(--bs-primary-rgb), .25); }
  .unit-tabel tr.grup .kode-grup { display: inline-grid; place-items: center; min-width: 2.2rem; height: 1.6rem; padding: 0 .4rem;
                                   border-radius: .4rem; background: var(--bs-primary); color: #fff; font-weight: 700; font-size: .8rem; }
  .unit-tabel tr.induk td { font-weight: 600; }
  .unit-tabel td.nama-unit { white-space: normal; min-width: 220px; }
  .unit-tabel .form-switch .form-check-input { cursor: pointer; }
  .detail-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary); margin-bottom: .15rem; }
  .detail-teks { white-space: pre-line; }
</style>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-building-community"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Unit Kerja</h4>
      <p class="pg-header-sub mb-0">Fakultas, program studi, biro, dan lembaga yang mengelola anggaran.</p>
    </div>
  </div>
  <div class="unit-stat mt-3">
    <span class="pg-header-chip"><i class="ti ti-building"></i><span><strong>{{ $statistik['total'] }}</strong> unit</span></span>
    <span class="pg-header-chip"><i class="ti ti-target"></i><span><strong>{{ $statistik['core'] }}</strong> core</span></span>
    <span class="pg-header-chip"><i class="ti ti-lifebuoy"></i><span><strong>{{ $statistik['supporting'] }}</strong> supporting</span></span>
    <span class="pg-header-chip"><i class="ti ti-stethoscope"></i><span><strong>{{ $statistik['klinik'] }}</strong> klinik aktif</span></span>
    <span class="pg-header-chip"><i class="ti ti-user-off"></i><span><strong>{{ $statistik['tanpa_akun'] }}</strong> tanpa akun</span></span>
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div class="input-group input-group-merge" style="width: 280px;">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="search" id="unit-cari" class="form-control" placeholder="Cari kode, nama, pimpinan...">
      </div>
      <select id="unit-filter" class="form-select" style="width: auto;">
        <option value="">Semua kelompok</option>
        <option value="1">Core Unit</option>
        <option value="2">Supporting Unit</option>
        <option value="klinik">Klinik aktif</option>
        <option value="tanpa-akun">Tanpa akun</option>
      </select>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('admin.unit.cetak') }}" target="_blank" rel="noopener" class="btn btn-label-info">
        <i class="ti ti-printer me-1"></i> Cetak
      </a>
      <button type="button" class="btn btn-primary" id="btn-tambah">
        <i class="ti ti-plus me-1"></i> Tambah Unit
      </button>
    </div>
  </div>

  <div class="table-responsive text-nowrap">
    <table class="table table-hover unit-tabel mb-0">
      <thead>
        <tr>
          <th style="width: 90px;">Kode</th>
          <th>Nama Unit</th>
          <th>Pimpinan</th>
          <th>Kelompok</th>
          <th class="text-center" title="Maksimal pengajuan SPP">Maks. SPP</th>
          <th class="text-center" title="Izin input anggaran di luar jadwal komite">Klinik</th>
          <th class="text-center">Akun</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($grup as $g)
          <tr class="grup" data-grup="{{ $g->kode }}">
            <td colspan="8">
              <span class="kode-grup me-2">{{ $g->kode }}</span>
              <span class="fw-semibold">{{ $g->nama }}</span>
              <small class="text-muted ms-1">· {{ $g->unit->count() }} unit</small>
            </td>
          </tr>
          @foreach ($g->unit as $u)
            @php
              $data = $u->only(['id_unit', 'kode', 'nama', 'unit', 'nama_pimp', 'an_nama_pimp', 'nip', 'jml_spp', 'kode_fak', 'nama_operator', 'jabatan_operator']);
              $data['unit'] = (int) $u->unit === 1 ? 1 : 2;
              $cariTeks = strtolower("{$u->kode} {$u->nama} {$u->nama_pimp} {$u->nip}");
            @endphp
            <tr class="baris-unit {{ $u->is_induk ? 'induk' : '' }}" data-grup="{{ $g->kode }}" data-cari="{{ $cariTeks }}"
                data-kelompok="{{ $data['unit'] }}" data-klinik="{{ $u->klinik_aktif ? 1 : 0 }}" data-akun="{{ $u->users_count }}">
              <td><span class="font-monospace">{{ $u->kode }}</span></td>
              <td class="nama-unit">
                {{ $u->nama }}
                @foreach ($otorisator[$u->id_unit] ?? [] as $o)
                  <span class="badge bg-label-warning ms-1">{{ $o === 'otorisator1' ? 'Otorisator 1' : 'Otorisator 2' }}</span>
                @endforeach
              </td>
              <td>
                <div>{{ $u->nama_pimp ?: '-' }}</div>
                @if ($u->nip)<small class="text-muted">NIP {{ $u->nip }}</small>@endif
              </td>
              <td>
                <span class="badge {{ $data['unit'] === 1 ? 'bg-label-primary' : 'bg-label-secondary' }}">{{ $u->kelompok }}</span>
              </td>
              <td class="text-center">{{ $u->jml_spp }}</td>
              <td class="text-center">
                <div class="form-check form-switch d-inline-block m-0">
                  <input class="form-check-input saklar-klinik" type="checkbox" role="switch"
                         data-url="{{ route('admin.unit.klinik', $u) }}" @checked($u->klinik_aktif)
                         aria-label="Klinik anggaran {{ $u->nama }}">
                </div>
              </td>
              <td class="text-center">
                <span class="badge rounded-pill {{ $u->users_count ? 'bg-label-success' : 'bg-label-danger' }}">{{ $u->users_count }}</span>
              </td>
              <td class="text-end">
                <div class="d-inline-flex gap-1">
                  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-detail" title="Detail"
                          data-url="{{ route('admin.unit.show', $u) }}">
                    <i class="ti ti-eye"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill btn-ubah" title="Ubah"
                          data-url="{{ route('admin.unit.update', $u) }}" data-unit='@json($data)'>
                    <i class="ti ti-pencil"></i>
                  </button>
                  <form method="POST" action="{{ route('admin.unit.destroy', $u) }}" class="form-hapus" data-label="{{ $u->kode }} - {{ $u->nama }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Hapus">
                      <i class="ti ti-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        @endforeach
        <tr id="baris-kosong" class="d-none">
          <td colspan="8" class="text-center text-muted py-5">Tidak ada unit yang cocok.</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

{{-- Modal tambah / ubah --}}
<div class="modal fade" id="modal-unit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="POST" action="{{ route('admin.unit.store') }}" class="modal-content" id="form-unit">
      @csrf
      <input type="hidden" name="_method" value="POST" id="form-unit-method">
      <input type="hidden" name="_form_action" value="{{ route('admin.unit.store') }}" id="form-unit-action">

      <div class="modal-header">
        <h5 class="modal-title" id="modal-unit-judul">Tambah Unit Kerja</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-sm-3">
            <label class="form-label" for="f-kode">Kode Unit</label>
            <input type="text" id="f-kode" name="kode" class="form-control font-monospace @error('kode') is-invalid @enderror"
                   placeholder="01.03" maxlength="5" value="{{ old('kode') }}" required>
            @error('kode')<div class="invalid-feedback">{{ $message }}</div>@else<small class="text-muted">Format xx.xx</small>@enderror
          </div>
          <div class="col-sm-9">
            <label class="form-label" for="f-nama">Nama Unit</label>
            <input type="text" id="f-nama" name="nama" maxlength="100" class="form-control @error('nama') is-invalid @enderror"
                   value="{{ old('nama') }}" required>
            @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-sm-6">
            <label class="form-label d-block">Kelompok Unit</label>
            @foreach (\App\Models\Unit::KELOMPOK as $nilai => $label)
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="unit" id="f-unit-{{ $nilai }}" value="{{ $nilai }}"
                       @checked((int) old('unit', 1) === $nilai)>
                <label class="form-check-label" for="f-unit-{{ $nilai }}">{{ $label }}</label>
              </div>
            @endforeach
            @error('unit')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-3">
            <label class="form-label" for="f-jml_spp">Maks. SPP</label>
            <input type="number" min="0" id="f-jml_spp" name="jml_spp" class="form-control @error('jml_spp') is-invalid @enderror"
                   value="{{ old('jml_spp', 2) }}" required>
            @error('jml_spp')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-3">
            <label class="form-label" for="f-kode_fak">Kode Fakultas</label>
            <input type="text" id="f-kode_fak" name="kode_fak" maxlength="2" class="form-control @error('kode_fak') is-invalid @enderror"
                   value="{{ old('kode_fak') }}">
            @error('kode_fak')<div class="invalid-feedback">{{ $message }}</div>@else<small class="text-muted">PDPT, unit induk</small>@enderror
          </div>

          <div class="col-12"><hr class="my-1"><small class="text-muted text-uppercase">Pimpinan</small></div>
          <div class="col-sm-7">
            <label class="form-label" for="f-nama_pimp">Nama Pimpinan</label>
            <input type="text" id="f-nama_pimp" name="nama_pimp" maxlength="100" class="form-control @error('nama_pimp') is-invalid @enderror"
                   value="{{ old('nama_pimp') }}" required>
            @error('nama_pimp')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-5">
            <label class="form-label" for="f-nip">NIP</label>
            <input type="text" id="f-nip" name="nip" maxlength="20" class="form-control @error('nip') is-invalid @enderror"
                   value="{{ old('nip') }}">
            @error('nip')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <label class="form-label" for="f-an_nama_pimp">A.n. Pimpinan</label>
            <input type="text" id="f-an_nama_pimp" name="an_nama_pimp" maxlength="225" class="form-control @error('an_nama_pimp') is-invalid @enderror"
                   value="{{ old('an_nama_pimp') }}" placeholder="Kosongkan bila SPP ditandatangani pimpinan langsung">
            @error('an_nama_pimp')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="col-12"><hr class="my-1"><small class="text-muted text-uppercase">Operator</small></div>
          <div class="col-sm-6">
            <label class="form-label" for="f-nama_operator">Nama Operator</label>
            <input type="text" id="f-nama_operator" name="nama_operator" maxlength="85" class="form-control" value="{{ old('nama_operator') }}">
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="f-jabatan_operator">Jabatan Operator</label>
            <input type="text" id="f-jabatan_operator" name="jabatan_operator" maxlength="100" class="form-control" value="{{ old('jabatan_operator') }}">
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

{{-- Panel detail --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="panel-detail" style="width: 440px;">
  <div class="offcanvas-header border-bottom">
    <div>
      <h5 class="offcanvas-title mb-0" id="detail-judul">Detail Unit</h5>
      <small class="text-muted font-monospace" id="detail-kode"></small>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
  </div>
  <div class="offcanvas-body" id="detail-isi">
    <div class="text-center text-muted py-5">Memuat...</div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
  const esc = s => $('<div>').text(s ?? '').html();

  // ---- Cari & filter ---------------------------------------------------------
  const inputCari = document.getElementById('unit-cari');
  const filter = document.getElementById('unit-filter');

  function saring() {
    const kata = inputCari.value.trim().toLowerCase();
    const f = filter.value;
    const grupTampil = new Set();
    let ada = 0;

    document.querySelectorAll('tr.baris-unit').forEach(function (tr) {
      const cocokKata = !kata || tr.dataset.cari.includes(kata);
      const cocokFilter = !f
        || (f === 'klinik' && tr.dataset.klinik === '1')
        || (f === 'tanpa-akun' && tr.dataset.akun === '0')
        || tr.dataset.kelompok === f;
      const tampil = cocokKata && cocokFilter;
      tr.classList.toggle('d-none', !tampil);
      if (tampil) { grupTampil.add(tr.dataset.grup); ada++; }
    });

    document.querySelectorAll('tr.grup').forEach(tr => tr.classList.toggle('d-none', !grupTampil.has(tr.dataset.grup)));
    document.getElementById('baris-kosong').classList.toggle('d-none', ada > 0);
  }
  inputCari.addEventListener('input', saring);
  filter.addEventListener('change', saring);

  // ---- Tambah / ubah ---------------------------------------------------------
  const modal = new bootstrap.Modal(document.getElementById('modal-unit'));
  const form = document.getElementById('form-unit');
  const urlSimpan = @json(route('admin.unit.store'));
  const kolom = ['kode', 'nama', 'nama_pimp', 'an_nama_pimp', 'nip', 'jml_spp', 'kode_fak', 'nama_operator', 'jabatan_operator'];

  function bukaForm(url, metode, judul, data, bersihkan) {
    form.action = url;
    document.getElementById('form-unit-action').value = url;
    document.getElementById('form-unit-method').value = metode;
    document.getElementById('modal-unit-judul').textContent = judul;
    if (data) {
      kolom.forEach(k => { document.getElementById('f-' + k).value = data[k] ?? ''; });
      const r = document.getElementById('f-unit-' + (data.unit || 1));
      if (r) r.checked = true;
    }
    if (bersihkan) form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    modal.show();
  }

  document.getElementById('btn-tambah').addEventListener('click', function () {
    bukaForm(urlSimpan, 'POST', 'Tambah Unit Kerja', { unit: 1, jml_spp: 2 }, true);
  });
  document.querySelectorAll('.btn-ubah').forEach(function (btn) {
    btn.addEventListener('click', function () {
      bukaForm(btn.dataset.url, 'PUT', 'Ubah Unit Kerja', JSON.parse(btn.dataset.unit), true);
    });
  });

  @if ($errors->any())
    @php
      $aksiLama = (string) old('_form_action');
      $aksiLama = str_starts_with($aksiLama, url('/') . '/') ? $aksiLama : route('admin.unit.store');
      $ubah = old('_method') === 'PUT';
    @endphp
    bukaForm(@json($aksiLama), @json($ubah ? 'PUT' : 'POST'), @json($ubah ? 'Ubah Unit Kerja' : 'Tambah Unit Kerja'), null, false);
  @endif

  // ---- Saklar klinik ---------------------------------------------------------
  document.querySelectorAll('.saklar-klinik').forEach(function (el) {
    el.addEventListener('change', function () {
      const aktif = el.checked;
      el.disabled = true;
      fetch(el.dataset.url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ aktif: aktif })
      })
        .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(d => {
          el.closest('tr').dataset.klinik = d.aktif ? '1' : '0';
          Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: d.pesan, showConfirmButton: false, timer: 2200 });
        })
        .catch(() => {
          el.checked = !aktif;
          Swal.fire({ icon: 'error', title: 'Gagal menyimpan', text: 'Perubahan klinik tidak tersimpan. Coba lagi.' });
        })
        .finally(() => { el.disabled = false; });
    });
  });

  // ---- Hapus -----------------------------------------------------------------
  document.querySelectorAll('.form-hapus').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      Swal.fire({
        title: 'Hapus unit?',
        html: 'Unit <b>' + esc(f.dataset.label) + '</b> akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
      }).then(r => { if (r.isConfirmed) f.submit(); });
    });
  });

  // ---- Panel detail ----------------------------------------------------------
  const panel = new bootstrap.Offcanvas(document.getElementById('panel-detail'));
  const isi = document.getElementById('detail-isi');
  @php
    $labelWewenang = [
      'admin' => 'Administrator', 'unit' => 'Unit', 'operator' => 'Operator Fakultas', 'otorisator1' => 'Otorisator 1',
      'otorisator2' => 'Otorisator 2', 'kasir' => 'Kasir', 'review' => 'Reviewer', 'operatorspm' => 'Operator SPM',
      'op1' => 'Operator Penggajian 1', 'op2' => 'Operator Penggajian 2',
    ];
  @endphp
  const labelWewenang = @json($labelWewenang);

  function bidang(label, nilai) {
    return '<div class="mb-3"><div class="detail-label">' + label + '</div><div class="detail-teks">' +
      (nilai ? esc(nilai) : '<span class="text-muted">-</span>') + '</div></div>';
  }

  document.querySelectorAll('.btn-detail').forEach(function (btn) {
    btn.addEventListener('click', function () {
      isi.innerHTML = '<div class="text-center text-muted py-5">Memuat...</div>';
      panel.show();
      fetch(btn.dataset.url, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(d => {
          const u = d.unit;
          document.getElementById('detail-judul').textContent = u.nama;
          document.getElementById('detail-kode').textContent = u.kode + ' · ' + u.kelompok + (u.klinik ? ' · Klinik aktif' : '');

          let akun = d.users.length
            ? '<ul class="list-group list-group-flush">' + d.users.map(a =>
                '<li class="list-group-item px-0 d-flex justify-content-between align-items-center">' +
                '<span><span class="font-monospace">' + esc(a.username) + '</span><br><small class="text-muted">' + esc(a.nama_lengkap) + '</small></span>' +
                '<span class="badge bg-label-primary">' + esc(labelWewenang[a.wewenang] || a.wewenang || '-') + '</span></li>').join('') + '</ul>'
            : '<div class="text-muted">Belum ada akun pengguna untuk unit ini.</div>';

          isi.innerHTML =
            '<h6 class="mb-3">Pimpinan & Operator</h6>' +
            bidang('Nama pimpinan', u.nama_pimp) + bidang('NIP', u.nip) + bidang('A.n. pimpinan', u.an_nama_pimp) +
            bidang('Operator', [u.nama_operator, u.jabatan_operator].filter(Boolean).join(' — ')) +
            bidang('Maks. SPP', String(u.jml_spp)) + bidang('Kode fakultas (PDPT)', u.kode_fak) +
            '<hr><h6 class="mb-3">Akun Pengguna <span class="badge bg-label-secondary">' + d.users.length + '</span></h6>' + akun +
            '<hr><h6 class="mb-1">Profil Unit</h6><p class="text-muted small mb-3">Diisi oleh unit melalui menu profil.</p>' +
            bidang('Visi', u.visi) + bidang('Misi', u.misi) + bidang('Tujuan umum', u.tu) + bidang('Tujuan khusus', u.tk) + bidang('Keterangan', u.ket);
        })
        .catch(() => { isi.innerHTML = '<div class="alert alert-danger">Gagal memuat detail unit.</div>'; });
    });
  });
});
</script>
@endsection
