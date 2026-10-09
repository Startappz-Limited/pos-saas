<?php

namespace App\Http\Requests;

use App\Enums\CreditTransactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditAccountStatementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::enum(CreditTransactionType::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'date_from' => 'from date',
            'date_to' => 'to date',
            'type' => 'transaction type',
        ];
    }
}
