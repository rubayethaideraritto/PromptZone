{{--
    Recommendation strips for a prompt's detail page.

    Expects:
      $related          Collection of prompts sharing tags
      $alsoLiked        Collection from the co-favorite lookup or a fallback
      $alsoLikedSource  'co_favorites' | 'related' | 'trending'

    Every prompt here has already been through the visibility scope, and every one
    arrives with category, user, tags and the aggregates eager loaded, so a strip
    costs no extra queries per card.
--}}
@php
    // When the co-favorite lookup found too little and fell back to the related
    // list, both strips would hold the same prompts, so only one is rendered.
    $relatedIsDistinct = $related->isNotEmpty() && $alsoLikedSource !== 'related';
@endphp

@if ($relatedIsDistinct)
    <section class="section">
        <div class="section__head">
            <h2 class="section__title">Related prompts</h2>
            <p class="section__note">Sharing tags with this one.</p>
        </div>

        <ul class="prompt-grid">
            @foreach ($related as $prompt)
                @include('prompts.partials.card', ['prompt' => $prompt])
            @endforeach
        </ul>
    </section>
@endif

@if ($alsoLiked->isNotEmpty())
    <section class="section">
        <div class="section__head">
            <h2 class="section__title">
                @if ($alsoLikedSource === 'co_favorites')
                    Users also liked
                @elseif ($alsoLikedSource === 'related')
                    Related prompts
                @else
                    Trending prompts
                @endif
            </h2>

            {{-- Stated plainly rather than implied: when there is not enough
                 interaction data, this is not a personalised recommendation. --}}
            @if ($alsoLikedSource !== 'co_favorites')
                <p class="section__note">
                    Not enough favorites on this prompt yet to personalise the list, so
                    {{ $alsoLikedSource === 'related' ? 'prompts sharing its tags' : 'currently trending prompts' }}
                    are shown instead.
                </p>
            @endif
        </div>

        <ul class="prompt-grid">
            @foreach ($alsoLiked as $prompt)
                @include('prompts.partials.card', ['prompt' => $prompt])
            @endforeach
        </ul>
    </section>
@endif
