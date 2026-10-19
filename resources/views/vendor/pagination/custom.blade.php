@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">
        <p class="pager__summary">
            Showing
            <strong>{{ $paginator->firstItem() }}</strong>&ndash;<strong>{{ $paginator->lastItem() }}</strong>
            of <strong>{{ $paginator->total() }}</strong>
        </p>

        <ul class="pager__list">
            @if ($paginator->onFirstPage())
                <li class="pager__item is-disabled">
                    <span class="pager__link" aria-disabled="true">Previous</span>
                </li>
            @else
                <li class="pager__item">
                    <a class="pager__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="pager__item is-disabled">
                        <span class="pager__link">{{ $element }}</span>
                    </li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="pager__item is-active">
                                <span class="pager__link" aria-current="page">{{ $page }}</span>
                            </li>
                        @else
                            <li class="pager__item">
                                <a class="pager__link" href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="pager__item">
                    <a class="pager__link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
                </li>
            @else
                <li class="pager__item is-disabled">
                    <span class="pager__link" aria-disabled="true">Next</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
