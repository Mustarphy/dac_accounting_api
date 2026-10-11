<?php

namespace App\Enums;

/**
 * Only meaningful for client-submitted documents. A null review_status
 * on the model (not representable here) means "not yet reviewed" —
 * distinct from Rejected.
 */
enum DocumentReviewStatus: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';
}
