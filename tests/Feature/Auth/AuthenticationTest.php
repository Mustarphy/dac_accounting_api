<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function refreshCookieFrom(TestResponse $response): string
    {
        $name = config('dac_auth.refresh_cookie_name');

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie->getValue() ?? '';
            }
        }

        return '';
    }

    /**
     * Two things the default JSON test client won't do on its own:
     * - prepareCookiesForJsonRequest() sends no cookies at all unless
     *   withCredentials() is called first — mirroring a real fetch()
     *   without credentials: 'include'.
     * - The refresh cookie is excluded from Laravel's cookie encryption
     *   (see bootstrap/app.php) since it's already an opaque random value
     *   hashed at rest, so it must be sent via withUnencryptedCookies(),
     *   not withCookie() (which would encrypt it, and the app would then
     *   see a value it never tries to decrypt).
     */
    private function withRefreshCookie(string $value): static
    {
        return $this->withCredentials()
            ->withUnencryptedCookies([config('dac_auth.refresh_cookie_name') => $value]);
    }

    public function test_register_creates_a_user_and_returns_an_access_token_and_refresh_cookie(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'passwordConfirmation' => 'Password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'test@example.com')
            ->assertJsonPath('data.user.emailVerifiedAt', null)
            ->assertJsonStructure(['data' => ['accessToken', 'accessTokenExpiresAt']]);

        $this->assertNotEmpty($this->refreshCookieFrom($response));
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_register_rejects_a_weak_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short',
            'passwordConfirmation' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertArrayHasKey('password', $response->json('errors'));
    }

    public function test_register_rejects_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'passwordConfirmation' => 'Different123',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('passwordConfirmation', $response->json('errors'));
    }

    public function test_login_with_correct_credentials_succeeds(): void
    {
        $user = User::factory()->create(['password' => 'Password123']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.user.email', $user->email);
        $this->assertNotEmpty($this->refreshCookieFrom($response));
    }

    public function test_login_with_wrong_password_fails_with_generic_message(): void
    {
        $user = User::factory()->create(['password' => 'Password123']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)->assertJsonPath('success', false);
    }

    public function test_login_with_unknown_email_fails_with_the_same_generic_message(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'WhateverPassword1',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'The email or password you entered is incorrect.');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $token"])
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_refresh_without_a_cookie_fails(): void
    {
        $this->postJson('/api/v1/auth/refresh')
            ->assertStatus(401)
            ->assertJsonPath('message', 'No refresh token presented.');
    }

    public function test_refresh_rotates_the_refresh_token_and_issues_a_new_access_token(): void
    {
        $user = User::factory()->create(['password' => 'Password123']);

        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123']);
        $firstRefreshCookie = $this->refreshCookieFrom($login);
        $firstAccessToken = $login->json('data.accessToken');

        $refreshed = $this->withRefreshCookie($firstRefreshCookie)->postJson('/api/v1/auth/refresh');

        $refreshed->assertStatus(200)->assertJsonStructure(['data' => ['accessToken', 'accessTokenExpiresAt']]);
        $this->assertNotSame($firstAccessToken, $refreshed->json('data.accessToken'));

        // The rotated-out token must no longer work.
        $this->withRefreshCookie($firstRefreshCookie)
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(401);
    }

    public function test_logout_revokes_the_refresh_token_and_blacklists_the_access_token(): void
    {
        $user = User::factory()->create(['password' => 'Password123']);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123']);
        $refreshCookie = $this->refreshCookieFrom($login);
        $accessToken = $login->json('data.accessToken');

        $this->withRefreshCookie($refreshCookie)
            ->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer $accessToken"])
            ->assertStatus(200);

        // Neither half of the session works afterwards.
        $this->withRefreshCookie($refreshCookie)
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(401);

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $accessToken"])
            ->assertStatus(401);
    }

    public function test_logout_still_succeeds_even_without_a_refresh_cookie(): void
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer $token"])
            ->assertStatus(200);
    }

    public function test_login_is_rate_limited_after_five_attempts_per_minute(): void
    {
        RateLimiter::clear('login');
        $email = 'rate-limit-test@example.com';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong'])
                ->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
