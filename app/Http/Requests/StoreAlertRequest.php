<?php

namespace App\Http\Requests;

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Shop;
use App\Rules\ExistsForViewer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Alert::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(AlertType::class)],
            'severity' => ['required', Rule::enum(AlertSeverity::class)],
            'category' => ['required', Rule::enum(AlertCategory::class)],
            // Null shop_id is a system-wide alert, not an omission.
            'shop_id' => ['nullable', 'integer', new ExistsForViewer(Shop::class)],
            'scheduled_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:scheduled_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expires_at.after' => __('The expiry must be later than the scheduled time.'),
        ];
    }

    /**
     * Block assigning an alert to a shop the user cannot access.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $shopId = $this->input('shop_id');

            if ($shopId && ! $this->user()->canAccessShop((int) $shopId)) {
                $validator->errors()->add('shop_id', __('You do not have access to that shop.'));
            }
        });
    }
}
