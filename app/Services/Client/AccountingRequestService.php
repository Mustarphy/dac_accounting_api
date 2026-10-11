<?php

namespace App\Services\Client;

use App\Enums\ActivityAction;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use App\Models\AccountingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountingRequestService
{
    public function __construct(
        private readonly RequestActivityLogger $activity,
        private readonly NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array{title: string, description: ?string, serviceType: ServiceType}  $data
     */
    public function create(User $client, array $data): AccountingRequest
    {
        return DB::transaction(function () use ($client, $data) {
            // forceFill, not create(): `reference` is deliberately excluded
            // from AccountingRequest's #[Fillable] (it's server-generated,
            // never client input) — create() would silently drop it, the
            // same gotcha as RefreshToken::revoked_at. See docs/backend-setup.md §9.
            $request = new AccountingRequest;
            $request->forceFill([
                'client_id' => $client->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'service_type' => $data['serviceType'],
                // Set explicitly rather than relying on the column's DB
                // default — Eloquent doesn't sync a column default back
                // into the in-memory model after insert, so the status
                // read moments later (in the response below) would see
                // null until the row was re-fetched from the database.
                'status' => RequestStatus::Opened,
                // Placeholder until the real reference (which needs this
                // row's own id) is assigned below.
                'reference' => 'PENDING-'.uniqid(),
            ])->save();

            $request->forceFill(['reference' => $this->generateReference($request)])->save();

            $this->activity->log(
                $request,
                $client,
                'client',
                ActivityAction::RequestCreated,
                "Request \"{$request->title}\" was submitted.",
            );

            $this->notifications->send(
                $client,
                NotificationType::RequestSubmitted,
                'Request submitted',
                "Your request \"{$request->title}\" ({$request->reference}) has been received and is awaiting review.",
                $request,
            );

            return $request;
        });
    }

    private function generateReference(AccountingRequest $request): string
    {
        return sprintf('DAC-%s-%05d', $request->created_at->format('Y'), $request->id);
    }
}
