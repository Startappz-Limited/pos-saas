# WhatsApp Template Notifications

## Architecture

- **DTO**: `App\Notifications\Messages\WhatsAppTemplateMessage` — encapsulates template name, language, and components
- **Channel**: `App\Notifications\Channels\WhatsAppChannel` — handles both plain text (string) and template (`WhatsAppTemplateMessage`) messages
- **Service**: `App\Services\WhatsAppService` — sends via Meta Cloud API (`sendTextMessage` / `sendTemplateMessage`)
- **Action**: `App\Actions\SendWhatsAppToCustomer` — direct customer messaging (text + template via `executeTemplate`)

## How It Works

1. A notification's `toWhatsApp()` returns either a `string` (text message) or a `WhatsAppTemplateMessage` (template)
2. `WhatsAppChannel::send()` checks the return type and calls the appropriate service method
3. Template messages are logged with `WhatsAppMessageType::Template` in the `whatsapp_messages` table

## WhatsAppTemplateMessage API

```php
(new WhatsAppTemplateMessage('template_name', 'en'))
    ->bodyParameters(['param1', 'param2'])   // Body {{1}}, {{2}}, etc.
    ->buttonUrl(0, $url);                    // Dynamic URL button at index 0
```

## Existing Templates

### `new_order_notification_utility`
- **Used in**: `App\Notifications\NewEcommerceOrderNotification::toWhatsApp()`
- **Category**: Utility
- **Language**: English
- **Body variables** (6):
  - `{{1}}` = Shop name (e.g. "Fitness Center Kenya")
  - `{{2}}` = Order number (e.g. "30237")
  - `{{3}}` = Customer name (e.g. "Matthew M")
  - `{{4}}` = Total with currency (e.g. "KES 6,999.00")
  - `{{5}}` = Item count (e.g. "1")
  - `{{6}}` = Items summary (e.g. "Eco-Flex 216 x1")
- **Button**: "View Invoice" — dynamic URL pointing to signed PDF route
- **Body text registered in Meta**:
  ```
  🛍️ New {{1}} Website Order!

  Order: {{2}}
  Customer: {{3}}
  Total: {{4}}

  📦 Items ({{5}}): {{6}}

  Please review and process this order.
  ```

## Invoice PDF

- **Route**: `GET /orders/{order:uuid}/invoice` (signed, no auth required)
- **Name**: `orders.invoice-pdf`
- **Controller**: `EcommerceOrderController::invoicePdf()`
- **View**: `resources/views/pdf/order-invoice.blade.php`
- **Package**: `barryvdh/laravel-dompdf`
- URLs are generated via `URL::signedRoute('orders.invoice-pdf', $order)` — tamper-proof, no expiry

## Adding a New Template

1. Create the template in Meta Business Manager → WhatsApp → Message Templates
2. In your notification class, return a `WhatsAppTemplateMessage` from `toWhatsApp()`:
   ```php
   public function toWhatsApp(object $notifiable): WhatsAppTemplateMessage
   {
       return (new WhatsAppTemplateMessage('your_template_name', 'en'))
           ->bodyParameters(['value1', 'value2'])
           ->buttonUrl(0, $url);  // optional
   }
   ```
3. The channel handles the rest — no changes needed to `WhatsAppChannel` or `WhatsAppService`

## Plain Text Fallback

If a notification returns a string from `toWhatsApp()`, it sends as a regular text message (e.g. `CustomerWhatsAppNotification`). This only works within the 24-hour messaging window.
