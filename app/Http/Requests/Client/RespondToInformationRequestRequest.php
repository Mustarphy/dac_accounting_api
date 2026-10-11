<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class RespondToInformationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
