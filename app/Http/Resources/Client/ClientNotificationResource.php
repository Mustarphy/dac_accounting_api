<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'accountingRequestId' => $this->accounting_request_id ? (string) $this->accounting_request_id : null,
            'isRead' => $this->isRead(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
