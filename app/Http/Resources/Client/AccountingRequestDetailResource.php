<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full shape for the request detail page. Expects documents,
 * informationRequests, and activities to already be eager-loaded by the
 * controller (see AccountingRequestController::show()) — this resource
 * doesn't lazy-load them itself, to keep query timing explicit.
 */
class AccountingRequestDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'description' => $this->description,
            'serviceType' => $this->service_type->value,
            'status' => $this->status->value,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'concludedAt' => $this->concluded_at?->toISOString(),
            'documents' => RequestDocumentResource::collection($this->whenLoaded('documents')),
            'informationRequests' => InformationRequestResource::collection($this->whenLoaded('informationRequests')),
            'activity' => RequestActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
