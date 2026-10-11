<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CreateInternalNoteRequest;
use App\Http\Resources\Staff\InternalNoteResource;
use App\Models\AccountingRequest;
use App\Models\InternalNote;
use Illuminate\Http\JsonResponse;

class InternalNoteController extends Controller
{
    public function store(CreateInternalNoteRequest $request, AccountingRequest $accountingRequest): JsonResponse
    {
        $note = InternalNote::create([
            'accounting_request_id' => $accountingRequest->id,
            'author_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return $this->success(['note' => new InternalNoteResource($note->load('author'))], 'Note added.', 201);
    }
}
