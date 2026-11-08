{{-- Pesan sekali-tampil (flash) dari controller. Hanya muncul bila ada. --}}
@if (session('status'))
<div class="alert alert-success alert-dismissible d-flex align-items-start" role="alert">
  <i class="ti ti-circle-check me-2 mt-1 flex-shrink-0"></i>
  <div>{{ session('status') }}</div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
@endif

@if (session('error'))
<div class="alert alert-danger alert-dismissible d-flex align-items-start" role="alert">
  <i class="ti ti-alert-circle me-2 mt-1 flex-shrink-0"></i>
  <div>{{ session('error') }}</div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
@endif
