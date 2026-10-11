<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'title', 'body', 'accounting_request_id'])]
class ClientNotification extends Model
{
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountingRequest(): BelongsTo
    {
        return $this->belongsTo(AccountingRequest::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
