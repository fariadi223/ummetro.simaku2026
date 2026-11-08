@php
// Administrator asli; hanya ada selama mode "login as" berjalan.
$impersonator = Helper::impersonasiAdmin();
@endphp

@if ($impersonator)
<div class="imp-bar">
  <div class="imp-bar-inner">
    <span class="imp-bar-text">
      <i class="ti ti-eye me-1"></i>
      Mode <strong>login as</strong> &mdash; Anda melihat sebagai
      <strong>{{ Auth::user()->name }}</strong>
      @if (Auth::user()->mahasiswa_npm) ({{ Auth::user()->mahasiswa_npm }}) @endif
      <span class="imp-bar-who">&middot; akun asli: {{ $impersonator->name }}</span>
    </span>

    <form method="POST" action="{{ route('impersonate.stop') }}" class="flex-shrink-0">
      @csrf
      <button type="submit" class="btn btn-xs btn-label-secondary">
        <i class="ti ti-arrow-back-up ti-xs me-1"></i>Kembali ke akun saya
      </button>
    </form>
  </div>
</div>

<style>
  .imp-bar {
    background: #fff3cd; border-bottom: 1px solid #ffe69c; color: #664d03;
  }
  html.dark-style .imp-bar { background: #4a3f22; border-bottom-color: #6b5a2b; color: #ffe69c; }
  .imp-bar-inner {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
    gap: .5rem; padding: .5rem 1.5rem; font-size: .82rem;
  }
  .imp-bar-who { opacity: .75; }
  @media (max-width: 575.98px) {
    .imp-bar-inner { padding: .5rem 1rem; }
    .imp-bar-who { display: block; width: 100%; }
  }
</style>
@endif
