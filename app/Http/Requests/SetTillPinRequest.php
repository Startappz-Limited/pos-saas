<?php

namespace App\Http\Requests;

use App\Rules\ValidTillPin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A person setting their own till PIN (Phase 7), confirmed with their
 * password and typed twice.
 */
class SetTillPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'pin' => ['required', 'string', new ValidTillPin, 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['pin.confirmed' => __('The two PINs don\'t match.')];
    }
}
