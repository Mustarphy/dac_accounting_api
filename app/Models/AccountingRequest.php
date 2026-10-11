<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The central workflow entity. `status` is deliberately excluded from
 * #[Fillable] — it must never be settable via client mass-assignment;
 * only RequestActivity-logged transitions (future staff actions) change
 * it. `reference` is generated server-side after creation, also never
 * client-supplied.
 */
#[Fillable(['client_id', 'title', 'description', 'service_type'])]
class AccountingRequest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'service_type' => ServiceType::class,
            'concluded_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RequestDocument::class);
    }

    public function informationRequests(): HasMany
    {
        return $this->hasMany(InformationRequest::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(RequestActivity::class)->latest('created_at');
    }

    /** Staff-only — never eager-loaded or referenced by anything client-facing. */
    public function internalNotes(): HasMany
    {
        return $this->hasMany(InternalNote::class)->latest('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(ClientNotification::class);
    }
}
