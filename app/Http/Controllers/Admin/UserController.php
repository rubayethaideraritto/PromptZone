<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->withCount('prompts')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', [
            'users' => $users,
            'adminCount' => User::adminCount(),
        ]);
    }

    /**
     * Change an account's role between 'user' and 'admin'.
     *
     * The final administrator cannot be demoted: with no administrator left,
     * nobody could restore the role, and the admin area would be permanently
     * unreachable. The check and the write share a transaction so the count
     * cannot go stale between them.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(User::ROLES)],
        ]);

        $role = $validated['role'];

        DB::transaction(function () use ($user, $role) {
            $isDemotion = $user->isAdmin() && $role !== User::ROLE_ADMIN;

            if ($isDemotion && User::adminCount() <= 1) {
                throw ValidationException::withMessages([
                    'role' => $user->is($this->currentUser())
                        ? 'You are the only administrator, so you cannot remove your own admin role. Promote another account first.'
                        : 'This is the only administrator. Promote another account before changing this one.',
                ]);
            }

            // `role` is deliberately not mass assignable, so it is assigned
            // explicitly rather than filled from the request array.
            $user->role = $role;
            $user->save();
        });

        return back()->with('status', sprintf(
            '%s is now %s.',
            $user->name,
            $role === User::ROLE_ADMIN ? 'an administrator' : 'a regular user'
        ));
    }

    /**
     * The signed-in administrator, used only to tailor the refusal message.
     */
    protected function currentUser(): ?User
    {
        return auth()->user();
    }
}
