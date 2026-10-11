<?php

namespace App\Http\Requests\Staff;

use App\Enums\DocumentReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ReviewDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(DocumentReviewStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
