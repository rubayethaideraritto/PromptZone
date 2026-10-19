<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Prompt;
use App\Services\PromptRankingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function __construct(protected readonly PromptRankingService $ranking) {}

    /**
     * The public front page.
     *
     * Featured prompts come from the moderation flag set in the admin area, and
     * the same visibility rule every other listing uses is applied first. When
     * nothing has been featured yet the section is omitted rather than padded
     * with unrelated content.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();

        $featured = Prompt::query()
            ->visibleTo($viewer)
            ->active()
            ->where('is_featured', true)
            ->with(['category', 'user', 'tags'])
            ->withCount(['favorites', 'ratings'])
            ->withAvg('ratings', 'rating')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('landing', [
            'featured' => $featured,
            // The ranked id list behind this is cached for the configured window;
            // the models themselves are fetched fresh so the eager loading and the
            // visibility scope match every other listing.
            'trending' => $this->ranking->trending(6),
            'categories' => Category::query()
                ->where('is_active', true)
                ->withCount(['prompts' => fn ($query) => $query
                    ->where('visibility', Prompt::VISIBILITY_PUBLIC)
                    ->where('status', Prompt::STATUS_ACTIVE),
                ])
                ->orderBy('name')
                ->get(),
            // Figures are counted, never hardcoded.
            'promptCount' => Prompt::query()->visibleTo($viewer)->active()->count(),
            'copyCount' => (int) Prompt::query()->visibleTo($viewer)->active()->sum('copy_count'),
        ]);
    }
}
