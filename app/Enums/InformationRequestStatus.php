<?php

namespace App\Enums;

enum InformationRequestStatus: string
{
    case Pending = 'pending';
    case Responded = 'responded';
}
