<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Suspending a business (Phase 5, B1): a reason, kept on the business and in
 * the audit log, and an explicit confirmation. Access is the route group's
 * (platform operators only).
 */
class SuspendBusinessRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm.accepted' => __('Confirm that nobody in this business will be able to work in it until it is reactivated.')];
    }
}
