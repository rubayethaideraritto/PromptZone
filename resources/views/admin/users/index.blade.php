@extends('layouts.app')

@section('title', 'Admin — Users')

@section('content')
    <section class="page-head">
        <h1>Users</h1>
        <p class="page-head__sub">
            {{ number_format($users->total()) }} accounts,
            {{ number_format($adminCount) }} administrator(s).
        </p>
    </section>

    @include('admin.partials.nav')

    <section class="panel">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Prompts</th>
                        <th scope="col">Joined</th>
                        <th scope="col">Change role</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>
                                {{ $user->name }}
                                @if ($user->is(auth()->user()))
                                    <span class="badge">You</span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if ($user->isAdmin())
                                    <span class="badge badge--category">Administrator</span>
                                @else
                                    <span class="badge">User</span>
                                @endif
                            </td>
                            <td>{{ number_format($user->prompts_count) }}</td>
                            <td>{{ $user->created_at?->format('j M Y') }}</td>
                            <td>
                                <form method="POST"
                                      action="{{ route('admin.users.update-role', $user) }}"
                                      class="inline-form">
                                    @csrf
                                    @method('PATCH')

                                    <select class="field__input field__input--compact"
                                            name="role"
                                            aria-label="Role for {{ $user->name }}">
                                        <option value="{{ \App\Models\User::ROLE_USER }}" @selected(! $user->isAdmin())>
                                            User
                                        </option>
                                        <option value="{{ \App\Models\User::ROLE_ADMIN }}" @selected($user->isAdmin())>
                                            Administrator
                                        </option>
                                    </select>

                                    <button type="submit" class="btn btn--sm">Save</button>
                                </form>

                                @if ($user->isAdmin() && $adminCount <= 1)
                                    <p class="table__note">
                                        Only administrator. Promote another account before demoting this one.
                                    </p>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </section>
@endsection
