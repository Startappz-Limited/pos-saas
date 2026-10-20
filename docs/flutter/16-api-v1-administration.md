# `/api/v1` business administration

**New in SaaS stage 3. The shipped app doesn't use these; the flat `/api` endpoints are unchanged.**

Every endpoint needs a `/api/v1` token (bound to one business, see `15-api-v1-sign-in.md`) and answers
`{ "success": bool, "message": string|null, "data": … }`. Validation errors are 422 with `errors.<field>[0]`;
refusals are 403; another business's records are 404.

| Method and path | Who | Notes |
|---|---|---|
| `GET /business/settings` | `settings.update` | name, currency (and the allowed `currencies`), timezone, invoice prefix, receipt header and footer, `logo_url` |
| `POST /business/settings` | `settings.update` | multipart; same fields; `logo` (png/jpg/webp, 2 MB) or `remove_logo=1` |
| `GET /business/onboarding` | any member | `steps[{key,label,done}]`, `complete` |
| `GET /business/integrations` | `settings.update` | `sms` and `whatsapp_cloud`: `{connected}`. Never the values |
| `PUT /business/integrations/{sms\|whatsapp_cloud}` | `settings.update` | `api_url` (https), `api_token`, `sender_id` or `phone_number_id`. Write-only; blank fields keep what is saved |
| `DELETE /business/integrations/{type}` | `settings.update` | disconnect |
| `PUT /shops/{shop}/mpesa` | `shops.update` on the shop | `shortcode`, `type` (`till`/`paybill`), `consumer_key`, `consumer_secret`, `passkey`. Saved only after a test query to M-Pesa succeeds; 422 on `shortcode` otherwise. One shop per number on the platform |
| `POST /products/import` | `products.create` | multipart: `shop` (uuid), `file` (CSV, header row with `name`, `selling_price`; optional `sku`, `barcode`, `category`, `cost_price`, `wholesale_price`, `stock`, `reorder_level`, `unit`). All or nothing: 422 with `errors.file[]` lines |
| `POST /business/exports` | owners | builds the export; owners are emailed the link |
| `POST /business/handover/code` | founding owner | `owner` (uuid of an existing co-owner); emails a code |
| `POST /business/handover` | founding owner | `code`; ownership passes, the caller stays a co-owner |
| `POST /business/closure/code` | owners | emails a code |
| `POST /business/closure` | owners | `code`; the business closes (its token stops working), data is erased after the grace period. Cancelling a closure is a web action |

Rate limits follow the flat API (`writes`, `destructive`).
