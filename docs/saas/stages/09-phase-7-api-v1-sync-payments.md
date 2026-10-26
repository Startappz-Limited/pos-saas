# Phase 7: `/api/v1` for Flutter, Offline Sync, Till Payments

> **Source:** saas-plan.md section 5 (Phase 6), sections 4.4, 4.5, 4.8, 4.9, section 1 (External
> integrations), section 8 (Open questions 2 and 6)
> **Branch:** `saas/phase-7-api-sync-payments` · **Depends on:** Phase 2 (can run alongside Phases 4
> and 5)

---

## Goal

A complete, documented API that a Flutter POS can trade on, online or offline, in any of the user's
businesses.

## Before you start

| Open question | Why it matters here |
|---------------|---------------------|
| **2. How does the private M-Pesa package reach a shop's own Till or Paybill?** | **Decided 12 October 2026:** the package acts as an API; each shop has its own account details, and payments are confirmed by querying the API and comparing against what it returns. The shop's account details are saved in Phase 3. |
| **6. eTIMS deferral** | Not blocking. Only the `FiscalProvider` seam is built here; if launching without eTIMS isn't acceptable, this work moves forward. |

## Relevant current state

- **API:** a flat Sanctum API with 118 routes (`{success, data}`) used by the existing Flutter app
  (`docs/flutter/`); `mobile:contract-diff` compares its `api_endpoints.dart` with the routes. No
  versioned API exists.
- **SMS** is broken: `SmsChannel::isConfigured()` reads `env()`; `SMSservice` uses `App\Models\Setting`,
  which doesn't exist; global cache keys.
- **M-Pesa (Daraja), KRA eTIMS, printers:** no integration.
- **Sales:** no idempotency key.

## Decisions that apply

- **4.4:** `/api/v1` tokens are always bound to one business; switching issues another token.
  *Confirmed.*
- **4.5 Offline support and sync** (confirmed):
  - Offline trading per business with two-way sync; the app keeps a separate local store per business,
    each with its own token and register.
  - **Pull:** catalogue, prices, per-shop stock, customers, staff and settings changed since a cursor,
    including deletions. **Push:** an outbox of sales, payments, new customers and cash-session events,
    each with a `client_uuid`; idempotent, applied in order, in batches, with a result per item.
  - **Editable offline:** sales, sale payments, customers, cash sessions. **Read-only offline:**
    catalogue and stock.
  - **Conflict rules:** the server wins for master data; offline sales are always accepted and stock may
    go negative (flagged); prices are taken from the sale as recorded; original timestamps are kept.
  - **Sync at least once a day:** after 24 hours without a sync (configurable), the shop owner is warned
    (dashboard banner and a notification naming the register and its last sync time) and the API includes
    a `sync_overdue` flag. Nothing is blocked.
  - **Soft deletes:** a soft-deleted record goes into the deletions log like a hard delete.
- **4.8 API strategy:** `/api/v1` alongside the unchanged flat `/api`, with `{success, message, data,
  meta, code}` responses, an error-code registry (`business_closing`, `subscription_inactive`,
  `plan_upgrade_required`, `shop_not_linked`, `account_inactive`, `sync_overdue`…), business-bound tokens,
  sync endpoints, idempotency keys, an OpenAPI document and contract tests. *Confirmed.*
- **4.9 Edge-case rules** (confirmed):
  - **Invoice numbers on an offline till:** per-register series
    `INV-{shop code}-{register code}-{year}-{sequence}`; the server checks on sync that each number is
    unique and the series has no gaps or repeats, and flags any that do; numbers unique per business.
  - **Sales made before a lapse, synced after it:** accepted if recorded before the lapse; later ones
    refused with `subscription_inactive` and kept on the device; implausible recorded times accepted but
    flagged. **Corrected (review):** a sale also has to fall within an offline trading lease the server
    issued.
  - **M-Pesa payments while the till is offline:** STK Push goes through the server; the server confirms
    the payment by querying the M-Pesa API with the shop's account details; the till marks the payment **pending** and gets the result on its next
    sync. A till with no connection can't send an STK Push: the customer pays the shop's Till or Paybill
    directly, the cashier enters the M-Pesa code, and on sync the server queries the M-Pesa
    API with the shop's account details and matches what it returns; no match stays pending and is
    flagged. Buy Goods tills carry no
    account reference, so matching is by M-Pesa code and amount.
  - **Duplicate customers created offline:** not merged automatically; a pushed customer whose normalized
    phone matches an existing customer is saved and flagged; owner or manager merges or keeps both from a
    review screen. Customers belong to the business (Open question 3), so the check runs across the whole
    business.
- **Assumption 13:** the till is connected when it sends an STK Push; only the confirmation can arrive
  after it goes offline.

### Decisions taken during Phase 7 (26 October 2026)

- **Scope: till first.** `/api/v1` covers what a till needs to trade:
  - register pairing;
  - sync pull and push;
  - M-Pesa;
  - receipts.

  Reports, purchasing, suppliers, expenses and returns stay on the flat API and move to v1 in a later stage. "The
  same features as the web" is therefore partly done in this phase.
- **M-Pesa is built on the contract.** `MpesaGateway` gains STK Push and queries, tested with the fake gateway. The
  null gateway stays bound until the private package is plugged in.
- **OpenAPI:** `dedoc/scramble` documents `/api/v1` at `/docs/api`. It is open in local and to platform operators
  only.
- **Registers (tills):**
  - **Pairing:** an owner or manager pairs a device to a register (`registers.pair`).
  - **Register-bound tokens:** the pairing user's token, and a cashier's who signs in on a paired till, are bound to
    the register and reach that register's shop only.
  - **Re-pairing** to another device gives the register a new, never-reused code and signs the old device out.
  - **One till per shop for now:** each shop has only its default register. Adding tills (within the plan's
    registers-per-shop limit) is a follow-up, needed before two tills can trade in one shop.
- **Offline trading lease:**
  - `trade_until` is now plus 3 days (`SYNC_LEASE_DAYS`), never past the subscription's paid-up date.
  - A lapsed business gets no offline time.
  - Leases are signed and recorded.
- **Offline sign-in with a till PIN:**
  - **The PIN:** one per person per business, exactly 4 digits. Repeats (1111), runs (1234, 4321) and common PINs
    are refused.
  - **Setting it:** each person sets their own with their password, on the profile page or
    `PUT /api/v1/me/till-pin`.
  - **Resetting and unlocking:** whoever may edit a person (`users.update`) can reset or unlock their PIN, from the
    person's page or the API.
  - **Storage:** only a bcrypt hash is stored. The till receives it with the staff list to check PINs offline.
  - **Lockout, applied by the till:**
    - after **4 wrong PINs** the person waits **15 minutes**;
    - each further lockout that day **doubles** the wait;
    - after **3 lockouts in a day** only someone holding `users.update` can unlock them, on the till.
    - The rules reach the till in `settings.till_pin`, so they can change without an app release.
  - **Server lock:** the server keeps the lock that needs a manager (`till_pins.locked_at`), so the person's other
    tills lock them too, until an unlock or a new PIN.
  - **Audit:** every PIN sign-in, failure, lockout and unlock is pushed by the till and audited with the person, the
    till and the time it happened. Setting, changing and resetting a PIN are audited too, without the hash.
  - **Flutter must:**
    - keep the staff list (with the PIN hashes) in the device's encrypted storage (Android Keystore / iOS Keychain);
    - apply the lockout;
    - push every sign-in event.

    With 4-digit PINs, a copied hash can be guessed in under an hour, so the encrypted storage is what protects a
    stolen till.
- **Product images** reach the till as full URLs (`image_url`).

## Work

### API foundation
- [x] `/api/v1` with an OpenAPI document and the error-code registry (`ApiErrorCode`; every v1 response is
      `{success, message, data, meta, code}`). "The same features as the web": the till's features only, per the
      scope decision above.
- [x] Rate limits per business on both APIs (a cap per business beside the one per user).
- [x] `mobile:contract-diff` extended to cover `/api/v1` (reported separately from the flat API).

### Registers and offline trading
- [x] **Register pairing:** a device is paired to a shop's register and receives a business-bound token
      and the last invoice number the server has for it. Re-pairing after a reinstall issues a new
      register code (4.5).
- [x] **Offline trading lease:** every sync returns a server-signed "trade until" time; the app stops
      taking sales after it, and the server accepts only sales inside a lease it issued (4.5).
- [x] **Offline sign-in:** a till PIN per person per business, with the lockout and audit above.
- [ ] **Adding tills** to a shop (follow-up; each shop has only its default register today).

### Sync
- [x] `GET /api/v1/sync/pull?since=` and `POST /api/v1/sync/push` (batched, idempotent by `client_uuid`,
      applied in order, results per item). Push items: `customer`, `sale`, `sale_payment`, `cash_session`,
      `till_sign_in`.
- [ ] Last-sync tracking; the **24-hour owner warning** and the `sync_overdue` flag.

### Till payments
- [ ] **M-Pesa STK Push at the till** through the private package, using the shop's own Till or Paybill.
      The server confirms the payment by querying the M-Pesa API with the shop's account details and
      marks the right sale paid, matched by shop number and request id. A till that has gone offline shows the payment as pending and receives the result on its next sync.
- [ ] **Offline M-Pesa entry:** a cashier on a till with no connection enters the M-Pesa code; on sync
      the server queries the M-Pesa API with the shop's account details and matches by code and amount,
      or flags it for review.

### Offline rules (4.9)
- [ ] Per-register invoice series checked on sync.
- [ ] Sales recorded before a lapse and inside an issued lease accepted after it.
- [ ] Possible duplicate customers flagged, with a review screen to merge or keep them. (Flagged in the push
      result since step 6; the review screen is step 8.)

### Receipts, SMS, fiscal
- [ ] Structured receipt JSON for ESC/POS printing.
- [x] Product images as full URLs in the pull.
- [ ] **SMS fix, keeping the current SMS service:** replace the missing `App\Models\Setting` with the
      tenant's SMS settings (from `business_integrations`), read configuration through `config()` instead
      of `env()`, and key any cache by business.
- [ ] The `FiscalProvider` seam (a null provider until eTIMS).

## Schema

- [ ] `payment_requests` (STK requests and the API's results per sale).
- [ ] The Phase 1 sync columns used (`client_uuid`, `updated_at` indexes, `sync_deletions`, register
      last-sync time and last invoice number). Used so far: `client_uuid` on sales, payments, customers and cash
      sessions; `sync_deletions`; the register's last invoice number. Last-sync time is step 7.
- [x] Added: `register_pairings`, `register_leases`, `till_pins`, `sync_push_receipts`,
      `personal_access_tokens.register_id`, `registers.is_default`.

## Pending decisions (Proposed, review)

- **Offline trading lease (4.5).** Proposed: the device clock can be changed, so each sync returns a
  server-signed "trade until" time (the subscription's paid-up date, capped at a few days ahead). The app
  stops taking sales after it, and the server accepts a pushed sale only if it falls within a lease it
  issued.
- **Reinstalls (4.5).** Proposed: pairing a register returns the last invoice number the server has seen
  for it, and re-pairing a device after a reinstall issues a **new register code**, so numbers can't
  repeat.

The work list and acceptance criteria above already assume both. **Both adopted** and built (steps 2 and 3).

## Dependencies

| Need | Package |
|------|---------|
| M-Pesa STK Push and payment confirmation | The **private M-Pesa package**, an API queried with each shop's own account details (Open question 2) |
| OpenAPI docs for `/api/v1` | **`dedoc/scramble`** (confirmed) |
| Tenant SMS | The current SMS service, fixed and made per tenant; no new package |

## Acceptance criteria

- [x] A paired register's token works only for its business and shop.
- [x] Pushing the same batch twice creates the records once.
- [x] A pull for business A returns only A's changes since the cursor, including deletions.
- [ ] A user in two businesses syncs both independently.
- [ ] A register that hasn't synced for 24 hours triggers the owner warning and the API flag, without
      blocking sales.
- [ ] An STK payment marks the right sale paid exactly once, including when the till syncs only
      afterwards.
- [ ] Two tills number sales offline without collisions; a repeated or missing number in a series is
      flagged on sync.
- [ ] A sale recorded before the lapse is accepted after it; one recorded after is refused and kept on the
      device. A sale outside any issued lease is refused even if its recorded time is before the lapse.
- [x] A device re-paired after a reinstall gets a new register code, and its numbers don't repeat.
- [x] A customer pushed with an existing phone number is saved and flagged, not merged.
- [ ] An M-Pesa code entered on an offline till is matched to the shop's payment notification on sync; an
      unmatched or reused code stays pending and is flagged.
- [x] The OpenAPI document matches the routes (contract test).
- [ ] SMS sends through the tenant's provider.
- [ ] The flat API contract tests still pass. (Passing after each step so far; ticked at the end of the phase.)
- [x] A till PIN sign-in, failure, lockout and unlock are each in the audit trail; a lockout that needs a manager
      reaches the business's other tills.

## Risks

- Duplicate sales on retried pushes.
- Pushes landing in the wrong business (checked against the token's business).
- Pulls leaking deleted or foreign rows.
- A payment result matched to the wrong sale.
- Clock skew on offline timestamps, or a device clock set back on purpose to trade after a lapse (the
  lease covers this).
- A reinstalled app reusing invoice numbers (re-pairing covers this).

## Definition of done

- [ ] Acceptance criteria met, with tests.
- [ ] The `/api/v1`, token, sync and M-Pesa rows of the isolation resource matrix filled in; isolation
      suite green.
- [ ] Pint, the full suite (SQLite and MySQL 8) and both API contract test sets pass.
- [ ] Hotspot checklist items for the API, sync and M-Pesa ticked in saas-plan.md section 2.
