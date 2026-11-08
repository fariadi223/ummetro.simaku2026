@extends('layouts/layoutMaster')

@section('title', $meta['judul'])

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('page-style')
<style>
  /* Stepper jalur level, gaya "kaca" di atas .pg-header bergradasi. */
  .coa-steps { list-style: none; padding: 0; display: grid; grid-template-columns: repeat(var(--coa-kolom, 6), minmax(0, 1fr)); gap: .75rem; }
  .coa-step-link { display: contents; color: inherit; text-decoration: none; }
  .coa-step { position: relative; min-width: 0; display: flex; gap: .65rem; align-items: flex-start; padding: .75rem;
              border-radius: 12px; color: #fff; background: rgba(255, 255, 255, .1);
              border: 1px solid rgba(255, 255, 255, .18); transition: all .15s ease; }
  .coa-step-dot { flex-shrink: 0; width: 2rem; height: 2rem; border-radius: 50%; display: grid; place-items: center;
                  font-weight: 600; font-size: .85rem; background: rgba(255, 255, 255, .16); color: #fff; }
  .coa-step-body { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
  .coa-step-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: rgba(255, 255, 255, .7); }
  .coa-step-kode { font-size: .8rem; font-weight: 600; }
  .coa-step-nama { font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: rgba(255, 255, 255, .85); }

  .coa-step-selesai { cursor: pointer; }
  .coa-step-selesai:hover { background: rgba(255, 255, 255, .18); transform: translateY(-2px); }
  .coa-step-selesai .coa-step-dot { background: rgba(255, 255, 255, .9); color: var(--bs-primary); }

  /* Level yang sedang dibuka: kartu putih solid */
  .coa-step-aktif { background: #fff; border-color: #fff; color: var(--bs-primary);
                    box-shadow: 0 10px 24px -12px rgba(0, 0, 0, .45); }
  .coa-step-aktif .coa-step-dot { background: var(--bs-primary); color: #fff; }
  .coa-step-aktif .coa-step-label { color: var(--bs-primary); font-weight: 700; }
  .coa-step-aktif .coa-step-nama { color: #6f6b7d; }

  .coa-step-nanti { border-style: dashed; background: transparent; opacity: .75; }

  @media (max-width: 1199.98px) { .coa-steps { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
  @media (max-width: 575.98px)  { .coa-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@php
  // Aksi form tambah: level anak yang sedang ditampilkan.
  $rt = $meta['route'];
  $Istilah = ucfirst($meta['istilah']);
  $urlSimpan = route("{$rt}.store", $levelAnak);
  $bisaPunyaAnak = $levelAnak < $levelMax;
@endphp

@section('content')
{{-- Header + jalur level --}}
<div class="pg-header mb-4">
  <div>
    <div class="d-flex flex-wrap align-items-center gap-3">
      <div class="pg-header-icon"><i class="{{ $meta['ikon'] }}"></i></div>
      <div class="flex-grow-1" style="min-width: 0;">
        <h4 class="mb-1">{{ $meta['judul'] }}</h4>
        <p class="pg-header-sub mb-0">{{ $meta['sub'] }}</p>
      </div>
      <span class="pg-header-chip">
        <i class="ti ti-database"></i>
        <span><strong>{{ number_format(array_sum($statistik), 0, ',', '.') }}</strong> data</span>
      </span>
    </div>

    <ol class="coa-steps mt-4 mb-0" style="--coa-kolom: {{ $levelMax }};" aria-label="Jalur level {{ $meta['istilah'] }}">
      @for ($l = 1; $l <= $levelMax; $l++)
        @php
          $titik = $jejak[$l - 1] ?? null;            // rekening yang dipilih di level ini
          $status = $titik ? 'selesai' : ($l === $levelAnak ? 'aktif' : 'nanti');
          // Klik langkah yang sudah dilewati -> tampilkan daftar di level itu.
          $urlLangkah = $titik
            ? ($l === 1 ? route("{$rt}.index") : route("{$rt}.show", [$l - 1, $jejak[$l - 2]['id']]))
            : null;
        @endphp
        <li class="coa-step coa-step-{{ $status }}" @if ($status === 'aktif') aria-current="step" @endif>
          @if ($urlLangkah)<a href="{{ $urlLangkah }}" class="coa-step-link" title="Kembali ke daftar level {{ $l }}">@endif
            <span class="coa-step-dot">
              @if ($status === 'selesai')<i class="ti ti-check"></i>@else{{ $l }}@endif
            </span>
            <span class="coa-step-body">
              <span class="coa-step-label">Level {{ $l }}</span>
              @if ($titik)
                <span class="coa-step-kode font-monospace">{{ $titik['kode_lengkap'] }}</span>
                <span class="coa-step-nama" title="{{ $titik['nama'] }}">{{ $titik['nama'] }}</span>
              @elseif ($status === 'aktif')
                <span class="coa-step-nama">{{ $daftar->count() }} data ditampilkan</span>
              @else
                <span class="coa-step-nama">{{ number_format($statistik[$l] ?? 0, 0, ',', '.') }} data</span>
              @endif
            </span>
          @if ($urlLangkah)</a>@endif
        </li>
      @endfor
    </ol>
  </div>
</div>

@include('_partials._flash')

<div class="card">
  <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <div>
      <h5 class="mb-0">
        @if ($induk)
          {{ $induk->kode_lengkap }} &mdash; {{ $induk->nama }}
        @else
          {{ $Istilah }} Level 1
        @endif
      </h5>
      <small class="text-muted">
        Menampilkan {{ $daftar->count() }} {{ $meta['istilah'] }} level {{ $levelAnak }}
      </small>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div style="min-width: 300px;">
        <select id="coa-cari" class="form-select" data-placeholder="Cari kode / nama {{ $meta['istilah'] }}..."></select>
      </div>
      @if ($induk)
        @php
          $naik = count($jejak) > 1 ? $jejak[count($jejak) - 2] : null;
        @endphp
        <a href="{{ $naik ? route("{$rt}.show", [$naik['level'], $naik['id']]) : route("{$rt}.index") }}"
           class="btn btn-label-secondary">
          <i class="ti ti-arrow-left me-1"></i> Kembali
        </a>
      @endif
      <a href="{{ route("{$rt}.cetak") }}" target="_blank" rel="noopener" class="btn btn-label-info"
         title="Cetak seluruh {{ $meta['istilah'] }} semua level">
        <i class="ti ti-printer me-1"></i> Cetak Semua
      </a>
      <a href="{{ route("{$rt}.export") }}" class="btn btn-label-success">
        <i class="ti ti-file-spreadsheet me-1"></i> Unduh Excel
      </a>
      <button type="button" class="btn btn-primary" id="btn-tambah">
        <i class="ti ti-plus me-1"></i> Tambah Level {{ $levelAnak }}
      </button>
    </div>
  </div>

  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th style="width: 180px;">Kode</th>
          <th>Nama {{ $Istilah }}</th>
          @foreach ($tambahan as $def)
            <th class="text-center" style="width: 90px;">{{ $def['label'] }}</th>
          @endforeach
          @if ($bisaPunyaAnak)
            <th class="text-center" style="width: 120px;">{{ ucfirst($meta['anak']) }}</th>
          @endif
          <th class="text-end" style="width: 160px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($daftar as $r)
          <tr>
            <td><span class="font-monospace fw-semibold">{{ $r->kode_lengkap }}</span></td>
            <td class="text-wrap">
              @if ($bisaPunyaAnak)
                <a href="{{ route("{$rt}.show", [$levelAnak, $r->id]) }}">{{ $r->nama }}</a>
              @else
                {{ $r->nama }}
              @endif
            </td>
            @foreach ($tambahan as $kolom => $def)
              <td class="text-center">{{ $r->{$kolom} }}</td>
            @endforeach
            @if ($bisaPunyaAnak)
              <td class="text-center">
                <span class="badge {{ $r->jumlah_anak ? 'bg-label-primary' : 'bg-label-secondary' }}">{{ $r->jumlah_anak }}</span>
              </td>
            @endif
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                @if ($bisaPunyaAnak && $r->jumlah_anak)
                  <button type="button" class="btn btn-sm btn-icon btn-text-info rounded-pill btn-salin"
                          title="Salin {{ $meta['anak'] }} ke {{ $meta['istilah'] }} lain"
                          data-url="{{ route("{$rt}.salin", [$levelAnak, $r->id]) }}"
                          data-label="{{ $r->kode_lengkap }} - {{ $r->nama }}">
                    <i class="ti ti-copy"></i>
                  </button>
                @endif
                <button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill btn-ubah"
                        title="Ubah"
                        data-url="{{ route("{$rt}.update", [$levelAnak, $r->id]) }}"
                        data-kode="{{ $r->kode }}" data-nama="{{ $r->nama }}"
                        data-tambahan="{{ json_encode(collect($tambahan)->keys()->mapWithKeys(fn ($k) => [$k => $r->{$k}])) }}">
                  <i class="ti ti-pencil"></i>
                </button>
                <form method="POST" action="{{ route("{$rt}.destroy", [$levelAnak, $r->id]) }}" class="form-hapus"
                      data-label="{{ $r->kode_lengkap }} - {{ $r->nama }}">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-icon btn-text-danger rounded-pill" title="Hapus">
                    <i class="ti ti-trash"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="{{ 3 + count($tambahan) + ($bisaPunyaAnak ? 1 : 0) }}" class="text-center text-muted py-5">
              Belum ada {{ $meta['istilah'] }} level {{ $levelAnak }}{{ $induk ? ' di bawah ' . $induk->kode_lengkap : '' }}.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Modal tambah / ubah --}}
<div class="modal fade" id="modal-rekening" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="{{ $urlSimpan }}" class="modal-content" id="form-rekening">
      @csrf
      <input type="hidden" name="_method" value="POST" id="form-rekening-method">
      <input type="hidden" name="_form_action" value="{{ $urlSimpan }}" id="form-rekening-action">
      @if ($induk)
        <input type="hidden" name="induk_id" value="{{ $induk->id }}">
      @endif

      <div class="modal-header">
        <h5 class="modal-title" id="modal-rekening-judul">Tambah {{ $Istilah }} Level {{ $levelAnak }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="input-kode">Kode {{ $Istilah }} Level {{ $levelAnak }}</label>
          <div class="input-group">
            @if ($induk)
              <span class="input-group-text font-monospace">{{ $induk->kode_lengkap }}.</span>
            @endif
            <input type="text" id="input-kode" name="kode" inputmode="numeric" maxlength="{{ $panjang['kode'] }}"
                   class="form-control font-monospace @error('kode') is-invalid @enderror" value="{{ old('kode') }}" required>
            @error('kode')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <small class="text-muted">Angka, maksimal {{ $panjang['kode'] }} digit.</small>
        </div>
        <div>
          <label class="form-label" for="input-nama">Nama {{ $Istilah }}</label>
          <input type="text" id="input-nama" name="nama" maxlength="{{ $panjang['nama'] }}"
                 class="form-control @error('nama') is-invalid @enderror" value="{{ old('nama') }}" required>
          @error('nama')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @if ($tambahan)
          <div class="row g-3 mt-0">
            @foreach ($tambahan as $kolom => $def)
              <div class="col">
                <label class="form-label" for="input-{{ $kolom }}">{{ $def['label'] }}</label>
                <input type="number" min="0" id="input-{{ $kolom }}" name="tambahan[{{ $kolom }}]" data-tambahan="{{ $kolom }}"
                       class="form-control @error('tambahan.' . $kolom) is-invalid @enderror"
                       value="{{ old('tambahan.' . $kolom, $def['bawaan'] ?? '') }}">
                @error('tambahan.' . $kolom)<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            @endforeach
          </div>
        @endif
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

{{-- Modal salin sub-akun --}}
@if ($bisaPunyaAnak)
<div class="modal fade" id="modal-salin" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="POST" action="#" class="modal-content" id="form-salin">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Salin {{ ucfirst($meta['anak']) }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Semua {{ $meta['anak'] }} (sampai level {{ $levelMax }}) dari:</p>
        <p class="fw-semibold font-monospace" id="salin-sumber"></p>
        <label class="form-label" for="salin-tujuan">disalin ke {{ $meta['istilah'] }} level {{ $levelAnak }}:</label>
        <select id="salin-tujuan" name="tujuan_id" class="form-select" required></select>
        <small class="text-muted d-block mt-2">Kode yang sudah ada di tujuan tidak akan diduplikasi.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-info">Salin</button>
      </div>
    </form>
  </div>
</div>
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const urlCari = @json(route("{$rt}.cari"));
  const istilah = @json($Istilah);
  const bawaanTambahan = @json(collect($tambahan)->map(fn ($d) => $d['bawaan'] ?? ''));
  const urlSimpan = @json($urlSimpan);
  const levelAnak = @json($levelAnak);

  // ---- Tambah / ubah -------------------------------------------------------
  const modalRek = new bootstrap.Modal(document.getElementById('modal-rekening'));
  const formRek = document.getElementById('form-rekening');
  const judul = document.getElementById('modal-rekening-judul');
  const method = document.getElementById('form-rekening-method');
  const kode = document.getElementById('input-kode');
  const nama = document.getElementById('input-nama');

  function isiTambahan(nilai) {
    formRek.querySelectorAll('[data-tambahan]').forEach(function (el) {
      const k = el.dataset.tambahan;
      el.value = (nilai && nilai[k] !== undefined && nilai[k] !== null) ? nilai[k] : (bawaanTambahan[k] ?? '');
    });
  }

  function bukaForm(url, metode, teksJudul, nilaiKode, nilaiNama, bersihkanError, nilaiTambahan) {
    formRek.action = url;
    if (nilaiTambahan !== false) isiTambahan(nilaiTambahan);
    document.getElementById('form-rekening-action').value = url;
    method.value = metode;
    judul.textContent = teksJudul;
    kode.value = nilaiKode;
    nama.value = nilaiNama;
    if (bersihkanError) {
      formRek.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }
    modalRek.show();
    setTimeout(() => kode.focus(), 300);
  }

  document.getElementById('btn-tambah').addEventListener('click', function () {
    bukaForm(urlSimpan, 'POST', 'Tambah ' + istilah + ' Level ' + levelAnak, '', '', true, null);
  });

  document.querySelectorAll('.btn-ubah').forEach(function (btn) {
    btn.addEventListener('click', function () {
      bukaForm(btn.dataset.url, 'PUT', 'Ubah ' + istilah + ' Level ' + levelAnak, btn.dataset.kode, btn.dataset.nama, true,
        JSON.parse(btn.dataset.tambahan || '{}'));
    });
  });

  // Buka kembali form bila validasi gagal.
  @if ($errors->any())
    @php
      $aksiLama = (string) old('_form_action');
      $aksiLama = str_starts_with($aksiLama, url('/') . '/') ? $aksiLama : $urlSimpan;
      $ubah = old('_method') === 'PUT' && $aksiLama !== $urlSimpan;
    @endphp
    bukaForm(@json($aksiLama), @json($ubah ? 'PUT' : 'POST'),
      @json(($ubah ? 'Ubah' : 'Tambah') . " {$Istilah} Level {$levelAnak}"),
      @json(old('kode', '')), @json(old('nama', '')), false, false);
  @endif

  // ---- Hapus ---------------------------------------------------------------
  document.querySelectorAll('.form-hapus').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      Swal.fire({
        title: 'Hapus ' + istilah + '?',
        html: istilah + ' <b>' + $('<div>').text(form.dataset.label).html() + '</b> akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
      }).then(r => { if (r.isConfirmed) form.submit(); });
    });
  });

  // ---- Pencarian global ----------------------------------------------------
  function select2Ajax($el, opsi) {
    $el.select2(Object.assign({
      minimumInputLength: 1,
      language: {
        inputTooShort: () => 'Ketik kode atau nama ' + istilah,
        noResults: () => istilah + ' tidak ditemukan',
        searching: () => 'Mencari...'
      },
      ajax: { url: urlCari, delay: 250, data: p => ({ q: p.term }), processResults: d => d }
    }, opsi));
  }

  const $cari = $('#coa-cari');
  select2Ajax($cari, {
    placeholder: $cari.data('placeholder'),
    templateResult: r => r.level ? $('<span>').text(r.text).append(' <small class="text-muted">(level ' + r.level + ')</small>') : r.text
  });
  $cari.on('select2:select', e => { window.location = e.params.data.url; });

  // ---- Salin sub-akun ------------------------------------------------------
  const elSalin = document.getElementById('modal-salin');
  if (elSalin) {
    const modalSalin = new bootstrap.Modal(elSalin);
    const $tujuan = $('#salin-tujuan');
    select2Ajax($tujuan, {
      dropdownParent: $(elSalin),
      placeholder: 'Cari ' + istilah + ' tujuan...',
      ajax: { url: urlCari, delay: 250, data: p => ({ q: p.term, level: levelAnak }), processResults: d => d }
    });

    document.querySelectorAll('.btn-salin').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('form-salin').action = btn.dataset.url;
        document.getElementById('salin-sumber').textContent = btn.dataset.label;
        $tujuan.val(null).trigger('change');
        modalSalin.show();
      });
    });
  }
});
</script>
@endsection
