<?php

namespace App\Http\Requests;

use App\Actions\Role\SyncRolePermissions;
use App\Models\Role;
use App\Rules\RoleName;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Creating a role in the current business (Phase 5): the shared name rule,
 * and only permissions the creator may give.
 */
class StoreRoleRequest extends FormRequest
{
    /**
     * The policy first, so a role the user may not change is refused (403 or
     * 404) before its fields are judged.
     */
    public function authorize(): Response
    {
        return Gate::inspect('create', Role::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => self::nameRules(new RoleName),
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The name rules create and edit share.
     *
     * @return array<int, mixed>
     */
    public static function nameRules(RoleName $unique): array
    {
        return ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', $unique];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::sharedMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function sharedMessages(): array
    {
        return [
            'name.required' => __('Role name is required.'),
            'name.regex' => __('Role name must only contain lowercase letters, numbers, and hyphens.'),
            'permissions.array' => __('Permissions must be a list.'),
            'permissions.*.exists' => __('One or more selected permissions are invalid.'),
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => self::refuseUngivable($validator, $this)];
    }

    /**
     * Refuses permissions the signed-in user doesn't hold (owners excepted).
     */
    public static function refuseUngivable(Validator $validator, FormRequest $request): void
    {
        if ($validator->errors()->has('permissions.*') || ! is_array($request->input('permissions'))) {
            return;
        }

        if (app(SyncRolePermissions::class)->refusedIds($request->user(), $request->input('permissions')) !== []) {
            $validator->errors()->add('permissions', __('You can only give permissions you hold.'));
        }
    }
}
