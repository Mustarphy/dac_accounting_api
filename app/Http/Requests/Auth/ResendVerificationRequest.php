<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The route requires auth:api, and the controller always sends to the
 * authenticated user's own email (never the request body's email) so a
 * caller can't be tricked into verifying/emailing an address they don't
 * own. `email` is accepted only because the frontend's existing contract
 * sends it; it's intentionally ignored for the actual send target.
 */
class ResendVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['sometimes', 'string', 'email'],
        ];
    }
}
