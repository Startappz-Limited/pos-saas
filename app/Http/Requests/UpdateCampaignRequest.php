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

class UpdateCampaignRequest extends FormRequest
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
            'shop_id' => ['sometimes', 'integer', new ExistsForViewer(Shop::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'campaign_type' => ['sometimes', Rule::enum(CampaignType::class)],
            'channel' => ['sometimes', Rule::enum(MarketingChannel::class)],
            'budget' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
            'target_conversions' => ['nullable', 'integer', 'min:0'],
            'target_reach' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::enum(CampaignStatus::class)],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'default_landing_url' => ['nullable', 'url', 'max:500'],
            'auto_post_enabled' => ['nullable', 'boolean'],
            'ai_assist_enabled' => ['nullable', 'boolean'],
            'ai_settings' => ['nullable', 'array'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', new ExistsForViewer(Product::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
