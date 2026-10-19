<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RatingController extends Controller
{
    /**
     * Create or update the signed-in user's rating for a prompt.
     *
     * "One rating per user per prompt" is enforced twice over: by the unique key
     * uq_ratings_user_prompt(user_id, prompt_id) in the schema, and by
     * updateOrCreate here, which finds the existing row and edits it rather than
     * inserting a second one.
     */
    public function store(Request $request, Prompt $prompt): RedirectResponse
    {
        // Reading the prompt is a precondition for rating it.
        abort_unless(Gate::allows('view', $prompt), 404);

        // ratings.rating is TINYINT UNSIGNED with no CHECK constraint, so MariaDB
        // would happily store 0 or 200. The 1-5 range exists only because it is
        // validated here, which is why this rule is not optional.
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ], [
            'rating.required' => 'Choose a rating from 1 to 5 stars.',
            'rating.integer' => 'Ratings run from 1 to 5 stars.',
            'rating.min' => 'Ratings run from 1 to 5 stars.',
            'rating.max' => 'Ratings run from 1 to 5 stars.',
        ]);

        $prompt->ratings()->updateOrCreate(
            ['user_id' => $request->user()->getKey()],
            ['rating' => $validated['rating']],
        );

        return back()->with('status', 'Your rating has been saved.');
    }
}
