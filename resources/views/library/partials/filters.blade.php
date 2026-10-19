{{--
    Search, filter and sort controls for the library.

    A plain GET form so the state lives in the URL: results stay shareable,
    bookmarkable and reloadable, and no JavaScript is required.
    The paginator calls withQueryString() so paging keeps this state too.

    Expects: $filters, $categories, $sorts, $isDefaultState
--}}
<form class="filter-bar" method="GET" action="{{ route('library.index') }}" role="search">
    <div class="filter-bar__grid">
        <div class="field filter-bar__search">
            <label class="field__label" for="q">Search</label>
            <input class="field__input"
                   id="q"
                   name="q"
                   type="search"
                   value="{{ $filters['search'] }}"
                   placeholder="Title, description, text or tag&hellip;"
                   maxlength="{{ \App\Services\PromptSearchService::MAX_QUERY_LENGTH }}">
        </div>

        <div class="field">
            <label class="field__label" for="category_id">Category</label>
            <select class="field__input" id="category_id" name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category_id'] === $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field__label" for="ai_model">AI model</label>
            <select class="field__input" id="ai_model" name="ai_model">
                <option value="">All models</option>
                @foreach (config('prompts.ai_models') as $model)
                    <option value="{{ $model }}" @selected($filters['ai_model'] === $model)>
                        {{ $model }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field__label" for="language">Language</label>
            <select class="field__input" id="language" name="language">
                <option value="">All languages</option>
                @foreach (config('prompts.languages') as $language)
                    <option value="{{ $language }}" @selected($filters['language'] === $language)>
                        {{ $language }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field__label" for="sort">Sort by</label>
            <select class="field__input" id="sort" name="sort">
                @foreach ($sorts as $sort)
                    {{-- Relevance is meaningless without a term to rank against,
                         so the option is withheld rather than offered and ignored. --}}
                    @continue($sort === \App\Services\PromptSearchService::SORT_RELEVANCE && $filters['search'] === '')
                    <option value="{{ $sort }}" @selected($filters['sort'] === $sort)>
                        {{ \App\Services\PromptSearchService::SORT_LABELS[$sort] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="filter-bar__actions">
        <button type="submit" class="btn btn--primary">Apply</button>

        @unless ($isDefaultState)
            <a class="btn" href="{{ route('library.index') }}">Clear filters</a>
        @endunless

        <div class="filter-bar__toggles">
            {{-- Only offered when an embedding model is reachable; otherwise the
                 toggle would silently do nothing. --}}
            @if ($aiConfigured)
                <label class="check-inline" for="semantic">
                    <input type="checkbox" id="semantic" name="semantic" value="1" @checked($filters['semantic'])>
                    Semantic search
                </label>
            @endif

            @auth
                <label class="check-inline" for="mine">
                    <input type="checkbox" id="mine" name="mine" value="1" @checked($filters['mine'])>
                    Only my prompts
                </label>
            @endauth
        </div>
    </div>
</form>

@if ($aiConfigured)
    <p class="filter-bar__hint">
        Semantic search compares the meaning of your query with an embedding of each prompt,
        so it can find related wording that contains none of your keywords. It still searches
        only the prompts you are allowed to see.
    </p>
@endif
