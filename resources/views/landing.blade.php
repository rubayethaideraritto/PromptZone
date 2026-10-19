@extends('layouts.app')

@section('title', config('app.name', 'Prompt Library'))
@section('meta_description', 'Save, organise, search and improve your AI prompts in one place.')

@section('content')
    <section class="hero">
        <div class="hero__text">
            <p class="hero__eyebrow">AI Prompt Library &amp; Manager</p>
            <h1 class="hero__title">Keep every prompt that works</h1>
            <p class="hero__lead">
                Prompts scatter across chat histories, notes and bookmarks. This library keeps them
                in one place: searchable, tagged, rated, and improvable with AI assistance.
            </p>

            <div class="hero__actions">
                <a class="btn btn--primary" href="{{ route('library.index') }}">Browse the library</a>

                @auth
                    <a class="btn" href="{{ route('prompts.create') }}">Create a prompt</a>
                @else
                    <a class="btn" href="{{ route('register') }}">Create an account</a>
                @endauth
            </div>
        </div>

        {{-- Counted from the database, never hardcoded. --}}
        <dl class="hero__stats">
            <div class="hero__stat">
                <dt>Prompts</dt>
                <dd>{{ number_format($promptCount) }}</dd>
            </div>
            <div class="hero__stat">
                <dt>Categories</dt>
                <dd>{{ number_format($categories->count()) }}</dd>
            </div>
            <div class="hero__stat">
                <dt>Times copied</dt>
                <dd>{{ number_format($copyCount) }}</dd>
            </div>
        </dl>
    </section>

    @if ($featured->isNotEmpty())
        <section class="section">
            <div class="section__head">
                <h2 class="section__title">Featured prompts</h2>
                <a class="btn btn--sm" href="{{ route('library.index') }}">See all</a>
            </div>

            <ul class="prompt-grid">
                @foreach ($featured as $prompt)
                    @include('prompts.partials.card', ['prompt' => $prompt])
                @endforeach
            </ul>
        </section>
    @else
        {{-- No featured prompts yet. Explained rather than padded with filler. --}}
        <section class="section">
            <div class="empty-state">
                <h2>Nothing is featured yet</h2>
                <p>
                    An administrator can mark public prompts as featured from the moderation
                    screen, and they will appear here.
                </p>
                <p class="empty-state__actions">
                    <a class="btn btn--primary" href="{{ route('library.index') }}">Browse all prompts</a>
                </p>
            </div>
        </section>
    @endif

    <section class="section">
        <div class="section__head">
            <h2 class="section__title">Browse by category</h2>
        </div>

        <ul class="category-grid">
            @foreach ($categories as $category)
                <li class="category-tile">
                    <a class="category-tile__link" href="{{ route('library.index', ['category_id' => $category->id]) }}">
                        <span class="category-tile__name">{{ $category->name }}</span>
                        <span class="category-tile__count">
                            {{ number_format($category->prompts_count) }}
                            {{ Str::plural('prompt', $category->prompts_count) }}
                        </span>
                    </a>
                    @if (filled($category->description))
                        <p class="category-tile__desc">{{ $category->description }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    @if ($trending->isNotEmpty())
        <section class="section">
            <div class="section__head">
                <h2 class="section__title">Trending now</h2>
                <a class="btn btn--sm" href="{{ route('library.index', ['sort' => 'trending']) }}">See the ranking</a>
            </div>

            <p class="section__note">
                Ranked by copies, favorites and ratings, divided by age, so recent
                activity counts for more than older activity of the same size. The list
                is cached for {{ config('prompts.trending_cache_minutes') }} minutes.
            </p>

            <ul class="prompt-grid">
                @foreach ($trending as $prompt)
                    @include('prompts.partials.card', ['prompt' => $prompt])
                @endforeach
            </ul>
        </section>
    @endif

    <section class="section">
        <h2 class="section__title">What you can do here</h2>

        <ul class="feature-grid">
            <li class="feature">
                <h3 class="feature__title">Search properly</h3>
                <p class="feature__text">
                    Full-text search across titles, descriptions and prompt text, plus tags, so a
                    half-remembered prompt is still findable.
                </p>
            </li>
            <li class="feature">
                <h3 class="feature__title">Organise as you go</h3>
                <p class="feature__text">
                    Categories and tags, private or public visibility, favourites and one-to-five
                    ratings.
                </p>
            </li>
            <li class="feature">
                <h3 class="feature__title">Improve with AI</h3>
                <p class="feature__text">
                    Ask for an analysis of what is missing, a better-structured rewrite, or
                    suggested categories and tags. Nothing is applied until you accept it.
                </p>
            </li>
        </ul>
    </section>
@endsection
