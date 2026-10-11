<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['accounting_request_id', 'author_id', 'body'])]
class InternalNote extends Model
{
    public function accountingRequest(): BelongsTo
    {
        return $this->belongsTo(AccountingRequest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
