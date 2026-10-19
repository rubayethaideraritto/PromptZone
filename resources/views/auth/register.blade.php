@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <div class="auth-shell">
        <div class="panel auth-card">
            <h1 class="auth-card__title">Create your account</h1>
            <p class="auth-card__sub">Save, organise and reuse your own prompts.</p>

            {{-- No role field is offered: accounts are always created as 'user'. --}}
            <form method="POST" action="{{ route('register') }}" class="form">
                @csrf

                <div class="field">
                    <label class="field__label" for="name">Name</label>
                    <input class="field__input"
                           id="name"
                           name="name"
                           type="text"
                           value="{{ old('name') }}"
                           required
                           autofocus
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
                           value="{{ old('email') }}"
                           required
                           autocomplete="username"
                           maxlength="190"
                           @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email')
                        <p class="field__error" id="email-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field">
                    <label class="field__label" for="password">Password</label>
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
                    <label class="field__label" for="password_confirmation">Confirm password</label>
                    <input class="field__input"
                           id="password_confirmation"
                           name="password_confirmation"
                           type="password"
                           required
                           autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn--primary btn--block">Create account</button>
            </form>

            <p class="auth-card__alt">
                Already registered? <a href="{{ route('login') }}">Log in</a>
            </p>
        </div>
    </div>
@endsection
