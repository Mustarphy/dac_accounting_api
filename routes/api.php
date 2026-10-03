<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
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
});
