<?php

namespace App\Enums;

/**
 * Immutable audit trail entries for RequestActivity. "actor" on the
 * activity row records who did it (client/staff/system); this records
 * what happened.
 */
enum ActivityAction: string
{
    case RequestCreated = 'request_created';
    case StatusChanged = 'status_changed';
    case DocumentUploaded = 'document_uploaded';
    case DocumentReviewed = 'document_reviewed';
    case InformationRequested = 'information_requested';
    case InformationResponded = 'information_responded';
    case DeliverableAdded = 'deliverable_added';
}
