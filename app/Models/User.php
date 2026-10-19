<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The values `users.role` may hold. It is a VARCHAR(20) defaulting to 'user'.
     *
     * @var list<string>
     */
    public const ROLES = [self::ROLE_USER, self::ROLE_ADMIN];

    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * `role` is intentionally absent: it is written only by authorised admin
     * operations, so submitted request data can never escalate a privilege.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Prompts owned by this user.
     *
     * System/sample prompts carry `user_id = NULL`, so they never appear here.
     *
     * @return HasMany<Prompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /**
     * Prompts this user has favourited.
     *
     * `favorites` has a composite primary key (user_id, prompt_id) and no `id`
     * column, so it is reachable only as a many-to-many pivot.
     *
     * @return BelongsToMany<Prompt, $this>
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class, 'favorites')->withTimestamps();
    }

    /**
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * Prompt versions attributed to this user.
     *
     * @return HasMany<PromptVersion, $this>
     */
    public function promptVersions(): HasMany
    {
        return $this->hasMany(PromptVersion::class, 'created_by_id');
    }

    /**
     * Whether this account holds the admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * How many administrators exist in total.
     *
     * Used to refuse the removal of the final one: with no administrator left,
     * nobody could grant the role back.
     */
    public static function adminCount(): int
    {
        return static::query()->where('role', self::ROLE_ADMIN)->count();
    }
}
