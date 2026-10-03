<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

/**
 * Built entirely on Laravel's own password-broker facilities (token
 * generation, hashing, expiry, throttling via config/auth.php's
 * passwords.users.expire/throttle) rather than a custom token table —
 * Laravel already does this well; User::sendPasswordResetNotification()
 * is overridden only to point the emailed link at the frontend.
 */
class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        // Same response whether or not the email exists — avoids account
        // enumeration. Laravel's sendResetLink() already silently no-ops
        // for an unknown email; we just never branch on its return value.
        $message = "If an account exists for this email, we've sent instructions to reset your password.";

        return $this->success(['message' => $message], $message);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        // passwordConfirmation already matched in ResetPasswordRequest's
        // own validation — the broker only needs email/token/password.
        $status = Password::reset(
            $request->only('email', 'password', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->failure('This reset link is invalid or has expired.', 400);
        }

        return $this->success(['message' => 'Password updated.'], 'Password updated.');
    }
}
