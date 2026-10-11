<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ClientNotificationResource;
use App\Models\ClientNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ClientNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest('created_at');

        if ($request->boolean('unreadOnly')) {
            $query->whereNull('read_at');
        }

        $paginator = $query->paginate(20);

        return $this->success([
            'notifications' => ClientNotificationResource::collection($paginator->items()),
            'unreadCount' => ClientNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'OK');
    }

    public function markRead(Request $request, ClientNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->isRead()) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $this->success(['notification' => new ClientNotificationResource($notification)], 'OK');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        ClientNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success(null, 'All notifications marked as read.');
    }
}
