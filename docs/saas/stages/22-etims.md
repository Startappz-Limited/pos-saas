# Stage: KRA eTIMS

> **Source:** Phase 7's open items ("KRA eTIMS plugs into `FiscalProvider`") and plan open question 6 (eTIMS
> deferral), given its own stage in the sixth round, 27 October 2026.
> **Branch:** `feat/etims` · **Depends on:** [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md)
> (the fiscal-number rule, per-register and web invoice series, step 5d), [14-returns-in-reports.md](./14-returns-in-reports.md)
> (returns and refunds, which become credit notes), [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md)
> (units, for item quantities), [17-platform-tills-sync-mpesa.md](./17-platform-tills-sync-mpesa.md) (several tills per
> shop), the VAT engine ([docs/vat.md](../../vat.md)) · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#stage-22) · **Traceability:**
> [TRACEABILITY-platform.md](./TRACEABILITY-platform.md)

---

## Revisions

**Created 27 October 2026 (sixth round).** Carries stage 10's decision that a fiscal number, once printed or signed,
is never changed by the server.

---

## Goal

Every sale a VAT-registered business makes is signed by KRA's eTIMS and its receipt carries the signature; every
correction is a credit note, signed too. Signing works when the till is offline and catches up when it comes back,
without ever changing a number a customer already holds.

## Before you start

Stages 10, 14 and 17 must be merged. Points 1 to 3 must be answered before the work starts: they decide the
integration.

## Decisions that apply

- **A fiscal number is never changed by the server (decided 27 October 2026, stage 10).** Once printed or signed, an
  invoice number stays; a correction is a credit note plus, where needed, a new sale.
- **Tills and the web never share a series** (stage 10, step 5d), so each device's numbers are its own.
- **VAT is computed on the server** (`TaxService`); products carry `TaxClass` with KRA codes (`kraCode()`).
- **`FiscalProvider`** is the seam (Phase 7): `signatureFor(Sale): ?FiscalSignature`; `SaleReceipt` already has a
  `fiscal` block; `NullFiscalProvider` is bound.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026.
- `App\Contracts\FiscalProvider` with one method; `FiscalSignature` (control code, device serial, invoice number, QR
  URL); `NullFiscalProvider` bound; the `/api/v1` receipt's `fiscal` is null.
- Voids (stage 10) and returns (stage 14) don't produce fiscal documents.
- Shops have `vat_registered` and tax PINs; customers can carry a KRA PIN (`KraPin` rule).

---

## Work (planned once points 1 to 3 are answered)

### 1. Devices and registration
- [ ] Each shop (a KRA branch) and each till or the web channel (a device or a sales control unit, per the integration
      chosen) is registered with eTIMS; credentials per business, encrypted (`business_integrations`).
- [ ] A shop can't sign until registration succeeds; the settings page says why.

### 2. Signing sales
- [ ] `EtimsFiscalProvider implements FiscalProvider`: sends the sale (invoice number as issued, lines with item codes,
      `TaxClass::kraCode()`, quantities and units, totals and VAT) and stores the signature on the sale
      (`fiscal_signatures`: sale, control code, device serial, QR URL, signed at, status).
- [ ] The receipt (web, PDF, `/api/v1` receipt) prints the signature and QR once signed.
- [ ] **Offline and failures:** an unsigned sale is queued (`etims:sign-pending`, retrying with back-off); the sale and
      its number are never changed; a sale signed late is reported on a daily list.

### 3. Corrections are credit notes
- [ ] A void of a signed sale (stage 10) issues a signed credit note for the whole sale instead of only voiding it; a
      return (stage 14) issues a signed credit note for its lines. Credit notes have their own series per shop.
- [ ] A void of an unsigned sale stays a plain void.

### 4. Reports and checks
- [ ] A daily reconciliation: sales and credit notes recorded vs signed, per shop; unsigned items listed.

### 5. Tests
- [ ] A sale is signed and its receipt shows the QR; an offline sale is signed on the next run with its number unchanged;
      a void of a signed sale issues a credit note; a refusal from eTIMS leaves the sale unchanged and listed; another
      business's credentials are never used.

## Contract

The `/api/v1` receipt's `fiscal` block (already there, null today) fills in; nothing else changes. Whether the app
prints the QR is an app check ([app-release-checklist.md](./app-release-checklist.md#stage-22)).

## Schema (planned)

| Change | Data carried across |
|---|---|
| `fiscal_signatures` (new) | None; past sales stay unsigned |
| Credit-note series per shop | None |
| eTIMS credentials in `business_integrations` | None |

## Acceptance criteria

- [ ] Every sale of a registered shop is signed, online or caught up, without changing its number.
- [ ] Every correction of a signed sale is a signed credit note.
- [ ] A daily reconciliation shows anything unsigned.
- [ ] Both full suites pass.

## Risks

- **Certification:** KRA requires integrators to be certified (the plan's "KRA integrator certification", deferred).
  Mitigation: point 2.
- **eTIMS outages** stop signing, not selling. Mitigation: the queue and the daily list.

## Rollout (UAT)

Planned with the integration: eTIMS's test environment first, one rehearsal shop, then a pilot business.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
pass, and their results are in the PR body.

## Points to confirm

1. **The integration type:** OSCU (online sales control unit, the server signs) or VSCU (a virtual unit per device),
   given tills that sell offline.
2. **Certification:** become a certified integrator, or use a certified third party's API through `FiscalProvider`?
3. **Who must use eTIMS:** only VAT-registered shops, or every business (KRA's rules on eTIMS invoices for expense
   claims)? This decides whether the setting is per shop or always on.

## Follow-ups (not this stage)

- None.
