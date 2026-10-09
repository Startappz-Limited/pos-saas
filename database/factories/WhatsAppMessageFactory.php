<?php

namespace Database\Factories;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppMessage>
 */
class WhatsAppMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'shop_id' => Shop::factory(),
            'customer_id' => Customer::factory(),
            'sent_by' => User::factory(),
            'phone' => fake()->numerify('+2547########'),
            'direction' => WhatsAppMessageDirection::Outbound,
            'message_type' => WhatsAppMessageType::Text,
            'content' => fake()->sentence(),
            'status' => WhatsAppMessageStatus::Pending,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WhatsAppMessageStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WhatsAppMessageStatus::Delivered,
            'sent_at' => now()->subMinutes(5),
            'delivered_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WhatsAppMessageStatus::Failed,
            'error_message' => 'API request failed',
            'failed_at' => now(),
        ]);
    }

    public function inbound(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => WhatsAppMessageDirection::Inbound,
            'sent_by' => null,
        ]);
    }

    public function template(string $templateName = 'hello_world'): static
    {
        return $this->state(fn (array $attributes) => [
            'message_type' => WhatsAppMessageType::Template,
            'template_name' => $templateName,
        ]);
    }

    public function forShop(Shop $shop): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $shop->id,
        ]);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id,
            'phone' => $customer->phone,
        ]);
    }
}
