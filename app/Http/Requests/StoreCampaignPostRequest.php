<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\SocialAccount;
use App\Rules\ExistsForViewer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignPostRequest extends FormRequest
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
            'social_account_id' => ['required', 'integer', new ExistsForViewer(SocialAccount::class)],
            'product_id' => ['nullable', 'integer', new ExistsForViewer(Product::class)],
            'caption' => ['required', 'string', 'max:5000'],
            'hashtags' => ['nullable', 'array'],
            'hashtags.*' => ['string', 'max:50'],
            'call_to_action' => ['nullable', 'string', 'max:100'],
            'media_urls' => ['nullable', 'array'],
            'media_urls.*' => ['url', 'max:1000'],
            'landing_url' => ['nullable', 'url', 'max:1000'],
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
            'ai_generated' => ['nullable', 'boolean'],
            'ai_provider' => ['nullable', 'string', 'max:50'],
            'ai_model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
