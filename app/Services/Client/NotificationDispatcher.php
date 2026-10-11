<?php

namespace App\Services\Client;

use App\Enums\NotificationType;
use App\Models\AccountingRequest;
use App\Models\ClientNotification;
use App\Models\User;

class NotificationDispatcher
{
    public function send(
        User $user,
        NotificationType $type,
        string $title,
        string $body,
        ?AccountingRequest $request = null
    ): ClientNotification {
        return ClientNotification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'accounting_request_id' => $request?->id,
        ]);
    }
}
