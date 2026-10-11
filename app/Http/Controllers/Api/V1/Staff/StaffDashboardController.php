<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\DocumentSource;
use App\Enums\InformationRequestStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Staff\AdminAccountingRequestResource;
use App\Models\AccountingRequest;
use App\Models\RequestActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffDashboardController extends Controller
{
    /**
     * Every figure is a live query across ALL clients' records — unlike
     * the client dashboard, which scopes to one client_id, this
     * deliberately doesn't, since staff need the whole queue.
     */
    public function summary(Request $request): JsonResponse
    {
        $counts = [
            'total' => AccountingRequest::query()->count(),
            'opened' => AccountingRequest::query()->where('status', RequestStatus::Opened)->count(),
            'ongoing' => AccountingRequest::query()->where('status', RequestStatus::Ongoing)->count(),
            'concluded' => AccountingRequest::query()->where('status', RequestStatus::Concluded)->count(),
            'awaitingClientInformation' => AccountingRequest::query()
                ->whereHas('informationRequests', fn ($q) => $q->where('status', InformationRequestStatus::Pending))
                ->count(),
            'awaitingStaffReview' => AccountingRequest::query()
                ->whereHas('documents', fn ($q) => $q->where('source', DocumentSource::Client)->whereNull('review_status'))
                ->count(),
        ];

        $recentRequests = AccountingRequest::query()
            ->with('client')
            ->withCount([
                'informationRequests as pending_information_requests_count' => fn ($q) => $q->where('status', InformationRequestStatus::Pending),
            ])
            ->latest('created_at')
            ->limit(8)
            ->get();

        $recentActivity = RequestActivity::query()
            ->with(['actor', 'accountingRequest'])
            ->latest('created_at')
            ->limit(15)
            ->get();

        return $this->success([
            'counts' => $counts,
            'recentRequests' => AdminAccountingRequestResource::collection($recentRequests),
            'recentActivity' => $recentActivity->map(fn ($activity) => [
                'id' => (string) $activity->id,
                'accountingRequestId' => (string) $activity->accounting_request_id,
                'accountingRequestTitle' => $activity->accountingRequest?->title,
                'actorType' => $activity->actor_type,
                'actorName' => $activity->actor?->name,
                'action' => $activity->action->value,
                'description' => $activity->description,
                'createdAt' => $activity->created_at?->toISOString(),
            ]),
        ], 'OK');
    }
}
