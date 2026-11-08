{{-- Tombol halaman bulat seragam (.pagination-simaku di simaku.css; first/prev/next/last), label Indonesia.
     Pemakaian: $paginator->onEachSide(1)->links('_partials.pagination') --}}
@if ($paginator->hasPages())
  <nav aria-label="Navigasi halaman">
    <ul class="pagination pagination-simaku mb-0">
      {{-- Pertama & sebelumnya --}}
      <li class="page-item first {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
        @if ($paginator->onFirstPage())
          <span class="page-link" aria-label="Halaman pertama"><i class="ti ti-chevrons-left ti-xs"></i></span>
        @else
          <a class="page-link" href="{{ $paginator->url(1) }}" aria-label="Halaman pertama"><i class="ti ti-chevrons-left ti-xs"></i></a>
        @endif
      </li>
      <li class="page-item prev {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
        @if ($paginator->onFirstPage())
          <span class="page-link" aria-label="Sebelumnya"><i class="ti ti-chevron-left ti-xs"></i></span>
        @else
          <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Sebelumnya"><i class="ti ti-chevron-left ti-xs"></i></a>
        @endif
      </li>

      {{-- Nomor halaman --}}
      @foreach ($elements as $element)
        @if (is_string($element))
          <li class="page-item disabled" aria-disabled="true"><span class="page-link">&hellip;</span></li>
        @endif
        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
            @else
              <li class="page-item"><a class="page-link" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a></li>
            @endif
          @endforeach
        @endif
      @endforeach

      {{-- Berikutnya & terakhir --}}
      <li class="page-item next {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
        @if ($paginator->hasMorePages())
          <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Berikutnya"><i class="ti ti-chevron-right ti-xs"></i></a>
        @else
          <span class="page-link" aria-label="Berikutnya"><i class="ti ti-chevron-right ti-xs"></i></span>
        @endif
      </li>
      <li class="page-item last {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
        @if ($paginator->hasMorePages())
          <a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}" aria-label="Halaman terakhir"><i class="ti ti-chevrons-right ti-xs"></i></a>
        @else
          <span class="page-link" aria-label="Halaman terakhir"><i class="ti ti-chevrons-right ti-xs"></i></span>
        @endif
      </li>
    </ul>
  </nav>
@endif
