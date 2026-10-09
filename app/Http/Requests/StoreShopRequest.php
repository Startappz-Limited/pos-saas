<?php

namespace App\Http\Requests;

use App\Enums\ShopStatus;
use App\Enums\TaxClass;
use App\Rules\KraPin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShopRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:shops,code', 'regex:/^[A-Z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['nullable', Rule::enum(ShopStatus::class)],
            'tax_pin' => ['nullable', 'string', 'max:32', new KraPin],
            'vat_registered' => ['nullable', 'boolean'],
            'settings.tax' => ['nullable', 'array'],
            'settings.tax.prices_include_tax' => ['nullable', 'boolean'],
            'settings.tax.default_class' => ['nullable', Rule::enum(TaxClass::class)],
            'manager_id' => ['nullable', 'exists:users,id'],
            'settings' => ['nullable', 'array'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],

            // E-Commerce Integration Fields
            'integrations' => ['nullable', 'array'],

            // WooCommerce Integration
            'integrations.woocommerce' => ['nullable', 'array'],
            'integrations.woocommerce.enabled' => ['nullable', 'boolean'],
            'integrations.woocommerce.store_url' => ['nullable', 'string', 'url', 'max:255'],
            'integrations.woocommerce.consumer_key' => ['nullable', 'string', 'max:255'],
            'integrations.woocommerce.consumer_secret' => ['nullable', 'string', 'max:255'],

            // Shopify Integration
            'integrations.shopify' => ['nullable', 'array'],
            'integrations.shopify.enabled' => ['nullable', 'boolean'],
            'integrations.shopify.shop_domain' => ['nullable', 'string', 'max:255'],
            'integrations.shopify.access_token' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The shop name is required.',
            'code.required' => 'The shop code is required.',
            'code.unique' => 'This shop code is already in use.',
            'code.regex' => 'The shop code must contain only uppercase letters, numbers, and hyphens.',
            'email.email' => 'Please provide a valid email address.',
            'manager_id.exists' => 'The selected manager does not exist.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',

            // Integration messages
            'integrations.woocommerce.store_url.url' => 'Please provide a valid WooCommerce store URL.',
            'integrations.shopify.shop_domain.max' => 'The Shopify domain is too long.',
        ];
    }
}
