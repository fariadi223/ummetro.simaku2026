@php
$containerNav = $containerNav ?? 'container-fluid';
$navbarDetached = ($navbarDetached ?? '');

// Administrator asli bila sedang dalam mode "login as"; null bila tidak.
$impersonator = Helper::impersonasiAdmin();
@endphp

<!-- Navbar -->
@if(isset($navbarDetached) && $navbarDetached == 'navbar-detached')
<nav class="layout-navbar {{$containerNav}} navbar navbar-expand-xl {{$navbarDetached}} align-items-center bg-navbar-theme" id="layout-navbar">
  @endif
  @if(isset($navbarDetached) && $navbarDetached == '')
  <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
    <div class="{{$containerNav}}">
      @endif

      <!--  Brand demo (display only for navbar-full and hide on below xl) -->
      @if(isset($navbarFull))
      <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4">
        <a href="{{url('/')}}" class="app-brand-link gap-2">
          <span class="app-brand-logo demo">
            @include('_partials.macros',["height"=>20])
          </span>
          <span class="app-brand-text demo menu-text fw-bold">{{config('variables.templateName')}}</span>
        </a>
      </div>
      @endif

      <!-- ! Not required for layout-without-menu -->
      @if(!isset($navbarHideToggle))
      <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0{{ isset($menuHorizontal) ? ' d-xl-none ' : '' }} {{ isset($contentNavbar) ?' d-xl-none ' : '' }}">
        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
          <i class="ti ti-menu-2 ti-sm"></i>
        </a>
      </div>
      @endif

      <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

        <!-- Style Switcher -->
        <div class="navbar-nav align-items-center">
          <a class="nav-link style-switcher-toggle hide-arrow" href="javascript:void(0);">
            <i class='ti ti-sm'></i>
          </a>
        </div>
        <!--/ Style Switcher -->

        <ul class="navbar-nav flex-row align-items-center ms-auto">

          <!-- User -->
          <li class="nav-item navbar-dropdown dropdown-user dropdown">
            <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
              <div class="avatar avatar-online">
                <img src="{{ Auth::user() ? asset('assets/img/avatars/0.png') : asset('assets/img/avatars/1.png') }}" alt class="w-px-40 h-auto rounded-circle">
              </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <div class="dropdown-item-text">
                  <div class="d-flex">
                    <div class="flex-shrink-0 me-3">
                      <div class="avatar avatar-online">
                        <img src="{{ asset('assets/img/avatars/0.png') }}" alt class="w-px-40 h-auto rounded-circle">
                      </div>
                    </div>
                    <div class="flex-grow-1">
                      <span class="fw-semibold d-block">
                        {{ Auth::check() ? Auth::user()->name : 'Universitas Muhammadiyah Metro' }}
                      </span>
                      @if (Auth::check())
                        <small class="text-muted">{{ Auth::user()->roleLabel() }}</small>

                        @if ($impersonator)
                        <small class="d-block text-warning-emphasis mt-1">
                          <i class="ti ti-eye ti-xs me-1"></i>Mode login as
                        </small>
                        @endif
                      @endif
                    </div>
                  </div>
                </div>
              </li>
              <li>
                <div class="dropdown-divider"></div>
              </li>
              @if (Auth::check())

                @if ($impersonator)
                {{-- Sedang "login as": jalan keluar utamanya adalah kembali ke
                     akun administrator, bukan logout penuh. --}}
                <li>
                  <a class="dropdown-item" href="javascript:void(0);"
                     onclick="document.getElementById('impersonate-stop-form').submit();">
                    <i class='ti ti-arrow-back-up me-2 ti-sm'></i>
                    <span class="align-middle">
                      Kembali ke akun saya
                      <small class="d-block text-muted">{{ $impersonator->name }}</small>
                    </span>
                  </a>
                </li>
                <form method="POST" id="impersonate-stop-form" action="{{ route('impersonate.stop') }}" class="d-none">
                  @csrf
                </form>
                <li>
                  <div class="dropdown-divider"></div>
                </li>
                @endif

              <li>
                <a class="dropdown-item {{ $impersonator ? 'text-danger' : '' }}" href="javascript:void(0);"
                   onclick="document.getElementById('logout-form').submit();">
                  <i class='ti ti-logout me-2 ti-sm'></i>
                  <span class="align-middle">
                    {{ $impersonator ? 'Logout penuh' : 'Logout' }}
                  </span>
                </a>
              </li>
              <form method="POST" id="logout-form" action="{{ route('logout') }}" class="d-none">
                @csrf
              </form>
              @else
              <li>
                <a class="dropdown-item" href="{{ route('login') }}">
                  <i class='ti ti-login me-2 ti-sm'></i>
                  <span class="align-middle">Login</span>
                </a>
              </li>
              @endif
            </ul>
          </li>
          <!--/ User -->
        </ul>
      </div>

      @if(!isset($navbarDetached))
    </div>
    @endif
  </nav>
  <!-- / Navbar -->
