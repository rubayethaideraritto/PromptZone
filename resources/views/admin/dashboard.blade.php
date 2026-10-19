@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <section class="page-head page-head--split">
        <div>
            <h1>Admin Dashboard</h1>
            <p class="page-head__sub">Overview of accounts, content and categories.</p>
        </div>
        <a class="btn" href="{{ route('library.index') }}">View public library</a>
    </section>

    @include('admin.partials.nav')

    <section class="stat-grid" aria-label="Site totals">
        <div class="stat-card">
            <p class="stat-card__label">Users</p>
            <p class="stat-card__value">{{ number_format($userCount) }}</p>
            <p class="stat-card__note">{{ number_format($adminCount) }} administrator(s)</p>
        </div>
        <div class="stat-card">
            <p class="stat-card__label">Prompts</p>
            <p class="stat-card__value">{{ number_format($promptCount) }}</p>
            <p class="stat-card__note">{{ number_format($publicPromptCount) }} public</p>
        </div>
        <div class="stat-card">
            <p class="stat-card__label">Categories</p>
            <p class="stat-card__value">{{ number_format($categoryCount) }}</p>
            <p class="stat-card__note">{{ number_format($activeCategoryCount) }} active</p>
        </div>
        <div class="stat-card">
            <p class="stat-card__label">Prompt copies</p>
            <p class="stat-card__value">{{ number_format($totalCopies) }}</p>
            <p class="stat-card__note">Lifetime total</p>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel__title">Management</h2>

        <ul class="plain-list">
            <li class="plain-list__item">
                <div>
                    <a class="plain-list__main" href="{{ route('admin.categories.index') }}">Categories</a>
                    <p class="panel__note">
                        Create and rename categories, activate or deactivate them, and delete the
                        unused ones. A category that still has prompts cannot be deleted.
                    </p>
                </div>
            </li>
            <li class="plain-list__item">
                <div>
                    <a class="plain-list__main" href="{{ route('admin.users.index') }}">Users</a>
                    <p class="panel__note">
                        Review accounts and grant or remove administrator access. The last
                        remaining administrator cannot be demoted.
                    </p>
                </div>
            </li>
            <li class="plain-list__item">
                <div>
                    <a class="plain-list__main" href="{{ route('admin.prompts.index') }}">Content moderation</a>
                    <p class="panel__note">
                        Feature or archive <strong>public</strong> prompts. Private prompts belong
                        to their owners and are never listed or altered here.
                    </p>
                </div>
            </li>
        </ul>
    </section>
@endsection
