<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend's AuthUser type exactly — see
 * dac-accounting-frontend/src/lib/auth/types.ts. `role` was added so the
 * frontend can decide whether to render the admin panel shell; this is a
 * UI convenience only, never the security boundary — every
 * /api/v1/staff/* route is independently gated by the `role:admin,staff`
 * middleware server-side regardless of what this response says.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'emailVerifiedAt' => $this->email_verified_at?->toISOString(),
            'role' => $this->role->value,
        ];
    }
}
