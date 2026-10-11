<?php

namespace App\Services\Staff;

use App\Enums\ActivityAction;
use App\Enums\InformationRequestStatus;
use App\Enums\NotificationType;
use App\Models\AccountingRequest;
use App\Models\InformationRequest;
use App\Models\User;
use App\Services\Client\NotificationDispatcher;
use App\Services\Client\RequestActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Closes the loop the client-only phase couldn't: previously, an
 * InformationRequest could only exist via a database seeder, since
 * nothing in the app created one. This is that missing piece.
 */
class InformationRequestCreationService
{
    public function __construct(
        private readonly RequestActivityLogger $activity,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function create(AccountingRequest $request, User $staff, string $message): InformationRequest
    {
        return DB::transaction(function () use ($request, $staff, $message) {
            // 'status' set explicitly, not left to the column default —
            // same bug class as docs/backend-setup.md §9: the in-memory
            // $informationRequest below is serialized into the response
            // immediately, and Eloquent never syncs a DB default back
            // after insert.
            $informationRequest = InformationRequest::create([
                'accounting_request_id' => $request->id,
                'requested_by_id' => $staff->id,
                'message' => $message,
            ]);
            $informationRequest->forceFill(['status' => InformationRequestStatus::Pending])->save();

            $this->activity->log(
                $request,
                $staff,
                'staff',
                ActivityAction::InformationRequested,
                'DAC Accounting requested additional information.',
            );

            $this->notifications->send(
                $request->client,
                NotificationType::InformationRequested,
                'Information needed on your request',
                "DAC Accounting needs more information on \"{$request->title}\" ({$request->reference}).",
                $request,
            );

            return $informationRequest;
        });
    }
}
