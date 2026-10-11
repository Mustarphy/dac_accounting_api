<?php

namespace App\Services\Staff;

use App\Enums\ActivityAction;
use App\Enums\InformationRequestStatus;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Models\AccountingRequest;
use App\Models\User;
use App\Services\Client\NotificationDispatcher;
use App\Services\Client\RequestActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only place that changes AccountingRequest::status. A client can
 * never reach this (no client-facing route calls it); staff hit the
 * state machine defined here, which is the server-side enforcement the
 * brief requires — "a request must not become concluded merely because
 * someone changes a dropdown."
 */
class RequestStatusTransitionService
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        'opened' => ['ongoing'],
        'ongoing' => ['concluded'],
        'concluded' => ['ongoing'], // explicit, authorized reopen
    ];

    public function __construct(
        private readonly RequestActivityLogger $activity,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function transition(AccountingRequest $request, RequestStatus $to, User $actor): AccountingRequest
    {
        $from = $request->status;

        if ($from === $to) {
            throw ValidationException::withMessages(['status' => ["This request is already {$to->value}."]]);
        }

        $allowed = self::ALLOWED_TRANSITIONS[$from->value] ?? [];
        if (! in_array($to->value, $allowed, strict: true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot move a request from \"{$from->value}\" to \"{$to->value}\"."],
            ]);
        }

        if ($to === RequestStatus::Concluded) {
            $this->assertCanConclude($request);
        }

        return DB::transaction(function () use ($request, $from, $to, $actor) {
            $request->forceFill([
                'status' => $to,
                'concluded_at' => $to === RequestStatus::Concluded ? now() : null,
            ])->save();

            $this->activity->log(
                $request,
                $actor,
                'staff',
                ActivityAction::StatusChanged,
                "Status changed from \"{$from->value}\" to \"{$to->value}\".",
                ['from' => $from->value, 'to' => $to->value],
            );

            $this->notifications->send(
                $request->client,
                NotificationType::StatusUpdated,
                'Request status updated',
                "Your request \"{$request->title}\" ({$request->reference}) is now {$to->value}.",
                $request,
            );

            return $request;
        });
    }

    /**
     * The one completion rule currently enforced: every information
     * request on this record must be resolved first. Add further
     * completion conditions here, not scattered across controllers, as
     * the business rules grow.
     */
    private function assertCanConclude(AccountingRequest $request): void
    {
        $hasPendingInformationRequests = $request->informationRequests()
            ->where('status', InformationRequestStatus::Pending)
            ->exists();

        if ($hasPendingInformationRequests) {
            throw ValidationException::withMessages([
                'status' => ['This request has unresolved information requests and cannot be concluded yet.'],
            ]);
        }
    }
}
