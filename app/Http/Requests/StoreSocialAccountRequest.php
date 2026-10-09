<?php

namespace App\Http\Requests;

use App\Enums\SocialPlatform;
use App\Models\Shop;
use App\Rules\ExistsForViewer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialAccountRequest extends FormRequest
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
            'shop_id' => ['required', 'integer', new ExistsForViewer(Shop::class)],
            'platform' => ['required', Rule::enum(SocialPlatform::class)],
            'account_name' => ['required', 'string', 'max:255'],
            'external_account_id' => ['nullable', 'string', 'max:100'],
            'username' => ['nullable', 'string', 'max:100'],
            'avatar_url' => ['nullable', 'url', 'max:500'],
            'access_token' => ['required', 'string', 'max:5000'],
            'refresh_token' => ['nullable', 'string', 'max:5000'],
            'token_expires_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
