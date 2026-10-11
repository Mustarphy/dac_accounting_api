<?php

namespace App\Http\Resources\Staff;

use App\Http\Resources\Client\InformationRequestResource;
use App\Http\Resources\Client\RequestActivityResource;
use App\Http\Resources\Client\RequestDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAccountingRequestDetailResource extends JsonResource
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
            'client' => $this->whenLoaded('client', fn () => [
                'id' => (string) $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
                'emailVerifiedAt' => $this->client->email_verified_at?->toISOString(),
            ]),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'concludedAt' => $this->concluded_at?->toISOString(),
            'documents' => RequestDocumentResource::collection($this->whenLoaded('documents')),
            'informationRequests' => InformationRequestResource::collection($this->whenLoaded('informationRequests')),
            'internalNotes' => InternalNoteResource::collection($this->whenLoaded('internalNotes')),
            'activity' => RequestActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}
