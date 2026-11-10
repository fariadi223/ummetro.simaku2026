{{-- Ditampilkan jika tabel ueu_lap_unit_rek_nonaktif / ueu_map_unit_prodi belum ada. --}}
@extends('layouts/layoutMaster')

@section('title', 'Rekening Laporan Unit')

@section('content')
<div class="card">
  <div class="card-body">
    <h4 class="mb-3"><i class="ti ti-database-cog me-1"></i>Pengaturan rekening laporan unit belum disiapkan</h4>
    <p class="mb-0">Jalankan <code>sql/laporan_unit/00_rekening_nonaktif.sql</code> (dan <code>sql/laporan_prodi/01_buat_tabel.sql</code> bila tabel pemetaan belum ada).</p>
  </div>
</div>
@endsection
