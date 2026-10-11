<?php

namespace App\Enums;

/**
 * The only three states an accounting request can be in. Clients can
 * never set this directly — only AccountingRequestService (future staff
 * actions) transitions it. See AccountingRequestPolicy.
 */
enum RequestStatus: string
{
    case Opened = 'opened';
    case Ongoing = 'ongoing';
    case Concluded = 'concluded';
}
