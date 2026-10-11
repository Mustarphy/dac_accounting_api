<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Session lifecycle: register, login, logout, refresh, me. Grouped
 * together (rather than one controller per action) because they're all
 * the same cohesive concern — establishing and tearing down a session —
 * and share the issueSession() helper below.
 */
class AuthController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        // forceFill the role explicitly rather than leaving it to the
        // column's DB default: the in-memory $user below is used
        // immediately (UserResource reads ->role->value in the response),
        // and Eloquent doesn't sync a column default back after insert —
        // the exact bug class documented in docs/backend-setup.md §9.
        $user = new User;
        $user->forceFill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => UserRole::Client,
        ])->save();

        $user->sendEmailVerificationNotification();

        return $this->issueSession($user, $request, rememberMe: false, message: 'Registration successful.', status: 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            // Deliberately generic: never reveal whether the email exists.
            return $this->failure('The email or password you entered is incorrect.', 401);
        }

        return $this->issueSession($user, $request, rememberMe: $request->boolean('rememberMe'), message: 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        // Wrapped in `user` to match the frontend's AuthUserResponse type
        // ({ user: AuthUser }) — see dac-accounting-frontend's
        // docs/auth-api-contract.md, written before this backend existed.
        return $this->success(['user' => new UserResource($request->user())], 'OK');
    }

    public function logout(Request $request): JsonResponse
    {
        $raw = $request->cookie((string) config('dac_auth.refresh_cookie_name'));

        if ($raw && ($token = $this->tokens->findValidRefreshToken($raw))) {
            $this->tokens->revoke($token);
        }

        try {
            auth('api')->logout();
        } catch (\Throwable) {
            // Already invalid/expired/missing — logout still succeeds;
            // there is nothing further to blacklist.
        }

        return $this->success(null, 'Logged out.')->withCookie($this->tokens->expiredCookie());
    }

    public function refresh(Request $request): JsonResponse
    {
        $raw = $request->cookie((string) config('dac_auth.refresh_cookie_name'));

        if (! $raw) {
            return $this->failure('No refresh token presented.', 401);
        }

        $token = $this->tokens->findValidRefreshToken($raw);

        if (! $token) {
            return $this->failure('Your session has expired. Please sign in again.', 401)
                ->withCookie($this->tokens->expiredCookie());
        }

        $user = $token->user;
        $rememberMe = (bool) $token->remember;

        // Rotate: revoke the presented token and issue a fresh one, so a
        // stolen-but-unused cookie can't be replayed indefinitely.
        $this->tokens->revoke($token);
        [$rawRefresh] = $this->tokens->issueRefreshToken($user, $request, $rememberMe);

        return $this->success([
            'accessToken' => $this->tokens->issueAccessToken($user),
            'accessTokenExpiresAt' => now()->addMinutes((int) config('jwt.ttl'))->toISOString(),
        ], 'Session refreshed.')->withCookie($this->tokens->cookieFor($rawRefresh, $rememberMe));
    }

    private function issueSession(User $user, Request $request, bool $rememberMe, string $message, int $status = 200): JsonResponse
    {
        $accessToken = $this->tokens->issueAccessToken($user);
        [$rawRefresh] = $this->tokens->issueRefreshToken($user, $request, $rememberMe);

        return $this->success([
            'user' => new UserResource($user),
            'accessToken' => $accessToken,
            'accessTokenExpiresAt' => now()->addMinutes((int) config('jwt.ttl'))->toISOString(),
        ], $message, $status)->withCookie($this->tokens->cookieFor($rawRefresh, $rememberMe));
    }
}
