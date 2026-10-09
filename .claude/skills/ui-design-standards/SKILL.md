---
name: ui-design-standards
description: Design-system rules for this POS admin/POS UI — semantic colors mapped to backend enums (status badges), the spacing scale, button/form/table/modal conventions, reusable widgets, accessibility (WCAG AA, keyboard, labels, focus), mobile-first responsive behaviour, i18n, and no hardcoded colors or inline styles. Use alongside the bootstrap-ui skill whenever building or reviewing a Blade screen, status badge, table, form, modal, or dashboard tile.
---

# UI & Design Standards (POS)

Source: [0.2 ui_design_guide.md](../../../.ai/general/0.2%20ui_design_guide.md). UI/UX is 🔴 mandatory for
new features ([ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md)).

**Read [bootstrap-ui](../bootstrap-ui/SKILL.md) first** — it covers *how* the UI is wired (static
compiled Bootstrap 5 theme under `public/assets/`, loaded with `asset()`, BS5 JS API, no
Tailwind/Vite). This skill covers *what the design must express*.

> Consistency over creativity. The UI reflects backend state — it never infers or derives it.

## Where the design system actually lives

⚠️ [0.2](../../../.ai/general/0.2%20ui_design_guide.md) points at `./designs/theme/` and a
`resources/design-system/` tokens folder. **Neither exists in this repo.** The real design system is the
compiled theme:

```
public/assets/
├── css/{vendor,app,icons}.min.css     ← what layouts load
├── scss/                              ← the SOURCE of truth for design tokens
│   ├── config/       theme variables (colors, typography, spacing)
│   ├── structure/    layout (topbar, sidebar, footer)
│   ├── components/   component overrides
│   ├── pages/        page-specific styles
│   └── plugins/
├── fonts/  images/
```

- Change a design token in `public/assets/scss/config/`, not in a Blade file.
- The brand palette in the guide (`#C5C470`, `#462350`, …) is an **example placeholder**, not this
  project's palette — take real values from the theme SCSS/`_variables`, never from that table.

## Semantic color = backend enum (the important rule)

Status colors map to Bootstrap's semantic classes, and the mapping is driven by the backend enum. The
established vocabulary in this repo's views:

| Meaning | Class | Typical enum state |
|---|---|---|
| success / active / completed / paid | `badge bg-success` | `Completed`, `Active`, `Paid`, `Approved` |
| danger / failed / voided | `badge bg-danger` | `Voided`, `Failed`, `Cancelled` |
| warning / pending | `badge bg-warning` | `Pending`, `PartiallyPaid`, `Suspended` |
| info / in-progress | `badge bg-info` | `Processing`, `Syncing` |
| muted / inactive / draft | `badge bg-secondary` / `bg-light` | `Draft`, `Inactive`, `Archived` |

Rules:
- **Never derive status in the frontend.** No `@if ($sale->total_paid >= $sale->total) Paid @endif` —
  read the enum the backend already computed.
- Put the enum → class mapping in **one** place (a method/accessor on the enum, or a Blade component),
  not repeated per view. Adding a new enum case must not require editing five templates.
- Every status column in a table uses a semantic badge — never bare text, never a hand-picked hex.

## Color & style discipline

- ❌ No hardcoded hex colors in Blade or `<style>` blocks — use theme classes/variables.
- ❌ No inline `style="..."` for anything a utility class covers. (69 view files currently carry inline
  styles; don't add more, and prefer replacing them when you're already editing that markup.)
- Utilities and components only: `card`, `btn btn-primary`, `table table-striped`, `badge`,
  `d-flex`, `justify-content-between`, `mb-3`, `gap-2`.

## Spacing & layout

Base unit **4px**, scale `4, 8, 12, 16, 24, 32, 48` — which is exactly Bootstrap's `*-1` … `*-5`
spacing utilities. Use them (`mb-3`, `p-4`, `gap-2`); no arbitrary pixel margins. Grid-based layouts
(`row`/`col-*`) only.

## Elements

**Buttons** — variants primary / secondary / destructive (`btn-danger`) / ghost (`btn-link`,
`btn-outline-*`). One primary action per screen. Destructive actions look destructive and require
confirmation.

**Forms** — `form-label` + `form-control`/`form-select`, `form-check` for checkboxes/radios,
`is-invalid` + `invalid-feedback` for errors. **Validation messages come from the backend response**
(`$errors`), never from frontend-invented rules. Required fields marked explicitly. Enum fields render
as `<select>`/radios populated from the enum — never a free-text input.

**Tables** — avoid horizontal scroll (wrap in `table-responsive` only when unavoidable); status columns
use semantic badges; actions grouped consistently in the last column; adapt to stacked cards on small
screens.

**Modals** — BS5 API (`new bootstrap.Modal(...)`), no nesting, close always visible, confirmation
required for destructive actions.

## Widgets

Composite components (status badge, metric tile, user card, confirmation dialog) live in
`resources/views/components/` (Blade components; `app/View/Components/` for those needing PHP). They are
stateless, configured by props, and **never call the API or query the database themselves** — the
controller/service supplies the data. For charts and dashboard tiles, also load the `dataviz` skill.

## Accessibility (mandatory)

- Contrast ≥ WCAG AA.
- Fully keyboard navigable; visible focus indicators (don't remove outlines).
- Every input has a real `<label for>`; icon-only buttons get `aria-label`; images get `alt`.
- Status must not be conveyed by color alone — the badge carries text too.
- Decorative icons `aria-hidden="true"`.

## Responsive

Mobile-first. No feature loss across breakpoints — the POS screen and admin tables must remain usable on
tablet, which is the realistic shop-floor device. Use responsive grid/utility variants rather than
hand-rolled media queries.

## Project rules that always apply

- Wrap every user-facing string in `__('...')`.
- Escape with `{{ }}`; never `{!! !!}` on user content.
- `@csrf` + `@honeypot` on every web form.
- Gate action buttons with `@can` — cosmetic only; the controller still authorizes
  ([laravel-authorization](../laravel-authorization/SKILL.md)).
- Assets: SVG preferred for icons, no inline base64 images, shared assets stay in the theme.

## Forbidden

- ❌ Hardcoded colors; inline styles where a utility exists
- ❌ Frontend-derived status / business rules in Blade
- ❌ Duplicated enum→badge mappings across views
- ❌ Tailwind utility classes in admin/POS views
- ❌ BS4 jQuery plugin calls (`$('#x').modal()`)
- ❌ Untranslated user-facing strings
- ❌ Inputs without labels; color-only status; removed focus outlines
- ❌ Widgets that fetch their own data
