<?php

namespace App\Http\Requests;

use App\Support\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized in the controller (settings.update).
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', Rule::in(array_keys(Currency::options()))],
            'timezone' => ['required', 'timezone:all'],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'invoice_prefix' => ['nullable', 'string', 'min:2', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('invoice_prefix')) {
            $this->merge(['invoice_prefix' => strtoupper($this->string('invoice_prefix')->toString())]);
        }
    }
}
