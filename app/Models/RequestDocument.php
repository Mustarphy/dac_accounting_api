<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Enums\DocumentReviewStatus;
use App\Enums\DocumentSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * review_status/reviewed_by_id/reviewed_at/review_notes are deliberately
 * excluded from #[Fillable] — only DocumentReviewService (staff-only,
 * via forceFill) ever sets them, never client or admin mass-assignment.
 */
#[Fillable([
    'accounting_request_id', 'uploaded_by_id', 'source', 'category',
    'original_filename', 'stored_path', 'mime_type', 'size',
])]
class RequestDocument extends Model
{
    protected function casts(): array
    {
        return [
            'source' => DocumentSource::class,
            'category' => DocumentCategory::class,
            'size' => 'integer',
            'review_status' => DocumentReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function accountingRequest(): BelongsTo
    {
        return $this->belongsTo(AccountingRequest::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
