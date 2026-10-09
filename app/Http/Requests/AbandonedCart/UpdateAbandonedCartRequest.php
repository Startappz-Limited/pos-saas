<?php

namespace App\Http\Requests\AbandonedCart;

use App\Enums\AbandonedCartStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAbandonedCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Staff can move a cart between abandoned / contacted / lost. `converted`
     * comes only from a real sale and `recovered` only from the website.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in(array_map(fn (AbandonedCartStatus $s) => $s->value, AbandonedCartStatus::manual()))],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
