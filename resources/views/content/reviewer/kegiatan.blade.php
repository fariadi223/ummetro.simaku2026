@extends('layouts/layoutMaster')

@section('title', 'Review Kegiatan')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-style')
<style>
  .bidang-tab { gap: .5rem; flex-wrap: wrap; }
  .bidang-tab .nav-link { display: flex; align-items: center; gap: .5rem; text-align: left; white-space: normal; padding: .55rem .9rem; }
  .bidang-tab .nav-link .kode { display: inline-grid; place-items: center; width: 1.6rem; height: 1.6rem; border-radius: .4rem; font-weight: 700;
                                font-size: .8rem; background: rgba(var(--bs-primary-rgb), .12); color: var(--bs-primary); flex-shrink: 0; }
  .bidang-tab .nav-link.active .kode { background: rgba(255,255,255,.25); color: #fff; }

  .rp { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .ringkas-tabel td, .ringkas-tabel th { padding: .45rem .75rem; }
  .sifat .progress { height: 8px; }

  .keg { border: 1px solid var(--bs-border-color); border-radius: .6rem; margin-bottom: .9rem; background: var(--bs-card-bg); }
  .keg.setuju { border-left: 4px solid var(--bs-success); }
  .keg.revisi { border-left: 4px solid var(--bs-danger); }
  .keg.ulang  { border-left: 4px solid var(--bs-info); }
  .keg.belum  { border-left: 4px solid var(--bs-warning); }
  .keg-kepala { display: flex; gap: .75rem; align-items: flex-start; padding: .85rem 1rem; cursor: pointer; }
  .keg-kepala .no { width: 1.8rem; color: var(--bs-secondary); flex-shrink: 0; padding-top: .1rem; }
  .keg-kepala .kode-mak { font-family: var(--bs-font-monospace); font-size: .75rem; color: var(--bs-secondary); }
  .keg-kepala .judul { font-weight: 600; }
  .keg-kepala .btn-lipat i { transition: transform .15s ease; }
  .keg-kepala[aria-expanded="true"] .btn-lipat i { transform: rotate(180deg); }
  .keg-isi { border-top: 1px dashed var(--bs-border-color); padding: 1rem 1rem 1.1rem 3.55rem; }
  @media (max-width: 575.98px) { .keg-isi { padding-left: 1rem; } }

  .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem 1.5rem; }
  .label-kecil { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: var(--bs-secondary); margin-bottom: .2rem; }
  .detail-grid .isi { white-space: pre-line; font-size: .875rem; }
  .rinci-tabel { font-size: .85rem; }
  .rinci-tabel td { vertical-align: top; }
  .rinci-tabel .kode-rek { font-family: var(--bs-font-monospace); font-size: .72rem; color: var(--bs-secondary); }
  .rinci-tabel tr.subjudul td { background: rgba(var(--bs-secondary-rgb), .06); font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
  .catatan { border-left: 3px solid var(--bs-border-color); padding: .4rem .75rem; margin-bottom: .5rem; font-size: .85rem; white-space: pre-line; }
  .catatan.ditolak { border-color: var(--bs-danger); background: rgba(var(--bs-danger-rgb), .05); }
  .catatan.disetujui { border-color: var(--bs-success); background: rgba(var(--bs-success-rgb), .04); }
  .form-review { background: rgba(var(--bs-primary-rgb), .04); border-radius: .5rem; padding: 1rem; }
</style>
@endsection

@php
  $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
  // Status penilaian reviewer ini: [kelas kartu, kelas badge, label]
  $statusSaya = function ($saya) {
    if (!$saya) return ['belum', 'warning', 'Belum dinilai'];
    if ($saya->status === 'disetujui') return ['setuju', 'success', 'Disetujui'];
    if ($saya->diperbaiki === 'Ya') return ['ulang', 'info', 'Revisi dikirim unit · nilai ulang'];
    return ['revisi', 'danger', 'Menunggu perbaikan unit'];
  };
  $maxBidang = max(1, $ringkasan['perBidang']->max(fn ($b) => max($b->pendapatan, $b->belanja)));
@endphp

@section('content')
<div class="pg-header mb-4">
  <div class="d-flex flex-wrap align-items-center gap-3">
    <div class="pg-header-icon"><i class="ti ti-clipboard-check"></i></div>
    <div class="flex-grow-1" style="min-width: 0;">
      <div class="pg-header-sub small mb-1">Klinik Anggaran · Sub Unit Kerja</div>
      <h4 class="mb-1">{{ $unit->nama }}</h4>
      <p class="pg-header-sub mb-0"><span class="font-monospace">{{ $unit->kode }}</span> · T.A {{ $tahun }}/{{ $tahun + 1 }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('reviewer.unit.index') }}" class="btn btn-light"><i class="ti ti-arrow-left me-1"></i> Daftar Unit</a>
      <a href="{{ route('reviewer.kegiatan.create', ['mak' => $aktif->kodemak1]) }}" class="btn btn-light"><i class="ti ti-plus me-1"></i> Tambah Kegiatan</a>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2 mt-3">
    <span class="pg-header-chip"><i class="ti ti-user-check"></i><span><strong>{{ $jumlahReviewer }}</strong> reviewer untuk unit ini</span></span>
    <span class="pg-header-chip"><i class="ti ti-arrow-down-left"></i><span>Pendapatan <strong>{{ $rp($ringkasan['pendapatan']) }}</strong></span></span>
    <span class="pg-header-chip"><i class="ti ti-arrow-up-right"></i><span>Belanja <strong>{{ $rp($ringkasan['belanja']) }}</strong></span></span>
  </div>
</div>

@include('_partials._flash')

{{-- Ringkasan anggaran unit (grafik berbasis fungsi & sifat di v1) --}}
<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header pb-2"><h5 class="card-title mb-0">Rekap berbasis fungsi</h5><small class="text-muted">Pendapatan & belanja per bidang</small></div>
      <div class="table-responsive">
        <table class="table ringkas-tabel mb-0">
          <thead><tr><th>Bidang</th><th class="text-end">Pendapatan</th><th class="text-end">Belanja</th><th class="text-end">Selisih</th></tr></thead>
          <tbody>
            @foreach ($ringkasan['perBidang'] as $b)
              <tr>
                <td style="min-width: 180px;">
                  <div class="small fw-semibold">{{ $b->kode }}. {{ Helper::judulMak($b->nama) }}</div>
                  <div class="progress mt-1" style="height: 4px;" title="Belanja"><div class="progress-bar bg-danger" style="width: {{ $b->belanja / $maxBidang * 100 }}%"></div></div>
                  <div class="progress mt-1" style="height: 4px;" title="Pendapatan"><div class="progress-bar bg-success" style="width: {{ $b->pendapatan / $maxBidang * 100 }}%"></div></div>
                </td>
                <td class="text-end rp small">{{ $rp($b->pendapatan) }}</td>
                <td class="text-end rp small">{{ $rp($b->belanja) }}</td>
                <td class="text-end rp small">{{ $rp($b->belanja - $b->pendapatan) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100 sifat">
      <div class="card-header pb-2"><h5 class="card-title mb-0">Rekap berbasis sifat</h5><small class="text-muted">Porsi terhadap total belanja {{ $rp($ringkasan['belanja']) }}</small></div>
      <div class="card-body">
        @foreach ($ringkasan['sifat'] as $i => $s)
          <div class="mb-3">
            <div class="d-flex justify-content-between small mb-1">
              <span class="fw-semibold">{{ $s->nama }}</span>
              <span><span class="rp text-muted me-2">{{ $rp($s->nilai) }}</span><strong>{{ number_format($s->persen, 1, ',', '.') }}%</strong></span>
            </div>
            <div class="progress"><div class="progress-bar {{ ['bg-primary', 'bg-info', 'bg-warning'][$i] ?? 'bg-secondary' }}" style="width: {{ $s->persen }}%"></div></div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header pb-0">
    <ul class="nav nav-pills bidang-tab mb-3">
      @foreach ($bidang as $b)
        @php $pb = $perBidang[$b->idmak1] ?? null; @endphp
        <li class="nav-item">
          <a href="{{ route('reviewer.kegiatan.index', ['mak' => $b->kodemak1]) }}" class="nav-link {{ $b->idmak1 === $aktif->idmak1 ? 'active' : '' }}">
            <span class="kode">{{ $b->kodemak1 }}</span>
            <span>{{ Helper::judulMak($b->namamak1) }}</span>
            <span class="badge rounded-pill {{ $b->idmak1 === $aktif->idmak1 ? 'bg-white text-primary' : 'bg-label-secondary' }}">{{ $pb->n ?? 0 }}</span>
            @if (($pb->perlu ?? 0) > 0)
              <span class="badge rounded-pill bg-warning" title="Perlu dinilai">{{ $pb->perlu }}</span>
            @endif
          </a>
        </li>
      @endforeach
    </ul>
  </div>

  <div class="card-body pt-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div class="fw-semibold">Proses Bisnis {{ $aktif->kodemak1 }} : {{ Helper::judulMak($aktif->namamak1) }}</div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-label-secondary" id="buka-semua"><i class="ti ti-arrows-maximize me-1"></i>Buka semua</button>
        <a href="{{ route('reviewer.kegiatan.create', ['mak' => $aktif->kodemak1]) }}" class="btn btn-sm btn-primary"><i class="ti ti-plus me-1"></i>Kegiatan {{ Helper::judulMak($aktif->namamak1) }}</a>
      </div>
    </div>

    @forelse ($kegiatan as $k)
      @php
        [$kelas, $warna, $label] = $statusSaya($k->saya);
        $rv = $k->review;
      @endphp
      <div class="keg {{ $kelas }}" id="keg-{{ $k->idkeg }}">
        <div class="keg-kepala" data-bs-toggle="collapse" data-bs-target="#isi-{{ $k->idkeg }}" aria-expanded="false" role="button">
          <div class="no">{{ $loop->iteration }}</div>
          <div class="flex-grow-1" style="min-width: 0;">
            <div class="kode-mak">{{ $k->kode_mak }} · {{ $k->namamak4 }}</div>
            <div class="judul">{{ $k->uraiankeg }}</div>
            <div class="small text-muted mt-1">
              {{ $k->jadwal }} @if ($k->nama_pagu) · Dana: {{ $k->nama_pagu }} @endif
              · Belanja <span class="rp">{{ $rp($k->total_belanja) }}</span>
              @if ($k->total_pendapatan) · Pendapatan <span class="rp">{{ $rp($k->total_pendapatan) }}</span> @endif
            </div>
          </div>
          <div class="text-end flex-shrink-0">
            <span class="badge bg-label-{{ $warna }} status-saya">{{ $label }}</span>
            <div class="small text-muted mt-1">{{ $rv->disetujui + $rv->ditolak }}/{{ $rv->reviewer }} reviewer menilai</div>
          </div>
          <span class="btn btn-sm btn-icon btn-text-secondary rounded-pill btn-lipat flex-shrink-0"><i class="ti ti-chevron-down"></i></span>
        </div>

        <div class="collapse" id="isi-{{ $k->idkeg }}">
          <div class="keg-isi">
            <div class="detail-grid mb-3">
              <div><div class="label-kecil">Latar Belakang</div><div class="isi">{{ $k->latblk }}</div></div>
              <div><div class="label-kecil">Tujuan</div><div class="isi">{{ $k->tujuan }}</div></div>
              <div><div class="label-kecil">Sumber Rujukan / Kebijakan</div>
                <div class="isi"><b>{{ $k->rujukan_nama ?: '—' }}</b>{{ $k->kebijakan_uraian ? "\n" . $k->kebijakan_uraian : '' }}</div></div>
              <div><div class="label-kecil">Indikator</div><div class="isi">{{ $k->indikator ? str_replace(',', ', ', $k->indikator) : '—' }}</div></div>
              <div><div class="label-kecil">Luaran / Output</div><div class="isi">{{ $k->lingkup }}</div></div>
            </div>

            <div class="table-responsive mb-3">
              <table class="table table-sm table-bordered rinci-tabel mb-0">
                <thead>
                  <tr><th style="width: 36px;">No</th><th>Rekening &amp; Uraian</th><th>Volume</th><th class="text-end">Harga Satuan</th><th class="text-end">Jumlah</th></tr>
                </thead>
                @foreach (['pendapatan' => 'Pendapatan', 'belanja' => 'Belanja'] as $jenis => $judul)
                  @php $daftar = $k->{$jenis}; @endphp
                  @continue($daftar->isEmpty() && $jenis === 'pendapatan')
                  <tbody>
                    <tr class="subjudul"><td colspan="5">{{ $judul }}</td></tr>
                    @forelse ($daftar as $r)
                      <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td style="min-width: 220px;"><div class="kode-rek">{{ $r->kode_rek }} · {{ $r->namarek6 }}</div>{{ $r->uraianrinci }}</td>
                        <td>{{ $r->volume }}</td>
                        <td class="text-end rp">{{ $rp($r->hargasat) }}</td>
                        <td class="text-end rp">{{ $rp($r->jumlah) }}</td>
                      </tr>
                    @empty
                      <tr><td colspan="5" class="text-center text-muted">Belum ada rincian {{ strtolower($judul) }}.</td></tr>
                    @endforelse
                    @if ($daftar->isNotEmpty())
                      <tr class="fw-semibold"><td colspan="4" class="text-end">Total {{ $judul }}</td><td class="text-end rp">{{ $rp($daftar->sum('jumlah')) }}</td></tr>
                    @endif
                  </tbody>
                @endforeach
              </table>
            </div>

            @if ($k->catatan_lain->isNotEmpty())
              <div class="label-kecil mb-1">Catatan reviewer lain</div>
              @foreach ($k->catatan_lain as $c)
                <div class="catatan {{ $c->status }}"><b>{{ $c->nama_lengkap ?: $c->username }}</b>
                  ({{ $c->status === 'ditolak' ? 'revisi' . ($c->diperbaiki === 'Ya' ? ', sudah diperbaiki' : '') : $c->status }}): {{ trim($c->keterangan) }}</div>
              @endforeach
            @endif

            <form method="POST" action="{{ route('reviewer.kegiatan.review', $k->idkeg) }}" class="form-review mt-2" data-label="{{ $k->uraiankeg }}">
              @csrf
              <label class="form-label fw-semibold" for="komentar-{{ $k->idkeg }}">Komentar Anda</label>
              <textarea rows="4" class="form-control" id="komentar-{{ $k->idkeg }}" name="keterangan"
                        placeholder="Catatan untuk unit. Wajib diisi bila meminta revisi.">{{ $k->saya->keterangan ?? '' }}</textarea>
              <div class="invalid-feedback"></div>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" name="status" value="disetujui" class="btn btn-success"><i class="ti ti-check me-1"></i>Setujui</button>
                <button type="submit" name="status" value="ditolak" class="btn btn-danger"><i class="ti ti-alert-triangle me-1"></i>Revisi</button>
                <a href="{{ route('reviewer.rinci.index', $k->idkeg) }}" class="btn btn-warning"><i class="ti ti-list-details me-1"></i>Edit Rincian</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    @empty
      <div class="text-center text-muted py-5">
        Unit belum memiliki kegiatan di bidang ini.
        <div class="mt-2"><a href="{{ route('reviewer.kegiatan.create', ['mak' => $aktif->kodemak1]) }}" class="btn btn-sm btn-primary"><i class="ti ti-plus me-1"></i>Tambah Kegiatan</a></div>
      </div>
    @endforelse
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Buka kegiatan dari tautan #keg-<id> (mis. setelah simpan tanpa JavaScript).
  const target = location.hash.match(/^#keg-(\d+)$/);
  if (target) {
    const isi = document.getElementById('isi-' + target[1]);
    if (isi) { new bootstrap.Collapse(isi, { toggle: true }); isi.closest('.keg').scrollIntoView({ block: 'start' }); }
  }

  document.getElementById('buka-semua')?.addEventListener('click', function () {
    const semua = [...document.querySelectorAll('.keg .collapse')];
    const buka = semua.some(c => !c.classList.contains('show'));
    semua.forEach(c => bootstrap.Collapse.getOrCreateInstance(c, { toggle: false })[buka ? 'show' : 'hide']());
    this.innerHTML = buka ? '<i class="ti ti-arrows-minimize me-1"></i>Tutup semua' : '<i class="ti ti-arrows-maximize me-1"></i>Buka semua';
  });

  const gaya = { success: ['setuju', 'success', 'Disetujui'], ditolak: ['revisi', 'danger', 'Menunggu perbaikan unit'] };

  document.querySelectorAll('.form-review').forEach(form => form.addEventListener('submit', async function (e) {
    e.preventDefault();
    const tombol = e.submitter;
    const status = tombol?.value;
    const komentar = form.querySelector('textarea');
    const umpan = form.querySelector('.invalid-feedback');
    komentar.classList.remove('is-invalid');

    if (status === 'ditolak' && komentar.value.trim() === '') {
      komentar.classList.add('is-invalid');
      umpan.textContent = 'Tuliskan komentar: apa yang harus direvisi oleh unit.';
      komentar.focus();
      return;
    }

    const data = new FormData(form);
    data.set('status', status);
    form.querySelectorAll('button').forEach(b => b.disabled = true);

    try {
      const res = await fetch(form.action, { method: 'POST', body: data, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      const json = await res.json().catch(() => ({}));
      if (!res.ok) {
        const pesan = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Penilaian gagal disimpan.');
        if (res.status === 422) { komentar.classList.add('is-invalid'); umpan.textContent = pesan; }
        else Swal.fire({ icon: 'error', title: 'Gagal', text: pesan, buttonsStyling: false, customClass: { confirmButton: 'btn btn-primary' } });
        return;
      }

      const [kelas, warna, label] = gaya[status === 'disetujui' ? 'success' : 'ditolak'];
      const kartu = form.closest('.keg');
      kartu.classList.remove('setuju', 'revisi', 'ulang', 'belum');
      kartu.classList.add(kelas);
      const badge = kartu.querySelector('.status-saya');
      badge.className = 'badge bg-label-' + warna + ' status-saya';
      badge.textContent = label;

      Swal.fire({ position: 'top', icon: 'success', title: status === 'disetujui' ? 'Disetujui' : 'Diminta revisi', text: json.pesan,
        showConfirmButton: false, timer: 2200 });
    } catch (err) {
      Swal.fire({ icon: 'error', title: 'Gagal', text: 'Server tidak dapat dihubungi. Coba lagi.', buttonsStyling: false, customClass: { confirmButton: 'btn btn-primary' } });
    } finally {
      form.querySelectorAll('button').forEach(b => b.disabled = false);
    }
  }));
});
</script>
@endsection
