<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

/**
 * Every API controller extends this for a consistent JSON envelope —
 * {success, message, data} on success, {success, message, errors?} on
 * failure (see bootstrap/app.php's exception rendering for the failure
 * side). Keeps individual controllers from each inventing their own
 * response shape.
 *
 * Also pulls in AuthorizesRequests for $this->authorize() — Laravel 13's
 * base Illuminate\Routing\Controller no longer includes it by default.
 */
abstract class Controller
{
    use AuthorizesRequests;

    protected function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function failure(string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors === [] ? null : $errors,
        ], $status);
    }
}
