@extends('layouts.app')

@section('title', 'Admin — Categories')

@section('content')
    <section class="page-head">
        <h1>Categories</h1>
        <p class="page-head__sub">
            {{ number_format($categories->count()) }} categories,
            {{ number_format($categories->where('is_active', true)->count()) }} active.
        </p>
    </section>

    @include('admin.partials.nav')

    @if ($editing)
        <section class="panel">
            <div class="panel__head">
                <h2 class="panel__title">Edit &ldquo;{{ $editing->name }}&rdquo;</h2>
                <a class="btn btn--sm" href="{{ route('admin.categories.index') }}">Cancel</a>
            </div>

            <form method="POST" action="{{ route('admin.categories.update', $editing) }}" class="form">
                @csrf
                @method('PUT')

                @include('admin.categories.partials.fields', ['category' => $editing])

                <div class="form__actions">
                    <button type="submit" class="btn btn--primary">Save category</button>
                </div>
            </form>
        </section>
    @else
        <section class="panel">
            <h2 class="panel__title">Add a category</h2>

            <form method="POST" action="{{ route('admin.categories.store') }}" class="form">
                @csrf

                @include('admin.categories.partials.fields', ['category' => null])

                <div class="form__actions">
                    <button type="submit" class="btn btn--primary">Create category</button>
                </div>
            </form>
        </section>
    @endif

    <section class="panel">
        <h2 class="panel__title">All categories</h2>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Slug</th>
                        <th scope="col">Prompts</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td>
                                <a href="{{ route('admin.categories.index', ['edit' => $category->id]) }}">
                                    {{ $category->name }}
                                </a>
                                @if (filled($category->description))
                                    <p class="table__note">{{ $category->description }}</p>
                                @endif
                            </td>
                            <td><code>{{ $category->slug }}</code></td>
                            <td>{{ number_format($category->prompts_count) }}</td>
                            <td>
                                @if ($category->is_active)
                                    <span class="badge badge--active">Active</span>
                                @else
                                    <span class="badge badge--inactive">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="table__actions">
                                    <form method="POST" action="{{ route('admin.categories.toggle-active', $category) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn--sm">
                                            {{ $category->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>

                                    {{-- Deleting is refused while prompts reference the
                                         category; the controller explains why. --}}
                                    <form method="POST"
                                          action="{{ route('admin.categories.destroy', $category) }}"
                                          data-confirm="Delete the category {{ $category->name }}? This cannot be undone.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--sm btn--danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="panel__note">
            A category with prompts cannot be deleted &mdash; <code>prompts.category_id</code>
            is declared <code>ON DELETE RESTRICT</code>. Deactivate it instead, or move its
            prompts to another category first.
        </p>
    </section>
@endsection
