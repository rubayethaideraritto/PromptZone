<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Services\PromptSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FavoriteController extends Controller
{
    /**
     * The prompts the signed-in user has favorited.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $prompts = $user->favorites()
            // A prompt can be favorited while public and turned private later, so
            // the same visibility rule the library uses is re-applied here: a
            // favorite must never outlive the right to read the prompt.
            ->visibleTo($user)
            ->active()
            // Eager loaded with the same aggregates the library cards expect.
            ->with(['category', 'user', 'tags'])
            ->withCount(['favorites', 'ratings'])
            ->withAvg('ratings', 'rating')
            // Most recently saved first. The pivot column must be qualified
            // because prompts has a created_at of its own, and id breaks ties so
            // pagination stays stable.
            ->orderByDesc('favorites.created_at')
            ->orderByDesc('prompts.id')
            ->paginate(PromptSearchService::PER_PAGE)
            ->withQueryString();

        return view('favorites.index', ['prompts' => $prompts]);
    }

    /**
     * Toggle the signed-in user's favourite for a prompt.
     *
     * Duplicates are impossible: the favorites table has a composite primary key
     * on (user_id, prompt_id), and toggle() only ever inserts a pair that is
     * absent or deletes one that is present, so repeated clicking cannot create
     * a second row.
     */
    public function toggle(Request $request, Prompt $prompt): RedirectResponse
    {
        // A prompt the viewer may not read must not be interactive either: 404
        // rather than 403 so the response cannot confirm that a private id exists.
        abort_unless(Gate::allows('view', $prompt), 404);

        $changes = $request->user()->favorites()->toggle($prompt->getKey());

        return back()->with('status', $changes['attached'] !== []
            ? 'Added to your favorites.'
            : 'Removed from your favorites.');
    }
}
