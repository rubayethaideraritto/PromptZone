<?php

namespace App\Policies;

use App\Models\Prompt;
use App\Models\User;

class PromptPolicy
{
    /**
     * Who may read a prompt's detail page.
     *
     * Delegates to Prompt::isVisibleTo() so the detail path and the list/search
     * path (Prompt::scopeVisibleTo) can never drift apart.
     *
     * The signature accepts a null user so guests can be evaluated.
     */
    public function view(?User $user, Prompt $prompt): bool
    {
        return $prompt->isVisibleTo($user);
    }

    /**
     * Who may change a prompt.
     *
     * Ownership is the only basis for mutation. No Gate::before bypass is
     * registered anywhere, so an administrator has no extra power here either:
     * admin moderation is a separate, deliberately-scoped flow for a later
     * phase and must not arrive by accident through the standard CRUD routes.
     */
    public function update(User $user, Prompt $prompt): bool
    {
        return $this->owns($user, $prompt);
    }

    /**
     * Who may delete a prompt.
     */
    public function delete(User $user, Prompt $prompt): bool
    {
        return $this->owns($user, $prompt);
    }

    /**
     * A null owner marks system/sample content, which is read-only for
     * everyone in this phase, administrators included.
     */
    protected function owns(User $user, Prompt $prompt): bool
    {
        return $prompt->user_id !== null && $user->id === $prompt->user_id;
    }
}
