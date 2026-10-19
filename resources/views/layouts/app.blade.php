<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Prompt Library'))</title>
    <meta name="description" content="@yield('meta_description', 'A searchable library of reusable AI prompts.')">
    {{-- Read by public/js/app.js to authorise the copy fetch and the AI panel. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{--
        Assets carry a modification stamp. They are served from /public with no
        Cache-Control, ETag or Last-Modified header, so a browser is free to apply
        heuristic caching and keep running a stale script after a change - which
        is exactly how a newly added feature can appear to be missing.
    --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">

    {{-- Inter - the typeface used across the whole interface. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header">
        <div class="wrap site-header__inner">
            <a class="brand" href="{{ url('/') }}">
                <span class="brand__mark" aria-hidden="true">PL</span>
                <span class="brand__text">{{ config('app.name', 'Prompt Library') }}</span>
            </a>

            <nav class="site-nav" aria-label="Main">
                <a href="{{ url('/library') }}" @class(['is-current' => request()->is('library*')])>Library</a>

                @auth
                    <a href="{{ route('dashboard') }}" @class(['is-current' => request()->is('dashboard')])>Dashboard</a>
                    <a href="{{ route('favorites.index') }}" @class(['is-current' => request()->is('favorites')])>Favorites</a>

                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" @class(['is-current' => request()->is('admin*')])>Admin</a>
                    @endif

                    <a href="{{ route('profile.edit') }}" @class(['is-current' => request()->is('profile')])>Profile</a>

                    <span class="site-nav__user" title="{{ auth()->user()->email }}">
                        {{ auth()->user()->name }}
                    </span>

                    <form method="POST" action="{{ route('logout') }}" class="site-nav__form">
                        @csrf
                        <button type="submit" class="link-button">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" @class(['is-current' => request()->is('login')])>Log in</a>
                    <a href="{{ route('register') }}" class="btn btn--primary btn--sm">Register</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="wrap main" id="main">
        @include('partials.alerts')

        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <p>AI Prompt Library &amp; Manager</p>
        </div>
    </footer>

    {{-- Flash notifications. The fixed wrapper keeps them in the bottom-right
         corner of the viewport, outside the document flow. --}}
    <div class="toast-stack">
        @include('partials.flash')
    </div>

    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
</body>
</html>
