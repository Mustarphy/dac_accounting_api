<?php

namespace App\Services\Client;

use App\Enums\ActivityAction;
use App\Models\AccountingRequest;
use App\Models\RequestActivity;
use App\Models\User;

/**
 * The only place that writes to request_activities — every workflow
 * event (request created, document uploaded, information requested or
 * responded to, status changed) goes through here so the audit trail
 * can never silently fall out of sync with what actually happened.
 */
class RequestActivityLogger
{
    public function log(
        AccountingRequest $request,
        ?User $actor,
        string $actorType,
        ActivityAction $action,
        string $description,
        array $meta = []
    ): RequestActivity {
        return RequestActivity::create([
            'accounting_request_id' => $request->id,
            'actor_id' => $actor?->id,
            'actor_type' => $actorType,
            'action' => $action,
            'description' => $description,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
