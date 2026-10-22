<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Changing only a role's permissions (Phase 5): the same policy and
 * no-escalation rule as editing the role.
 */
class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('role'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreRoleRequest::sharedMessages();
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => StoreRoleRequest::refuseUngivable($validator, $this)];
    }
}
