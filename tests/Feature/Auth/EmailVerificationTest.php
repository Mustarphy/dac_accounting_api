<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The raw token only ever exists in the notification instance and the
     * emailed link (only its hash is persisted) — Reflection is the only
     * way to recover it in a test without weakening that on purpose.
     */
    private function extractToken(VerifyEmailNotification $notification): string
    {
        $property = new \ReflectionProperty($notification, 'token');

        return $property->getValue($notification);
    }

    public function test_registering_queues_a_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verify_with_a_valid_token_marks_the_email_verified(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        $sent = Notification::sent($user, VerifyEmailNotification::class)->first();
        $token = $this->extractToken($sent);

        $response = $this->postJson('/api/v1/auth/email/verify', ['token' => $token]);

        $response->assertStatus(200)->assertJsonPath('data.user.email', $user->email);
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_verify_with_an_already_used_token_reports_already_verified(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $token = $this->extractToken(Notification::sent($user, VerifyEmailNotification::class)->first());

        $this->postJson('/api/v1/auth/email/verify', ['token' => $token])->assertStatus(200);

        $this->postJson('/api/v1/auth/email/verify', ['token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This email address has already been verified.');
    }

    public function test_verify_with_an_invalid_token_fails(): void
    {
        $this->postJson('/api/v1/auth/email/verify', ['token' => 'not-a-real-token'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'This verification link is invalid or has expired.');
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/email/resend', ['email' => 'test@example.com'])
            ->assertStatus(401);
    }

    public function test_resend_sends_to_the_authenticated_users_own_email_regardless_of_body(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $token = auth('api')->login($user);

        // Deliberately sends a DIFFERENT email in the body — the backend
        // must ignore it and only ever send to the authenticated user.
        $this->postJson(
            '/api/v1/auth/email/resend',
            ['email' => 'someone-else@example.com'],
            ['Authorization' => "Bearer $token"]
        )->assertStatus(200);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_refuses_once_already_verified(): void
    {
        $user = User::factory()->create(); // verified by default factory state
        $token = auth('api')->login($user);

        $this->postJson('/api/v1/auth/email/resend', [], ['Authorization' => "Bearer $token"])
            ->assertStatus(409);
    }
}
