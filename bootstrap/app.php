<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // The refresh-token cookie is already an unguessable random value
        // whose hash (never the raw value) is what's stored server-side —
        // Laravel's cookie encryption would be a redundant second layer
        // here, and excluding it is what lets a plain curl/browser client
        // round-trip the cookie exactly as issued (and what the frontend's
        // proxy.ts reads for presence, unencrypted, by design). Hardcoded
        // rather than read from config() — this closure runs too early in
        // the console/kernel bootstrap for the config() helper to resolve.
        // Keep in sync with REFRESH_COOKIE_NAME / dac_auth.refresh_cookie_name.
        $middleware->encryptCookies(except: [
            'dac_refresh_token',
        ]);

        // This is a pure API with no server-rendered 'login' route to
        // redirect guests to (Laravel's default guest-redirect callback
        // calls route('login'), which doesn't exist here and would throw
        // RouteNotFoundException instead of a clean 401). Returning null
        // makes the auth middleware throw AuthenticationException instead,
        // which our custom renderer in withExceptions() below turns into
        // a proper {success:false, message:"Unauthenticated."} 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Every branch below keeps the same {success, message, errors}
        // envelope used by Controller::success()/failure() — and, for
        // anything unexpected, strips internals (stack traces, SQL,
        // file paths) unless APP_DEBUG is on. Order matters: more
        // specific types are checked first by Laravel, generic
        // Throwable last.

        $exceptions->render(fn (ValidationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422)
            : null);

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'Unauthenticated.', 'errors' => null], 401)
            : null);

        $exceptions->render(fn (AuthorizationException $e, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => $e->getMessage() ?: 'This action is unauthorized.', 'errors' => null], 403)
            : null);

        $exceptions->render(fn (ModelNotFoundException $e, Request $request) => $request->is('api/*')
            ? response()->json(['success' => false, 'message' => 'The requested resource was not found.', 'errors' => null], 404)
            : null);

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return response()->json([
                    'success' => false,
                    'message' => $status >= 500 ? 'Something went wrong. Please try again later.' : ($e->getMessage() ?: 'Request failed.'),
                    'errors' => null,
                ], $status);
            }

            if (config('app.debug')) {
                // Let Laravel's normal detailed JSON rendering through locally.
                return null;
            }

            return response()->json(['success' => false, 'message' => 'Something went wrong. Please try again later.', 'errors' => null], 500);
        });
    })->create();
