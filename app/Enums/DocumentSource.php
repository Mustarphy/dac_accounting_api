<?php

namespace App\Enums;

/**
 * Distinguishes a client's own submission from a file DAC staff issued
 * back to them (e.g. a completed deliverable) — same table, different
 * provenance. See RequestDocument.
 */
enum DocumentSource: string
{
    case Client = 'client';
    case Dac = 'dac';
}
