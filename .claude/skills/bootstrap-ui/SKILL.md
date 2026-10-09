---
name: bootstrap-ui
description: Build and modify front-end UI in this repo using the Bootstrap 5 CSS framework as it is actually wired here (static compiled assets loaded via Blade asset() tags, NOT npm/Vite/Tailwind). Use when creating or editing Blade views, forms, modals, toasts, tabs, dropdowns, tables, or any markup that needs Bootstrap classes/components.
---

# Bootstrap 5 UI (POS)

This project styles its admin panel, POS screen, and public pages with a **static compiled Bootstrap 5
admin theme**. Match the existing wiring exactly — do not introduce a different setup.

## How Bootstrap is loaded here (do NOT change the mechanism)

- The theme is shipped as **static compiled assets** under `public/assets/`, referenced from Blade with
  `{{ asset(...) }}` in the production layouts (`resources/views/layouts/app.blade.php`,
  `layouts/pos.blade.php`, `layouts/guest.blade.php`):
  - CSS: `{{ asset('assets/css/vendor.min.css') }}`, `{{ asset('assets/css/app.min.css') }}`, `{{ asset('assets/css/icons.min.css') }}`
  - JS: `{{ asset('assets/js/vendor.js') }}`, `{{ asset('assets/js/app.js') }}` (+ `config.js`, `layout.js`)
  - Theme SCSS source lives in `public/assets/scss/`.
- The production UI is **NOT** built through Vite. `@vite` appears only in leftover Breeze scaffolding
  (`welcome.blade.php`, `layouts/auth.blade.php`, the demo `components/ui-layout.blade.php`) and pulls in
  `resources/css/app.css` (Tailwind directives) + Alpine — these are **not** how the admin/POS UI is styled.
- ⚠️ `resources/js/bootstrap.js` (imported by `app.js`) is **Laravel's axios bootstrap file — unrelated to
  the Bootstrap CSS framework.** Don't confuse the two.
- This repo's real UI uses Bootstrap, **not Tailwind**. Don't add Tailwind utility classes to admin views.

### Adding Bootstrap to a new page
Extend an existing production layout (`resources/views/layouts/app.blade.php` for admin, `layouts/pos.blade.php`
for the POS screen, `layouts/guest.blade.php` for public) so the theme CSS/JS tags come for free. Only add
`<script>`/`<link>` asset tags directly if a page renders standalone, and follow the same
`{{ asset('assets/...') }}` pattern already in those layouts.

## JavaScript API — Bootstrap 5, not jQuery plugins

Bootstrap 5 dropped the jQuery dependency. Use the global `bootstrap` object and its component
constructors (the pattern already in `layouts/auth.blade.php`):

```js
// correct (BS5) — matches existing code
var toast = new bootstrap.Toast(document.querySelector('#myToast')).show();
var modal = new bootstrap.Modal(document.querySelector('#myModal'));
modal.show();
// also: bootstrap.Dropdown, bootstrap.Tab, bootstrap.Tooltip, bootstrap.Collapse, bootstrap.Offcanvas
```

Do **not** write Bootstrap-4-style `$('#x').modal('show')` / `.toast()` / `.tooltip()` jQuery calls.
(jQuery may still be present for other plugins, but Bootstrap components use the BS5 API.)

## Available Bootstrap plugins already vendored (reuse, don't re-add)

Check `public/assets/` (`css/`, `js/`, `scss/`) for vendored theme plugins and reuse what's already there
rather than adding new npm dependencies. Reference any plugin with the same `{{ asset('assets/...') }}`
pattern used by the existing layouts before introducing a new library.

## Markup conventions

- Use standard Bootstrap 5 utilities and components: grid (`row`/`col-*`), `card`, `form-control`/`form-select`/`form-label`, `btn btn-primary`, `modal`, `nav-tabs`, `table table-striped`, `badge`, spacing utils (`mb-3`, `gap-2`), flex utils (`d-flex`, `justify-content-between`).
- BS5 form markup: `form-label` + `form-control`, `form-check`/`form-check-input` for checkboxes/radios, `is-invalid` + `invalid-feedback` for validation errors, `form-floating` for floating labels. (Note BS5 renamed BS4 classes: `custom-control` → `form-check`, `form-group` removed, `ml-*/mr-*` → `ms-*/me-*`.)
- Responsive: use the grid + responsive utility variants; don't hand-roll media queries when a utility exists.
- Accessibility: include `aria-*` attributes, `alt` text, and labels tied to inputs.

## Project rules that still apply to all Blade/UI work

- Wrap every user-facing string in `__('...')` (i18n).
- Escape output with `{{ }}`; avoid `{!! !!}` with user-supplied content (XSS).
- `@csrf` on every form; never put raw user input into HTML-building helpers (`generateMenu()` etc.).
- For broader engineering rules (security, validation, etc.) defer to the [laravel-standards](../laravel-standards/SKILL.md) skill.
