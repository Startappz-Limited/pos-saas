<?php

namespace App\Http\Requests\AbandonedCart;

use Illuminate\Foundation\Http\FormRequest;

class SendAbandonedCartWhatsAppRequest extends FormRequest
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
            // Optional custom text; the recovery link is appended when missing.
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
