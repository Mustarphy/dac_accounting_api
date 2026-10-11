<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Summary shape — used in list views. See AccountingRequestDetailResource
 * for the full shape used on the request detail page.
 */
class AccountingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'serviceType' => $this->service_type->value,
            'status' => $this->status->value,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'concludedAt' => $this->concluded_at?->toISOString(),
            // Populated only when the controller eager-loads this specific
            // named count (withCount(['informationRequests as pending...'])).
            'pendingInformationRequestCount' => $this->when(
                isset($this->pending_information_requests_count),
                fn () => (int) $this->pending_information_requests_count
            ),
        ];
    }
}
