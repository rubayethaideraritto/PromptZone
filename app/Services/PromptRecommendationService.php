<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Related and recommended prompts.
 *
 * Both methods start from Prompt::visibleTo(), so a recommendation can never
 * surface a prompt the viewer is not allowed to read, and both eager load
 * everything the shared card partial renders, so a strip of four costs the same
 * fixed set of queries as a strip of one.
 */
class PromptRecommendationService
{
    public function __construct(protected readonly PromptRankingService $ranking) {}

    /**
     * Prompts sharing tags with the given one, most shared tags first.
     *
     * "Related" is defined strictly as shared-tag overlap, per the PRD. A prompt
     * with no tags therefore has no related prompts, and the caller hides the
     * section rather than padding it with unrelated content.
     *
     * @return Collection<int, Prompt>
     */
    public function related(Prompt $prompt, ?User $viewer, ?int $limit = null): Collection
    {
        $limit ??= (int) config('prompts.related_limit');

        $tagIds = $prompt->tags->pluck('id')->all();

        if ($tagIds === []) {
            return collect();
        }

        return Prompt::query()
            // The visibility rule, always first.
            ->visibleTo($viewer)
            ->active()
            ->whereKeyNot($prompt->getKey())
            ->whereHas('tags', fn ($query) => $query->whereIn('tags.id', $tagIds))
            ->with(['category', 'user', 'tags'])
            ->withCount([
                'favorites',
                'ratings',
                // Ranks by how many tags the two prompts share.
                'tags as shared_tags_count' => fn ($query) => $query->whereIn('tags.id', $tagIds),
            ])
            ->withAvg('ratings', 'rating')
            ->orderByDesc('shared_tags_count')
            ->orderByDesc('prompts.id')
            ->limit($limit)
            ->get();
    }

    /**
     * "Users also liked": prompts that people who favorited this one also
     * favorited, excluding anything the viewer has already saved.
     *
     * Item-to-item rather than user-to-user, so it produces a sensible answer for
     * a guest too, who has no favourite history of their own to compare with.
     *
     * Falls back exactly as the PRD requires when the interaction data is too
     * sparse: related prompts first, then trending. The chosen source is returned
     * so the view can state which one produced the list instead of implying a
     * personalisation that did not happen.
     *
     * @return array{prompts: Collection<int, Prompt>, source: string}
     */
    public function alsoLiked(Prompt $prompt, ?User $viewer, ?int $limit = null): array
    {
        $limit ??= (int) config('prompts.also_liked_limit');

        // The users who favorited this prompt.
        $fanIds = DB::table('favorites')
            ->where('prompt_id', $prompt->getKey())
            ->pluck('user_id');

        if ($fanIds->isNotEmpty()) {
            $alreadySaved = $viewer !== null
                ? $viewer->favorites()->pluck('prompts.id')->all()
                : [];

            $prompts = Prompt::query()
                ->visibleTo($viewer)
                ->active()
                ->whereKeyNot($prompt->getKey())
                // Anything at least one of those users also favorited.
                ->whereIn('prompts.id', function ($sub) use ($fanIds) {
                    $sub->select('prompt_id')->from('favorites')->whereIn('user_id', $fanIds);
                })
                ->when($alreadySaved !== [], fn ($query) => $query->whereNotIn('prompts.id', $alreadySaved))
                ->with(['category', 'user', 'tags'])
                ->withCount([
                    'favorites',
                    'ratings',
                    // How many of those users favorited it.
                    'favorites as co_favorite_count' => fn ($query) => $query->whereIn('favorites.user_id', $fanIds),
                ])
                ->withAvg('ratings', 'rating')
                ->orderByDesc('co_favorite_count')
                ->orderByDesc('prompts.id')
                ->limit($limit)
                ->get();

            if ($prompts->isNotEmpty()) {
                return ['prompts' => $prompts, 'source' => 'co_favorites'];
            }
        }

        $related = $this->related($prompt, $viewer, $limit);

        if ($related->isNotEmpty()) {
            return ['prompts' => $related, 'source' => 'related'];
        }

        return ['prompts' => $this->ranking->trending($limit), 'source' => 'trending'];
    }
}
