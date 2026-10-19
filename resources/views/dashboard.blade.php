@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <section class="page-head page-head--split">
        <div>
            <h1>Dashboard</h1>
            <p class="page-head__sub">
                Signed in as {{ $user->email }}
                @if ($user->isAdmin())
                    <span class="badge badge--category">Administrator</span>
                @endif
            </p>
        </div>

        {{-- Quick create: the one action a returning user most often wants. --}}
        <a class="btn btn--primary" href="{{ route('prompts.create') }}">Quick create prompt</a>
    </section>

    {{-- Every figure is read from the database, and each one leads somewhere so
         no number is a dead end. --}}
    <section class="stat-grid" aria-label="Your activity">
        <a class="stat-card stat-card--link" href="{{ route('library.index', ['mine' => 1]) }}">
            <p class="stat-card__label">My prompts</p>
            <p class="stat-card__value">{{ number_format($promptCount) }}</p>
            <p class="stat-card__note">View and edit them</p>
        </a>

        <a class="stat-card stat-card--link" href="{{ route('favorites.index') }}">
            <p class="stat-card__label">Favorites</p>
            <p class="stat-card__value">{{ number_format($favoriteCount) }}</p>
            <p class="stat-card__note">Prompts you saved</p>
        </a>

        <div class="stat-card">
            <p class="stat-card__label">Ratings given</p>
            <p class="stat-card__value">{{ number_format($ratingCount) }}</p>
            <p class="stat-card__note">Across all prompts</p>
        </div>
    </section>

    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Recent prompts</h2>
            <div class="button-row">
                <a class="btn btn--sm" href="{{ route('library.index', ['mine' => 1]) }}">All my prompts</a>
                <a class="btn btn--sm" href="{{ route('library.index') }}">Browse library</a>
            </div>
        </div>

        @if ($recentPrompts->isEmpty())
            <div class="empty-state">
                <h3>You have not created any prompts yet</h3>
                <p>Prompts you save will be listed here, newest first.</p>
                <p class="empty-state__actions">
                    <a class="btn btn--primary" href="{{ route('prompts.create') }}">Create your first prompt</a>
                </p>
            </div>
        @else
            <ul class="plain-list">
                @foreach ($recentPrompts as $prompt)
                    <li class="plain-list__item">
                        <a class="plain-list__main" href="{{ route('prompts.show', $prompt) }}">
                            {{ $prompt->title }}
                        </a>
                        <span class="meta-chip">{{ ucfirst($prompt->visibility) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
