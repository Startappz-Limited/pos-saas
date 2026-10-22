<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Switching a role on or off (Phase 5). Switching it off needs an explicit
 * confirmation that the people holding it won't be able to do anything with
 * it (set by the dialog, or a checkbox without JavaScript); switching it back
 * on needs none.
 */
class SetRoleActiveRequest extends FormRequest
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
            'active' => ['required', 'boolean'],
            'confirm' => self::confirmRules($this->route('role'), fn (): bool => ! $this->boolean('active')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::confirmMessages();
    }

    /**
     * The confirmation rule shared with the edit form: required only when an
     * active role is being switched off.
     *
     * @param  \Closure(): bool  $switchingOff
     * @return array<int, mixed>
     */
    public static function confirmRules(Role $role, \Closure $switchingOff): array
    {
        return [Rule::excludeIf(fn (): bool => ! $role->is_active || ! $switchingOff()), 'accepted'];
    }

    /**
     * @return array<string, string>
     */
    public static function confirmMessages(): array
    {
        return [
            'confirm.accepted' => __('Confirm that the people with this role won\'t be able to do anything in the system with it until it\'s switched back on.'),
            'confirm.required' => __('Confirm that the people with this role won\'t be able to do anything in the system with it until it\'s switched back on.'),
        ];
    }
}
