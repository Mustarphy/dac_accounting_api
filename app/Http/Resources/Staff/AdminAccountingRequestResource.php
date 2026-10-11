<?php

namespace App\Http\Resources\Staff;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List-view shape for the staff request queue — includes the client's
 * name/email (staff need to know whose request this is at a glance),
 * which the client-facing AccountingRequestResource never exposes.
 */
class AdminAccountingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'serviceType' => $this->service_type->value,
            'status' => $this->status->value,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => (string) $this->client->id,
                'name' => $this->client->name,
                'email' => $this->client->email,
            ]),
            'pendingInformationRequestCount' => $this->when(
                isset($this->pending_information_requests_count),
                fn () => (int) $this->pending_information_requests_count
            ),
            'unreviewedDocumentCount' => $this->when(
                isset($this->unreviewed_documents_count),
                fn () => (int) $this->unreviewed_documents_count
            ),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'concludedAt' => $this->concluded_at?->toISOString(),
        ];
    }
}
