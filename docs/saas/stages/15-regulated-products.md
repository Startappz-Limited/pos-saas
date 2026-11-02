# Stage: Regulated Products

> **Source:** follow-up of stage 11 ("prescription-only items and a controlled-drugs register"), treated as needed
> in the fourth round; redesigned as a configurable framework in the sixth round, 27 October 2026.
> **Branch:** `feat/regulated-products` · **Depends on:**
> [11-units-quantities-and-batches.md](./11-units-quantities-and-batches.md) (business types, batches and expiry,
> units), [12-receiving-and-moving-stock.md](./12-receiving-and-moving-stock.md) (GRNs with lot numbers, the recall
> report), [10-stock-correctness-and-voids.md](./10-stock-correctness-and-voids.md) (`permissions:sync`, the till sync
> review), Phase 7 (till PINs, the pull and push) · **Schedule:** **unscheduled** until a regulated customer (a
> pharmacy, or an agro-vet selling restricted chemicals) is in view · **App checks:**
> [app-release-checklist.md](./app-release-checklist.md#stage-15)

---

## Revisions

**Created 27 October 2026 (fourth round)** as "Pharmacy controls", an outline. **Sixth round (27 October 2026):**
renamed "Regulated products" (file `15-regulated-products.md`) and designed as a general framework, written out in
full: control classes and record templates are per-business data, pharmacy and agro-vet are presets, so onboarding a
regulated business is configuration, not development. Decided: dispensing needs `sales.dispense`, held by a pharmacist
role template; pharmacy-only medicines record the supervising pharmacist (by till PIN), with no patient details. One
point stays open: the exact legal fields, retention and register formats for Kenya.

---

## Goal

A business that sells regulated goods sets up, as data, which of its products are controlled and how: who may sell
them, whether a supervisor must confirm at the till, what record each sale must capture, and whether it can be sold
offline. Records are kept against the sale line, the batch and the customer, retained as long as the template says,
and exported as the register an inspector asks for. A pharmacy or an agro-vet starts from a preset; confirming the
legal details later changes the preset, not the code.

## Before you start

Stages 11 and 12 must be merged. The stage stays unscheduled until a regulated customer is in view (PROGRESS.md says
so). One point is open (see "Points to confirm"); it doesn't block building the framework, only using a preset for a
real customer.

## Decisions that apply

- **A configurable framework (decided 27 October 2026, sixth round):** control classes and record templates are
  tables per business, not enums; presets create them. No code path depends on a business type.
- **Dispensing (decided 27 October 2026, sixth round):** selling a class that requires it needs `sales.dispense`, held
  by the **pharmacist** role template (pharmacy preset only), not every role.
- **Pharmacy-only medicines (decided 27 October 2026, sixth round):** the sale records which pharmacist supervised it
  (by till PIN), with no patient details.
- **Business types set defaults, never restrictions** (stage 11): presets suggest; the owner can change everything.
- **Batches** (stage 11) and **the recall report** (stage 12) are the base: every record names its batch.
- **Every stock change goes through `StockLedger`**; a register entry is written from the ledger's movement.
- **Live-data rule:** applying a preset to an existing business is a command with a dry run.

## Relevant current state

Checked against `main` after Phase 7 (`832f684`), 27 October 2026, and stages 11 and 12's plans.
- Nothing classifies products for control; `ProductStatus` and the stage 11 business type are the only per-product and
  per-business settings.
- Phase 7's till PIN (`TillPin`, `ManageTillPin`, the staff list with PIN hashes in the pull, lockout after 4 tries)
  can confirm a second person at the till.
- Roles are per business (Spatie teams); templates are seeded by `RoleSeeder` and reach businesses through stage 10's
  `permissions:sync`. No pharmacist template exists.
- The audit trail (`Auditable`, `AuditService`) records model changes; reading a record isn't audited anywhere today.
- Stage 11 adds `businesses.type` (pharmacy, agro-vet among six) and `track_batches`; stage 12 adds lot numbers and the
  recall report.

---

## Work

Every part keeps to the tenancy rules: business-owned records use `BelongsToBusiness`, shop-owned ones
`BelongsToShop`; every new route gets its `ResourceMatrixTest` row.

### 1. Control classes
- [ ] `control_classes` (`uuid`, business, `name`, `code`, `sort_order`, `is_active`; unique code per business):
  - `required_permission` (nullable: a permission name, e.g. `sales.dispense`);
  - `requires_supervisor` (boolean) and `supervisor_permission` (the permission the confirming person must hold);
  - `record_template_id` (nullable: the record the sale must capture);
  - `sellable_offline` (boolean);
  - `max_quantity_per_sale` (nullable, base units).
- [ ] `product_variations.control_class_id` (nullable: no control). Set on the product form and in the CSV import
      (`control_class` column, by code).
- [ ] A "Regulated products" settings page (`settings.update`) lists classes and templates; changes audited.
- [ ] Tests: a class without a template only checks the permission; a variation's class is per business; another
      business's class id is refused.

### 2. Record templates
- [ ] `record_templates` (`uuid`, business, `name`, `code`, `version` (increments on every change; old versions kept),
      `fields` (JSON: each field's `key`, `label`, `type` (`text`, `number`, `date`, `select`, `person`, `customer`,
      `file`), `required`, `validation` (regex, min, max, options), `visible_to` (role names)), `retention_months`,
      `export_columns` (the register's column order), `is_active`).
- [ ] `regulated_records` (`uuid`, business, shop, `record_template_id`, `template_version`, `sale_item_id`,
      `cost_layer_id` (the batch), `customer_id` (nullable), `values` (JSON, encrypted at rest where a field is marked
      sensitive), `recorded_by`, `supervised_by` (nullable), `recorded_at`, `retain_until`). Immutable: corrections are
      a new record that names the one it corrects.
- [ ] **Validation** reads the template version the record names: required fields, types, rules; a `person` field must
      be a staff member, a `customer` field a customer of the business.
- [ ] Tests: a record missing a required field is refused online; a record is validated against its own version after
      the template changed; a field hidden from a role isn't shown or exported to it.

### 3. Selling a controlled line
- [ ] **Online** (web POS, `/api/v1`, flat API): a line of a controlled variation needs the class's permission
      (refused `controlled_product_permission`), the supervisor's confirmation when required (a second person entering
      their PIN at the web POS, or the till PIN offline; refused `supervisor_required`), and a valid record when the
      class has a template (refused `regulated_record_required`, with the template's fields). The record is written in
      the sale's transaction against the line and the batch the ledger took (stage 11).
- [ ] **Offline:** the pull sends classes and templates (step 5). If the class isn't `sellable_offline`, the till
      refuses the line. Otherwise the till captures the record and the supervisor's PIN; the push carries them with
      `template_version`. The server validates the record against that version; a sale whose record fails, or whose
      seller or supervisor didn't hold the permission at `recorded_at`, is recorded (it happened) and goes on the till
      sync review as `regulated_record_invalid`, open.
- [ ] `max_quantity_per_sale` is checked in base units across the sale's lines of the variation.
- [ ] Tests: a pharmacy-only line needs a supervising pharmacist's PIN and records them with no patient fields; a
      prescription-only line without its record is refused online and flagged offline; a class not sellable offline is
      refused by the server when pushed (held) and the till is told why; the record names the batch FEFO took.

### 4. Retention, access and the register
- [ ] **Retention:** each record carries `retain_until` (recorded date + the template's `retention_months`);
      `regulated:prune` (daily) deletes records past it, logging counts; a record under an open inspection hold
      (`legal_hold`, set by the owner) is never pruned.
- [ ] **Access:** viewing a record, the register or an export needs `regulated-records.view` (owner, manager,
      pharmacist templates); every view and export is audited (who, which records, when).
- [ ] **The register:** per template, per shop and date range, rows in the template's `export_columns` order, with
      running balances where the template has a quantity field (from the ledger, per batch); printable and CSV.
- [ ] Tests: a 25-month-old record of a 24-month template is pruned, one under legal hold isn't; a view and an export
      each write an audit row; the register's columns follow the template's order; another business's register is a
      404.

### 5. Presets
- [ ] `regulated:apply-preset --business=<id> --preset=pharmacy|agro_vet` (dry run by default; `--apply`) creates the
      preset's classes, templates and role template, and suggests defaults (`track_batches` on, FEFO), never changing
      existing products. Business sign-up with type `pharmacy` or `agro_vet` (stage 11) offers it.
- [ ] **Pharmacy preset:** classes "General sale" (no control), "Pharmacy only" (`sales.dispense`, supervisor by PIN,
      no template), "Prescription only" (`sales.dispense`, the prescription template, not sellable offline until
      confirmed), "Controlled" (`sales.dispense`, supervisor, the controlled-drugs register template, not sellable
      offline); a **pharmacist** role template holding `sales.dispense` and `regulated-records.view`.
- [ ] **Agro-vet preset:** classes "General" and "Restricted pesticide" (a permission `sales.restricted-chemicals`,
      the restricted-pesticide sale record template, sellable offline).
- [ ] Each preset's templates are the **unverified** guesses in "Points to confirm", marked `verified = false`; a
      business can't use a preset template for a real sale until an operator marks it verified (platform console),
      which is what confirming the legal fields changes.
- [ ] Tests: applying a preset twice changes nothing the second time; the dry run lists what it would create; the
      pharmacist template reaches business copies through `permissions:sync`.

### 6. Sync
- [ ] **Pull:** entities `control_classes` and `record_templates` (current versions), and each variation's
      `control_class_uuid`. **Push:** sale lines may carry `regulated_record` (`template_uuid`, `template_version`,
      `values`) and `supervised_by` with `supervised_at`.
- [ ] Tests: an older app (no `X-App-Version` at this stage's minimum) selling a controlled variation is held
      (`regulated_record_required`, `hold`) when the class has a template or isn't sellable offline, and otherwise
      recorded and flagged; the pull carries classes and templates.

## Contract (paired app changes)

The paired app release isn't scheduled. The server stays backward compatible: the new pull entities and fields are
additions; a controlled sale from an older app is held or flagged, never silently accepted. What the paired app adds,
without a date: showing control classes on lines, capturing records from templates (any version the pull sent),
asking for the supervisor's PIN, refusing lines not sellable offline. App checks:
[app-release-checklist.md](./app-release-checklist.md#stage-15).

## Schema

| Change | Data carried across |
|---|---|
| `control_classes`, `record_templates`, `regulated_records` (new) | None |
| `product_variations.control_class_id` (nullable) | NULL for every existing variation |
| Permissions `sales.dispense`, `sales.restricted-chemicals`, `regulated-records.view`; role template `pharmacist` | Through `permissions:sync`; the pharmacist template only in businesses that apply the pharmacy preset |
| `ApiErrorCode` cases `controlled_product_permission`, `supervisor_required`, `regulated_record_required` | None (an enum) |
| `sync_issues` type `regulated_record_invalid` | None |
| Scheduled `regulated:prune` (daily) | None |

## Acceptance criteria

- [ ] A business sets up control classes and record templates as data; a pharmacy or agro-vet preset creates them,
      with a dry run.
- [ ] A controlled line needs its permission, its supervisor and its record, online; offline it is refused by the till
      when the class isn't sellable offline, and otherwise recorded and validated against the template version it was
      captured with.
- [ ] Records name the sale line, the batch and the customer; they are kept until their retention ends, never edited,
      and every view and export is audited.
- [ ] The register exports in the template's column order, printable and CSV.
- [ ] A preset template can't be used for a real sale until marked verified.
- [ ] Pint, the full SQLite and MySQL suites, and the isolation suite pass.

## Risks

- **The preset fields are guesses** until a pharmacist and an agro-vet confirm them. Mitigation: `verified = false`
  blocks real use; confirming them is a data change.
- **Sensitive personal data** (patients) in records. Mitigation: fields marked sensitive are encrypted; visibility per
  role; every view audited; retention enforced.
- **Offline records validated late** may turn out invalid after the goods left. Mitigation: classes that need a record
  default to not sellable offline in the pharmacy preset; invalid records are reviewed, never dropped.

## Rollout (UAT)

1. Take a snapshot; restore it into `rehearsal_testing`; run the migrations (additive).
2. `permissions:sync` (new permissions; no business gets the pharmacist template unless it applies the preset).
3. On a rehearsal business set to `pharmacy`: `regulated:apply-preset --preset=pharmacy` (dry run, then `--apply`); sell
   one product of each class on the web; export the register.
4. Check the server against the app's recorded requests (none uses a controlled class; they still succeed).
5. Nothing is applied to a UAT business without its owner's request.

## Definition of done

Every acceptance criterion is ticked with evidence. The stage file and PROGRESS.md are updated. The pre-push checks
(Pint, SQLite, MySQL) pass, and their results are in the PR body.

## Points to confirm

1. **The exact legal fields, retention periods and register formats for Kenya**, to be confirmed with a pharmacist
   (Pharmacy and Poisons Board) and an agro-vet (Pest Control Products Board) before a preset is used by a real
   customer. Because the fields are configuration, confirming them changes the preset, not the code. Current guesses,
   all **unverified**:
   - **Prescription record (pharmacy, "Prescription only"):** patient name; patient age or date of birth; patient
     phone (optional); prescriber name; prescriber registration number; prescribing facility; prescription date;
     prescription reference number; medicine, strength and quantity (from the line); directions; pharmacist who
     dispensed (from the sale); a scan of the prescription (optional file). Retention guess: 24 months.
   - **Controlled-drugs register entry (pharmacy, "Controlled"):** date; patient name and address; prescriber name and
     registration number; prescription reference; drug, strength and form; batch and expiry (from the batch); quantity
     received or supplied; running balance per drug and shop; pharmacist's name and signature (the supervisor's PIN
     confirmation). Retention guess: 24 months after the last entry on the page (register kept as a bound record).
   - **Pharmacy-only sale:** supervising pharmacist only (decided), no patient fields. Retention guess: 12 months.
   - **Restricted pesticide sale record (agro-vet):** buyer name; buyer national ID or registration number; buyer phone;
     intended use or crop; product, registration number (PCPB) and quantity (from the line); batch (from the batch);
     seller. Retention guess: 24 months.

## Follow-ups (not this stage)

- E-prescriptions received electronically, if a prescribing system offers them.
