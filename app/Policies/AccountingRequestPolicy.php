<?php

namespace App\Policies;

use App\Models\AccountingRequest;
use App\Models\User;

/**
 * The single source of truth for "does this user own this request" —
 * every controller touching a request, its documents, its information
 * requests, or its activity must call through this (via $this->authorize()
 * or Gate::authorize()), never just trust a route-model-bound ID. A
 * client changing a URL's {id} must never reach another client's data.
 */
class AccountingRequestPolicy
{
    public function view(User $user, AccountingRequest $request): bool
    {
        return $user->id === $request->client_id;
    }

    public function createDocument(User $user, AccountingRequest $request): bool
    {
        return $user->id === $request->client_id;
    }

    /**
     * Clients never update the request itself (status, title, etc. are
     * staff/system-only) — no `update` ability is defined here on
     * purpose. Only document upload and information-request responses
     * are client-writable actions, and those have their own abilities.
     */
}
