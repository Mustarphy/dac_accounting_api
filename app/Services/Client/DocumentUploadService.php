<?php

namespace App\Services\Client;

use App\Enums\ActivityAction;
use App\Enums\DocumentCategory;
use App\Enums\DocumentSource;
use App\Enums\NotificationType;
use App\Models\AccountingRequest;
use App\Models\RequestDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores on the private `local` disk (storage/app/private — never
 * `public`) under a per-request, UUID-named path so the original
 * filename (user-controlled, shown back in the UI) never touches the
 * filesystem path itself. Files are only ever read back through
 * DocumentDownloadController's authorized stream, never a direct URL.
 */
class DocumentUploadService
{
    public function __construct(
        private readonly RequestActivityLogger $activity,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public function store(
        AccountingRequest $request,
        User $uploadedBy,
        UploadedFile $file,
        DocumentCategory $category,
        DocumentSource $source = DocumentSource::Client,
    ): RequestDocument {
        return DB::transaction(function () use ($request, $uploadedBy, $file, $category, $source) {
            $extension = $file->getClientOriginalExtension();
            $storedName = Str::uuid()->toString().($extension ? ".{$extension}" : '');
            $path = $file->storeAs("accounting-requests/{$request->id}", $storedName, 'local');

            $document = RequestDocument::create([
                'accounting_request_id' => $request->id,
                'uploaded_by_id' => $uploadedBy->id,
                'source' => $source,
                'category' => $category,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);

            $isDeliverable = $source === DocumentSource::Dac;

            $this->activity->log(
                $request,
                $uploadedBy,
                $source === DocumentSource::Client ? 'client' : 'staff',
                $isDeliverable ? ActivityAction::DeliverableAdded : ActivityAction::DocumentUploaded,
                $isDeliverable
                    ? "DAC Accounting added a deliverable: {$document->original_filename}."
                    : "{$uploadedBy->name} uploaded {$document->original_filename}.",
            );

            if ($isDeliverable) {
                $this->notifications->send(
                    $request->client,
                    NotificationType::DeliverableAvailable,
                    'Deliverable available',
                    "A new document is available on your request \"{$request->title}\" ({$request->reference}).",
                    $request,
                );
            }

            return $document;
        });
    }
}
