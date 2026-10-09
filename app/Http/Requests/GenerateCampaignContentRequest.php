<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Rules\ExistsForViewer;
use Illuminate\Foundation\Http\FormRequest;

class GenerateCampaignContentRequest extends FormRequest
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
            'product_id' => ['nullable', 'integer', new ExistsForViewer(Product::class)],
            'platform' => ['nullable', 'string', 'max:50'],
            'provider' => ['nullable', 'string', 'max:50'],
            'tone' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:50'],
            'extra_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
