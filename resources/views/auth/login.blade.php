@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <div class="auth-shell">
        <div class="panel auth-card">
            <h1 class="auth-card__title">Log in</h1>
            <p class="auth-card__sub">Welcome back. Continue to your prompt library.</p>

            <form method="POST" action="{{ route('login') }}" class="form">
                @csrf

                <div class="field">
                    <label class="field__label" for="email">Email address</label>
                    <input class="field__input"
                           id="email"
                           name="email"
                           type="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="username"
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
                           autocomplete="current-password"
                           @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    @error('password')
                        <p class="field__error" id="password-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="field field--checkbox">
                    <input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
                    <label for="remember">Remember me on this device</label>
                </div>

                <button type="submit" class="btn btn--primary btn--block">Log in</button>
            </form>

            <p class="auth-card__alt">
                Don&rsquo;t have an account? <a href="{{ route('register') }}">Register</a>
            </p>
        </div>
    </div>
@endsection
