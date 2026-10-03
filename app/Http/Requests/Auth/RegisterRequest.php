<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Field names (passwordConfirmation, not password_confirmation) match the
 * frontend's existing RegisterRequest type exactly — see
 * dac-accounting-frontend/src/lib/auth/types.ts.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'passwordConfirmation' => ['required', 'same:password'],
        ];
    }
}
