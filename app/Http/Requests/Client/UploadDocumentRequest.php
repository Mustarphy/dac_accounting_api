<?php

namespace App\Http\Requests\Client;

use App\Enums\DocumentCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 10MB max; the allow-list covers everything a client is
            // realistically asked for (invoices, receipts, statements) —
            // deliberately no executables/archives/scripts.
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv'],
            'category' => ['required', new Enum(DocumentCategory::class)],
        ];
    }
}
