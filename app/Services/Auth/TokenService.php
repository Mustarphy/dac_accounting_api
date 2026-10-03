<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Issues the two halves of the session: a short-lived JWT access token
 * (signed by tymon/jwt-auth, never persisted server-side) and a long-lived
 * opaque refresh token (persisted hashed, carried in an HttpOnly cookie).
 * Deliberately not using jwt-auth's own built-in "refresh" mechanism
 * (reissuing from a near-expired JWT) — that doesn't support
 * server-side revocation or an HttpOnly-cookie-based flow as cleanly as a
 * DB-backed token does, and the frontend's contract already commits to
 * the cookie-based design. See docs/auth-api-contract.md.
 */
class TokenService
{
    public function issueAccessToken(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    /**
     * @return array{0: string, 1: RefreshToken} [rawToken, persistedRecord]
     */
    public function issueRefreshToken(User $user, Request $request, bool $rememberMe = false): array
    {
        $raw = Str::random(64);

        $token = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($raw),
            'remember' => $rememberMe,
            'expires_at' => now()->addDays($this->ttlDays($rememberMe)),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return [$raw, $token];
    }

    public function findValidRefreshToken(string $raw): ?RefreshToken
    {
        $token = RefreshToken::where('token_hash', $this->hash($raw))->first();

        return $token?->isValid() ? $token : null;
    }

    public function revoke(RefreshToken $token): void
    {
        // forceFill, not update(): revoked_at is deliberately excluded from
        // RefreshToken's #[Fillable(...)] (it must never be settable via
        // mass-assignment from request input) — update() would silently
        // discard it rather than persist the revocation.
        $token->forceFill(['revoked_at' => now()])->save();
    }

    public function cookieFor(string $rawToken, bool $rememberMe = false): Cookie
    {
        return $this->baseCookie($rawToken, $this->ttlDays($rememberMe) * 24 * 60);
    }

    public function expiredCookie(): Cookie
    {
        return $this->baseCookie('', -1);
    }

    private function baseCookie(string $value, int $minutes): Cookie
    {
        return cookie(
            name: (string) config('dac_auth.refresh_cookie_name'),
            value: $value,
            minutes: $minutes,
            path: '/',
            domain: config('dac_auth.refresh_cookie_domain'),
            secure: (bool) config('dac_auth.refresh_cookie_secure'),
            httpOnly: true,
            raw: false,
            sameSite: config('dac_auth.refresh_cookie_samesite'),
        );
    }

    private function ttlDays(bool $rememberMe): int
    {
        return (int) config($rememberMe ? 'dac_auth.refresh_token_remember_ttl_days' : 'dac_auth.refresh_token_ttl_days');
    }

    private function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
