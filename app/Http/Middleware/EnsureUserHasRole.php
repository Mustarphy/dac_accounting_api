<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered as the `role` middleware alias (see bootstrap/app.php).
 * Usage once portal routes exist: ->middleware('role:admin,staff').
 * Not applied to any route yet — Step 18 of the setup brief says not to
 * build the portal prematurely; this is the mechanism waiting for it.
 * A `role` column existing on users does not, by itself, protect
 * anything — every protected action still needs this (or an equivalent
 * Policy/Gate check) applied explicitly.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $allowed = array_map(fn (string $role) => UserRole::from($role), $roles);

        if (! in_array($user->role, $allowed, strict: true)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
