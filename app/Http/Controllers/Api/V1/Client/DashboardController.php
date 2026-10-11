<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Enums\InformationRequestStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\AccountingRequestResource;
use App\Http\Resources\Client\RequestActivityResource;
use App\Models\AccountingRequest;
use App\Models\RequestActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Every figure here is a live count/query against the client's own
     * rows — nothing is hardcoded or fabricated. A brand-new client with
     * no requests yet gets real zeros, not placeholder numbers.
     */
    public function summary(Request $request): JsonResponse
    {
        $clientId = $request->user()->id;

        $baseQuery = fn () => AccountingRequest::query()->where('client_id', $clientId);

        $counts = [
            'opened' => $baseQuery()->where('status', RequestStatus::Opened)->count(),
            'ongoing' => $baseQuery()->where('status', RequestStatus::Ongoing)->count(),
            'concluded' => $baseQuery()->where('status', RequestStatus::Concluded)->count(),
            'awaitingYourAction' => $baseQuery()
                ->whereHas('informationRequests', fn ($q) => $q->where('status', InformationRequestStatus::Pending))
                ->count(),
        ];

        $recentRequests = $baseQuery()->latest('created_at')->limit(5)->get();

        $recentActivity = RequestActivity::query()
            ->whereHas('accountingRequest', fn ($q) => $q->where('client_id', $clientId))
            ->with('actor')
            ->latest('created_at')
            ->limit(10)
            ->get();

        return $this->success([
            'counts' => $counts,
            'recentRequests' => AccountingRequestResource::collection($recentRequests),
            'recentActivity' => RequestActivityResource::collection($recentActivity),
        ], 'OK');
    }
}
