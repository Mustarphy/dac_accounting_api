<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately no `stored_path` or any direct file URL — downloads only
 * ever happen through the authorized DocumentDownloadController, which
 * takes just the document's id.
 *
 * reviewStatus/reviewNotes ARE client-visible on purpose: a rejected
 * document's notes are the client's only way to know what to fix. This
 * is distinct from InternalNote, which never appears in any client
 * resource.
 */
class RequestDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'accountingRequestId' => (string) $this->accounting_request_id,
            'source' => $this->source->value,
            'category' => $this->category->value,
            'filename' => $this->original_filename,
            'mimeType' => $this->mime_type,
            'size' => (int) $this->size,
            'uploadedBy' => $this->whenLoaded('uploadedBy', fn () => $this->uploadedBy->name),
            'reviewStatus' => $this->review_status?->value,
            'reviewNotes' => $this->review_notes,
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
