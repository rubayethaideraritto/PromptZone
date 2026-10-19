@extends('layouts.app')

@section('title', 'Your favorites')

@section('content')
    <section class="page-head page-head--split">
        <div>
            <h1>Your favorites</h1>

            <p class="page-head__sub">
                @if ($prompts->total() > 0)
                    Showing
                    <strong>{{ number_format($prompts->firstItem()) }}</strong>&ndash;<strong>{{ number_format($prompts->lastItem()) }}</strong>
                    of <strong>{{ number_format($prompts->total()) }}</strong>
                    saved {{ Str::plural('prompt', $prompts->total()) }}.
                @else
                    Prompts you save will be listed here.
                @endif
            </p>
        </div>

        <a class="btn" href="{{ route('library.index') }}">Browse library</a>
    </section>

    @if ($prompts->isEmpty())
        <div class="empty-state">
            <h2>You have not saved any prompts yet</h2>
            <p>
                Open any prompt and choose <strong>Add to favorites</strong> to keep it here for
                quick access.
            </p>
            <p class="empty-state__actions">
                <a class="btn btn--primary" href="{{ route('library.index') }}">Find prompts to save</a>
            </p>
        </div>
    @else
        {{-- The same card partial the library uses, plus an unfavorite control. --}}
        <ul class="prompt-grid">
            @foreach ($prompts as $prompt)
                @include('prompts.partials.card', ['prompt' => $prompt, 'showRemoveFavorite' => true])
            @endforeach
        </ul>

        {{ $prompts->links() }}
    @endif
@endsection
