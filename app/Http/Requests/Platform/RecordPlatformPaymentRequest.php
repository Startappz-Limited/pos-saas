<?php

namespace App\Http\Requests\Platform;

use App\Models\PlatformPayment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Recording a payment received by hand (Phase 5, B2; decision 4.6: until
 * M-Pesa go-live): a subscription payment or an AI top-up. An M-Pesa receipt
 * number can be recorded once on the whole platform. Access is the route
 * group's.
 */
class RecordPlatformPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::in([PlatformPayment::SUBSCRIPTION, PlatformPayment::AI_TOPUP])],
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'receipt' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9]+$/', function (string $attribute, mixed $value, Closure $fail): void {
                // Platform-wide on purpose (the column is unique across businesses).
                if (DB::table('platform_payments')->where('mpesa_receipt', $value)->exists()) {
                    $fail(__('This M-Pesa receipt has already been recorded.'));
                }
            }],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $minimum = (int) config('billing.ai_min_topup', 500);

            if ($this->input('purpose') === PlatformPayment::AI_TOPUP && is_numeric($this->input('amount')) && (float) $this->input('amount') < $minimum) {
                $validator->errors()->add('amount', __('The minimum AI top-up is KES :min.', ['min' => $minimum]));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['receipt.regex' => __('An M-Pesa receipt has only letters and numbers.')];
    }
}
