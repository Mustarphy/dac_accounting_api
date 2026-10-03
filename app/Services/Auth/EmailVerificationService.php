<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Str;

class EmailVerificationService
{
    public function sendVerificationEmail(User $user): void
    {
        $raw = Str::random(64);

        EmailVerificationToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($raw),
            'expires_at' => now()->addHours((int) config('dac_auth.email_verification_ttl_hours')),
        ]);

        $user->notify(new VerifyEmailNotification($raw));
    }

    /**
     * @return array{status: 'verified'|'already_verified'|'invalid', user: ?User}
     */
    public function verify(string $rawToken): array
    {
        $token = EmailVerificationToken::where('token_hash', $this->hash($rawToken))->first();

        if (! $token || $token->expires_at->isPast()) {
            return ['status' => 'invalid', 'user' => null];
        }

        $user = $token->user;

        // Not deleted on success: if the same link is clicked again after
        // verification, hasVerifiedEmail() below reports "already_verified"
        // instead of a misleading "invalid" once the token also expires.
        if ($user->hasVerifiedEmail()) {
            return ['status' => 'already_verified', 'user' => $user];
        }

        $user->markEmailAsVerified();

        return ['status' => 'verified', 'user' => $user];
    }

    private function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
