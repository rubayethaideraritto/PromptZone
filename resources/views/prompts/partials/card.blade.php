{{--
    One prompt card, shared by the library grid and the favorites list so the two
    stay visually identical.

    Expects:
      $prompt              (Prompt) the prompt to render
      $showRemoveFavorite  (bool, optional) render an unfavorite button

    The prompt must be eager loaded with category, user and tags, and carry the
    favorites_count, ratings_count and ratings_avg_rating aggregates, or this
    partial will trigger extra queries per card.
--}}
<li class="prompt-card">
    <div class="prompt-card__top">
        <span class="badge badge--category">{{ $prompt->category->name }}</span>

        @if ($prompt->isSystem())
            <span class="badge badge--system"
                  title="Sample content, read-only for regular users">System sample</span>
        @elseif ($prompt->visibility === 'private')
            <span class="badge badge--private" title="Visible only to its owner">Private</span>
        @else
            <span class="meta-chip">By <strong>{{ $prompt->user->name }}</strong></span>
        @endif
    </div>

    <h2 class="prompt-card__title">
        <a href="{{ route('prompts.show', $prompt) }}">{{ $prompt->title }}</a>
    </h2>

    @if (filled($prompt->description))
        <p class="prompt-card__desc">{{ $prompt->description }}</p>
    @endif

    <dl class="prompt-card__meta">
        <div class="meta-chip">
            <dt>Model</dt>
            <dd>{{ $prompt->ai_model }}</dd>
        </div>
        <div class="meta-chip">
            <dt>Language</dt>
            <dd>{{ $prompt->language }}</dd>
        </div>
    </dl>

    {{-- Counts and the average come from withCount/withAvg on the listing query,
         so they cost no extra database round trip. Zero ratings shows
         "No ratings yet" rather than 0 stars. --}}
    <ul class="card-stats">
        <li class="card-stat" title="Average rating">
            <span class="card-stat__icon" aria-hidden="true">&#9733;</span>
            @if ($prompt->ratings_count > 0)
                <span class="card-stat__value">
                    {{ number_format((float) $prompt->ratings_avg_rating, 1) }}
                </span>
                <span class="card-stat__note">({{ number_format($prompt->ratings_count) }})</span>
            @else
                <span class="card-stat__note">No ratings yet</span>
            @endif
        </li>
        <li class="card-stat" title="Times favorited">
            <span class="card-stat__icon" aria-hidden="true">&#9825;</span>
            <span class="card-stat__value">{{ number_format($prompt->favorites_count) }}</span>
        </li>
        <li class="card-stat" title="Times copied">
            <span class="card-stat__icon" aria-hidden="true">&#10697;</span>
            <span class="card-stat__value">{{ number_format($prompt->copy_count) }}</span>
        </li>
    </ul>

    <div class="prompt-card__body">
        <h3 class="prompt-card__label">Prompt text</h3>
        {{-- Escaped by Blade: prompt content is never rendered as HTML. --}}
        <pre class="prompt-text">{{ $prompt->prompt_text }}</pre>
    </div>

    @if ($prompt->tags->isNotEmpty())
        <ul class="tag-list">
            @foreach ($prompt->tags as $tag)
                <li class="tag">{{ $tag->name }}</li>
            @endforeach
        </ul>
    @endif

    @if ($showRemoveFavorite ?? false)
        <form method="POST"
              action="{{ route('prompts.favorite', $prompt) }}"
              class="prompt-card__action">
            @csrf
            <button type="submit" class="btn btn--sm">Remove from favorites</button>
        </form>
    @endif
</li>
