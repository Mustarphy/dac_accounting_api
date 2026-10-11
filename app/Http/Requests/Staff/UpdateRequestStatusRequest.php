<?php

namespace App\Http\Requests\Staff;

use App\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(RequestStatus::class)],
        ];
    }
}
