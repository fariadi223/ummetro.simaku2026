@extends('layouts/layoutMaster')

@section('title', 'Penetapan Review')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .rv-tabel tr.grup td { background: rgba(var(--bs-primary-rgb), .06); border-top: 2px solid rgba(var(--bs-primary-rgb), .25); }
  .rv-tabel .kode-grup { display: inline-grid; place-items: center; min-width: 2.2rem; height: 1.6rem; padding: 0 .4rem;
                         border-radius: .4rem; background: var(--bs-primary); color: #fff; font-weight: 700; font-size: .8rem; }
  .rv-tabel td.nama { white-space: normal; min-width: 220px; }
  .rv-tabel tr.terpilih td { background: rgba(var(--bs-primary-rgb), .05); }
  .chip-rv { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .25rem .2rem .6rem; margin: .1rem .2rem .1rem 0;
             border-radius: 999px; font-size: .8rem; background: rgba(var(--bs-primary-rgb), .1); color: var(--bs-primary); }
  .chip-rv form { display: inline; }
  .chip-rv button { border: 0; background: transparent; color: inherit; padding: 0 .15rem; line-height: 1; opacity: .6; cursor: pointer; }
  .chip-rv button:hover { opacity: 1; }
  .chip-rv .n { font-size: .7rem; opacity: .75; }
  .rv-daftar .list-group-item { cursor: pointer; }
  .rv-daftar .list-group-item.active .text-muted { color: rgba(255,255,255,.75) !important; }
  .bilah-aksi { position: sticky; bottom: 0; z-index: 3; }
</style>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-user-check"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Penetapan Review T.A {{ $tahun }}/{{ $tahun + 1 }}</h4>
      <p class="pg-header-sub mb-0">Tentukan reviewer (tim komite) yang menilai rencana kegiatan tiap unit.</p>
    </div>
    <a href="{{ route('admin.periode.index') }}" class="pg-header-chip text-white text-decoration-none" title="Ganti tahun anggaran">
      <i class="ti ti-calendar-event"></i><span>Ganti T.A</span>
    </a>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-building"></i><span><strong>{{ $statistik['unit'] }}</strong> sub-unit</span></span>
    <span class="pg-header-chip"><i class="ti ti-circle-check"></i><span><strong>{{ $statistik['ditetapkan'] }}</strong> sudah ada reviewer</span></span>
    <span class="pg-header-chip"><i class="ti ti-circle-dashed"></i><span><strong>{{ $statistik['unit'] - $statistik['ditetapkan'] }}</strong> belum</span></span>
    <span class="pg-header-chip"><i class="ti ti-users"></i><span><strong>{{ $statistik['reviewer'] }}</strong> reviewer bertugas</span></span>
  </div>
</div>

@include('_partials._flash')
@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-4">
  {{-- Daftar reviewer --}}
  <div class="col-xl-3 col-lg-4">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Reviewer</h6>
        <a href="{{ route('admin.user.index') }}" class="small" title="Kelola akun di menu Pengguna">Kelola akun</a>
      </div>
      <div class="list-group list-group-flush rv-daftar" id="rv-daftar">
        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center active" data-rv="">
          Semua unit
          <span class="badge bg-label-secondary rounded-pill">{{ $statistik['unit'] }}</span>
        </button>
        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-rv="kosong">
          Belum ada reviewer
          <span class="badge bg-label-danger rounded-pill">{{ $statistik['unit'] - $statistik['ditetapkan'] }}</span>
        </button>
        @forelse ($reviewer as $r)
          <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-rv="{{ $r->idlog }}">
            <span>
              {{ $r->nama_lengkap ?: $r->username }}
              <small class="d-block text-muted font-monospace">{{ $r->username }}</small>
            </span>
            <span class="badge bg-label-primary rounded-pill" title="Unit yang ditinjau T.A ini">{{ $perReviewer[$r->idlog] ?? 0 }}</span>
          </button>
        @empty
          <div class="list-group-item text-muted">Belum ada akun ber-wewenang Reviewer.</div>
        @endforelse
      </div>
      @if ($sebelum)
        <div class="card-body border-top">
          <form method="POST" action="{{ route('admin.review.salin') }}" id="form-salin">
            @csrf
            <button type="submit" class="btn btn-label-secondary w-100">
              <i class="ti ti-copy me-1"></i> Salin dari T.A {{ $tahun - 1 }}
            </button>
            <small class="text-muted d-block mt-2">{{ $sebelum }} penetapan T.A {{ $tahun - 1 }}; yang sudah ada tidak diduplikasi.</small>
          </form>
        </div>
      @endif
    </div>
  </div>

  {{-- Unit & reviewernya --}}
  <div class="col-xl-9 col-lg-8">
    <div class="card">
      <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="input-group input-group-merge" style="width: 260px;">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="search" id="rv-cari" class="form-control" placeholder="Cari kode atau nama unit...">
        </div>
        <small class="text-muted">Centang unit lalu pilih reviewer di bilah bawah.</small>
      </div>

      <div class="table-responsive">
        <table class="table rv-tabel mb-0">
          <thead>
            <tr>
              <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="cek-semua" title="Pilih semua yang tampil"></th>
              <th style="width: 80px;">Kode</th>
              <th>Nama Unit</th>
              <th>Reviewer</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($grup as $g)
              <tr class="grup" data-grup="{{ $g->kode }}">
                <td><input type="checkbox" class="form-check-input cek-grup" data-grup="{{ $g->kode }}" title="Pilih semua unit kelompok ini"></td>
                <td colspan="3">
                  <span class="kode-grup me-2">{{ $g->kode }}</span>
                  <span class="fw-semibold">{{ $g->nama }}</span>
                  <small class="text-muted">· {{ $g->unit->count() }} unit</small>
                </td>
              </tr>
              @foreach ($g->unit as $u)
                @php
                  $daftar = $penetapan[$u->id_unit] ?? collect();
                @endphp
                <tr class="baris-unit" data-grup="{{ $g->kode }}" data-rv="{{ $daftar->pluck('user_id')->implode(',') }}"
                    data-cari="{{ mb_strtolower($u->kode . ' ' . $u->nama) }}">
                  <td><input type="checkbox" class="form-check-input cek-unit" value="{{ $u->id_unit }}"></td>
                  <td><span class="font-monospace">{{ $u->kode }}</span></td>
                  <td class="nama">{{ $u->nama }}</td>
                  <td>
                    @forelse ($daftar as $p)
                      @php
                        $n = $direview[$u->id_unit][$p->user_id] ?? 0;
                      @endphp
                      <span class="chip-rv">
                        {{ $namaReviewer[$p->user_id] ?? 'Akun #' . $p->user_id }}
                        @if ($n)<span class="n" title="Kegiatan yang sudah direview">· {{ $n }} keg.</span>@endif
                        <form method="POST" action="{{ route('admin.review.hapus', $p) }}" class="form-hapus"
                              data-label="{{ ($namaReviewer[$p->user_id] ?? 'reviewer') . ' dari ' . $u->kode . ' - ' . $u->nama }}" data-n="{{ $n }}">
                          @csrf
                          @method('DELETE')
                          <button type="submit" title="Hapus penetapan"><i class="ti ti-x ti-xs"></i></button>
                        </form>
                      </span>
                    @empty
                      <span class="text-danger small">Belum ada reviewer</span>
                    @endforelse
                  </td>
                </tr>
              @endforeach
            @endforeach
            <tr id="rv-kosong" class="d-none"><td colspan="4" class="text-center text-muted py-5">Tidak ada unit yang cocok.</td></tr>
          </tbody>
        </table>
      </div>

      {{-- Bilah aksi massal --}}
      <div class="card-footer bilah-aksi bg-body border-top d-none" id="bilah-aksi">
        <form method="POST" action="{{ route('admin.review.tetapkan') }}" id="form-massal" class="d-flex flex-wrap gap-2 align-items-center">
          @csrf
          <span class="fw-semibold me-1"><span id="jumlah-pilih">0</span> unit dipilih</span>
          <select name="user_id" id="massal-reviewer" class="form-select" style="width: auto; min-width: 220px;" required>
            @foreach ($reviewer as $r)
              <option value="{{ $r->idlog }}">{{ $r->nama_lengkap ?: $r->username }}</option>
            @endforeach
          </select>
          <button type="submit" class="btn btn-primary"><i class="ti ti-user-plus me-1"></i> Tetapkan</button>
          <button type="button" class="btn btn-label-danger" id="btn-lepas"><i class="ti ti-user-minus me-1"></i> Lepas</button>
          <button type="button" class="btn btn-text-secondary ms-auto" id="btn-batal-pilih">Batal pilih</button>
          <div id="massal-unit"></div>
        </form>
        <form method="POST" action="{{ route('admin.review.lepas') }}" id="form-lepas" class="d-none">
          @csrf
          <input type="hidden" name="user_id" id="lepas-reviewer">
          <div id="lepas-unit"></div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const esc = s => $('<div>').text(s ?? '').html();
  const baris = () => Array.from(document.querySelectorAll('tr.baris-unit'));

  // ---- Filter: kata kunci + reviewer -------------------------------------------
  const cari = document.getElementById('rv-cari');
  let rv = '';
  function saring() {
    const k = cari.value.trim().toLowerCase(), grupTampil = new Set();
    let ada = 0;
    baris().forEach(function (tr) {
      const daftar = tr.dataset.rv ? tr.dataset.rv.split(',') : [];
      const cocokRv = !rv || (rv === 'kosong' ? daftar.length === 0 : daftar.includes(rv));
      const tampil = (!k || tr.dataset.cari.includes(k)) && cocokRv;
      tr.classList.toggle('d-none', !tampil);
      if (tampil) { grupTampil.add(tr.dataset.grup); ada++; }
    });
    document.querySelectorAll('tr.grup').forEach(tr => tr.classList.toggle('d-none', !grupTampil.has(tr.dataset.grup)));
    document.getElementById('rv-kosong').classList.toggle('d-none', ada > 0);
    perbaruiPilihan();
  }
  cari.addEventListener('input', saring);
  document.querySelectorAll('#rv-daftar [data-rv]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#rv-daftar [data-rv]').forEach(b => b.classList.toggle('active', b === btn));
      rv = btn.dataset.rv;
      // Saat menyaring per reviewer, jadikan reviewer itu pilihan default bilah aksi.
      if (rv && rv !== 'kosong') document.getElementById('massal-reviewer').value = rv;
      saring();
    });
  });

  // ---- Pilihan unit ---------------------------------------------------------------
  const bilah = document.getElementById('bilah-aksi');
  function terpilih() {
    return baris().filter(tr => !tr.classList.contains('d-none') && tr.querySelector('.cek-unit').checked)
      .map(tr => tr.querySelector('.cek-unit').value);
  }
  function perbaruiPilihan() {
    baris().forEach(tr => tr.classList.toggle('terpilih', tr.querySelector('.cek-unit').checked));
    const n = terpilih().length;
    document.getElementById('jumlah-pilih').textContent = n;
    bilah.classList.toggle('d-none', n === 0);
  }
  document.querySelectorAll('.cek-unit').forEach(c => c.addEventListener('change', perbaruiPilihan));
  document.querySelectorAll('.cek-grup').forEach(function (c) {
    c.addEventListener('change', function () {
      baris().filter(tr => tr.dataset.grup === c.dataset.grup && !tr.classList.contains('d-none'))
        .forEach(tr => { tr.querySelector('.cek-unit').checked = c.checked; });
      perbaruiPilihan();
    });
  });
  document.getElementById('cek-semua').addEventListener('change', function () {
    baris().filter(tr => !tr.classList.contains('d-none')).forEach(tr => { tr.querySelector('.cek-unit').checked = this.checked; });
    document.querySelectorAll('.cek-grup').forEach(c => { c.checked = this.checked; });
    perbaruiPilihan();
  });
  document.getElementById('btn-batal-pilih').addEventListener('click', function () {
    document.querySelectorAll('.cek-unit, .cek-grup, #cek-semua').forEach(c => { c.checked = false; });
    perbaruiPilihan();
  });

  function isiUnit(wadah) {
    wadah.innerHTML = terpilih().map(id => '<input type="hidden" name="unit_id[]" value="' + id + '">').join('');
  }
  document.getElementById('form-massal').addEventListener('submit', function () { isiUnit(document.getElementById('massal-unit')); });

  document.getElementById('btn-lepas').addEventListener('click', function () {
    const sel = document.getElementById('massal-reviewer');
    Swal.fire({
      title: 'Lepas reviewer?',
      html: '<b>' + esc(sel.options[sel.selectedIndex].text) + '</b> akan dilepas dari ' + terpilih().length + ' unit terpilih.',
      icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, lepas', cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false
    }).then(function (r) {
      if (!r.isConfirmed) return;
      document.getElementById('lepas-reviewer').value = sel.value;
      isiUnit(document.getElementById('lepas-unit'));
      document.getElementById('form-lepas').submit();
    });
  });

  // ---- Hapus satu penetapan -----------------------------------------------------------
  document.querySelectorAll('.form-hapus').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      const n = Number(f.dataset.n);
      Swal.fire({
        title: 'Hapus penetapan?',
        html: 'Lepas <b>' + esc(f.dataset.label) + '</b>?' +
          (n ? '<br><span class="text-warning">Reviewer ini sudah mereview ' + n + ' kegiatan unit tersebut; hasil review tetap tersimpan.</span>' : ''),
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false
      }).then(r => { if (r.isConfirmed) f.submit(); });
    });
  });

  // ---- Salin dari tahun lalu -----------------------------------------------------------
  document.getElementById('form-salin')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const f = this;
    Swal.fire({
      title: 'Salin penetapan?', text: 'Penetapan reviewer T.A sebelumnya akan disalin ke T.A ini.',
      icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, salin', cancelButtonText: 'Batal',
      customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-label-secondary' }, buttonsStyling: false
    }).then(r => { if (r.isConfirmed) f.submit(); });
  });
});
</script>
@endsection
