<?php

namespace App\Http\Resources\Staff;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'emailVerifiedAt' => $this->email_verified_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'requestCount' => $this->when(isset($this->accounting_requests_count), fn () => (int) $this->accounting_requests_count),
        ];
    }
}
