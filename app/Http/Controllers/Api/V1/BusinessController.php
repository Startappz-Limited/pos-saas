<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiV1;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The user's businesses on `/api/v1`, and switching (stage 3). Switching
 * issues a token bound to the other business and revokes the one used; a
 * business the user can't work in is a 404.
 */
class BusinessController extends Controller
{
    public function index(Request $request, TenantContext $tenant): JsonResponse
    {
        return ApiV1::ok(self::listFor($request->user(), $tenant->id()));
    }

    public function switch(Request $request, Business $business, TenantContext $tenant): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->canEnterBusiness($business), 404);

        $current = $user->currentAccessToken();
        $session = AuthController::session($user, $business, $current->name, $tenant);
        $current->delete();

        return ApiV1::ok($session, __('Switched business.'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listFor(User $user, ?int $currentId): array
    {
        return $user->usableBusinesses()
            ->map(fn (Business $business): array => [...self::present($business, $user), 'is_current' => $business->id === $currentId])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Business $business, User $user): array
    {
        return [
            'uuid' => $business->uuid,
            'name' => $business->name,
            'currency' => $business->currency,
            'timezone' => $business->timezone,
            'is_default' => $business->id === $user->default_business_id,
        ];
    }
}
