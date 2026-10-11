<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Models\RequestDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    /**
     * The only way a document's bytes are ever served — no public
     * storage URL exists for these. Authorization goes through the same
     * AccountingRequestPolicy as everything else: a client can only
     * download a document whose parent request belongs to them,
     * regardless of what document id they guess in the URL.
     */
    public function __invoke(Request $request, RequestDocument $document): StreamedResponse
    {
        $this->authorize('view', $document->accountingRequest);

        abort_unless(Storage::disk('local')->exists($document->stored_path), 404);

        return Storage::disk('local')->download($document->stored_path, $document->original_filename);
    }
}
