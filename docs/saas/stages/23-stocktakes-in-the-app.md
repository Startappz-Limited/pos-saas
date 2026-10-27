# Stage: Stocktakes in the App (outline)

> **Source:** sixth round, 27 October 2026: counting is on the web in stage 13; counting in the Flutter app is a
> follow-up stage. **Outline:** Goal, Decisions, Work and Points to confirm; the full plan is written when the app's
> next release is scheduled.
> **Branch:** `feat/stocktakes-app` · **Depends on:** [13-stocktakes.md](./13-stocktakes.md), a scheduled app release ·
> **App checks:** [app-release-checklist.md](./app-release-checklist.md#stage-23)

---

## Revisions

**Created 27 October 2026 (sixth round)** as an outline.

---

## Goal

Counters count with the app on a phone or till, online or offline, into the same stocktake documents as the web.

## Decisions that apply

- **Stocktakes are documents on the server** (stage 13); blind counts; each count compared with stock at the moment it
  was counted; approval by a second person.
- **The server stays backward compatible;** the app's release isn't scheduled.

## Work (outline)

- [ ] `/api/v1` stocktake endpoints: list a shop's stocktakes in `counting`, an item list without expected quantities,
      submit counts (each with its own `client_uuid`, `counted_at`, unit, batch).
- [ ] Offline: a push item `stocktake_count`, idempotent per `client_uuid`, judged at its `counted_at` (stage 13's
      expected-at-count rule makes late arrival harmless); a count for a stocktake no longer counting is held for
      review.
- [ ] The pull carries open stocktakes and their items for the till's shop (no expected quantities).
- [ ] Tests: offline counts pushed after the counter is back are compared at their own moments; a count for a closed
      stocktake is held; blind: no expected quantity in any response.

## Points to confirm

1. Phones as well as tills? (A phone isn't a paired register; it would need a counting-only token.)
2. Offline counting at all, or online only in the first version?
