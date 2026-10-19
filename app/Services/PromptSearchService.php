<?php

namespace App\Services;

use App\Exceptions\AiException;
use App\Models\Prompt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;

/**
 * Keyword/full-text search, filtering and sorting for the prompt library.
 *
 * Kept out of the controller because the search strategy is non-trivial: it
 * chooses between a FULLTEXT match and a LIKE scan, adds a correlated tag
 * lookup, and has to keep pagination stable.
 */
class PromptSearchService
{
    public const PER_PAGE = 12;

    public const SORT_NEWEST = 'newest';

    public const SORT_MOST_COPIED = 'most_copied';

    public const SORT_RELEVANCE = 'relevance';

    public const SORT_TRENDING = 'trending';

    public const SORT_HIGHEST_RATED = 'highest_rated';

    /**
     * Sort values the UI may offer.
     *
     * "relevance" is only meaningful alongside a search term; the view hides it
     * otherwise and the query falls back to newest.
     *
     * @var list<string>
     */
    public const SORTS = [
        self::SORT_NEWEST,
        self::SORT_MOST_COPIED,
        self::SORT_HIGHEST_RATED,
        self::SORT_TRENDING,
        self::SORT_RELEVANCE,
    ];

    public const DEFAULT_SORT = self::SORT_NEWEST;

    /**
     * Display labels for the sort dropdown, kept beside the whitelist so the
     * options offered and the options accepted stay in step.
     *
     * @var array<string, string>
     */
    public const SORT_LABELS = [
        self::SORT_NEWEST => 'Newest first',
        self::SORT_MOST_COPIED => 'Most copied',
        self::SORT_HIGHEST_RATED => 'Highest rated',
        self::SORT_TRENDING => 'Trending',
        self::SORT_RELEVANCE => 'Best match (relevance)',
    ];

    public const MAX_QUERY_LENGTH = 100;

    /**
     * MariaDB/MySQL ignore FULLTEXT tokens shorter than innodb_ft_min_token_size,
     * which is 3 on this server. Anything shorter must use the LIKE fallback.
     */
    protected const MIN_FULLTEXT_TERM_LENGTH = 3;

    /**
     * BOOLEAN MODE operator characters. They are stripped from user input so a
     * query is always treated as words rather than as operators.
     *
     * @var list<string>
     */
    protected const BOOLEAN_OPERATORS = ['+', '-', '>', '<', '(', ')', '~', '*', ':', '"', '&', '|', '@', '\\', '/'];

    public function __construct(
        protected readonly ?User $viewer,
        protected readonly PromptRankingService $ranking,
        protected readonly EmbeddingService $embeddings,
    ) {}

    public static function for(?User $viewer): self
    {
        return app()->make(static::class, ['viewer' => $viewer]);
    }

    /**
     * Why a semantic search fell back to keyword matching, if it did.
     *
     * Surfaced so the interface can say that the results are keyword results
     * rather than silently presenting them as semantic ones.
     */
    protected ?string $semanticNotice = null;

    public function semanticNotice(): ?string
    {
        return $this->semanticNotice;
    }

    /**
     * How much of the eligible set is indexed, so a partly built index is
     * reported honestly instead of looking like a complete search.
     *
     * @var array{indexed: int, eligible: int}
     */
    protected array $semanticCoverage = ['indexed' => 0, 'eligible' => 0];

    /**
     * @return array{indexed: int, eligible: int}
     */
    public function semanticCoverage(): array
    {
        return $this->semanticCoverage;
    }

    /**
     * Read the filter state out of the query string.
     *
     * Values are whitelisted rather than validated with a redirect: a stale or
     * hand-edited filter should quietly show a sensible result set rather than
     * bouncing the user through a validation error that loses their search.
     * Every value is compared against a known list before it can reach a query.
     *
     * @return array{search: string, category_id: int|null, ai_model: string|null, language: string|null, sort: string, mine: bool, semantic: bool}
     */
    public static function filtersFrom(Request $request): array
    {
        $category = self::scalar($request, 'category_id');
        $model = self::scalar($request, 'ai_model');
        $language = self::scalar($request, 'language');
        $sort = self::scalar($request, 'sort');
        $mine = self::scalar($request, 'mine');
        $semantic = self::scalar($request, 'semantic');

        return [
            'search' => mb_substr(self::scalar($request, 'q') ?? '', 0, self::MAX_QUERY_LENGTH),
            'category_id' => filter_var($category, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
            'ai_model' => in_array($model, config('prompts.ai_models'), true) ? $model : null,
            'language' => in_array($language, config('prompts.languages'), true) ? $language : null,
            'sort' => in_array($sort, self::SORTS, true) ? $sort : self::DEFAULT_SORT,
            'mine' => in_array($mine, ['1', 'true'], true),
            'semantic' => in_array($semantic, ['1', 'true'], true),
        ];
    }

    /**
     * @param  array{search: string, category_id: int|null, ai_model: string|null, language: string|null, sort: string, mine: bool, semantic: bool}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $builder = Prompt::query()
            // The shared visibility rule, applied before anything else so no
            // later condition can widen it.
            ->visibleTo($this->viewer)
            ->active()
            // Eager loaded so each rendered card costs no extra query.
            ->with(['category', 'user', 'tags'])
            // Aggregates arrive as correlated sub-selects inside this one query,
            // not as extra round trips, so the card metrics stay N+1 free.
            ->withCount(['favorites', 'ratings'])
            ->withAvg('ratings', 'rating');

        // Semantic mode replaces keyword matching: the query is compared to the
        // stored vectors instead of being searched for as text.
        if ($filters['semantic'] && $filters['search'] !== '') {
            return $this->semanticPaginate($builder, $filters);
        }

        $this->applySearch($builder, $filters['search']);
        $this->applyFilters($builder, $filters);
        $this->applySort($builder, $filters);

        return $builder->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Rank by cosine similarity against an embedded query.
     *
     * The candidate set is narrowed in SQL first (visibility, status, and the
     * dropdown filters) and only then scored, so no prompt the viewer may not
     * read is ever considered. Scoring itself happens in PHP because there is no
     * vector index available: this is linear in the number of candidates, which
     * is acceptable for a library of this size and is documented as the reason
     * it would not scale.
     *
     * When the query cannot be embedded, the keyword search runs instead and a
     * notice is recorded, so the results are never mislabelled as semantic.
     *
     * @param  Builder<Prompt>  $base
     * @param  array{search: string, category_id: int|null, ai_model: string|null, language: string|null, sort: string, mine: bool, semantic: bool}  $filters
     */
    protected function semanticPaginate(Builder $base, array $filters): LengthAwarePaginator
    {
        if (! $this->embeddings->isConfigured()) {
            $this->semanticNotice = 'Semantic search is unavailable because AI features are not configured. These are keyword results.';

            return $this->keywordFallback($base, $filters);
        }

        // Everything the viewer is allowed to see, with the dropdown filters
        // applied but without any keyword condition.
        $eligible = (clone $base)->select('prompts.id')->pluck('prompts.id')->map(fn ($id) => (int) $id);
        $this->semanticCoverage = $this->embeddings->coverage($eligible->all());

        try {
            $queryVector = $this->embeddings->embedQuery($filters['search']);
        } catch (AiException $exception) {
            $this->semanticNotice = $exception->getMessage().' These are keyword results.';

            return $this->keywordFallback($base, $filters);
        }

        $vectors = $this->embeddings->vectorsFor($eligible->all());

        if ($vectors === []) {
            $this->semanticNotice = 'None of the prompts you can see have been indexed yet. Run "php artisan embeddings:sync".';

            return $this->keywordFallback($base, $filters);
        }

        $scores = [];

        foreach ($vectors as $promptId => $vector) {
            $scores[$promptId] = $this->embeddings->cosineSimilarity($queryVector, $vector);
        }

        arsort($scores);

        // Two-part filter. The ratio keeps whatever is close to the best match,
        // which is what makes this work on a dataset whose similarities are all
        // bunched together; the floor stops a query that resembles nothing from
        // returning a long list of unrelated prompts. Both are explained in
        // config/prompts.php beside the measurements behind them.
        $floor = max(0.0, (float) config('prompts.semantic_min_similarity'));
        $ratio = min(1.0, max(0.0, (float) config('prompts.semantic_min_ratio_of_best')));
        $best = $scores === [] ? 0.0 : max($scores);
        $cutoff = max($floor, $best * $ratio);

        $scores = array_filter($scores, fn (float $score) => $score >= $cutoff);

        $ranked = array_map('intval', array_keys($scores));

        if ($this->semanticCoverage['indexed'] === 0) {
            $this->semanticNotice = 'No prompts have been indexed for semantic search yet. Run "php artisan embeddings:sync".';
        }

        $page = max(1, (int) Paginator::resolveCurrentPage());
        $slice = array_slice($ranked, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $items = $slice === [] ? collect() : Prompt::query()
            ->whereKey($slice)
            ->with(['category', 'user', 'tags'])
            ->withCount(['favorites', 'ratings'])
            ->withAvg('ratings', 'rating')
            // FIELD() keeps the similarity ranking that whereIn would discard.
            ->orderByRaw('field(prompts.id, '.implode(',', $slice).')')
            ->get();

        // A manually built paginator, because the ranking happened in PHP. The
        // concrete class is named in full on purpose: the bare name in this file
        // is the Contracts interface used by the method signatures, and that
        // cannot be instantiated.
        return new \Illuminate\Pagination\LengthAwarePaginator($items, count($ranked), self::PER_PAGE, $page, [
            'path' => Paginator::resolveCurrentPath(),
            // Preserved so paging keeps the query, the filters and the mode.
            'query' => request()->query(),
        ]);
    }

    /**
     * @param  Builder<Prompt>  $base
     * @param  array{search: string, category_id: int|null, ai_model: string|null, language: string|null, sort: string, mine: bool, semantic: bool}  $filters
     */
    protected function keywordFallback(Builder $base, array $filters): LengthAwarePaginator
    {
        $this->applySearch($base, $filters['search']);
        $this->applyFilters($base, $filters);
        $this->applySort($base, $filters);

        return $base->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * @param  array{search: string, category_id: int|null, ai_model: string|null, language: string|null, sort: string, mine: bool, semantic: bool}  $filters
     */
    public static function hasFilters(array $filters): bool
    {
        return $filters['search'] !== ''
            || $filters['category_id'] !== null
            || $filters['ai_model'] !== null
            || $filters['language'] !== null
            || $filters['mine'];
    }

    /**
     * True when nothing at all has been narrowed, including the sort order.
     *
     * @param  array{search: string, category_id: int|null, ai_model: string|null, language: string|null, sort: string, mine: bool, semantic: bool}  $filters
     */
    public static function isDefaultState(array $filters): bool
    {
        return ! self::hasFilters($filters)
            && $filters['sort'] === self::DEFAULT_SORT
            // Semantic mode is not a filter, but it is still a departure from
            // the default view, so Clear must reset it too.
            && ! $filters['semantic'];
    }

    /**
     * Read a query value only when it is a plain string; arrays are ignored.
     */
    protected static function scalar(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : null;
    }

    /**
     * @param  Builder<Prompt>  $builder
     */
    protected function applySearch(Builder $builder, string $search): void
    {
        if ($search === '') {
            return;
        }

        // Both branches match on the same normalised text. Operator characters
        // are stripped before they reach FULLTEXT, so they must be stripped from
        // the tag pattern too: otherwise "laravel*" would match via full-text
        // but the literal asterisk would stop the tag lookup, making a wildcard
        // search return fewer prompts than the plain word.
        $terms = $this->terms($search);

        $expression = $this->fullTextExpression($terms);
        $like = $this->likePattern($terms === [] ? $search : implode(' ', $terms));

        if ($expression !== '') {
            // The column list must match the ft_prompts_search index exactly,
            // in the same order, or MySQL cannot use the index.
            $builder->where(function (Builder $group) use ($expression, $like) {
                $group
                    ->whereRaw(
                        'MATCH(prompts.title, prompts.description, prompts.prompt_text) AGAINST (? IN BOOLEAN MODE)',
                        [$expression]
                    )
                    // Full-text cannot index tags, so tag matches are layered on
                    // with the same relationship query the LIKE branch uses.
                    // Without this, searching "laravel" would miss the prompts
                    // that carry the tag but not the word.
                    ->orWhereExists(fn (QueryBuilder $sub) => $this->tagsMatch($sub, $like));
            });

            return;
        }

        // Fallback for queries the FULLTEXT parser cannot serve: one or two
        // character terms, which this server ignores entirely because
        // innodb_ft_min_token_size is 3.
        $builder->where(function (Builder $group) use ($like) {
            $group
                ->where('prompts.title', 'like', $like)
                ->orWhere('prompts.description', 'like', $like)
                ->orWhere('prompts.prompt_text', 'like', $like)
                ->orWhereExists(fn (QueryBuilder $sub) => $this->tagsMatch($sub, $like));
        });
    }

    /**
     * The AGAINST argument: every usable term prefix-matched with a trailing
     * wildcard.
     *
     * The wildcard is what makes partial words work ("larav" finds "laravel")
     * and it also reaches past the InnoDB stopword list, which would otherwise
     * ignore common words entirely.
     *
     * Returns an empty string when no term is long enough for FULLTEXT, which is
     * the signal to use the LIKE fallback.
     *
     * @param  list<string>  $terms
     */
    protected function fullTextExpression(array $terms): string
    {
        return collect($terms)
            ->filter(fn (string $term) => mb_strlen($term) >= self::MIN_FULLTEXT_TERM_LENGTH)
            ->map(fn (string $term) => $term.'*')
            ->implode(' ');
    }

    /**
     * Split the search text into terms, discarding BOOLEAN MODE operators so
     * user text is always treated as text.
     *
     * @return list<string>
     */
    protected function terms(string $search): array
    {
        $cleaned = str_replace(self::BOOLEAN_OPERATORS, ' ', $search);

        return collect(preg_split('/\s+/u', $cleaned) ?: [])
            ->map(fn (string $term) => trim($term))
            ->filter(fn (string $term) => $term !== '')
            ->values()
            ->all();
    }

    /**
     * Build a LIKE pattern with the user's own wildcards neutralised, so a
     * search for "100%" cannot become a match-everything pattern.
     */
    protected function likePattern(string $search): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        return '%'.$escaped.'%';
    }

    /**
     * Correlated EXISTS over the tag pivot.
     *
     * EXISTS rather than a join: a join would multiply rows per matching tag and
     * force DISTINCT, which then breaks pagination counts.
     */
    protected function tagsMatch(QueryBuilder $sub, string $like): QueryBuilder
    {
        return $sub
            ->selectRaw('1')
            ->from('prompt_tag')
            ->join('tags', 'tags.id', '=', 'prompt_tag.tag_id')
            ->whereColumn('prompt_tag.prompt_id', 'prompts.id')
            ->where(function (QueryBuilder $tag) use ($like) {
                // tags.name is case-insensitive under utf8mb4_unicode_ci;
                // tags.slug is stored lowercase, so the pattern is lowered too.
                $tag->where('tags.name', 'like', $like)
                    ->orWhere('tags.slug', 'like', mb_strtolower($like));
            });
    }

    /**
     * @param  Builder<Prompt>  $builder
     * @param  array{search: string, category_id: int|null, ai_model: string|null, language: string|null, sort: string, mine: bool, semantic: bool}  $filters
     */
    protected function applyFilters(Builder $builder, array $filters): void
    {
        $builder
            ->when($filters['category_id'], fn (Builder $q, int $id) => $q->where('prompts.category_id', $id))
            ->when($filters['ai_model'], fn (Builder $q, string $model) => $q->where('prompts.ai_model', $model))
            ->when($filters['language'], fn (Builder $q, string $language) => $q->where('prompts.language', $language));

        if ($filters['mine']) {
            if ($this->viewer === null) {
                // A guest owns nothing. Passing null to where() would compile to
                // "user_id is null" and hand a guest every system prompt as if it
                // were theirs, so the impossible condition is stated explicitly.
                $builder->whereRaw('1 = 0');
            } else {
                $builder->where('prompts.user_id', $this->viewer->getKey());
            }
        }
    }

    /**
     * @param  Builder<Prompt>  $builder
     * @param  array{search: string, category_id: int|null, ai_model: string|null, language: string|null, sort: string, mine: bool, semantic: bool}  $filters
     */
    protected function applySort(Builder $builder, array $filters): void
    {
        if ($filters['sort'] === self::SORT_MOST_COPIED) {
            $builder->orderByDesc('prompts.copy_count')->orderByDesc('prompts.id');

            return;
        }

        if ($filters['sort'] === self::SORT_TRENDING) {
            $this->addScoreColumn($builder, $this->ranking->trendingExpression(), 'trend_score');
            $builder->orderByDesc('trend_score')->orderByDesc('prompts.id');

            return;
        }

        if ($filters['sort'] === self::SORT_HIGHEST_RATED) {
            $this->addScoreColumn($builder, $this->ranking->wilsonExpression(), 'wilson_score');
            // Prompts nobody has rated all score 0, so the rating count breaks
            // that block of ties before id is consulted.
            $builder
                ->orderByDesc('wilson_score')
                ->orderByDesc('ratings_count')
                ->orderByDesc('prompts.id');

            return;
        }

        if ($filters['sort'] === self::SORT_RELEVANCE && $filters['search'] !== '') {
            $this->orderByRelevance($builder, $filters['search']);

            return;
        }

        // Newest, and also the fallback whenever relevance is requested without
        // a search term.
        //
        // The tiebreaker is required, not cosmetic: every imported sample row
        // shares one created_at value, so ordering by that column alone leaves
        // ties in an arbitrary order and rows repeat or vanish between pages.
        $builder->orderByDesc('prompts.created_at')->orderByDesc('prompts.id');
    }

    /**
     * Add a computed score to the select list as an alias, so the ORDER BY can
     * name the alias and carry no bindings of its own.
     *
     * The guard matters. withCount() and withAvg() have already populated the
     * select list with their sub-selects, and calling select() would REPLACE
     * that list, silently dropping favorites_count, ratings_count and
     * ratings_avg_rating from the results. The base columns are therefore added
     * only when nothing has selected them yet.
     *
     * The expression is built by PromptRankingService from constants, never from
     * user input.
     *
     * @param  Builder<Prompt>  $builder
     */
    protected function addScoreColumn(Builder $builder, string $expression, string $alias, array $bindings = []): void
    {
        if ($builder->getQuery()->columns === null) {
            $builder->select('prompts.*');
        }

        $builder->selectRaw($expression.' as '.$alias, $bindings);
    }

    /**
     * @param  Builder<Prompt>  $builder
     */
    protected function orderByRelevance(Builder $builder, string $search): void
    {
        $expression = $this->fullTextExpression($this->terms($search));

        if ($expression === '') {
            $builder->orderByDesc('prompts.id');

            return;
        }

        // The score is exposed as a select alias and the ORDER BY names only
        // that alias, so the order clause carries no bindings of its own.
        $this->addScoreColumn(
            $builder,
            'MATCH(prompts.title, prompts.description, prompts.prompt_text) AGAINST (? IN BOOLEAN MODE)',
            'relevance_score',
            [$expression]
        );

        $builder
            ->orderByDesc('relevance_score')
            ->orderByDesc('prompts.id');
    }
}
