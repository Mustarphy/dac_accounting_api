<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ChangePasswordRequest;
use App\Http\Requests\Client\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['name' => $request->validated('name')]);

        return $this->success(['user' => new UserResource($user)], 'Profile updated.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('newPassword')]);

        return $this->success(null, 'Password updated.');
    }
}
