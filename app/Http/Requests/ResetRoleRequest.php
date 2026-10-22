<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Resetting a copy of a role template to default (Phase 5): the policy, and
 * an explicit confirmation (set by the dialog, or the checkbox without
 * JavaScript).
 */
class ResetRoleRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('reset', $this->route('role'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['confirm' => ['accepted']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm.accepted' => __('Confirm that you want to reset this role to default.')];
    }
}
