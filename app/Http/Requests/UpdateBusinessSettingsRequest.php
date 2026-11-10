<?php

namespace App\Http\Requests;

use App\Support\Currency;
use App\Support\Sales\VoidWindow;
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
            // Stock adjustments at or above it need a second person (stage 10, step 1g).
            'adjustment_approval_threshold' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            // How long a sale can be voided (stage 10, step 2).
            'void_window' => ['nullable', Rule::in([VoidWindow::SESSION, VoidWindow::END_OF_DAY, 'minutes', VoidWindow::NONE])],
            'void_window_minutes' => ['nullable', 'required_if:void_window,minutes', 'integer', 'min:1', 'max:'.VoidWindow::MAX_MINUTES],
            // The shop this business opens into (stage 10a, step 3).
            'default_shop_id' => ['nullable', 'integer', 'exists:shops,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('invoice_prefix')) {
            $this->merge(['invoice_prefix' => strtoupper($this->string('invoice_prefix')->toString())]);
        }
    }
}
