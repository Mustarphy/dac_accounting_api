<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit log — always append via RequestActivityService, never
 * update or delete a row from application code.
 */
#[Fillable(['accounting_request_id', 'actor_id', 'actor_type', 'action', 'description', 'meta'])]
class RequestActivity extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function accountingRequest(): BelongsTo
    {
        return $this->belongsTo(AccountingRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
