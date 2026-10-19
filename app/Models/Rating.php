<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    /**
     * user_id and prompt_id identify the row and are safe to mass assign only
     * because callers must pass the authenticated user and the routed prompt.
     * They must never be taken from raw request input.
     *
     * Re-rating the same prompt must update the existing row: a unique key on
     * (user_id, prompt_id) enforces at most one rating per user per prompt.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'prompt_id',
        'rating',
        'review',
    ];

    /**
     * `rating` is TINYINT UNSIGNED with no CHECK constraint, so the database
     * accepts values outside 1-5; the 1-5 range must be validated in the
     * application.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Prompt, $this>
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }
}
