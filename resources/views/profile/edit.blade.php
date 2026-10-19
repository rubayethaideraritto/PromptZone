@extends('layouts.app')

@section('title', 'Your profile')

@section('content')
    <section class="page-head">
        <h1>Your profile</h1>
        <p class="page-head__sub">
            Signed in as {{ $user->email }}
            @if ($user->isAdmin())
                <span class="badge badge--category">Administrator</span>
            @endif
        </p>
    </section>

    <div class="profile-grid">
        <div>
            <section class="panel">
                <h2 class="panel__title">Account details</h2>
                <p class="panel__note">Your name is shown on prompts you publish.</p>

                <form method="POST" action="{{ route('profile.update') }}" class="form">
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label class="field__label" for="name">Name</label>
                        <input class="field__input"
                               id="name"
                               name="name"
                               type="text"
                               value="{{ old('name', $user->name) }}"
                               required
                               autocomplete="name"
                               maxlength="120"
                               @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                        @error('name')
                            <p class="field__error" id="name-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="email">Email address</label>
                        <input class="field__input"
                               id="email"
                               name="email"
                               type="email"
                               value="{{ old('email', $user->email) }}"
                               required
                               autocomplete="username"
                               maxlength="190"
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        <p class="field__hint">Used to sign in, and must be unique.</p>
                        @error('email')
                            <p class="field__error" id="email-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form__actions">
                        <button type="submit" class="btn btn--primary">Save details</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <h2 class="panel__title">Change password</h2>
                <p class="panel__note">
                    Your current password is required. You will stay signed in on this device.
                </p>

                <form method="POST" action="{{ route('profile.password') }}" class="form">
                    @csrf
                    @method('PUT')

                    <div class="field">
                        <label class="field__label" for="current_password">Current password</label>
                        <input class="field__input"
                               id="current_password"
                               name="current_password"
                               type="password"
                               required
                               autocomplete="current-password"
                               @error('current_password') aria-invalid="true" aria-describedby="current_password-error" @enderror>
                        @error('current_password')
                            <p class="field__error" id="current_password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="password">New password</label>
                        <input class="field__input"
                               id="password"
                               name="password"
                               type="password"
                               required
                               autocomplete="new-password"
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        <p class="field__hint">At least 8 characters.</p>
                        @error('password')
                            <p class="field__error" id="password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field__label" for="password_confirmation">Confirm new password</label>
                        <input class="field__input"
                               id="password_confirmation"
                               name="password_confirmation"
                               type="password"
                               required
                               autocomplete="new-password">
                    </div>

                    <div class="form__actions">
                        <button type="submit" class="btn btn--primary">Change password</button>
                    </div>
                </form>
            </section>
        </div>

        <aside>
            <section class="panel">
                <h2 class="panel__title">Your activity</h2>

                <ul class="plain-list">
                    <li class="plain-list__item">
                        <a class="plain-list__main" href="{{ route('library.index', ['mine' => 1]) }}">
                            My prompts
                        </a>
                        <span class="meta-chip">{{ number_format($promptCount) }}</span>
                    </li>
                    <li class="plain-list__item">
                        <a class="plain-list__main" href="{{ route('favorites.index') }}">Favorites</a>
                        <span class="meta-chip">{{ number_format($favoriteCount) }}</span>
                    </li>
                    <li class="plain-list__item">
                        <span class="plain-list__main">Ratings given</span>
                        <span class="meta-chip">{{ number_format($ratingCount) }}</span>
                    </li>
                </ul>

                <p class="panel__note">
                    Member since {{ $user->created_at?->format('j M Y') }}.
                </p>
            </section>
        </aside>
    </div>
@endsection
