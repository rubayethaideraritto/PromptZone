<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptVersion extends Model
{
    /**
     * `prompt_versions` has a created_at column but no updated_at column, so
     * Eloquent must be told not to maintain one.
     */
    public const UPDATED_AT = null;

    /**
     * `version_number` is computed server-side (MAX + 1 inside a transaction)
     * because (prompt_id, version_number) is unique, and `created_by_id` is
     * taken from the authenticated user rather than from request input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'prompt_id',
        'title',
        'prompt_text',
        'change_note',
    ];

    /**
     * @return BelongsTo<Prompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /**
     * The user who saved this version. Nullable: the creator may be deleted
     * (ON DELETE SET NULL) while the version history is kept.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
