<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PairRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pair', $this->route('register'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // The app's own id for this installation; a reinstall makes a new one.
            'device_id' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
