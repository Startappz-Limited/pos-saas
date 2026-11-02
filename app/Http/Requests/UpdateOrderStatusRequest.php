<?php

namespace App\Http\Requests;

use App\Enums\EcommerceOrderStatus;
use App\Models\EcommerceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * The same policy check as the controller's, made here so a caller who
     * may not do this is refused with 403 before their input is validated.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof EcommerceOrder && ($this->user()?->can('updateStatus', $order) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(EcommerceOrderStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Please select a status.',
        ];
    }
}
