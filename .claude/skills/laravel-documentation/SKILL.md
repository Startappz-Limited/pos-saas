---
name: laravel-documentation
description: Documentation standards for this POS codebase — PHPDoc on services and public methods, documenting enums and their lifecycles, API endpoint docs, where markdown docs live under docs/, keeping .env.example in sync with new config keys, the required doc structure, diagrams, and the rule that comments explain WHY. Use when adding a config/env key, a new API endpoint, a new module or subsystem, an enum, or when asked to document, update docs, or write a README.
---

# Documentation (POS)

Source: [0.6 documentation_guide.md](../../../.ai/general/0.6%20documentation_guide.md). Documentation is
🔴 mandatory for new features, config changes, database changes, API changes, and breaking changes
([ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md)).

> Documentation is production code: accurate or removed. Outdated docs are worse than none.

## Where docs live here

Flat topic files plus subfolders under [docs/](../../../docs/) — **not** the `docs/architecture|api|setup|changelog`
or versioned `docs/v1|v2` layout the guide sketches. 63 markdown files exist. Match the real layout:

```
docs/
├── flutter/          API contract notes for the mobile client
├── system/           system/subsystem behaviour
├── implementation/   implementation notes
├── migration/        migration/upgrade notes
├── fixes/            specific fix write-ups
├── baileys-integration-guide.md, cash-register-system.md,
    whatsapp-templates.md, ui-components-reference.md, …
```

- Put a new doc in the subfolder that already covers its area; only create a folder for a genuinely new
  area.
- Touching an integration or subsystem? Update its existing doc rather than adding a second one beside
  it (`baileys-*`, `cash-register-system.md`, `docs/flutter/`).
- **Changing an API response, field, or endpoint means updating [docs/flutter/](../../../docs/flutter/)** —
  that's the mobile client's contract.
- There is **no CHANGELOG file** in this repo. The guide asks for changelog entries; until one exists,
  record the change in the relevant `docs/` file and the commit message rather than inventing a parallel
  changelog. If a change is breaking, say so explicitly where a consumer will see it.

## Config keys — `.env.example` is 🔴 mandatory

Any new `config/` key backed by an env var must be added to
[.env.example](../../../.env.example) in the same change, with a safe placeholder (never a real
secret).

⚠️ `.env.example` is already **missing** keys the code reads — including `WHATSAPP_API_URL` /
`WHATSAPP_API_TOKEN` / `WHATSAPP_PHONE_NUMBER_ID`, `META_APP_ID` / `META_APP_SECRET` /
`META_GRAPH_VERSION` / `META_REDIRECT_URI`, `GOOGLE_ADS_*`, `AI_DRIVER` / `QWEN_*` / `OPENAI_*`,
`POSTMARK_API_KEY`, `RESEND_API_KEY`, `SLACK_BOT_USER_OAUTH_TOKEN`, and `AUDIT_DB_DATABASE`. If you
touch a config file whose keys are missing there, add them.

Read config through `config()`; `env()` only inside `config/`.

## Inline documentation

PHPDoc is required on services, repositories, complex methods, and public APIs:

```php
/**
 * Record a stock movement and update the product's cost layers.
 *
 * @param  array<string, mixed>  $data  Movement payload (product, variation, quantity, unit cost)
 * @return StockMovement  The persisted movement
 *
 * @throws InsufficientStockException  When the movement would drive stock negative
 */
public function record(array $data): StockMovement
```

- Document **enums** and their lifecycle — the enum is the source of truth for domain state:

```php
/**
 * Sale lifecycle: Draft -> Completed -> (Voided | Returned)
 */
enum SaleStatus: string
{
    /** Sale is being built in the POS screen and has not affected stock. */
    case Draft = 'draft';
    // ...
}
```

- Comments explain **WHY**, not WHAT. No redundant comments, no `TODO` in place of real documentation,
  no commented-out code.

## API endpoint documentation

Each new or changed endpoint documents: URL, method, auth requirement, required permission, rate limit,
headers, request body + validation rules, success response, and every error case — with examples. Use
the **actual** response shape (`{success, data}`) and the actual flat path (`/api/sales`), not the
`/api/v1` envelope form the guide's template shows. See [laravel-api](../laravel-api/SKILL.md).

## New module / subsystem docs

Document domain purpose, exposed endpoints, business rules, **status lifecycle**, permissions used, and
audit behaviour. Prefer a Mermaid state or sequence diagram for anything with a lifecycle (sale →
return → refund, purchase order → intake → cost layer, cash register open → close). Update the diagram
when the logic changes.

## Documentation review gate

Before finishing a change, check:

- [ ] PHPDoc on new/changed public methods
- [ ] `.env.example` updated for any new config key
- [ ] API docs / `docs/flutter/` updated for endpoint or payload changes
- [ ] Relevant `docs/` file updated (not duplicated)
- [ ] Breaking changes called out explicitly
- [ ] Enum lifecycle documented
- [ ] Diagram updated if the flow changed

## Forbidden

- ❌ Leaving docs stale after changing behaviour
- ❌ New config key without `.env.example`
- ❌ Real secrets or live hosts in example/committed files
- ❌ Redundant comments; `TODO` instead of documentation
- ❌ Duplicating an existing doc instead of updating it
- ❌ Documenting the aspirational API shape instead of the shipped one
- ❌ Screenshot-only documentation
