@extends('layouts.app')

@section('title', 'Prompt Library')
@section('meta_description', 'Search and browse prompts for ChatGPT, Gemini and Claude.')

@section('content')
    <section class="page-head page-head--split">
        <div>
            <h1>Prompt Library</h1>

            <p class="page-head__sub">
                @if ($prompts->total() > 0)
                    Showing
                    <strong>{{ number_format($prompts->firstItem()) }}</strong>&ndash;<strong>{{ number_format($prompts->lastItem()) }}</strong>
                    of <strong>{{ number_format($prompts->total()) }}</strong>
                    {{-- Written as a ternary rather than a nested @if: Blade will
                         not compile a directive that directly follows a word
                         character, so "words@endif" would stay literal text. --}}
                    {{ Str::plural('prompt', $prompts->total()) }}{{ $hasFilters ? ' matching your filters' : '' }}.
                @elseif ($hasFilters)
                    No prompts matched your filters.
                @else
                    Prompts will appear here once they are available.
                @endif
            </p>
        </div>

        @auth
            <a class="btn btn--primary" href="{{ route('prompts.create') }}">Create prompt</a>
        @endauth
    </section>

    @include('library.partials.filters')

    {{-- A semantic search that had to fall back says so, rather than letting
         keyword results pass as semantic ones. --}}
    @if ($semanticNotice)
        <div class="alert alert--error" role="alert">{{ $semanticNotice }}</div>
    @endif

    @if ($filters['semantic'] && $filters['search'] !== '' && $semanticCoverage['eligible'] > 0)
        <p class="search-note">
            Ranked by meaning against
            <strong>{{ number_format($semanticCoverage['indexed']) }}</strong>
            of <strong>{{ number_format($semanticCoverage['eligible']) }}</strong>
            eligible {{ Str::plural('prompt', $semanticCoverage['eligible']) }}.
            @if ($semanticCoverage['indexed'] < $semanticCoverage['eligible'])
                Prompts without a stored embedding cannot be ranked &mdash; run
                <code>php artisan embeddings:sync</code> to index them.
            @endif
        </p>
    @endif

    {{-- Current filter state, so the applied narrowing is always visible even
         when the results simply look short. --}}
    @if ($hasFilters)
        <div class="filter-summary">
            <span class="filter-summary__label">Applied:</span>

            <ul class="filter-summary__list">
                @if ($filters['search'] !== '')
                    <li class="chip">Search &ldquo;{{ $filters['search'] }}&rdquo;</li>
                @endif

                @php $activeCategory = $categories->firstWhere('id', $filters['category_id']); @endphp
                @if ($activeCategory)
                    <li class="chip">Category: {{ $activeCategory->name }}</li>
                @endif

                @if ($filters['ai_model'])
                    <li class="chip">Model: {{ $filters['ai_model'] }}</li>
                @endif

                @if ($filters['language'])
                    <li class="chip">Language: {{ $filters['language'] }}</li>
                @endif

                @if ($filters['mine'])
                    <li class="chip">Only my prompts</li>
                @endif

                @if ($filters['semantic'] && $filters['search'] !== '')
                    <li class="chip">Semantic search</li>
                @endif
            </ul>

            <a class="filter-summary__clear" href="{{ route('library.index') }}">Clear all</a>
        </div>
    @endif

    @if ($prompts->isEmpty())
        @if ($hasFilters)
            <div class="empty-state">
                <h2>No prompts found for your search</h2>
                <p>
                    Nothing matched those keywords and filters. Try a different word,
                    or widen the filters &mdash; search covers the title, description,
                    prompt text and tags.
                </p>
                <p class="empty-state__actions">
                    <a class="btn btn--primary" href="{{ route('library.index') }}">Clear all filters</a>
                </p>
            </div>
        @else
            <div class="empty-state">
                <h2>No public prompts yet</h2>
                <p>Nothing has been published to the library. Check back later.</p>
            </div>
        @endif
    @else
        <ul class="prompt-grid">
            @foreach ($prompts as $prompt)
                @include('prompts.partials.card', ['prompt' => $prompt])
            @endforeach
        </ul>

        {{ $prompts->links() }}
    @endif
@endsection
