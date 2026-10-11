<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\DocumentCategory;
use App\Enums\DocumentReviewStatus;
use App\Enums\DocumentSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PublishDeliverableRequest;
use App\Http\Requests\Staff\ReviewDocumentRequest;
use App\Http\Resources\Client\RequestDocumentResource;
use App\Models\AccountingRequest;
use App\Models\RequestDocument;
use App\Services\Client\DocumentUploadService;
use App\Services\Staff\DocumentReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentReviewService $reviews,
        private readonly DocumentUploadService $uploads,
    ) {}

    /**
     * Staff equivalent of Client\DocumentDownloadController. No
     * AccountingRequestPolicy check here on purpose — the `role:admin,staff`
     * middleware on the whole /staff group is the authorization boundary,
     * same reasoning as every other staff endpoint (see routes/api.php).
     * Still no public storage URL: bytes are only ever streamed through
     * an authorized controller.
     */
    public function download(RequestDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }

    public function review(ReviewDocumentRequest $request, RequestDocument $document): JsonResponse
    {
        $document = $this->reviews->review(
            $document,
            $request->user(),
            DocumentReviewStatus::from($request->validated('status')),
            $request->validated('notes'),
        );

        return $this->success(['document' => new RequestDocumentResource($document)], 'Document reviewed.');
    }

    /**
     * Publishing a deliverable is just an upload with source=Dac,
     * category=Deliverable — DocumentUploadService (built for the client
     * portal) already supports this fully; no duplicate storage logic.
     */
    public function publishDeliverable(PublishDeliverableRequest $request, AccountingRequest $accountingRequest): JsonResponse
    {
        $document = $this->uploads->store(
            $accountingRequest,
            $request->user(),
            $request->file('file'),
            DocumentCategory::Deliverable,
            DocumentSource::Dac,
        );

        return $this->success(['document' => new RequestDocumentResource($document)], 'Deliverable published.', 201);
    }
}
