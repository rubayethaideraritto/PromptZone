@extends('layouts.app')

@section('title', 'Admin — Moderation')

@section('content')
    <section class="page-head">
        <h1>Content moderation</h1>
        <p class="page-head__sub">
            {{ number_format($prompts->total()) }} public
            {{ Str::plural('prompt', $prompts->total()) }} available for moderation.
        </p>
    </section>

    @include('admin.partials.nav')

    <section class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Public prompts</h2>
            <a class="btn btn--sm" href="{{ route('library.index') }}">View in library</a>
        </div>

        @if ($prompts->isEmpty())
            <div class="empty-state">
                <h3>No public prompts to moderate</h3>
                <p>Nothing has been published, or everything public has been archived.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">Prompt</th>
                            <th scope="col">Category</th>
                            <th scope="col">Owner</th>
                            <th scope="col">Signals</th>
                            <th scope="col">Flags</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prompts as $prompt)
                            <tr>
                                <td>
                                    <a href="{{ route('prompts.show', $prompt) }}">{{ $prompt->title }}</a>
                                </td>
                                <td>{{ $prompt->category->name }}</td>
                                <td>
                                    @if ($prompt->isSystem())
                                        <span class="badge badge--system">System sample</span>
                                    @else
                                        {{ $prompt->user->name }}
                                    @endif
                                </td>
                                <td class="table__numeric">
                                    &#9733;
                                    @if ($prompt->ratings_count > 0)
                                        {{ number_format((float) $prompt->ratings_avg_rating, 1) }}
                                        <span class="table__note">({{ number_format($prompt->ratings_count) }})</span>
                                    @else
                                        <span class="table__note">none</span>
                                    @endif
                                    &middot; &#9825; {{ number_format($prompt->favorites_count) }}
                                    &middot; &#10697; {{ number_format($prompt->copy_count) }}
                                </td>
                                <td>
                                    @if ($prompt->is_featured)
                                        <span class="badge badge--featured">Featured</span>
                                    @endif
                                    @if ($prompt->status === \App\Models\Prompt::STATUS_ARCHIVED)
                                        <span class="badge badge--inactive">Archived</span>
                                    @endif
                                    @if (! $prompt->is_featured && $prompt->status === \App\Models\Prompt::STATUS_ACTIVE)
                                        <span class="table__note">No flags</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="table__actions">
                                        <form method="POST" action="{{ route('admin.prompts.feature', $prompt) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn--sm">
                                                {{ $prompt->is_featured ? 'Unfeature' : 'Feature' }}
                                            </button>
                                        </form>

                                        <form method="POST"
                                              action="{{ route('admin.prompts.status', $prompt) }}"
                                              @if ($prompt->status === \App\Models\Prompt::STATUS_ACTIVE)
                                                  data-confirm="Archive this prompt? It will disappear from the public library until restored."
                                              @endif>
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn--sm {{ $prompt->status === \App\Models\Prompt::STATUS_ACTIVE ? 'btn--danger' : '' }}">
                                                {{ $prompt->status === \App\Models\Prompt::STATUS_ACTIVE ? 'Archive' : 'Restore' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $prompts->links() }}
        @endif

        <p class="panel__note">
            Only <strong>public</strong> prompts appear here. Another user's private prompt is
            never listed, and no administrator can edit or delete one: ownership rules apply to
            administrators too.
        </p>
    </section>
@endsection
