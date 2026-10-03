<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function extractToken(ResetPasswordNotification $notification): string
    {
        $property = new \ReflectionProperty($notification, 'token');

        return $property->getValue($notification);
    }

    public function test_forgot_password_returns_the_same_generic_message_for_a_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);

        $response->assertStatus(200)
            ->assertJsonPath('message', "If an account exists for this email, we've sent instructions to reset your password.");
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_returns_the_identical_message_for_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com']);

        $response->assertStatus(200)
            ->assertJsonPath('message', "If an account exists for this email, we've sent instructions to reset your password.");
        Notification::assertNothingSent();
    }

    public function test_reset_with_a_valid_token_changes_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'OldPassword123']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);
        $token = $this->extractToken(Notification::sent($user, ResetPasswordNotification::class)->first());

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword456',
            'passwordConfirmation' => 'NewPassword456',
        ]);

        $response->assertStatus(200);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'NewPassword456'])
            ->assertStatus(200);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'OldPassword123'])
            ->assertStatus(401);
    }

    public function test_reset_token_is_single_use(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'OldPassword123']);

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email]);
        $token = $this->extractToken(Notification::sent($user, ResetPasswordNotification::class)->first());

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewPassword456',
            'passwordConfirmation' => 'NewPassword456',
        ];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertStatus(200);
        $this->postJson('/api/v1/auth/password/reset', $payload)->assertStatus(400);
    }

    public function test_reset_with_an_invalid_token_fails(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'token' => 'not-a-real-token',
            'password' => 'NewPassword456',
            'passwordConfirmation' => 'NewPassword456',
        ])->assertStatus(400);
    }

    public function test_reset_rejects_mismatched_confirmation_before_touching_the_broker(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'token' => 'irrelevant',
            'password' => 'NewPassword456',
            'passwordConfirmation' => 'Different789',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('passwordConfirmation', $response->json('errors'));
    }
}
