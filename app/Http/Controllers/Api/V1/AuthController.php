<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SignInRequest;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            return response()->json([
                'success' => false,
                'message' => __('You are not a member of any active business.'),
            ], 403);
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
            return response()->json([
                'success' => true,
                'message' => __('Choose a business, then sign in again with it.'),
                'data' => [
                    'business_required' => true,
                    'token' => null,
                    'businesses' => BusinessController::listFor($user, null),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('Signed in.'),
            'data' => self::session($user, $business, $validated['device_name'], $this->tenant),
        ]);
    }

    public function signOut(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => __('Signed out.'), 'data' => null]);
    }

    /**
     * A new token bound to $business, with the user as they are in it.
     *
     * @return array<string, mixed>
     */
    public static function session(User $user, Business $business, string $deviceName, TenantContext $tenant): array
    {
        $newToken = $user->createToken($deviceName);
        $newToken->accessToken->forceFill(['business_id' => $business->id])->save();

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
