@extends('layouts/layoutMaster')

@section('title', 'Buku Besar')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

@section('page-style')
<style>
  .bb-akun-wrap { max-height: 300px; overflow: auto; }
  .bb-tabel { font-size: .8rem; margin: 0; }
  .bb-tabel th, .bb-tabel td { white-space: nowrap; vertical-align: middle; }
  .bb-tabel thead th { position: sticky; top: 0; z-index: 2; background-color: var(--bs-card-bg, #fff); }
  #tabelAkun > :not(caption) > * > *, #tabelDetail > :not(caption) > * > * { background-color: var(--bs-card-bg, #fff); }
  .bb-tabel .kode { font-family: var(--bs-font-monospace); font-size: .75rem; }
  .bb-tabel td.n { text-align: right; font-variant-numeric: tabular-nums; }
  .bb-tabel td.uraian { white-space: normal; min-width: 280px; }
  #tabelAkun tr.aktif > td { box-shadow: inset 0 0 0 999px rgba(var(--bs-primary-rgb), .08); }
  #tabelAkun tr.aktif td:first-child { box-shadow: inset 3px 0 0 var(--bs-primary), inset 0 0 0 999px rgba(var(--bs-primary-rgb), .08); }
  .bb-detail-wrap { max-height: 60vh; overflow: auto; }
  #tabelDetail tr.khusus > td { font-weight: 700; box-shadow: inset 0 0 0 999px rgba(var(--bs-secondary-rgb), .07); }
  #tabelDetail td.m { color: var(--bs-danger); }
  .filter-bb .f-sumber { width: 280px; }
  .filter-bb .f-cari { width: 280px; }
  @media (max-width: 575.98px) { .filter-bb .f-sumber, .filter-bb .f-cari { width: 100%; } }
  /* Ringkasan detail: chip berbentuk pill seperti .pg-header-chip, versi untuk di dalam kartu. */
  .bb-chips { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
  .bb-chip { --c: var(--bs-secondary-rgb); display: inline-flex; align-items: center; gap: .45rem; padding: .38rem .8rem .38rem .45rem;
    border-radius: 999px; font-size: .78rem; line-height: 1.15; color: var(--bs-body-color);
    background: rgba(var(--c), .08); border: 1px solid rgba(var(--c), .18); white-space: nowrap; }
  .bb-chip .ik { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0;
    background: rgba(var(--c), .16); color: rgb(var(--c)); font-size: .9rem; }
  .bb-chip .lbl { color: var(--bs-secondary-color); font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; display: block; }
  .bb-chip strong { color: var(--bs-heading-color); font-size: .9rem; font-variant-numeric: tabular-nums; display: block; }
  .bb-chip.c-primary { --c: var(--bs-primary-rgb); }
  .bb-chip.c-info { --c: var(--bs-info-rgb); }
  .bb-chip.c-warning { --c: var(--bs-warning-rgb); }
  .bb-chip.c-success { --c: var(--bs-success-rgb); }
  .bb-chip.c-danger { --c: var(--bs-danger-rgb); }
  .bb-chip-aksi { display: inline-flex; align-items: center; gap: .35rem; padding: .5rem .9rem; border-radius: 999px; font-size: .78rem; font-weight: 500;
    border: 1px solid transparent; text-decoration: none; cursor: pointer; transition: all .15s ease; }
  .bb-chip-aksi i { font-size: .95rem; }
  .bb-chip-aksi.excel { color: rgb(var(--bs-success-rgb)); background: rgba(var(--bs-success-rgb), .1); border-color: rgba(var(--bs-success-rgb), .25); }
  .bb-chip-aksi.excel:hover { color: #fff; background: rgb(var(--bs-success-rgb)); box-shadow: 0 6px 14px -6px rgba(var(--bs-success-rgb), .7); }
  .bb-chip-aksi.ubah { color: #fff; background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 1), #5a4fd6); box-shadow: 0 6px 14px -6px rgba(var(--bs-primary-rgb), .7); }
  .bb-chip-aksi.ubah:hover { filter: brightness(1.08); transform: translateY(-1px); }
  .bb-muat { display: none; }
  .bb-memuat .bb-muat { display: inline-block; }
</style>
@endsection

@php
  $periode = \Illuminate\Support\Carbon::parse($pAwal)->locale('id')->translatedFormat('d M Y') . ' – ' . \Illuminate\Support\Carbon::parse($pAkhir)->locale('id')->translatedFormat('d M Y');
  $urlDetail = route('akuntansi.buku-besar.detail', ['idrek6' => '__ID__']);
  $urlExcel = route('akuntansi.buku-besar.excel', ['idrek6' => '__ID__']);
  $urlKelompok = route('akuntansi.buku-besar.kelompok', ['idrek6' => '__ID__']);
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-book-2"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <h4 class="mb-1">Buku Besar</h4>
      <p class="pg-header-sub mb-0">Laporan Keuangan · T.A {{ $tahun }}/{{ $tahun + 1 }} · {{ $periode }}</p>
    </div>
    <a href="{{ route('akuntansi.buku-besar.semua') }}" class="btn btn-label-warning"><i class="ti ti-file-download me-1"></i>Download Semua (CSV)</a>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header border-bottom">
    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 filter-bb">
      <div class="input-group f-cari">
        <span class="input-group-text"><i class="ti ti-search"></i></span>
        <input type="search" id="cariAkun" class="form-control" placeholder="Cari kode / nama akun">
      </div>
      <div class="f-sumber">
        <select id="f-sumber" class="form-select" data-placeholder="Semua sumber dana">
          <option value=""></option>
          @foreach ($sumber as $s)<option value="{{ $s->id }}" @selected($awal['sumber'] === $s->id)>{{ $s->kode }} · {{ $s->nama_penerimaan }}</option>@endforeach
        </select>
      </div>
    </div>
    <small class="text-muted d-block mt-2">Klik <strong>Lihat</strong> untuk memilih tanggal awal & akhir. Sumber dana berlaku untuk detail dan tombol Excel.</small>
  </div>
  <div class="bb-akun-wrap">
    <table class="table table-sm table-hover bb-tabel" id="tabelAkun">
      <thead><tr><th style="width: 44px;">No</th><th>Kode</th><th>Nama Rekening</th><th class="text-end" style="width: 360px;">Menu</th></tr></thead>
      <tbody>
        @forelse ($akun as $a)
          <tr data-id="{{ $a->idrek6 }}" data-cari="{{ strtolower($a->kode . ' ' . $a->namarek6) }}">
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td class="kode">{{ $a->kode }}</td>
            <td>{{ $a->namarek6 }}</td>
            <td class="text-end">
              <button type="button" class="btn btn-xs btn-primary aksi-lihat"><i class="ti ti-eye ti-xs me-1"></i>Lihat</button>
              <button type="button" class="btn btn-xs btn-label-success aksi-excel"><i class="ti ti-file-spreadsheet ti-xs me-1"></i>Excel</button>
              <button type="button" class="btn btn-xs btn-label-warning aksi-kelompok" data-kelompok="Pembatasan">Pembatasan</button>
              <button type="button" class="btn btn-xs btn-label-warning aksi-kelompok" data-kelompok="Tanpa Pembatasan">Tanpa Pembatasan</button>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-4">Belum ada jurnal pada T.A ini.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card" id="kartuDetail">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div style="min-width: 0;">
      <h5 class="mb-0" id="d-judul">Tabel Rekening</h5>
      <small class="text-muted" id="d-sub">Pilih akun lalu klik <strong>Lihat</strong>.</small>
    </div>
    <div class="bb-chips">
      <div class="bb-chips" id="d-ringkas"></div>
      <button type="button" class="bb-chip-aksi ubah d-none" id="d-ubah"><i class="ti ti-calendar"></i>Ubah tanggal</button>
    </div>
  </div>
  <div class="bb-detail-wrap">
    <table class="table table-sm bb-tabel" id="tabelDetail">
      <thead><tr><th style="width: 44px;">No</th><th>Tgl Transaksi</th><th>No. Bukti</th><th>Uraian</th>
        <th class="text-end">Debet</th><th class="text-end">Kredit</th><th class="text-end">Saldo</th></tr></thead>
      <tbody id="d-isi"><tr><td colspan="7" class="text-center text-muted py-5">Belum ada akun dipilih.</td></tr></tbody>
    </table>
  </div>
</div>
<div class="modal fade" id="modalTanggal" tabindex="-1" aria-labelledby="modalTanggalJudul" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="formTanggal" novalidate>
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="modalTanggalJudul">Pilih Tanggal</h5>
          <small class="text-muted" id="m-akun"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex flex-wrap gap-2 mb-3" id="m-preset">
          <button type="button" class="btn btn-sm btn-label-secondary" data-preset="ta">Seluruh T.A</button>
          <button type="button" class="btn btn-sm btn-label-secondary" data-preset="bulan">Bulan ini</button>
          <button type="button" class="btn btn-sm btn-label-secondary" data-preset="bulan-lalu">Bulan lalu</button>
          <button type="button" class="btn btn-sm btn-label-secondary" data-preset="hari-ini">s/d hari ini</button>
        </div>
        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label fw-semibold" for="m-awal">Tanggal Awal</label>
            <input type="text" id="m-awal" class="form-control" placeholder="Pilih tanggal" readonly>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold" for="m-akhir">Tanggal Akhir</label>
            <input type="text" id="m-akhir" class="form-control" placeholder="Pilih tanggal" readonly>
          </div>
        </div>
        <div class="alert alert-secondary small mt-3 mb-0">
          <div><strong>Saldo Awal per tanggal awal</strong> = saldo awal T.A ({{ \Illuminate\Support\Carbon::parse($pAwal)->format('d-m-Y') }}) + mutasi sampai sehari sebelum tanggal awal.</div>
          <div class="mt-1"><strong>Saldo Akhir per tanggal akhir</strong> = saldo awal per tanggal awal + transaksi dalam rentang.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary"><i class="ti ti-eye me-1"></i>Tampilkan</button>
      </div>
    </form>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const url = { detail: @json($urlDetail), excel: @json($urlExcel), kelompok: @json($urlKelompok) };
  const awal = @json($awal);
  const periode = { awal: @json($pAwal), akhir: @json($pAkhir) };
  const tabelAkun = document.getElementById('tabelAkun');
  const isi = document.getElementById('d-isi');
  const kartu = document.getElementById('kartuDetail');
  const rp = n => (n < 0 ? '−' : '') + Math.abs(Math.round(n)).toLocaleString('id-ID');
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const tglId = t => { if (!t) return ''; const [y, m, d] = t.split('-'); return d + '-' + m + '-' + y; };
  let aktif = null, permintaan = 0;

  // Periode tampil (dipilih di modal Lihat); default seluruh T.A.
  const localeId = {
    firstDayOfWeek: 1,
    weekdays: { shorthand: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'], longhand: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'] },
    months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
      longhand: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'] }
  };
  const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  const jepit = t => t < periode.awal ? periode.awal : (t > periode.akhir ? periode.akhir : t);
  const rentang = { awal: periode.awal, akhir: periode.akhir };
  const opsiTgl = { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y', monthSelectorType: 'static', locale: localeId,
    minDate: periode.awal, maxDate: periode.akhir };
  const fpAwal = flatpickr('#m-awal', Object.assign({}, opsiTgl, { onChange: (d, s) => fpAkhir.set('minDate', s || periode.awal) }));
  const fpAkhir = flatpickr('#m-akhir', Object.assign({}, opsiTgl, { onChange: (d, s) => fpAwal.set('maxDate', s || periode.akhir) }));
  const setModal = (awal, akhir) => {
    fpAwal.set('maxDate', periode.akhir); fpAkhir.set('minDate', periode.awal);
    fpAwal.setDate(awal, false); fpAkhir.setDate(akhir, false);
    fpAwal.set('maxDate', akhir); fpAkhir.set('minDate', awal);
  };
  document.getElementById('m-preset').addEventListener('click', function (e) {
    const b = e.target.closest('[data-preset]');
    if (!b) return;
    const h = new Date();
    const p = {
      'ta': [periode.awal, periode.akhir],
      'bulan': [ymd(new Date(h.getFullYear(), h.getMonth(), 1)), ymd(new Date(h.getFullYear(), h.getMonth() + 1, 0))],
      'bulan-lalu': [ymd(new Date(h.getFullYear(), h.getMonth() - 1, 1)), ymd(new Date(h.getFullYear(), h.getMonth(), 0))],
      'hari-ini': [periode.awal, ymd(h)],
    }[b.dataset.preset];
    const awalP = jepit(p[0]), akhirP = jepit(p[1]);
    setModal(awalP <= akhirP ? awalP : akhirP, akhirP);
  });
  const modalEl = document.getElementById('modalTanggal');
  let akunModal = null;
  const bukaModal = id => {
    akunModal = id;
    const tr = tabelAkun.querySelector('tr[data-id="' + id + '"]');
    document.getElementById('m-akun').textContent = tr ? tr.children[1].textContent + ' — ' + tr.children[2].textContent : '';
    setModal(rentang.awal, rentang.akhir);
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  };
  document.getElementById('formTanggal').addEventListener('submit', function (e) {
    e.preventDefault();
    rentang.awal = document.getElementById('m-awal').value || periode.awal;
    rentang.akhir = document.getElementById('m-akhir').value || periode.akhir;
    if (rentang.awal > rentang.akhir) [rentang.awal, rentang.akhir] = [rentang.akhir, rentang.awal];
    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    lihat(akunModal);
    kartu.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
  document.getElementById('d-ubah').addEventListener('click', () => { if (aktif) bukaModal(aktif); });

  $('#f-sumber').select2({ placeholder: 'Semua sumber dana', allowClear: true, width: '100%' }).on('change', () => muatUlang());

  const parameter = () => {
    const p = new URLSearchParams();
    const sb = $('#f-sumber').val();
    // Rentang tanggal selalu dikirim agar baris Saldo Awal / Saldo Akhir per tanggal tampil.
    p.set('dari', rentang.awal);
    p.set('sampai', rentang.akhir);
    if (sb) p.set('sumber', sb);
    return p;
  };
  const urlDengan = (pola, id, tambahan) => {
    const p = parameter();
    Object.entries(tambahan || {}).forEach(([k, v]) => p.set(k, v));
    const q = p.toString();
    return pola.replace('__ID__', id) + (q ? '?' + q : '');
  };

  // Detail buku besar (Lihat() + dbtabel0() v1).
  const lihat = id => {
    aktif = id;
    tabelAkun.querySelectorAll('tbody tr').forEach(tr => tr.classList.toggle('aktif', tr.dataset.id === String(id)));
    const no = ++permintaan;
    kartu.classList.add('bb-memuat');
    isi.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>Memuat…</td></tr>';
    fetch(urlDengan(url.detail, id), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(data => {
        if (no !== permintaan) return; // hasil permintaan lama, abaikan
        document.getElementById('d-judul').textContent = data.akun.kode + ' — ' + data.akun.nama;
        const p = parameter();
        const ket = [];
        ket.push('Periode ' + tglId(rentang.awal) + ' s/d ' + tglId(rentang.akhir));
        if (p.get('sumber')) ket.push('Sumber: ' + $('#f-sumber option:selected').text());
        document.getElementById('d-sub').textContent = ket.join(' · ');
        document.getElementById('d-ubah').classList.remove('d-none');
        const r = data.ringkas;
        const chip = (warna, ikon, label, nilai) => '<span class="bb-chip ' + warna + '"><span class="ik"><i class="ti ' + ikon + '"></i></span>'
          + '<span><span class="lbl">' + label + '</span><strong>' + nilai + '</strong></span></span>';
        document.getElementById('d-ringkas').innerHTML =
          chip('', 'ti-list-numbers', 'Transaksi', r.transaksi.toLocaleString('id-ID'))
          + chip('c-info', 'ti-arrow-down-right', 'Debet', rp(r.debet))
          + chip('c-warning', 'ti-arrow-up-right', 'Kredit', rp(r.kredit))
          + chip(r.saldo < 0 ? 'c-danger' : 'c-success', 'ti-wallet', 'Saldo Akhir', rp(r.saldo))
          + '<a class="bb-chip-aksi excel" href="' + esc(urlDengan(url.excel, data.akun.id)) + '"><i class="ti ti-file-spreadsheet"></i>Excel</a>';
        if (!data.baris.length) {
          isi.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5">Tidak ada transaksi.</td></tr>';
          return;
        }
        const angka = v => v === null ? '<td class="n"></td>' : '<td class="n">' + rp(v) + '</td>';
        isi.innerHTML = data.baris.map((b, i) => '<tr' + (b.jenis === 'trx' ? '' : ' class="khusus"') + '>'
          + '<td class="text-muted">' + (i + 1) + '</td><td>' + tglId(b.tgl) + '</td><td class="kode">' + esc(b.bukti) + '</td>'
          + '<td class="uraian">' + esc(b.uraian) + '</td>' + angka(b.debet) + angka(b.kredit)
          + '<td class="n' + (b.saldo < 0 ? ' m' : '') + '">' + rp(b.saldo) + '</td></tr>').join('');
      })
      .catch(() => { if (no === permintaan) isi.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-5">Gagal memuat data.</td></tr>'; })
      .finally(() => { if (no === permintaan) kartu.classList.remove('bb-memuat'); });
  };
  const muatUlang = () => { if (aktif) lihat(aktif); };

  tabelAkun.addEventListener('click', function (e) {
    const tr = e.target.closest('tbody tr[data-id]');
    if (!tr) return;
    const id = tr.dataset.id;
    if (e.target.closest('.aksi-excel')) { window.location = urlDengan(url.excel, id); return; }
    const kel = e.target.closest('.aksi-kelompok');
    if (kel) { window.location = url.kelompok.replace('__ID__', id) + '?' + new URLSearchParams({ kelompok: kel.dataset.kelompok }); return; }
    bukaModal(id);
  });

  document.getElementById('cariAkun').addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    tabelAkun.querySelectorAll('tbody tr[data-cari]').forEach(tr => tr.classList.toggle('d-none', q !== '' && !tr.dataset.cari.includes(q)));
  });

  // ?rek6=&sumber= membuka akun langsung (seperti v1).
  if (awal.rek6) {
    lihat(awal.rek6); // langsung seluruh T.A
    const tr = tabelAkun.querySelector('tr[data-id="' + awal.rek6 + '"]');
    if (tr) tr.scrollIntoView({ block: 'nearest' });
  }
});
</script>
@endsection
