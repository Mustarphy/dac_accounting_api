<?php

namespace App\Http\Resources\Staff;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Never referenced by any class under Http\Resources\Client. */
class InternalNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'accountingRequestId' => (string) $this->accounting_request_id,
            'body' => $this->body,
            'authorName' => $this->whenLoaded('author', fn () => $this->author->name),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
