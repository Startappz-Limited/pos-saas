<?php

namespace App\Http\Requests;

use App\Models\ProductVariation;
use App\Models\StockIntake;
use App\Support\Stock\IntakeQuantities;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStockIntakeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('stock-intake.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'intake_date' => ['sometimes', 'required', 'date'],
            // As on create: the ledger holds three decimals, and the base unit's precision is checked in withValidator().
            'quantity_received' => ['sometimes', 'required', 'numeric', 'min:0.001'],
            'quantity_accepted' => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_rejected' => ['sometimes', 'required', 'numeric', 'min:0'],
            'quality_notes' => ['nullable', 'string'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'bin_location' => ['nullable', 'string', 'max:255'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * The same quantity checks as creating an intake (IntakeQuantities), on the figures the intake will hold
     * after this edit: a field the edit leaves out keeps its stored value.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $intake = $this->route('stock_intake');

            if (! $intake instanceof StockIntake) {
                return;
            }

            $values = array_merge(
                $intake->only(IntakeQuantities::FIELDS),
                $this->only(array_filter(IntakeQuantities::FIELDS, fn (string $field): bool => $this->has($field)))
            );

            foreach (IntakeQuantities::errors($this->variationOf($intake), $values) as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }

    private function variationOf(StockIntake $intake): ?ProductVariation
    {
        return $intake->product_variation_id !== null
            ? ProductVariation::query()->withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->find($intake->product_variation_id)
            : $intake->product?->defaultVariation()->first();
    }
}
