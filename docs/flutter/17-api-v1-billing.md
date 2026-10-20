# `/api/v1` billing

**New in SaaS Phase 4. The shipped app doesn't use these; the flat `/api` endpoints are unchanged.**

`/api/v1` token, owners only (403 otherwise). Reachable while the business is read-only.
Responses `{ "success": bool, "message": string|null, "data": … }`.

| Method and path | Notes |
|---|---|
| `GET /billing` | `plan`, `status` (`trialing`, `active`, `lapsed`, `unmetered`), `ends_at`, `grace_ends_at`, `read_only_reason`, `shops_paid`, `shops_used`, `monthly_total`, `ai_credits`, `ai_min_topup`, `online_payment` |
| `POST /billing/pay` | `phone`, optional `months` (1–12). Starts an STK push; 422 when online payment isn't available yet |
| `POST /billing/ai-topup` | `phone`, `amount` (KES, minimum 500) |
| `POST /billing/payments/{uuid}/check` | Asks M-Pesa about a pending payment: `status` is `pending`, `confirmed` or `failed` |
| `POST /billing/shops` | `shop_count`: the shops paid for. Lowering it below the shops in use makes the business read-only (`read_only_reason: shops_over_quota`) |

## Errors the app should handle on any write

| Status | `code` | Meaning |
|---|---|---|
| 402 | `subscription_inactive` | The business is read-only (`reason`: `subscription_inactive` or `shops_over_quota`). Reading still works |
| 402 | `plan_limit_reached` | Adding past the plan (`limit`, `allowed`) |
| 402 | `feature_unavailable` | The plan doesn't include the feature (`feature`) |
| 402 | `ai_credit_exhausted` | Out of AI credit; the request is saved and runs after a top-up |
