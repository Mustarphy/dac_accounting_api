<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches the frontend's AuthUser type exactly (id, name, email,
 * emailVerifiedAt) — see dac-accounting-frontend/src/lib/auth/types.ts.
 * Deliberately excludes password, role, and everything else: the
 * frontend never needs them, and the fewer fields a user-info endpoint
 * returns the smaller the blast radius if a response ever leaks.
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
        ];
    }
}
