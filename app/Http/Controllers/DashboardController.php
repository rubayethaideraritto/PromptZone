<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * The signed-in user's own dashboard.
     *
     * Every figure is read from the database rather than hardcoded, so the
     * page stays truthful as prompts are created in later phases.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'promptCount' => $user->prompts()->count(),
            'favoriteCount' => $user->favorites()->count(),
            'ratingCount' => $user->ratings()->count(),
            'recentPrompts' => $user->prompts()->orderByDesc('id')->limit(5)->get(),
        ]);
    }
}
