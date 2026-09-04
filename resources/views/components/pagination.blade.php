@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="giya-pagination" role="navigation" aria-label="{{ __('giya.misc.pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('giya.misc.prev_page') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
        @endif

        @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2),
                                          min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            @if ($page == $paginator->currentPage())
                <span class="is-active" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('giya.misc.next_page') }}">
                <i class="bi bi-chevron-right"></i>
            </a>
        @else
            <span class="is-disabled" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
        @endif
    </nav>
@endif
