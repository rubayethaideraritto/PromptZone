<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /**
     * Both `name` and `slug` are UNIQUE under a case-insensitive collation
     * (utf8mb4_unicode_ci), so tag creation must look up by slug before
     * inserting rather than relying on a blind insert.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Prompts carrying this tag (pivot: prompt_tag).
     *
     * @return BelongsToMany<Prompt, $this>
     */
    public function prompts(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class, 'prompt_tag')->withTimestamps();
    }
}
