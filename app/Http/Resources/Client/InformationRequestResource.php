<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InformationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'accountingRequestId' => (string) $this->accounting_request_id,
            'message' => $this->message,
            'status' => $this->status->value,
            'responseMessage' => $this->response_message,
            'respondedAt' => $this->responded_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
