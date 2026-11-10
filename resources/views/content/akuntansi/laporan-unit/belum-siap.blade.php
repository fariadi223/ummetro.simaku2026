{{-- Ditampilkan jika tabel ueu_map_unit_prodi / ueu_ref_prodi belum ada. --}}
@extends('layouts/layoutMaster')

@section('title', 'Laporan Keuangan Unit')

@section('content')
<div class="card">
  <div class="card-body">
    <h4 class="mb-3"><i class="ti ti-database-cog me-1"></i>Laporan Keuangan Unit belum disiapkan</h4>
    <p>Tabel <code>ueu_map_unit_prodi</code> dan/atau <code>ueu_ref_prodi</code> belum ada. Jalankan <code>sql/laporan_prodi/01_buat_tabel.sql</code>,
      lalu <code>php artisan simaku:sinkron-prodi</code> dan isi pemetaan unit → prodi.
      Tabel rekening nonaktif dibuat dengan <code>sql/laporan_unit/00_rekening_nonaktif.sql</code>.</p>
  </div>
</div>
@endsection
