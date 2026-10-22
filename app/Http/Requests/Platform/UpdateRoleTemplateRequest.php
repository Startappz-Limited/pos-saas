<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editing a shared role template's permissions (Phase 5, B6). The owner role
 * changes for every business's owners at once, so editing it needs an
 * explicit confirmation. Access is the route group's.
 */
class UpdateRoleTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
            'confirm' => [Rule::excludeIf(fn (): bool => ! $this->route('role')->isOwnerRole()), 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm.accepted' => __('Confirm that this changes what every business\'s owners can do, at once.')];
    }
}
