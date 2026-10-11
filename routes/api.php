<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Client\AccountController;
use App\Http\Controllers\Api\V1\Client\AccountingRequestController;
use App\Http\Controllers\Api\V1\Client\DashboardController;
use App\Http\Controllers\Api\V1\Client\DocumentController;
use App\Http\Controllers\Api\V1\Client\DocumentDownloadController;
use App\Http\Controllers\Api\V1\Client\InformationRequestController;
use App\Http\Controllers\Api\V1\Client\NotificationController;
use App\Http\Controllers\Api\V1\Staff\AdminDocumentController;
use App\Http\Controllers\Api\V1\Staff\AdminInformationRequestController;
use App\Http\Controllers\Api\V1\Staff\AdminRequestController;
use App\Http\Controllers\Api\V1\Staff\ClientController;
use App\Http\Controllers\Api\V1\Staff\InternalNoteController;
use App\Http\Controllers\Api\V1\Staff\StaffDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Registered under /api by bootstrap/app.php's withRouting(api: ...), with
| the `api` middleware group applied automatically. The `v1` prefix here
| is the one and only versioning convention in this project — see
| docs/backend-setup.md §"API versioning". The frontend's
| NEXT_PUBLIC_API_URL is expected to already include /api/v1, so route
| paths below match the frontend's relative AUTH_ENDPOINTS exactly
| (see dac-accounting-frontend/src/lib/auth/endpoints.ts).
|
*/

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:refresh');

        Route::post('/password/forgot', [PasswordResetController::class, 'forgot'])->middleware('throttle:forgot-password');
        Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:forgot-password');

        Route::post('/email/verify', [EmailVerificationController::class, 'verify'])->middleware('throttle:verify-email');

        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:resend-verification');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Client workflow (OAC)
    |----------------------------------------------------------------------
    |
    | Prefixed `/client` on purpose: the future admin/staff panel will add
    | a sibling `/staff` (or `/admin`) prefix over the *same* underlying
    | models, so the two surfaces never collide on a route name. Every
    | action here is authorized per-request via AccountingRequestPolicy —
    | see app/Policies/AccountingRequestPolicy.php — not just gated by
    | being logged in.
    |
    */
    Route::prefix('client')->middleware('auth:api')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'summary']);

        Route::get('/requests', [AccountingRequestController::class, 'index']);
        Route::post('/requests', [AccountingRequestController::class, 'store']);
        Route::get('/requests/{accountingRequest}', [AccountingRequestController::class, 'show']);
        Route::post('/requests/{accountingRequest}/documents', [DocumentController::class, 'store'])
            ->middleware('throttle:document-upload');

        Route::get('/documents', [DocumentController::class, 'index']);
        Route::get('/documents/{document}/download', DocumentDownloadController::class);

        Route::get('/information-requests', [InformationRequestController::class, 'index']);
        Route::post('/information-requests/{informationRequest}/respond', [InformationRequestController::class, 'respond']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        Route::patch('/account', [AccountController::class, 'update']);
        Route::post('/account/password', [AccountController::class, 'changePassword']);
    });

    /*
    |----------------------------------------------------------------------
    | Staff / admin panel (OAC)
    |----------------------------------------------------------------------
    |
    | Gated by `role:admin,staff` (see app/Http/Middleware/EnsureUserHasRole.php)
    | on the WHOLE group — that middleware, not any per-request
    | authorize() call, is the actual boundary: a client can never reach
    | any route in here no matter what the frontend does. There is no
    | AccountingRequestPolicy check inside these controllers on purpose —
    | staff can see every client's requests, that's the point of the
    | panel. The only thing scoping access further is which actions are
    | available to `staff` vs `admin` roles, left for a future, more
    | granular permission model if the business needs it — not required
    | for the workflow this phase implements.
    |
    */
    Route::prefix('staff')->middleware(['auth:api', 'role:admin,staff'])->group(function () {
        Route::get('/dashboard', [StaffDashboardController::class, 'summary']);

        Route::get('/clients', [ClientController::class, 'index']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);

        Route::get('/requests', [AdminRequestController::class, 'index']);
        Route::get('/requests/{accountingRequest}', [AdminRequestController::class, 'show']);
        Route::patch('/requests/{accountingRequest}/status', [AdminRequestController::class, 'updateStatus']);

        Route::post('/requests/{accountingRequest}/information-requests', [AdminInformationRequestController::class, 'store']);
        Route::post('/requests/{accountingRequest}/notes', [InternalNoteController::class, 'store']);
        Route::post('/requests/{accountingRequest}/deliverables', [AdminDocumentController::class, 'publishDeliverable'])
            ->middleware('throttle:document-upload');

        Route::get('/documents/{document}/download', [AdminDocumentController::class, 'download']);
        Route::post('/documents/{document}/review', [AdminDocumentController::class, 'review']);
    });
});
