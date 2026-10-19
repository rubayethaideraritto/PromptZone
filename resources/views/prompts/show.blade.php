@extends('layouts.app')

@section('title', $prompt->title)
@section('meta_description', $prompt->description ?? 'A saved prompt.')

@section('content')
    <article class="panel">
        <div class="prompt-card__top">
            <span class="badge badge--category">{{ $prompt->category->name }}</span>

            @if ($prompt->isSystem())
                <span class="badge badge--system" title="Sample content, read-only for regular users">
                    System sample
                </span>
            @elseif ($prompt->visibility === 'private')
                <span class="badge badge--private" title="Visible only to its owner">Private</span>
            @else
                <span class="meta-chip">By <strong>{{ $prompt->user->name }}</strong></span>
            @endif
        </div>

        <h1 class="prompt-detail__title">{{ $prompt->title }}</h1>

        @if (filled($prompt->description))
            <p class="prompt-card__desc">{{ $prompt->description }}</p>
        @endif

        {{-- Community signals. Visible to everyone, since ratings and copies are
             not private information. --}}
        <ul class="prompt-stats">
            <li class="stat-pill">
                <span class="stat-pill__icon" aria-hidden="true">&#9733;</span>
                @if ($prompt->ratings_count > 0)
                    <span class="stat-pill__value">
                        {{ number_format((float) $prompt->ratings_avg_rating, 1) }}
                    </span>
                    <span class="stat-pill__note">
                        of 5 from {{ number_format($prompt->ratings_count) }}
                        {{ Str::plural('rating', $prompt->ratings_count) }}
                    </span>
                @else
                    <span class="stat-pill__note">No ratings yet</span>
                @endif
            </li>

            <li class="stat-pill">
                <span class="stat-pill__icon" aria-hidden="true">&#9825;</span>
                <span class="stat-pill__value" id="favorite-count">
                    {{ number_format($prompt->favorites_count) }}
                </span>
                <span class="stat-pill__note">{{ Str::plural('favorite', $prompt->favorites_count) }}</span>
            </li>

            <li class="stat-pill">
                <span class="stat-pill__icon" aria-hidden="true">&#10697;</span>
                {{-- Updated in place by public/js/app.js after a successful copy. --}}
                <span class="stat-pill__value" id="copy-count">{{ number_format($prompt->copy_count) }}</span>
                <span class="stat-pill__note">{{ Str::plural('copy', $prompt->copy_count) }}</span>
            </li>
        </ul>

        <dl class="prompt-card__meta">
            <div class="meta-chip">
                <dt>Model</dt>
                <dd>{{ $prompt->ai_model }}</dd>
            </div>
            <div class="meta-chip">
                <dt>Language</dt>
                <dd>{{ $prompt->language }}</dd>
            </div>
            <div class="meta-chip">
                <dt>Added</dt>
                <dd>{{ $prompt->created_at?->format('j M Y') }}</dd>
            </div>
        </dl>

        <div class="prompt-card__body">
            <h2 class="prompt-card__label">Prompt text</h2>
            {{-- Escaped by Blade, and read verbatim by the copy button, so prompt
                 content is never rendered as HTML. --}}
            <pre class="prompt-text prompt-text--full" id="prompt-text">{{ $prompt->prompt_text }}</pre>
        </div>

        @if ($prompt->tags->isNotEmpty())
            <ul class="tag-list">
                @foreach ($prompt->tags as $tag)
                    <li class="tag">{{ $tag->name }}</li>
                @endforeach
            </ul>
        @endif

        @auth
            <div class="prompt-toolbar">
                <form method="POST" action="{{ route('prompts.favorite', $prompt) }}" class="prompt-toolbar__form">
                    @csrf
                    <button type="submit"
                            class="btn btn--sm @if ($isFavorited) btn--active @endif"
                            aria-pressed="{{ $isFavorited ? 'true' : 'false' }}">
                        <span aria-hidden="true">{{ $isFavorited ? '&#9829;' : '&#9825;' }}</span>
                        {{ $isFavorited ? 'Saved to favorites' : 'Add to favorites' }}
                    </button>
                </form>

                {{-- The copy endpoint is only called after the clipboard write
                     succeeds, so the counter reflects real copies. --}}
                <button type="button"
                        class="btn btn--sm"
                        data-copy-url="{{ route('prompts.copy', $prompt) }}"
                        data-copy-target="#prompt-text"
                        data-copy-status="#copy-status"
                        data-copy-counter="#copy-count">
                    <span aria-hidden="true">&#10697;</span>
                    Copy prompt
                </button>
            </div>

            <p class="copy-status" id="copy-status" role="status" aria-live="polite"></p>

            @if ($prompt->isSystem())
                <p class="prompt-detail__note">
                    This is read-only sample content: it cannot be edited or deleted, but you can
                    favorite, rate and copy it.
                </p>
            @endif

            <section class="rating-block">
                <h2 class="prompt-card__label">Your rating</h2>

                <form method="POST" action="{{ route('prompts.rating', $prompt) }}" class="rating-form">
                    @csrf

                    {{-- Radio inputs in descending order inside a row-reversed flex
                         container: the stars then read 1-5 left to right, while the
                         "checked ~ label" rule still fills the chosen star and every
                         lower one. --}}
                    <div class="rating">
                        @for ($star = 5; $star >= 1; $star--)
                            <input class="rating__input"
                                   type="radio"
                                   id="rating-{{ $star }}"
                                   name="rating"
                                   value="{{ $star }}"
                                   required
                                   @checked((int) old('rating', $viewerRating) === $star)>
                            <label class="rating__label" for="rating-{{ $star }}">
                                <span aria-hidden="true">&#9733;</span>
                                <span class="visually-hidden">{{ $star }} star{{ $star > 1 ? 's' : '' }}</span>
                            </label>
                        @endfor
                    </div>

                    <button type="submit" class="btn btn--primary btn--sm">
                        {{ $viewerRating ? 'Update rating' : 'Save rating' }}
                    </button>
                </form>

                <p class="field__hint">
                    @if ($viewerRating)
                        You rated this {{ $viewerRating }} out of 5. Saving again replaces your existing rating.
                    @else
                        You have not rated this prompt yet.
                    @endif
                </p>
            </section>
        @else
            <p class="prompt-detail__note">
                <a href="{{ route('login') }}">Log in</a> to favorite, rate or copy this prompt.
            </p>
        @endauth

        @if ($versions->isNotEmpty())
            {{-- Visible to the owner only: the controller passes an empty
                 collection to everyone else. --}}
            <section class="version-history">
                <h2 class="prompt-card__label">Earlier versions</h2>
                <p class="field__hint">
                    Each entry is the text that was replaced when a newer version was saved.
                    The current text is the one shown above.
                </p>

                <ul class="version-list">
                    @foreach ($versions as $version)
                        <li class="version-list__item">
                            <details>
                                <summary class="version-list__summary">
                                    <span class="version-list__number">v{{ $version->version_number }}</span>
                                    <span class="version-list__title">{{ $version->title }}</span>
                                    <span class="version-list__meta">
                                        {{ $version->created_at?->format('j M Y H:i') }}
                                        @if ($version->creator)
                                            &middot; {{ $version->creator->name }}
                                        @endif
                                    </span>
                                </summary>

                                @if (filled($version->change_note))
                                    <p class="version-list__note">{{ $version->change_note }}</p>
                                @endif

                                <pre class="prompt-text">{{ $version->prompt_text }}</pre>
                            </details>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @can('update', $prompt)
            <div class="prompt-detail__actions">
                <a class="btn btn--primary" href="{{ route('prompts.edit', $prompt) }}">Edit prompt</a>

                <form method="POST"
                      action="{{ route('prompts.destroy', $prompt) }}"
                      data-confirm="Delete this prompt permanently? Its tags, ratings and saved versions are removed too.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn--danger">Delete prompt</button>
                </form>
            </div>
        @endcan

        <p class="prompt-detail__back">
            <a href="{{ route('library.index') }}">&larr; Back to the library</a>
        </p>
    </article>

    @include('prompts.partials.recommendations', [
        'related' => $related,
        'alsoLiked' => $alsoLiked,
        'alsoLikedSource' => $alsoLikedSource,
    ])
@endsection
