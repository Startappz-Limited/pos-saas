# Stage: Platform: More Tills, Sync Indexes, M-Pesa Package, Test Stability

> **Source:** Phase 7's open items in PROGRESS.md, planned in the sixth round, 27 October 2026.
> **Branch:** `feat/platform-tills-sync`, from `main` once stage 10 is merged · **Depends on:**
> [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md) (per-register invoice series and the web
> series, step 5d; `outbox_count`; `X-App-Version`), Phase 4 (plan limits), Phase 7 (pairing, the pull, `MpesaGateway`)
> · **Can run alongside:** stages 11 to 14 (step 2's indexes include tables stage 11 adds once it has shipped) ·
> **App checks:** [app-release-checklist.md](./app-release-checklist.md#stage-17) · **Traceability:**
> [TRACEABILITY-platform.md](./TRACEABILITY-platform.md)

---

## Revisions

**Created 27 October 2026 (sixth round)** from Phase 7's open items: more than one till per shop, `updated_at`
indexes for the pull, plugging in the M-Pesa package, and the intermittently failing `BusinessErasureTest`. eTIMS,
also on that list, has its own stage, [22](./22-etims.md).

---

## Goal

A shop can run several tills, each with its own register and invoice series, within its plan. The sync pull stays fast
as catalogues grow. Real M-Pesa payments work through the business's own account once the package is available. The
test suite stops failing at random.

## Before you start

Stage 10 must be merged (step 5d separates tills' series from the web's, which several tills per shop need). Two points
are open (see "Points to confirm"); point 1 blocks step 3 only.

## Decisions that apply

- **Each shop had one till "for now"** (decided 26 October 2026, Phase 7); this stage lifts it.
- **Tills and the web never share an invoice series; a fiscal number is never changed** (stage 10).
- **Plan limits are data** (Phase 4; stage 21 makes plans editable): tills per shop is the existing `registers` limit,
  counted per shop.
- **M-Pesa is confirmed by querying the API** (open question 2, answered 12 October 2026); each shop has its own
  account details (`business_integrations`, Phase 3).
- **The server stays backward compatible** with the app in shops.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026.
- **Plan limit:** `Limit::Registers` exists and is counted per shop (`PlanLimits`), checked on `Register::creating`.
- **Registers:** `registers` (`shop_id`, `code` unique per shop, `is_default`, `device_id`, `paired_at`,
  `last_synced_at`, `last_invoice_number`); every shop has exactly one (UAT: one per shop, none paired). `/api/v1`
  lists registers and pairs one (`V1RegisterController`); there is **no web screen** to add, rename or retire a
  register. Cash sessions belong to a register.
- **Sync pull:** pages each entity by `updated_at`; none of the pulled tables (`categories`, `products`,
  `product_variations`, `pricing_rules`, `customers`, `shop_variation`, `payment_requests`) has an index on
  `updated_at` (Phase 7 listed it as a follow-up; the pull reads `COALESCE(updated_at, …)`).
- **M-Pesa:** `MpesaGateway` with STK Push, a status query and a lookup; `FakeMpesaGateway` in tests,
  `NullMpesaGateway` bound in `AppServiceProvider` until the private package is plugged in.
- **`BusinessErasureTest`** failed once in a long targeted run (another business's private files missing) and passed
  alone and in both full suites (PROGRESS.md, Phase 7).

---

## Work

### 1. More than one till per shop
- [ ] **Registers screen** (Shops → a shop → Tills; `registers.manage`, new: owner and manager templates): add a till
      (code `R2`, `R3`, … in order; never `W`, reserved for the web series), rename it, retire it (only with no open
      cash session and an `outbox_count` of 0, or with a confirmation that records the count), see its device, last
      sync, app version and unsynced count (stage 10).
- [ ] **Plan limit:** the existing `Limit::Registers` (Phase 4: counted per shop, enforced when a register is created,
      `PlanLimits::assertCanAddForShop`). Adding a till over it is refused with the plan's upgrade message
      (`plan_limit_reached`); the screen shows "Tills: 1 of 2". Nothing new to build for the limit itself.
- [ ] **Pairing:** `/api/v1/registers` lists every active till of the shop with whether it is paired; pairing a till
      already paired to another device needs stage 10's confirmation; each till numbers in its own series.
- [ ] **Cash sessions** stay per till; the web POS picks the till its session runs on, numbering in the web series
      (stage 10, step 5d).
- [ ] Tests: two tills of one shop pair, sell offline and push the same sequence number without collision; a third till
      over a plan limit of two is refused; a retired till can't pair; a till of another shop is a 404.

### 2. Indexes for the pull
- [ ] `sync:backfill-updated-at --business=<id>` (dry run; `--apply`): sets `updated_at = created_at` where it is NULL on
      the pulled tables, with counts, so the pull can read `updated_at` alone.
- [ ] Indexes `(business_id, updated_at, id)` (shop-owned: `(shop_id, updated_at, id)`) on every pulled table,
      including the entities stages 10 and 11 add once they exist (`units`, `unit_prices`, `sale_voids`' source); the
      pull's queries drop `COALESCE` and use the index.
- [ ] Tests: on MySQL, `EXPLAIN` for each entity's page query uses the index (a test reads the plan); a pull of 20,000
      products pages in the same order before and after.

### 3. The M-Pesa package
- [ ] When the private package is available (point 1), an adapter `PackageMpesaGateway implements MpesaGateway` maps
      STK Push, the status query and the transaction lookup; the binding is chosen by config per environment
      (`MPESA_GATEWAY=null|fake|package`).
- [ ] Each shop's credentials come from `business_integrations` (encrypted), never `.env`.
- [ ] A contract test runs the same scenarios against `FakeMpesaGateway` and the adapter (with the package's own test
      double), so both behave alike: a confirmed payment applies once; a cancelled one doesn't; a lookup by code
      matches by amount.

### 4. `BusinessErasureTest` stability
- [ ] Reproduce: run the erasure and file tests in random order with fixed seeds (`--order-by=random --random-order-seed`)
      until it fails; record the seed.
- [ ] Fix the cause found (expected: a shared fake disk or a `TenantFiles` cache keyed without the business between
      tests); each test fakes its own disk.
- [ ] Acceptance: 20 random-order runs of `tests/Feature` pass on SQLite and MySQL.

## Contract

`/api/v1/registers` already lists registers; more than one is an addition the app may not expect. Whether the app can
choose among several tills when pairing is an app check
([app-release-checklist.md](./app-release-checklist.md#stage-17)). The pull's responses are unchanged by the indexes.

## Schema

| Change | Data carried across |
|---|---|
| Permission `registers.manage` | Through `permissions:sync` |
| `registers.retired_at` (nullable) | None |
| Indexes `(business_id, updated_at, id)` / `(shop_id, updated_at, id)` on pulled tables | `sync:backfill-updated-at` fills NULL `updated_at` first |

## Acceptance criteria

- [ ] A shop adds tills within its plan; each pairs, sells offline and numbers in its own series without collision.
- [ ] Every pull query uses an `updated_at` index.
- [ ] With the package available, real M-Pesa payments go through the business's own account and behave as the fake
      does.
- [ ] `BusinessErasureTest` passes 20 random-order runs.
- [ ] The app's recorded requests still succeed; both full suites pass.

## Risks

- **An app in shops may assume one till per shop.** Mitigation: a shop has more than one only when its owner adds it;
  the app check comes first.
- **Index builds on large tables lock writes on MySQL.** Mitigation: built online (`ALGORITHM=INPLACE`), rehearsed on
  the restored snapshot for timing.

## Rollout (UAT)

1. Take a snapshot; restore it into `rehearsal_testing`.
2. `sync:backfill-updated-at` for every business (dry run, then `--apply`); run the migrations (indexes); time them.
3. `permissions:sync`; add a second till to one rehearsal shop, pair it with the recorded requests, push a sale.
4. Check the server against the app's recorded requests.
5. The user runs steps 2 and 3's commands on UAT.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
pass, and their results are in the PR body.

## Points to confirm

1. **The M-Pesa package:** when is it available, and does it offer a test double? Step 3 waits for it.
2. **Tills per plan:** how many tills per shop does each tier include (the `registers` limit)? Plans and tiers aren't
   agreed yet (stage 21).

## Follow-ups (not this stage)

- None.
