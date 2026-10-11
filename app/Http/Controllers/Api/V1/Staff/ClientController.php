<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Staff\AdminAccountingRequestResource;
use App\Http\Resources\Staff\ClientSummaryResource;
use App\Models\AccountingRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deliberately read-mostly: the only write here is nothing at all — no
 * endpoint returns a password or auth secret, and no endpoint lets staff
 * change a client's role (that's the CLI-only provisioning command, see
 * PromoteUserCommand). Staff can observe, not impersonate or escalate.
 */
class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('role', UserRole::Client)
            ->withCount('accountingRequests')
            ->latest('created_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate(20);

        return $this->success([
            'clients' => ClientSummaryResource::collection($paginator->items()),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], 'OK');
    }

    public function show(User $client): JsonResponse
    {
        abort_unless($client->role === UserRole::Client, 404);

        $requests = AccountingRequest::query()
            ->where('client_id', $client->id)
            ->withCount([
                'informationRequests as pending_information_requests_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->latest('created_at')
            ->get();

        return $this->success([
            'client' => new ClientSummaryResource($client),
            'requests' => AdminAccountingRequestResource::collection($requests),
        ], 'OK');
    }
}
