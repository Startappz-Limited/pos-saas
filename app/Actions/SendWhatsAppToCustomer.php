<?php

namespace App\Actions;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Customer;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;

class SendWhatsAppToCustomer
{
    public function __construct(protected WhatsAppService $whatsAppService) {}

    /**
     * Send a WhatsApp text message to a customer.
     */
    public function execute(Customer $customer, string $content, int $sentBy, ?int $shopId = null): WhatsAppMessage
    {
        if (empty($customer->phone)) {
            throw new \InvalidArgumentException('Customer does not have a phone number');
        }

        return DB::transaction(function () use ($customer, $content, $sentBy, $shopId) {
            $shopId = $shopId ?? $customer->shop_id;

            $whatsAppMessage = WhatsAppMessage::create([
                'shop_id' => $shopId,
                'customer_id' => $customer->id,
                'sent_by' => $sentBy,
                'phone' => $customer->phone,
                'direction' => WhatsAppMessageDirection::Outbound,
                'message_type' => WhatsAppMessageType::Text,
                'content' => $content,
                'status' => WhatsAppMessageStatus::Pending,
            ]);

            $result = $this->whatsAppService->sendTextMessage($customer->phone, $content);

            if ($result['success']) {
                $whatsappMessageId = $result['response']['messages'][0]['id'] ?? null;
                $whatsAppMessage->markAsSent($whatsappMessageId);
            } else {
                $whatsAppMessage->markAsFailed($result['message']);
            }

            return $whatsAppMessage->fresh();
        });
    }

    /**
     * Send a WhatsApp template message to a customer.
     *
     * @param  array<string, mixed>  $components
     */
    public function executeTemplate(Customer $customer, string $templateName, int $sentBy, string $languageCode = 'en', array $components = [], ?int $shopId = null): WhatsAppMessage
    {
        if (empty($customer->phone)) {
            throw new \InvalidArgumentException('Customer does not have a phone number');
        }

        return DB::transaction(function () use ($customer, $templateName, $sentBy, $languageCode, $components, $shopId) {
            $shopId = $shopId ?? $customer->shop_id;

            $whatsAppMessage = WhatsAppMessage::create([
                'shop_id' => $shopId,
                'customer_id' => $customer->id,
                'sent_by' => $sentBy,
                'phone' => $customer->phone,
                'direction' => WhatsAppMessageDirection::Outbound,
                'message_type' => WhatsAppMessageType::Template,
                'content' => "Template: {$templateName}",
                'template_name' => $templateName,
                'status' => WhatsAppMessageStatus::Pending,
            ]);

            $result = $this->whatsAppService->sendTemplateMessage(
                $customer->phone,
                $templateName,
                $languageCode,
                $components
            );

            if ($result['success']) {
                $whatsappMessageId = $result['response']['messages'][0]['id'] ?? null;
                $whatsAppMessage->markAsSent($whatsappMessageId);
            } else {
                $whatsAppMessage->markAsFailed($result['message']);
            }

            return $whatsAppMessage->fresh();
        });
    }
}
