<?php

namespace App\Enums;

enum NotificationType: string
{
    case RequestSubmitted = 'request_submitted';
    case StatusUpdated = 'status_updated';
    case InformationRequested = 'information_requested';
    case InformationResponded = 'information_responded';
    case DocumentUploaded = 'document_uploaded';
    case DeliverableAvailable = 'deliverable_available';
}
