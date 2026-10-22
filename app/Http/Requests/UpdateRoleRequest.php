<?php

namespace App\Http\Requests;

use App\Rules\RoleName;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Editing one of the current business's roles (Phase 5): the same name rule
 * as creating, and only permissions the editor may give.
 */
class UpdateRoleRequest extends FormRequest
{
    /**
     * The policy first, so a role the user may not change is refused (403 or
     * 404) before its fields are judged.
     */
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
            'name' => StoreRoleRequest::nameRules(new RoleName($this->route('role'))),
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
