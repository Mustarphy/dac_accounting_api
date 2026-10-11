<?php

namespace App\Models;

use App\Enums\InformationRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `status`/`response_message`/`responded_at` are excluded from
 * #[Fillable] — a client may only ever change these through
 * InformationRequestService::respond(), never raw mass-assignment.
 */
#[Fillable(['accounting_request_id', 'requested_by_id', 'message'])]
class InformationRequest extends Model
{
    protected function casts(): array
    {
        return [
            'status' => InformationRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function accountingRequest(): BelongsTo
    {
        return $this->belongsTo(AccountingRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
}
