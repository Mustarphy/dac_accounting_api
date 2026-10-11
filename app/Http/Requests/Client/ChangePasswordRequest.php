<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'current_password:api'],
            'newPassword' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'newPasswordConfirmation' => ['required', 'same:newPassword'],
        ];
    }
}
