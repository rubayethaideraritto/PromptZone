<?php

namespace App\Http\Controllers;

use App\Http\Requests\DuplicateCheckRequest;
use App\Http\Requests\PromptRequest;
use App\Http\Requests\SaveVersionRequest;
use App\Models\Category;
use App\Models\Prompt;
use App\Models\PromptVersion;
use App\Models\Tag;
use App\Services\AiPromptService;
use App\Services\PromptRecommendationService;
use App\Services\PromptSearchService;
use App\Services\PromptSimilarityService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PromptController extends Controller
{
    public function __construct(
        protected readonly AiPromptService $ai,
        protected readonly PromptRecommendationService $recommendations,
        protected readonly PromptSimilarityService $similarity,
    ) {}

    /**
     * Public prompt library: search, filters, sorting and pagination.
     *
     * The query itself is composed by PromptSearchService, which always begins
     * from Prompt::scopeVisibleTo(), so a guest sees only public prompts while a
     * signed-in user additionally sees their own private ones.
     */
    public function index(Request $request): View
    {
        $filters = PromptSearchService::filtersFrom($request);

        $search = PromptSearchService::for($request->user());

        return view('library.index', [
            'prompts' => $search->paginate($filters),
            'filters' => $filters,
            'categories' => Category::orderBy('name')->get(),
            'sorts' => PromptSearchService::SORTS,
            'hasFilters' => PromptSearchService::hasFilters($filters),
            'isDefaultState' => PromptSearchService::isDefaultState($filters),
            // Set when a semantic search had to fall back to keywords, so the
            // results are never presented as something they are not.
            'semanticNotice' => $search->semanticNotice(),
            'semanticCoverage' => $search->semanticCoverage(),
            // Semantic search needs the same provider key the AI panel does.
            'aiConfigured' => $this->ai->isConfigured(),
        ]);
    }

    /**
     * A single prompt's detail page.
     *
     * Answers 404 rather than 403 for a prompt the viewer may not see: a
     * "forbidden" response would confirm that a private prompt with this id
     * exists.
     */
    public function show(Request $request, Prompt $prompt): View
    {
        abort_unless(Gate::allows('view', $prompt), 404);

        // Counts and the average arrive as correlated sub-selects in the same
        // query rather than as separate aggregate calls.
        $prompt->load(['category', 'user', 'tags'])
            ->loadCount(['favorites', 'ratings'])
            ->loadAvg('ratings', 'rating');

        // Version history is shown to the owner only, but is small and bounded.
        $isOwner = $request->user() !== null && $request->user()->id === $prompt->user_id;

        $versions = $isOwner
            ? $prompt->versions()->with('creator')->orderByDesc('version_number')->limit(10)->get()
            : collect();

        $viewer = $request->user();

        // Recommendations start from the same visibility rule as everything else,
        // so a related prompt or a recommendation can never expose a prompt the
        // viewer may not read. Both return fully eager loaded models.
        $alsoLiked = $this->recommendations->alsoLiked($prompt, $viewer);

        return view('prompts.show', [
            'prompt' => $prompt,
            'versions' => $versions,
            'related' => $this->recommendations->related($prompt, $viewer),
            'alsoLiked' => $alsoLiked['prompts'],
            // Which of the three sources produced the strip, so the view can be
            // accurate about whether it is personalised.
            'alsoLikedSource' => $alsoLiked['source'],
            // Two small lookups, only for a signed-in viewer, so the toolbar can
            // show its own state. Without a viewer there is nothing to look up.
            'isFavorited' => $viewer !== null
                && $viewer->favorites()->whereKey($prompt->getKey())->exists(),
            'viewerRating' => $viewer !== null
                ? $prompt->ratings()->where('user_id', $viewer->getKey())->value('rating')
                : null,
        ]);
    }

    /**
     * Report prompts the author has already written that resemble this draft.
     *
     * Advisory only: the create and edit forms call this while the user types and
     * display the result. Nothing is blocked, and the check only ever compares
     * the signed-in user's own prompts.
     */
    public function checkDuplicate(DuplicateCheckRequest $request): JsonResponse
    {
        $ignoreId = $request->validated('ignore_id');

        $matches = $this->similarity->findSimilar(
            $request->user(),
            (string) ($request->validated('title') ?? ''),
            (string) ($request->validated('prompt_text') ?? ''),
            $ignoreId !== null ? (int) $ignoreId : null,
        );

        return response()->json([
            'success' => true,
            'duplicates' => array_map(fn (array $match) => [
                'id' => $match['prompt']->getKey(),
                'title' => $match['prompt']->title,
                'url' => route('prompts.show', $match['prompt']),
                // Percentages, so the message can be specific about why it matched.
                'title_similarity' => (int) round($match['title_similarity'] * 100),
                'body_similarity' => (int) round($match['body_similarity'] * 100),
                'reason' => $match['reason'],
            ], $matches),
        ]);
    }

    /**
     * Record that the prompt text was copied, and report the new total.
     *
     * The counter is raised in SQL (copy_count = copy_count + 1) so a
     * client-supplied number is never trusted and two simultaneous copies cannot
     * overwrite each other's increment.
     *
     * Note this also refreshes updated_at, because the column is declared
     * `ON UPDATE CURRENT_TIMESTAMP` in the schema and MariaDB applies that to any
     * row update. Setting $timestamps = false does not prevent it. Nothing sorts
     * by updated_at ("newest" uses created_at), so the side effect is harmless,
     * but it means updated_at is a row-modification stamp rather than a strictly
     * content-edit stamp.
     */
    public function copy(Prompt $prompt): JsonResponse
    {
        // Only prompts the viewer may read can be copied.
        abort_unless(Gate::allows('view', $prompt), 404);

        $prompt->increment('copy_count');

        return response()->json([
            'success' => true,
            'copy_count' => $prompt->copy_count,
        ]);
    }

    public function create(): View
    {
        return view('prompts.create', [
            'categories' => $this->selectableCategories(),
            // When false the form shows a plain "not configured" notice instead
            // of buttons that could not work.
            'aiConfigured' => $this->ai->isConfigured(),
        ]);
    }

    public function store(PromptRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        $prompt = DB::transaction(function () use ($request, $data, $tags) {
            // Ownership is assigned by the server through the relationship, so
            // user_id is never taken from request input.
            $prompt = $request->user()->prompts()->create($data);

            $this->syncTags($prompt, $tags);

            return $prompt;
        });

        return redirect()
            ->route('prompts.show', $prompt)
            ->with('status', 'Prompt created.');
    }

    public function edit(Prompt $prompt): View
    {
        $this->authorize('update', $prompt);

        $prompt->load('tags');

        return view('prompts.edit', [
            'prompt' => $prompt,
            'categories' => $this->selectableCategories($prompt->category_id),
            'tagString' => $prompt->tags->pluck('name')->implode(', '),
            'aiConfigured' => $this->ai->isConfigured(),
        ]);
    }

    public function update(PromptRequest $request, Prompt $prompt): RedirectResponse
    {
        $this->authorize('update', $prompt);

        $data = $request->validated();
        $tags = $data['tags'] ?? null;
        unset($data['tags']);

        DB::transaction(function () use ($prompt, $data, $tags) {
            // Only the seven validated columns are writable, so ownership,
            // counters, moderation flags and import keys cannot be changed here.
            $prompt->update($data);

            $this->syncTags($prompt, $tags);
        });

        return redirect()
            ->route('prompts.show', $prompt)
            ->with('status', 'Prompt updated.');
    }

    public function destroy(Prompt $prompt): RedirectResponse
    {
        $this->authorize('delete', $prompt);

        // Hard delete. prompt_tag, favorites, ratings, prompt_versions and
        // prompt_embeddings rows cascade through their foreign keys, and the
        // schema has no deleted_at column, so no soft delete applies.
        $prompt->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Prompt deleted.');
    }

    /**
     * Replace a prompt's text, keeping the text it replaces in the history.
     *
     * This is the second half of the AI improver: the user reviews the draft and
     * chooses deliberately between "use this draft" (which only fills the form)
     * and saving it as a new version (which writes).
     *
     * A prompt_versions row records the text being superseded, matching the
     * schema's intent that it "stores prior prompt text when a new version is
     * saved".
     */
    public function saveVersion(SaveVersionRequest $request, Prompt $prompt): RedirectResponse
    {
        // Owner only. A system prompt has no owner, so the policy refuses it and
        // administrators get no exception here either.
        $this->authorize('update', $prompt);

        $validated = $request->validated();

        DB::transaction(function () use ($request, $prompt, $validated) {
            // Lock the row so two concurrent saves cannot compute the same
            // version number. The unique key (prompt_id, version_number) is the
            // final backstop if they somehow do.
            $locked = Prompt::query()
                ->whereKey($prompt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nextNumber = (int) $locked->versions()->max('version_number') + 1;

            $version = new PromptVersion([
                'prompt_id' => $locked->getKey(),
                'title' => $locked->title,
                'prompt_text' => $locked->prompt_text,
                'change_note' => filled($validated['change_note'] ?? null)
                    ? $validated['change_note']
                    : 'Replaced with an improved draft.',
            ]);

            // Neither of these is mass assignable, so both are assigned
            // explicitly rather than filled from the request.
            $version->version_number = $nextNumber;
            $version->created_by_id = $request->user()->getKey();
            $version->save();

            $locked->prompt_text = $validated['prompt_text'];
            $locked->save();
        });

        return redirect()
            ->route('prompts.show', $prompt)
            ->with('status', 'Saved as a new version. The previous text is kept in the history.');
    }

    /**
     * Categories offered in the create/edit dropdown.
     *
     * Only active categories are selectable, except that a prompt already filed
     * under a since-deactivated category keeps that option so editing cannot
     * silently move it elsewhere.
     *
     * @return Collection<int, Category>
     */
    protected function selectableCategories(?int $includeId = null): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->when($includeId, fn ($query, $id) => $query->orWhere('id', $id))
            ->orderBy('name')
            ->get();
    }

    /**
     * Replace a prompt's tags from the comma-separated form input.
     *
     * Tags are resolved by slug, because tags.name and tags.slug are both unique
     * under a case-insensitive collation: "PHP", "Php" and "php" all mean the
     * same row. Looking up before inserting avoids a duplicate-key error, and
     * the name already stored is kept. Slugs are lowercased and hyphenated to
     * match the sample dataset's convention ("Academic Writing" ->
     * "academic-writing").
     */
    protected function syncTags(Prompt $prompt, ?string $input): void
    {
        $tagIds = collect(explode(',', (string) $input))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique(fn (string $name) => Str::lower($name))
            ->map(function (string $name) {
                $slug = Str::slug($name);

                if ($slug === '') {
                    // Non-Latin names (for example Bangla) transliterate to
                    // nothing; fall back to a lowercase, hyphenated form rather
                    // than dropping the tag silently.
                    $slug = Str::lower(preg_replace('/\s+/u', '-', $name));
                }

                if ($slug === '') {
                    return null;
                }

                $slug = Str::limit($slug, config('prompts.max_tag_slug_length'), '');
                $label = Str::limit($name, config('prompts.max_tag_name_length'), '');

                return Tag::firstOrCreate(['slug' => $slug], ['name' => $label])->id;
            })
            ->filter()
            ->values()
            ->all();

        $prompt->tags()->sync($tagIds);
    }
}
