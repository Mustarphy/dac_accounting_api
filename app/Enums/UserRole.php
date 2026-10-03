<?php

namespace App\Enums;

/**
 * The portal foundation only — admin/staff/client is the full extent of
 * what's needed before the actual portal features (letters, invoices,
 * etc.) are built. Add cases here, not scattered string checks, when a
 * new role is needed.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Client = 'client';
}
