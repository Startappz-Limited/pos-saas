<?php

namespace App\Http\Requests\AbandonedCart;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbandonedCartNoteRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
