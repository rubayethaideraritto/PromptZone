<?php

namespace App\Providers;

use App\Models\Prompt;
use App\Policies\PromptPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The shipped pagination views are styled for Tailwind/Bootstrap, which
        // this project does not load. Point every paginator at our own markup
        // so future paginated pages are styled consistently too.
        Paginator::defaultView('vendor.pagination.custom');

        // Registered explicitly rather than relying on convention discovery, so
        // the ownership rules are greppable from one place.
        //
        // No Gate::before() is registered: an administrator must not inherit the
        // ability to edit or delete another user's prompt, or any system/sample
        // prompt, through the standard CRUD routes.
        Gate::policy(Prompt::class, PromptPolicy::class);

        // AI calls cost money per request, so they are capped per user rather
        // than per IP: a shared connection cannot exhaust one person's budget.
        // Guests never reach these endpoints, but the IP is the fallback key.
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('services.gemini.rate_limit_per_minute')))
                ->by($request->user()?->getKey() ?: $request->ip());
        });
    }
}
