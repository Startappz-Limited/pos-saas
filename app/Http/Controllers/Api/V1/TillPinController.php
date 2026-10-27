<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\User\ManageTillPin;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetTillPinRequest;
use App\Http\Responses\ApiV1;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Till PINs for the Flutter POS (Phase 7): set your own (with your password),
 * or reset a colleague's when you may edit them. The till receives the PIN
 * hashes with the staff list on its next pull.
 */
class TillPinController extends Controller
{
    public function update(SetTillPinRequest $request, ManageTillPin $pins): JsonResponse
    {
        $tillPin = $pins->set($request->user(), $request->validated('pin'));

        return ApiV1::ok(['set_at' => $tillPin->set_at->toIso8601String()], __('Till PIN saved.'));
    }

    public function reset(User $user, ManageTillPin $pins): JsonResponse
    {
        $this->authorize('update', $user);

        return ApiV1::ok(['reset' => $pins->reset($user)], __('Till PIN reset.'));
    }

    public function unlock(User $user, ManageTillPin $pins): JsonResponse
    {
        $this->authorize('update', $user);

        return ApiV1::ok(['unlocked' => $pins->unlock($user)], __('Unlocked.'));
    }
}
