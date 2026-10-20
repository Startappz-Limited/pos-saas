<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveShopMpesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized in the controller (ShopPolicy::update).
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'shortcode' => ['required', 'string', 'regex:/^\d[\d\s]{3,12}$/'],
            'type' => ['required', 'in:till,paybill'],
            'consumer_key' => ['nullable', 'string', 'max:255'],
            'consumer_secret' => ['nullable', 'string', 'max:255'],
            'passkey' => ['nullable', 'string', 'max:255'],
        ];
    }
}
