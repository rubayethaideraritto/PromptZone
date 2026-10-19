<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prompt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PromptController extends Controller
{
    /**
     * Public prompts available for moderation.
     *
     * Deliberately restricted to visibility = 'public'. An administrator has no
     * mandate over other people's private prompts, so they are not listed, not
     * counted, and not actionable here. The standard CRUD routes already refuse
     * an admin the ability to edit or delete someone else's prompt, and nothing
     * in this screen reintroduces it.
     */
    public function index(): View
    {
        $prompts = Prompt::query()
            ->where('visibility', Prompt::VISIBILITY_PUBLIC)
            ->with(['category', 'user'])
            ->withCount(['favorites', 'ratings'])
            ->withAvg('ratings', 'rating')
            // Featured first, then newest, with id as a stable tiebreaker.
            ->orderByDesc('is_featured')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.prompts.index', ['prompts' => $prompts]);
    }

    /**
     * Feature or unfeature a public prompt.
     *
     * is_featured is a moderation flag and is not mass assignable, so it is set
     * explicitly here.
     */
    public function toggleFeatured(Prompt $prompt): RedirectResponse
    {
        $this->ensurePublic($prompt);

        $prompt->is_featured = ! $prompt->is_featured;
        $prompt->save();

        return back()->with('status', $prompt->is_featured
            ? 'Prompt featured. It will appear in the featured section.'
            : 'Prompt removed from the featured section.');
    }

    /**
     * Archive or restore a public prompt.
     *
     * Archiving sets status = 'archived', which every listing already filters on
     * via Prompt::scopeActive(), so the prompt disappears from the library
     * without being deleted. Nothing is lost, and the toggle is reversible.
     */
    public function toggleStatus(Prompt $prompt): RedirectResponse
    {
        $this->ensurePublic($prompt);

        $prompt->status = $prompt->status === Prompt::STATUS_ACTIVE
            ? Prompt::STATUS_ARCHIVED
            : Prompt::STATUS_ACTIVE;

        $prompt->save();

        return back()->with('status', $prompt->status === Prompt::STATUS_ARCHIVED
            ? 'Prompt archived. It is hidden from the library until restored.'
            : 'Prompt restored to the library.');
    }

    /**
     * Moderation applies to public content only.
     *
     * 404 rather than 403 for anything else, so the response cannot confirm that
     * a private prompt with this id exists.
     */
    protected function ensurePublic(Prompt $prompt): void
    {
        abort_unless($prompt->visibility === Prompt::VISIBILITY_PUBLIC, 404);
    }
}
