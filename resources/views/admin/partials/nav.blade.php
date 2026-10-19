{{-- Shared sub-navigation for every administration screen. --}}
<nav class="admin-nav" aria-label="Administration">
    <a href="{{ route('admin.dashboard') }}"
       @class(['is-current' => request()->routeIs('admin.dashboard')])>Overview</a>

    <a href="{{ route('admin.categories.index') }}"
       @class(['is-current' => request()->routeIs('admin.categories.*')])>Categories</a>

    <a href="{{ route('admin.users.index') }}"
       @class(['is-current' => request()->routeIs('admin.users.*')])>Users</a>

    <a href="{{ route('admin.prompts.index') }}"
       @class(['is-current' => request()->routeIs('admin.prompts.*')])>Moderation</a>
</nav>
