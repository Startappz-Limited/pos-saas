<?php

namespace App\Http\Requests\AbandonedCart;

use Illuminate\Foundation\Http\FormRequest;

class OptOutAbandonedCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
