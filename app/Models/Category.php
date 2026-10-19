<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /**
     * Admin-managed table (`categories`): name and slug are the only unique
     * identifiers, so both are submitted by the admin form.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Prompts filed under this category.
     *
     * `prompts.category_id` is NOT NULL with ON DELETE RESTRICT, so a category
     * in use cannot be deleted until its prompts are reassigned.
     *
     * @return HasMany<Prompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }
}
