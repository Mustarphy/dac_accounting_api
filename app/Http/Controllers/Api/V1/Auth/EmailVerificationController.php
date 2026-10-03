<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendVerificationRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function __construct(private readonly EmailVerificationService $verification) {}

    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        $result = $this->verification->verify($request->validated('token'));

        return match ($result['status']) {
            'verified' => $this->success(
                ['message' => 'Email verified.', 'user' => new UserResource($result['user'])],
                'Email verified.'
            ),
            'already_verified' => $this->failure('This email address has already been verified.', 409),
            default => $this->failure('This verification link is invalid or has expired.', 400),
        };
    }

    /**
     * Always sends to the authenticated user's own email — the request
     * body's `email` field is accepted for contract compatibility with the
     * frontend but never used as the send target. See
     * ResendVerificationRequest's docblock.
     */
    public function resend(ResendVerificationRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->failure('This email address has already been verified.', 409);
        }

        $user->sendEmailVerificationNotification();

        return $this->success(['message' => 'Verification email sent.'], 'Verification email sent.');
    }
}
