<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately name-only. Email changes aren't supported here — they'd
 * need their own re-verification flow (a new email isn't "verified"
 * just because the old one was), which is out of scope for this phase.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
