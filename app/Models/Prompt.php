<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prompt extends Model
{
    /**
     * Controlled values for prompts.visibility and prompts.status.
     *
     * Kept as constants because the moderation screen is the first place the
     * application writes 'archived'; a typo in a read merely returns no rows,
     * but a typo in a write would silently hide a prompt from every listing.
     */
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * Only fields a user may legitimately submit through the create/edit form
     * are mass assignable.
     *
     * Deliberately excluded and therefore set by server-side code only:
     *   user_id      - ownership; prevents writing into another account
     *   external_key - import identity for the seeded dataset
     *   source       - system provenance marker ('synthetic-demo')
     *   status       - archive/moderation state
     *   is_featured  - admin moderation flag
     *   copy_count   - counters are never trusted from the client
     *   view_count   - counters are never trusted from the client
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'prompt_text',
        'ai_model',
        'language',
        'visibility',
    ];

    /**
     * Counters are INT UNSIGNED and flags are TINYINT(1) in the schema.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'copy_count' => 'integer',
            'view_count' => 'integer',
        ];
    }

    /**
     * Owner. NULL for system/sample prompts, which are read-only to regular
     * users.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every prompt requires a category (`category_id` is NOT NULL).
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * `prompt_tag` has a composite primary key (prompt_id, tag_id) and no `id`
     * column, so it is reachable only as a many-to-many pivot.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'prompt_tag')->withTimestamps();
    }

    /**
     * Users who have favourited this prompt (pivot: favorites).
     *
     * Named `favorites` rather than `favoritedBy` so it mirrors User::favorites()
     * across the same pivot, and so withCount('favorites') yields the natural
     * `favorites_count` attribute. Note the models returned are User records:
     * the count is "how many people saved this", not "how many prompts I saved".
     *
     * @return BelongsToMany<User, $this>
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * One rating row per user per prompt, enforced by a unique key.
     *
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Saved prior versions of this prompt/its text.
     *
     * @return HasMany<PromptVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PromptVersion::class);
    }

    /**
     * Current embedding for semantic search, if one has been generated.
     *
     * `prompt_embeddings` is keyed by prompt_id itself, so this is a hasOne.
     *
     * @return HasOne<PromptEmbedding, $this>
     */
    public function embedding(): HasOne
    {
        return $this->hasOne(PromptEmbedding::class, 'prompt_id');
    }

    /**
     * Whether this is system/sample content rather than a user's own prompt.
     */
    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Whether the given viewer may read this prompt.
     *
     * THE visibility rule for a single row. Its SQL twin is scopeVisibleTo()
     * below; the two encode the same decision and must be changed together.
     *
     *   public + active             -> readable by everyone, including guests
     *   public + archived           -> withdrawn from public view; readable by
     *                                  its owner and by administrators, who need
     *                                  to reach it in order to restore it
     *   private + owner             -> readable by the owner
     *   private + anyone else       -> not readable
     *
     * A private prompt whose owner has been removed (user_id NULL) matches no
     * rule and is therefore readable by nobody, which is the safe outcome.
     *
     * The nullable parameter is deliberate: guests must be evaluated too.
     */
    public function isVisibleTo(?User $viewer): bool
    {
        if ($this->visibility === self::VISIBILITY_PUBLIC) {
            if ($this->status === self::STATUS_ACTIVE) {
                return true;
            }

            return $viewer !== null
                && ($viewer->isAdmin() || $viewer->id === $this->user_id);
        }

        return $viewer !== null
            && $this->user_id !== null
            && $viewer->id === $this->user_id;
    }

    /**
     * Restrict a query to the prompts a viewer is allowed to read.
     *
     * Every listing, search, recommendation and embedding query must start from
     * this scope rather than filtering visibility in the view.
     *
     * The orWhere clauses are wrapped in closures on purpose. Without that
     * grouping the ORs would escape the surrounding AND conditions, so a query
     * such as "status = active AND (public OR owner = 5)" would silently degrade
     * to "… OR owner = 5" and leak other users' private prompts.
     *
     * @param  Builder<Prompt>  $query
     * @return Builder<Prompt>
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        return $query->where(function (Builder $group) use ($viewer) {
            // Published public content.
            $group->where(function (Builder $public) {
                $public->where('prompts.visibility', self::VISIBILITY_PUBLIC)
                    ->where('prompts.status', self::STATUS_ACTIVE);
            });

            if ($viewer === null) {
                return;
            }

            // A viewer always sees their own prompts, whatever their visibility
            // or status.
            $group->orWhere('prompts.user_id', $viewer->id);

            // An administrator can additionally reach any public prompt,
            // including archived ones, in order to moderate them. This grant
            // never extends to another account's private prompt.
            if ($viewer->isAdmin()) {
                $group->orWhere('prompts.visibility', self::VISIBILITY_PUBLIC);
            }
        });
    }

    /**
     * Only prompts that have not been archived.
     *
     * @param  Builder<Prompt>  $query
     * @return Builder<Prompt>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('prompts.status', self::STATUS_ACTIVE);
    }
}
