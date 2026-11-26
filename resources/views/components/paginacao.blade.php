@if ($paginator->hasPages())
<nav class="pagination" aria-label="Paginação de alunos">
    <p class="pagination-summary">Exibindo {{ $paginator->firstItem() }} a {{ $paginator->lastItem() }} de {{ $paginator->total() }} alunos</p>
    <div class="pagination-controls">
        @if ($paginator->onFirstPage())
            <span class="pagination-link pagination-disabled" aria-disabled="true">&lsaquo; <span>Anterior</span></span>
        @else
            <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo; <span>Anterior</span></a>
        @endif
        <div class="pagination-pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-ellipsis" aria-hidden="true">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-link pagination-current" aria-current="page" aria-label="Página {{ $page }}">{{ $page }}</span>
                        @else
                            <a class="pagination-link" href="{{ $url }}" aria-label="Página {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>
        @if ($paginator->hasMorePages())
            <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next"><span>Próxima</span> &rsaquo;</a>
        @else
            <span class="pagination-link pagination-disabled" aria-disabled="true"><span>Próxima</span> &rsaquo;</span>
        @endif
    </div>
</nav>
@endif
