<ul class="menu-sub">
  @if (isset($menu))
    @php
      // Slug item daun paling spesifik yang cocok dengan route saat ini di antara saudara-saudaranya,
      // agar 'akuntansi.laporan-unit' tidak ikut aktif pada 'akuntansi.laporan-unit.aktivitas'.
      $routeSaatIni = (string) Route::currentRouteName();
      $slugTerbaik = collect($menu)
        ->filter(fn ($m) => !isset($m->submenu) && is_string($m->slug ?? null)
          && ($routeSaatIni === $m->slug || str_starts_with($routeSaatIni, $m->slug . '.')))
        ->map(fn ($m) => $m->slug)
        ->sortByDesc(fn ($s) => strlen($s))
        ->first();
    @endphp
    @foreach ($menu as $submenu)

    {{-- sembunyikan submenu yang tidak sesuai role user --}}
    @if ((isset($submenu->role) || isset($submenu->sembunyiAdminUnit) || isset($submenu->hanyaAdminUnit)) && (!Auth::check() || Auth::user()->menuTersembunyi($submenu)))
    @continue
    @endif

    {{-- active menu method --}}
    @php
      $activeClass = null;
      $active = $configData["layout"] === 'vertical' ? 'active open':'active';
      $currentRouteName =  (string) Route::currentRouteName();

      // Aktif juga untuk route turunan, mis. slug 'admin.coa' pada 'admin.coa.show' (hanya slug terbaik di atas).
      if (!isset($submenu->submenu) && is_string($submenu->slug ?? null) && $submenu->slug === $slugTerbaik) {
          $activeClass = 'active';
      }
      elseif (isset($submenu->submenu)) {
        if (gettype($submenu->slug) === 'array') {
          foreach($submenu->slug as $slug){
            if (str_contains($currentRouteName,$slug) and strpos($currentRouteName,$slug) === 0) {
                $activeClass = $active;
            }
          }
        }
        else{
          if (str_contains($currentRouteName,$submenu->slug) and strpos($currentRouteName,$submenu->slug) === 0) {
            $activeClass = $active;
          }
        }
      }
    @endphp

      <li class="menu-item {{$activeClass}}">
        <a href="{{ isset($submenu->url) ? url($submenu->url) : 'javascript:void(0)' }}" class="{{ isset($submenu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}" @if (isset($submenu->target) and !empty($submenu->target)) target="_blank" @endif>
          @if (isset($submenu->icon))
          <i class="{{ $submenu->icon }}"></i>
          @endif
          <div>{{ isset($submenu->name) ? __($submenu->name) : '' }}</div>
        </a>

        {{-- submenu --}}
        @if (isset($submenu->submenu))
          @include('layouts.sections.menu.submenu',['menu' => $submenu->submenu])
        @endif
      </li>
    @endforeach
  @endif
</ul>
