<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Account details and the two settings forms.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile.edit', [
            'user' => $user,
            'promptCount' => $user->prompts()->count(),
            'favoriteCount' => $user->favorites()->count(),
            'ratingCount' => $user->ratings()->count(),
        ]);
    }

    /**
     * Update the account's name and email address.
     *
     * Only `name` and `email` are accepted; both are mass assignable on User.
     * `role` is not fillable anywhere, so this form cannot escalate a privilege.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        return back()->with('status', 'Your account details have been updated.');
    }

    /**
     * Change the account password.
     *
     * The success path is the same whether or not the submitted name or email
     * changed, so nothing here reveals another account's details.
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Verify the current password against the stored hash before anything is
        // written. Throwing here keeps the failure on the form as a field error
        // and leaves the account untouched.
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That does not match your current password.',
            ]);
        }

        // Hash::make produces a bcrypt hash at the configured cost. The User
        // model's `hashed` cast detects an already-hashed value and will not
        // hash it twice.
        $user->password = Hash::make($validated['password']);
        $user->save();

        // Re-issue the session id after a credential change.
        //
        // Two consequences worth knowing. regenerate() also rotates the CSRF
        // token, so a form rendered in another tab before this point will be
        // rejected with 419 until that page is reloaded. And because it does not
        // destroy the previous session, other devices stay signed in: revoking
        // them would need the database session driver plus AuthenticateSession.
        $request->session()->regenerate();

        return back()->with('status', 'Your password has been changed.');
    }
}
