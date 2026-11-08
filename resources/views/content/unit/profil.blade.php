@extends('layouts/layoutMaster')

@section('title', 'Visi & Misi')

@section('page-style')
<style>
  .profil-form textarea { font-size: .95rem; line-height: 1.6; resize: vertical; }
  .profil-form .bagian + .bagian { border-top: 1px dashed var(--bs-border-color); padding-top: 1.5rem; margin-top: 1.5rem; }
  .profil-form .judul-bagian { display: flex; align-items: center; gap: .5rem; }
  .profil-form .judul-bagian .ikon { width: 2rem; height: 2rem; border-radius: .5rem; display: grid; place-items: center;
                                     background: rgba(var(--bs-primary-rgb), .1); color: var(--bs-primary); }

  /* Pratinjau = tampilan di dashboard unit */
  .pratinjau { font-size: .9rem; line-height: 1.6; min-height: 100%; padding: .75rem; border-radius: .5rem; background: rgba(var(--bs-secondary-rgb), .05); }
  .pratinjau ol { padding-left: 0; margin: 0; list-style: none; counter-reset: poin; }
  .pratinjau ol li { position: relative; padding: .4rem .5rem .4rem 2.5rem; border-radius: .4rem; counter-increment: poin; }
  .pratinjau ol li:nth-child(odd) { background: rgba(var(--bs-primary-rgb), .04); }
  .pratinjau ol li::before { content: counter(poin); position: absolute; left: .5rem; top: .4rem; width: 1.5rem; height: 1.5rem; border-radius: 50%;
                             display: grid; place-items: center; font-size: .75rem; font-weight: 700;
                             background: rgba(var(--bs-primary-rgb), .14); color: var(--bs-primary); }
  .pratinjau .visi { padding: .75rem 1rem; border-left: 4px solid var(--bs-primary); font-style: italic; background: rgba(var(--bs-primary-rgb), .06); border-radius: .5rem; }
  .pratinjau .kosong { color: var(--bs-secondary); font-style: italic; }
  .label-kecil { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary); }
</style>
@endsection

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-file-description"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Visi &amp; Misi Unit</h4>
      <p class="pg-header-sub mb-0">{{ $unit->kode }} · {{ $unit->nama }}</p>
    </div>
    <a href="{{ url('unit') }}" class="pg-header-chip text-white text-decoration-none"><i class="ti ti-layout-dashboard"></i><span>Lihat di dashboard</span></a>
  </div>
</div>

@include('_partials._flash')

<form method="POST" action="{{ route('unit.profil.update') }}" class="card profil-form">
  @csrf
  @method('PUT')
  <div class="card-body">
    <div class="alert alert-info small d-flex gap-2 mb-4">
      <i class="ti ti-info-circle mt-1"></i>
      <div>Tulis satu poin per baris dengan awalan nomor (mis. <code>1. ...</code>, <code>2. ...</code>) agar tampil sebagai daftar bernomor di dashboard.</div>
    </div>

    @foreach ($bagian as $kolom => [$judul, $ikon, $petunjuk])
      <div class="bagian row g-3">
        <div class="col-lg-7">
          <label class="form-label judul-bagian mb-2" for="f-{{ $kolom }}">
            <span class="ikon"><i class="ti {{ $ikon }}"></i></span>
            <span><span class="fw-semibold d-block">{{ $judul }}</span><small class="text-muted fw-normal">{{ $petunjuk }}</small></span>
          </label>
          <textarea id="f-{{ $kolom }}" name="{{ $kolom }}" rows="{{ $kolom === 'visi' ? 3 : 6 }}" data-kolom="{{ $kolom }}"
                    class="form-control isian @error($kolom) is-invalid @enderror">{{ old($kolom, $unit->{$kolom}) }}</textarea>
          @error($kolom)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-5">
          <div class="label-kecil mb-2">Pratinjau</div>
          <div class="pratinjau" id="pratinjau-{{ $kolom }}"></div>
        </div>
      </div>
    @endforeach
  </div>
  <div class="card-footer d-flex justify-content-end gap-2 border-top">
    <a href="{{ url('unit') }}" class="btn btn-label-secondary">Batal</a>
    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan</button>
  </div>
</form>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const esc = s => $('<div>').text(s).html();

  // Aturan sama dengan dashboard: bila >= setengah baris diawali nomor, tampilkan sebagai daftar.
  function render(kolom, teks) {
    const baris = teks.split(/\r?\n/).map(b => b.trim()).filter(Boolean);
    if (!baris.length) return '<span class="kosong">Belum diisi.</span>';
    const bernomor = baris.filter(b => /^\d+\s*[.)]/.test(b)).length;
    if (baris.length > 1 && bernomor >= baris.length / 2) {
      return '<ol>' + baris.map(b => '<li>' + esc(b.replace(/^\d+\s*[.)]\s*/, '')) + '</li>').join('') + '</ol>';
    }
    const isi = esc(baris.join('\n')).replace(/\n/g, '<br>');
    return kolom === 'visi' ? '<div class="visi">' + isi + '</div>' : '<p class="mb-0">' + isi + '</p>';
  }

  document.querySelectorAll('textarea.isian').forEach(function (ta) {
    const target = document.getElementById('pratinjau-' + ta.dataset.kolom);
    const segarkan = () => { target.innerHTML = render(ta.dataset.kolom, ta.value); };
    ta.addEventListener('input', segarkan);
    segarkan();
  });
});
</script>
@endsection
