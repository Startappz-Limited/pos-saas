<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A console action that changes businesses' roles (Phase 5, B6: "Apply to
 * all copies", resetting a business's copy): an explicit confirmation, set by
 * the dialog or the checkbox without JavaScript. Access is the route group's.
 */
class ConfirmPlatformActionRequest extends FormRequest
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
        return ['confirm' => ['accepted']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm.accepted' => __('Confirm the change first.')];
    }
}
