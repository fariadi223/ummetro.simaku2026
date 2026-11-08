@extends('layouts/layoutMaster')

@section('title', ($item ? 'Ubah ' : 'Tambah ') . ucfirst($jenis))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .volume-baris { display: grid; grid-template-columns: 2rem 1fr 1.2fr; gap: .5rem; align-items: center; margin-bottom: .5rem; }
  .volume-baris .kali { text-align: center; color: var(--bs-secondary); font-weight: 700; }
  .hitung { border-radius: .75rem; padding: 1.25rem; background: rgba(var(--bs-primary-rgb), .06); }
  .hitung .label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary); }
  .hitung .total { font-size: 1.6rem; font-weight: 800; font-variant-numeric: tabular-nums; }
  .hitung .rumus { font-size: .85rem; color: var(--bs-secondary); word-break: break-word; }
  .rp { font-variant-numeric: tabular-nums; }
</style>
@endsection

@php
  $label = ucfirst($jenis);
  $aksi = $item ? route('unit.rinci.update', [$keg->idkeg, $item->idrinci]) : route('unit.rinci.store', [$keg->idkeg, $jenis]);
  $nilai = fn ($k, $d = '') => old($k, $item ? $item->{$k} : $d);
  // qty 0 pada data lama = kosong
  $qty = fn ($i) => old("qty{$i}", $item && (float) $item->{"qty{$i}"} != 0 ? rtrim(rtrim(number_format((float) $item->{"qty{$i}"}, 4, '.', ''), '0'), '.') : '');
  $paguInfo = $pagu ? ['nominal' => $pagu['nominal'], 'belanja' => $pagu['belanja_unit'], 'lama' => $item ? \App\Models\Rinci::nilai($item) : 0] : null;
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti {{ $jenis === 'pendapatan' ? 'ti-arrow-down-left' : 'ti-arrow-up-right' }}"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">{{ $item ? 'Ubah' : 'Tambah' }} {{ $label }}</h4>
      <p class="pg-header-sub mb-0">{{ $keg->kode_mak }} · {{ $keg->uraiankeg }}</p>
    </div>
    @if (($komite['sisa_hari'] ?? 0) > 0 && $komite['dibuka'])
      <span class="pg-header-chip"><i class="ti ti-clock"></i><span>Waktu tersisa <strong>{{ $komite['sisa_hari'] }} hari</strong></span></span>
    @endif
  </div>
</div>

@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->has('hargasat') && str_starts_with($errors->first('hargasat'), 'Melebihi') ? $errors->first('hargasat') : 'Data yang dimasukkan belum lengkap atau tidak valid.' }}</div>
@endif

<form method="POST" action="{{ $aksi }}" class="card" id="form-rinci">
  @csrf
  @if ($item) @method('PUT') @endif
  <div class="card-body">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="mb-3">
          <label class="form-label fw-semibold" for="f-idrek6">Nama Rekening</label>
          <select id="f-idrek6" name="idrek6" class="form-select @error('idrek6') is-invalid @enderror">
            <option value=""></option>
            @foreach ($rekening->groupBy(fn ($r) => $r->namarek4 . ' › ' . $r->namarek5) as $grup => $daftar)
              <optgroup label="{{ $grup }}">
                @foreach ($daftar as $r)
                  <option value="{{ $r->idrek6 }}" @selected((int) $nilai('idrek6') === $r->idrek6)>{{ $r->kode }} — {{ $r->namarek6 }}</option>
                @endforeach
              </optgroup>
            @endforeach
          </select>
          @error('idrek6')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold" for="f-uraian">Uraian</label>
          <input type="text" id="f-uraian" name="uraianrinci" maxlength="198" class="form-control @error('uraianrinci') is-invalid @enderror"
                 value="{{ $nilai('uraianrinci') }}" placeholder="Mis. Honor Ketua" required>
          @error('uraianrinci')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <label class="form-label fw-semibold mb-2">Volume</label>
        <div class="volume-baris small text-muted mb-1"><span></span><span>Kuantitas</span><span>Satuan</span></div>
        @foreach ([1, 2, 3, 4] as $i)
          <div class="volume-baris">
            <span class="kali">{{ $i === 1 ? '' : '×' }}</span>
            <input type="number" step="any" min="0" name="qty{{ $i }}" class="form-control qty @error('qty' . $i) is-invalid @enderror"
                   value="{{ $qty($i) }}" placeholder="{{ $i === 1 ? '0' : 'opsional' }}" @if ($i === 1) required @endif>
            <input type="text" name="satuan{{ $i }}" maxlength="{{ $i === 1 ? 50 : 15 }}" class="form-control @error('satuan' . $i) is-invalid @enderror"
                   value="{{ $nilai('satuan' . $i) }}" placeholder="{{ $i === 1 ? 'Mis. Orang' : 'Mis. Hari' }}">
          </div>
          @error('qty' . $i)<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        @endforeach
        <div class="small text-muted">Kuantitas ke-2 s.d. ke-4 boleh dikosongkan (dihitung 1).</div>

        <div class="mt-4">
          <label class="form-label fw-semibold" for="f-harga">Harga Satuan</label>
          <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="text" inputmode="numeric" id="f-harga" name="hargasat" class="form-control text-end @error('hargasat') is-invalid @enderror"
                   value="{{ $nilai('hargasat') }}" required>
          </div>
          @error('hargasat')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="col-lg-5">
        <div class="hitung">
          <div class="label">Jumlah</div>
          <div class="total" id="h-total">Rp 0</div>
          <div class="rumus mt-1" id="h-rumus">—</div>
          @if ($paguInfo)
            <hr>
            <div class="label mb-1">Pagu {{ $pagu['sumber'] }}</div>
            <div class="d-flex justify-content-between small"><span>Pagu unit</span><span class="rp">Rp {{ number_format($paguInfo['nominal'], 0, ',', '.') }}</span></div>
            <div class="d-flex justify-content-between small"><span>Belanja unit setelah disimpan</span><span class="rp" id="h-setelah"></span></div>
            <div class="d-flex justify-content-between small fw-semibold mt-1"><span>Sisa pagu</span><span class="rp" id="h-sisa"></span></div>
            <div class="alert alert-danger small mt-2 mb-0 d-none" id="h-lebih">Melebihi pagu — rincian tidak akan dapat disimpan.</div>
          @endif
        </div>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-end gap-2 border-top">
    <a href="{{ route('unit.rinci.index', [$keg->idkeg, 'tab' => $jenis]) }}" class="btn btn-label-secondary">Kembali</a>
    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> Simpan</button>
  </div>
</form>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  $('#f-idrek6').select2({ placeholder: 'Pilih nama rekening', allowClear: true, width: '100%' });

  const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
  const harga = document.getElementById('f-harga');
  const pagu = @json($paguInfo);
  const angkaHarga = () => Number(harga.value.replace(/\D/g, '')) || 0;

  function hitung() {
    harga.value = angkaHarga() ? angkaHarga().toLocaleString('id-ID') : harga.value.replace(/\D/g, '');
    const qty = [1, 2, 3, 4].map(i => document.querySelector('[name="qty' + i + '"]').value);
    const sat = [1, 2, 3, 4].map(i => document.querySelector('[name="satuan' + i + '"]').value.trim());
    let total = angkaHarga(), bagian = [];
    qty.forEach(function (q, i) {
      const n = parseFloat(String(q).replace(',', '.'));
      if (i === 0) { total *= (n || 0); bagian.push((n || 0) + (sat[0] ? ' ' + sat[0] : '')); }
      else if (n) { total *= n; bagian.push(n + (sat[i] ? ' ' + sat[i] : '')); }
    });
    document.getElementById('h-total').textContent = rp(total);
    document.getElementById('h-rumus').textContent = bagian.join(' × ') + ' × ' + rp(angkaHarga());
    if (pagu) {
      const setelah = pagu.belanja - pagu.lama + total, sisa = pagu.nominal - setelah;
      document.getElementById('h-setelah').textContent = rp(setelah);
      const elSisa = document.getElementById('h-sisa');
      elSisa.textContent = rp(sisa);
      elSisa.classList.toggle('text-danger', sisa < 0);
      document.getElementById('h-lebih').classList.toggle('d-none', sisa >= 0);
    }
  }
  document.querySelectorAll('#form-rinci .qty, #form-rinci [name^="satuan"], #f-harga').forEach(el => el.addEventListener('input', hitung));
  hitung();
});
</script>
@endsection
