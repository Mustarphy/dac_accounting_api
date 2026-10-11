<?php

namespace App\Http\Requests\Client;

use App\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListAccountingRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', new Enum(RequestStatus::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
