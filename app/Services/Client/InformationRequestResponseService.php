<?php

namespace App\Services\Client;

use App\Enums\ActivityAction;
use App\Enums\InformationRequestStatus;
use App\Models\InformationRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InformationRequestResponseService
{
    public function __construct(private readonly RequestActivityLogger $activity) {}

    public function respond(InformationRequest $informationRequest, User $client, string $message): InformationRequest
    {
        if ($informationRequest->status === InformationRequestStatus::Responded) {
            throw ValidationException::withMessages([
                'message' => ['This information request has already been responded to.'],
            ]);
        }

        return DB::transaction(function () use ($informationRequest, $client, $message) {
            $informationRequest->forceFill([
                'status' => InformationRequestStatus::Responded,
                'response_message' => $message,
                'responded_at' => now(),
            ])->save();

            $this->activity->log(
                $informationRequest->accountingRequest,
                $client,
                'client',
                ActivityAction::InformationResponded,
                "{$client->name} responded to a request for information.",
            );

            // No notification dispatched here: the recipient of this event
            // is DAC staff, and no staff accounts/notifications exist yet
            // in this client-only phase (see Step 18 of the setup brief).

            return $informationRequest;
        });
    }
}
