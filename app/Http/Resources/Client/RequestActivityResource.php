<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'accountingRequestId' => (string) $this->accounting_request_id,
            'actorType' => $this->actor_type,
            'actorName' => $this->whenLoaded('actor', fn () => $this->actor?->name),
            'action' => $this->action->value,
            'description' => $this->description,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
