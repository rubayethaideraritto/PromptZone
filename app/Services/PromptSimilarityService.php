<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Finds prompts the author has already written that look like the one they are
 * about to save.
 *
 * This is deliberately advisory. It never prevents a save, and it only ever
 * looks at the author's own prompts: "have I written this before" is a personal
 * check, and searching the whole table would leak the existence of other
 * people's private prompts through the match list.
 *
 * Similarity is the Jaccard overlap of two word sets:
 *
 *     |shared words| / |union of words|
 *
 * which is 0 for unrelated texts and 1 for identical wording. It is easy to
 * explain and to test, and unlike edit distance it is not thrown off by length.
 */
class PromptSimilarityService
{
    /**
     * Possible duplicates, closest first.
     *
     * @return list<array{prompt: Prompt, title_similarity: float, body_similarity: float, reason: string, score: float}>
     */
    public function findSimilar(User $user, string $title, string $body, ?int $ignorePromptId = null): array
    {
        $titleTokens = $this->tokens($title);
        $bodyTokens = $this->tokens($body);

        if ($titleTokens === [] && $bodyTokens === []) {
            return [];
        }

        $titleThreshold = (float) config('prompts.duplicate_title_threshold');
        $bodyThreshold = (float) config('prompts.duplicate_body_threshold');

        $matches = [];

        foreach ($this->candidates($user, array_merge($titleTokens, $bodyTokens), $ignorePromptId) as $candidate) {
            $titleSimilarity = $this->jaccard($titleTokens, $this->tokens($candidate->title));
            $bodySimilarity = $this->jaccard($bodyTokens, $this->tokens($candidate->prompt_text));

            $matchedTitle = $titleSimilarity >= $titleThreshold;
            $matchedBody = $bodySimilarity >= $bodyThreshold;

            if (! $matchedTitle && ! $matchedBody) {
                continue;
            }

            $matches[] = [
                'prompt' => $candidate,
                'title_similarity' => round($titleSimilarity, 3),
                'body_similarity' => round($bodySimilarity, 3),
                // Reported so the warning can say which part of the text is close.
                'reason' => $matchedTitle ? 'title' : 'body',
                'score' => max($titleSimilarity, $bodySimilarity),
            ];
        }

        usort($matches, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, max(1, (int) config('prompts.duplicate_max_matches')));
    }

    /**
     * Narrow the comparison set in SQL before scoring exactly in PHP.
     *
     * Only prompts that share at least one distinctive word can be duplicates, so
     * a LIKE over the longest few words keeps the PHP pass small. The scan is
     * also bounded, so an account with a very large library cannot make this
     * endpoint expensive.
     *
     * @param  list<string>  $tokens
     * @return Collection<int, Prompt>
     */
    protected function candidates(User $user, array $tokens, ?int $ignorePromptId): Collection
    {
        $terms = collect($tokens)
            // Longest words carry the most signal; "the" style noise is already
            // removed by the minimum word length.
            ->sortByDesc(fn (string $token) => mb_strlen($token))
            ->take(5)
            ->values();

        $query = Prompt::query()
            // Scoped to the author's own prompts, which is also what keeps this
            // from being a way to discover other people's content.
            ->where('prompts.user_id', $user->getKey())
            ->select(['prompts.id', 'prompts.title', 'prompts.prompt_text']);

        if ($ignorePromptId !== null) {
            $query->whereKeyNot($ignorePromptId);
        }

        if ($terms->isNotEmpty()) {
            $query->where(function ($group) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%'.$this->escapeLike($term).'%';

                    $group->orWhere('prompts.title', 'like', $like)
                        ->orWhere('prompts.prompt_text', 'like', $like);
                }
            });
        }

        return $query->limit(200)->get();
    }

    /**
     * Lowercase words of usable length, as a set.
     *
     * The character class keeps any Unicode letter or digit, so non-Latin text is
     * tokenised rather than discarded.
     *
     * @return list<string>
     */
    protected function tokens(string $text): array
    {
        $normalised = mb_strtolower($text);
        $normalised = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalised) ?? '';
        $minimum = (int) config('prompts.duplicate_min_word_length');

        return collect(preg_split('/\s+/u', $normalised) ?: [])
            ->filter(fn (string $word) => mb_strlen($word) >= $minimum)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    protected function jaccard(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($left, $right));
        $union = count(array_unique(array_merge($left, $right)));

        return $union === 0 ? 0.0 : $intersection / $union;
    }

    /**
     * Neutralise LIKE wildcards in a word taken from user text.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
