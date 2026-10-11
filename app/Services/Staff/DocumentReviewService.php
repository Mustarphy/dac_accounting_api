<?php

namespace App\Services\Staff;

use App\Enums\ActivityAction;
use App\Enums\DocumentReviewStatus;
use App\Enums\NotificationType;
use App\Models\RequestDocument;
use App\Models\User;
use App\Services\Client\NotificationDispatcher;
use App\Services\Client\RequestActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentReviewService
{
    public function __construct(
        private readonly RequestActivityLogger $activity,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function review(RequestDocument $document, User $staff, DocumentReviewStatus $status, ?string $notes): RequestDocument
    {
        if ($document->source->value !== 'client') {
            throw ValidationException::withMessages([
                'document' => ['Only client-submitted documents can be reviewed.'],
            ]);
        }

        return DB::transaction(function () use ($document, $staff, $status, $notes) {
            $document->forceFill([
                'review_status' => $status,
                'reviewed_by_id' => $staff->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();

            $request = $document->accountingRequest;
            $outcome = $status === DocumentReviewStatus::Approved ? 'approved' : 'needs correction';

            $this->activity->log(
                $request,
                $staff,
                'staff',
                ActivityAction::DocumentReviewed,
                "DAC Accounting reviewed \"{$document->original_filename}\": {$outcome}.",
            );

            if ($status === DocumentReviewStatus::Rejected) {
                $this->notifications->send(
                    $request->client,
                    NotificationType::DocumentUploaded,
                    'A document needs correction',
                    "\"{$document->original_filename}\" on \"{$request->title}\" ({$request->reference}) needs a correction — see the request for details.",
                    $request,
                );
            }

            return $document;
        });
    }
}
