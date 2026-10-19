<?php

namespace App\Services;

use App\Models\Prompt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The ranking formulas, expressed once as SQL so that ordering happens in the
 * database. That keeps filtering, pagination and the query count unaffected:
 * a ranked page still costs the same handful of queries as an unranked one, and
 * no ranking runs N+1.
 *
 * Formula references are PROJECT.md section 10.
 */
class PromptRankingService
{
    /**
     * Cache key for the public trending list. Deliberately not viewer specific:
     * it is the front page strip, which is the same for everybody and is the
     * list the PRD asks to cache.
     */
    public const TRENDING_CACHE_KEY = 'prompts.trending.ids';

    /**
     * The trending score:
     *
     *   (1.5*ln(1+copies) + 2.0*ln(1+favorites) + rating_component)
     *     / pow(2 + age_hours, 0.65)
     *
     * where rating_component is 0 when there are no ratings, otherwise
     * (average/5) * ln(1 + rating_count).
     *
     * The age divisor is what makes this "trending" rather than "all time":
     * a prompt with the same engagement scores lower every hour, so newer
     * activity outranks older activity of the same size.
     *
     * COALESCE around the rating component matters: avg() over no rows is NULL,
     * and NULL would poison the whole sum into NULL rather than contributing 0.
     */
    public function trendingExpression(): string
    {
        $favorites = $this->correlatedCount('favorites');
        $ratings = $this->correlatedCount('ratings');
        $averageRating = '(select avg(ratings.rating) from ratings where ratings.prompt_id = prompts.id)';

        return '('
            .'1.5 * ln(1 + prompts.copy_count)'
            .' + 2.0 * ln(1 + '.$favorites.')'
            .' + coalesce(('.$averageRating.' / 5) * ln(1 + '.$ratings.'), 0)'
            .') / pow(2 + coalesce(timestampdiff(second, prompts.created_at, now()) / 3600, 0), 0.65)';
    }

    /**
     * The Wilson lower bound of the positive-rating proportion.
     *
     *   (p + z^2/(2n) - z*sqrt((p(1-p) + z^2/(4n))/n)) / (1 + z^2/n)
     *
     * with n the total number of ratings and p the proportion of them that are
     * at least wilson_positive_min. Returns 0 when n is 0.
     *
     * The lower bound is the point: it is the pessimistic end of the confidence
     * interval, so a single 5-star rating cannot outrank a prompt with twenty
     * consistently positive ratings. It is a ranking metric, not an average, and
     * must not be displayed as a star rating.
     */
    public function wilsonExpression(): string
    {
        $z = (float) config('prompts.wilson_z');
        $zSquared = round($z * $z, 6);
        $minimumPositive = (int) config('prompts.wilson_positive_min');

        $total = $this->correlatedCount('ratings');
        $positive = $this->correlatedCount('ratings', 'ratings.rating >= '.$minimumPositive);

        // p = positive / total. Repeated rather than aliased, because a select
        // alias cannot be referenced from another expression in the same list.
        $proportion = '('.$positive.' / '.$total.')';

        return 'case when '.$total.' = 0 then 0 else ('
            .'('.$proportion
            .' + '.$zSquared.' / (2 * '.$total.')'
            .' - '.$z.' * sqrt((('.$proportion.' * (1 - '.$proportion.'))'
            .' + '.$zSquared.' / (4 * '.$total.')) / '.$total.'))'
            .' / (1 + '.$zSquared.' / '.$total.')'
            .') end';
    }

    /**
     * The ordered ids of the publicly trending prompts, cached for the
     * configured window.
     *
     * Only the id list is cached, never the hydrated models: fetching the rows
     * afterwards keeps the eager loading and the visibility scope identical to
     * every other listing.
     *
     * @return list<int>
     */
    public function trendingIds(int $limit = 6): array
    {
        $minutes = max(1, (int) config('prompts.trending_cache_minutes'));

        return Cache::remember(
            self::TRENDING_CACHE_KEY.'.'.$limit,
            now()->addMinutes($minutes),
            fn (): array => Prompt::query()
                ->where('prompts.visibility', Prompt::VISIBILITY_PUBLIC)
                ->active()
                ->select('prompts.id')
                ->selectRaw($this->trendingExpression().' as trend_score')
                // id breaks ties so the order is stable while every score is
                // still zero, which is the state of a freshly imported dataset.
                ->orderByDesc('trend_score')
                ->orderByDesc('prompts.id')
                ->limit($limit)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );
    }

    /**
     * The trending prompts themselves, in ranked order.
     *
     * @return Collection<int, Prompt>
     */
    public function trending(int $limit = 6): Collection
    {
        $ids = $this->trendingIds($limit);

        if ($ids === []) {
            return collect();
        }

        // FIELD() preserves the cached ranking, which a plain whereIn would lose.
        $order = implode(',', array_map('intval', $ids));

        return Prompt::query()
            ->whereKey($ids)
            ->with(['category', 'user', 'tags'])
            ->withCount(['favorites', 'ratings'])
            ->withAvg('ratings', 'rating')
            ->orderByRaw('field(prompts.id, '.$order.')')
            ->get();
    }

    /**
     * Forget the cached trending list.
     *
     * Called when a moderation action changes what the front page would show.
     */
    public function forgetTrending(): void
    {
        foreach ([3, 4, 6, 8, 12] as $limit) {
            Cache::forget(self::TRENDING_CACHE_KEY.'.'.$limit);
        }
    }

    /**
     * A correlated count of child rows for the current prompt.
     *
     * Written as a subquery rather than a join because a join would multiply the
     * prompt rows and force DISTINCT, which then breaks pagination counts.
     */
    protected function correlatedCount(string $table, ?string $extraCondition = null): string
    {
        $sql = '(select count(*) from '.$table.' where '.$table.'.prompt_id = prompts.id';

        if ($extraCondition !== null) {
            $sql .= ' and '.$extraCondition;
        }

        return $sql.')';
    }

    /**
     * Diagnostic helper for verification and reporting: the computed scores for
     * the public prompts, highest first.
     *
     * @return Collection<int, object>
     */
    public function trendScoreReport(int $limit = 10): Collection
    {
        return DB::table('prompts')
            ->where('visibility', Prompt::VISIBILITY_PUBLIC)
            ->where('status', Prompt::STATUS_ACTIVE)
            ->select('prompts.id', 'prompts.title', 'prompts.copy_count')
            ->selectRaw($this->trendingExpression().' as trend_score')
            ->selectRaw($this->wilsonExpression().' as wilson_score')
            ->orderByDesc('trend_score')
            ->limit($limit)
            ->get();
    }
}
