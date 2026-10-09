<?php

namespace App\Http\Requests;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\MarketingChannel;
use App\Models\Product;
use App\Models\Shop;
use App\Rules\ExistsForViewer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via policy.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', new ExistsForViewer(Shop::class)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'campaign_type' => ['required', Rule::enum(CampaignType::class)],
            'channel' => ['required', Rule::enum(MarketingChannel::class)],
            'budget' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
            'target_conversions' => ['nullable', 'integer', 'min:0'],
            'target_reach' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(CampaignStatus::class)],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'default_landing_url' => ['nullable', 'url', 'max:500'],

            // Social / AI
            'auto_post_enabled' => ['nullable', 'boolean'],
            'ai_assist_enabled' => ['nullable', 'boolean'],
            'ai_settings' => ['nullable', 'array'],
            'ai_settings.provider' => ['nullable', 'string', 'max:50'],
            'ai_settings.tone' => ['nullable', 'string', 'max:100'],
            'ai_settings.language' => ['nullable', 'string', 'max:50'],

            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', new ExistsForViewer(Product::class)],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
