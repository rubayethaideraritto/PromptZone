<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        // The password is hashed by the User model's `hashed` cast, and `role`
        // is never set here so the column default ('user') applies.
        $user = User::create($request->validated());

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
