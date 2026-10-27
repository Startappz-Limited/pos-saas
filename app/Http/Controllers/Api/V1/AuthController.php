<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Register\PairRegister;
use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SignInRequest;
use App\Http\Responses\ApiV1;
use App\Models\Business;
use App\Models\Register;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * `/api/v1` sign-in (decision 4.4, stage 3). The same rules as the web: one
 * usable business, or a usable default, gives a token bound to it; with
 * several and no usable default the client is given the list and signs in
 * again naming one, which becomes the default. Every token acts on one
 * business only; switching issues another (BusinessController).
 */
class AuthController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function signIn(SignInRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => [__('The provided credentials are incorrect.')]]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => [__('Your account is not active. Please contact an administrator.')]]);
        }

        $usable = $user->usableBusinesses();

        if ($usable->isEmpty()) {
            return ApiV1::error(ApiErrorCode::NoActiveBusiness, __('You are not a member of any active business.'), 403);
        }

        if (isset($validated['business'])) {
            $business = $usable->firstWhere('uuid', $validated['business']);

            if ($business === null) {
                throw ValidationException::withMessages(['business' => [__('You cannot sign in to this business.')]]);
            }

            if (! $user->canEnterBusiness($user->default_business_id)) {
                $user->forceFill(['default_business_id' => $business->id])->save();
            }
        } else {
            $business = $usable->firstWhere('id', $user->businessToOpen());
        }

        if ($business === null) {
            return ApiV1::ok([
                'business_required' => true,
                'token' => null,
                'businesses' => BusinessController::listFor($user, null),
            ], __('Choose a business, then sign in again with it.'));
        }

        $register = isset($validated['register'])
            ? $this->pairedRegister($user, $business, $validated['register'], $validated['device_id'])
            : null;

        return ApiV1::ok(self::session($user, $business, $validated['device_name'], $this->tenant, $register), __('Signed in.'));
    }

    /**
     * The till $user is signing in on: a register of $business, in a shop
     * they may access, paired to this very device. Refused otherwise, before
     * any token is made.
     *
     * @throws ValidationException
     */
    private function pairedRegister(User $user, Business $business, string $registerUuid, string $deviceId): Register
    {
        // Read by hand, not through the scopes: no one is signed in yet, so
        // the shop check is made here, against $user's own shops.
        $row = DB::table('registers')
            ->join('shops', 'shops.id', '=', 'registers.shop_id')
            ->where('shops.business_id', $business->id)
            ->where('registers.uuid', $registerUuid)
            ->first(['registers.id', 'registers.shop_id', 'registers.device_id']);

        $allowed = $row !== null && $this->tenant->run(
            $business,
            fn (): bool => in_array((int) $row->shop_id, $this->tenant->accessibleShopIdsFor($user), true),
        );

        if (! $allowed) {
            throw ValidationException::withMessages(['register' => [__('You cannot sign in on this till.')]]);
        }

        if ($row->device_id === null || ! hash_equals((string) $row->device_id, $deviceId)) {
            throw ValidationException::withMessages(['device_id' => [__('This device is not paired to that till. Ask a manager to pair it.')]]);
        }

        return (new Register)->newFromBuilder((array) DB::table('registers')->where('id', $row->id)->first());
    }

    public function signOut(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiV1::ok(null, __('Signed out.'));
    }

    /**
     * A new token bound to $business (and to $register when signing in on a
     * paired till), with the user as they are in it.
     *
     * @return array<string, mixed>
     */
    public static function session(User $user, Business $business, string $deviceName, TenantContext $tenant, ?Register $register = null): array
    {
        $newToken = $register
            ? PairRegister::tokenFor($user, $business->id, $register, $deviceName)
            : tap($user->createToken($deviceName), fn ($token) => $token->accessToken->forceFill(['business_id' => $business->id])->save());

        return $tenant->run($business, fn (): array => [
            'business_required' => false,
            'token' => $newToken->plainTextToken,
            'business' => BusinessController::present($business, $user),
            'businesses' => BusinessController::listFor($user, $business->id),
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }
}
