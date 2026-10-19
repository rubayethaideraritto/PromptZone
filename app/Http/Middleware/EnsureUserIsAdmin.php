<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Refuse the request unless the authenticated user holds the admin role.
     *
     * Runs after the `auth` middleware, so a signed-out visitor is redirected
     * to the login page while a signed-in non-admin gets a 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
