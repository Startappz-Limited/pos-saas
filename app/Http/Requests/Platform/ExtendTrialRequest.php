<?php

namespace App\Http\Requests\Platform;

use App\Actions\Platform\ExtendTrial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Extending a business's trial (Phase 5, B1): by 1 to 90 days.
 */
class ExtendTrialRequest extends FormRequest
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
            'days' => ['required', 'integer', 'min:1', 'max:'.ExtendTrial::MAX_DAYS],
        ];
    }
}
