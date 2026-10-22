# Phase 5: Platform Admin Console and Roles per Business

> **Sources:** saas-plan.md section 5 (Phase 5), sections 4.4, 4.6 and 4.7; the roles review of 22 October 2026
> (after Phase 4), decision 4.4 (accounts model), Phase 3 as built (roles per business, templates copied per
> business, owner = `admin`).
> **Branch:** `saas/phase-5-platform-console` · **Depends on:** Phase 4, `fix/ui-button-links` merged

---

## Goal

Manage businesses on the platform without database access, and make role management match the SaaS model:

- The **platform operator** (`super-admin`) runs the platform from a console: businesses, plans, payments,
  impersonation, and the shared role templates.
- **Each business manages only its own roles.** Shared templates and the owner role can't be changed from a
  business; nobody can raise their own permissions by editing a role; a role can be switched off; and every
  screen shows true information.

The two pieces are delivered together because they share one line: what the platform operator may do that a
business never can. Isolating roles (Part A) defines that line; the console (Part B) is where the operator
uses it.

## Before you start

No open question blocks this stage.

## Decisions that apply

- **4.4:** `super-admin` is the platform operator, global, never a business member, and the only user
  `Gate::before` lets do everything. The business owner's role is `admin`. *Confirmed.*
- **4.6:** until M-Pesa go-live (around 12 November 2026), payments are recorded by hand in the platform
  console. *Confirmed.*
- **4.7:** the first platform operator is created by a console command, not seeded. *Confirmed.*
- **Roles review (22 October 2026):**
  - **Copied templates can't be deleted;** they can be reset to the template's current permissions.
  - **The `customer` template stays:** kept, copied into every new business and protected like the other
    templates (revising the first answer).
  - **`attributes.view`, `attributes.create` and `stock.view` become real permissions.**
  - **A role can be active or inactive** (a real flag). **Switching a role off is confirmed first** in a
    SweetAlert dialog warning that the people who hold it won't be able to do anything in the system with it,
    and how many people hold it.
- **Console review (21 October 2026):**
  - **Impersonation:** the operator acts strictly as the impersonated user; platform-operator rights don't
    apply during an impersonated session.
  - **Template changes:** apply to new businesses and "Reset to default" only; existing copies change only
    through an explicit, confirmed "Apply to all copies".

## Relevant current state

- **Model (sound):** Spatie teams on `business_id`. Shared templates (`business_id` NULL): `admin` (owner) plus
  manager, cashier, inventory-clerk, accountant and customer, the last five copied into every new business
  (`CopyRoleTemplates`). The business's copy hides the template in lists and lookups
  (`BusinessOrSharedScope`, `Role::findByParam`). `RoleSeeder` grants permissions added later to the copies.
  Giving a role to a person is guarded by `GrantableRole` (only an owner gives `admin`; others only roles
  within their own permissions).
- **Gaps found:**
  - `RolePolicy` never checks whose role it is. A business that deletes its copy of a template then sees the
    shared template, and editing it changes the role for every business and every future business.
  - `roles.full-access` (`RolePolicy::before`) passes every check, including the owner-role protection in
    `delete`; only the controller still blocks it.
  - A non-owner allowed to edit roles can give any permission to a role, including their own.
  - `unique:roles,name` is checked across the whole platform: business B can't use a name business A has.
  - Create allows lowercase-hyphen names, edit allows anything.
  - `is_active` is on the role forms and pages, but no column exists: the checkbox does nothing and every role
    shows "Inactive".
  - The owner shows as `admin`; messages still say "Super Admin".
  - Copies of templates can be deleted, after which the shared template reappears in the business.
  - The sidebar checks `attributes.view`, `attributes.create` and `stock.view`, which don't exist, so those
    menu entries are hidden from everyone but the platform operator. `AttributeController` has no
    authorization at all.
  - There is no screen for the platform operator: businesses, plans, payments and templates can only be
    changed in the database.

---

## Work

Order: Part A first (the console relies on the policy rules it sets), then Part B.

### Part A: Roles per business

> **As built (22 October 2026):** A1 `d75e049`, A2 `a47e435`, A3 `3b085fb`, A4 `21be769`, A5 `7870d06`; tests in
> `tests/Feature/Roles/` and `ViewPermissionNamesTest`, role rows in `ResourceMatrixTest`. There is no role API
> (flat or `/api/v1`) besides the `GET api/users/roles` list, so "web and API" is met on the web. Evidence per step:
> [PROGRESS.md](../PROGRESS.md), Stage 5.

#### A1. Isolation and escalation
- [x] `RolePolicy`: a business changes only its **own** roles. Shared templates and the owner role (`admin`)
      are read-only from a business (update, permissions, activate/deactivate, delete); only the platform
      operator may change them (through the console, B6). `roles.full-access` counts as every other `roles.*`
      permission and never passes these protections.
- [x] **No escalation through role editing:** a non-owner can give a role only permissions they hold
      themselves, and can't edit or deactivate a role they hold. Owners can edit any role of their business
      except `admin`.
- [x] **Names unique within the business** (a `UniqueForTenant`-style rule for roles, soft rules on the
      shared names): a business can't create `admin` or a second role with a template's name. Create and edit
      share one name rule.
- [x] Holder counts and the "has holders" check before deleting count only the current business.

#### A2. Templates: link, protect, reset
- [x] `roles.template_id`: a copy points at the template it was copied from (migration fills it for existing
      copies by name; `CopyRoleTemplates` sets it). Name matching is no longer needed to find a copy's template.
- [x] **Copies can't be deleted or renamed;** they can have their permissions edited, be switched off, and be
      **reset to default** (permissions replaced by the template's current ones, after a confirmation).
- [x] Custom roles (no template) keep edit, rename and delete (delete still refused while held).

#### A3. Active and inactive roles
- [x] `roles.is_active` (boolean, default true; additive migration, every existing role active).
- [x] **An inactive role grants nothing:** its permissions don't count in permission checks (web, both APIs,
      the permissions returned on sign-in and `/api/user`), while the people who hold it keep the assignment, so
      switching it back on restores their access.
- [x] An inactive role can't be given to anyone (`GrantableRole`, invites, Add/Edit User).
- [x] The owner role can't be switched off. Switching a role is a policy-checked action (`roles.update`),
      audited like other role changes.
- [x] **Confirmation before switching a role off:** a SweetAlert2 dialog (already bundled in the theme's
      `vendor.js`/`vendor.min.css`, `Swal.fire`) warns: "People with this role won't be able to do anything in the
      system with it until it's switched back on", names how many people in the business hold it, and needs an
      explicit confirm button; cancelling changes nothing. Shown wherever a role can be switched off (role list,
      role page, edit form). Switching a role back on needs no warning. Without JavaScript, the server still
      asks for confirmation (a `confirm` field the dialog sets), so a role is never switched off by accident.
- [x] The role screens show the real state (badge, filter), and the forms' existing checkbox writes it.

#### A4. New permissions
- [x] `attributes.view`, `attributes.create`, `attributes.update`, `attributes.delete` and `stock.view` in
      `PermissionSeeder`. Owners hold every permission; `RoleSeeder` gives them to the manager template and,
      as permissions added later, to every business's manager copy. `stock.view` also to inventory-clerk.
      The customer template's permissions are unchanged.
- [x] `AttributePolicy` on every `AttributeController` action, and on the matching `/api` routes if any.
- [x] The sidebar entries then show for the roles that hold them.

#### A5. Business-facing screens
- [x] Display names for the templates (Owner, Manager, Cashier, Inventory clerk, Accountant, Customer) on every
      screen; stored names stay unchanged (the Flutter app reads them).
- [x] The permission picker grouped by area, listing only permissions the editor may give; `*.full-access`
      explained in place.
- [x] Messages no longer say "Super Admin" (the term is reserved for the platform operator and never shown to a
      business).

### Part B: Platform admin console

A `platform.*` route group gated to `super-admin`, under `/platform`.

> **As built (22 October 2026):** B4 `e09bcc4`, B5 `a80e669`, B1 `c7bdf85`, B2 `d6ec15e`, B3 `9e3b5fc` (banner on the
> guest layout too: `2210798`), B6 `0db02d4`; tests in `tests/Feature/Platform/`, and every `/platform` route in
> `ResourceMatrixTest` (`b1c298a`). The gate is the `users.is_platform_operator` flag (not a `super-admin` role,
> as built in Phase 3) and "not impersonating"; anyone else gets a 404. Evidence per step:
> [PROGRESS.md](../PROGRESS.md), Stage 5.

#### B1. Businesses
- [x] List and detail: plan, shops, registers, members, usage, status, AI balance.
- [x] Suspend and reactivate; change plan; extend trial.

#### B2. Payments
- [x] **Record manual payments and AI top-ups** (needed until M-Pesa go-live), attributed to the operator who
      entered them.

#### B3. Impersonation
- [x] Audited, time-limited impersonation with a banner visible on every page while it lasts.
- [x] While impersonating, the operator acts with the impersonated user's permissions only; platform-operator
      rights (template edits, `Gate::before`) don't apply inside the business session.
- [x] Platform-operator rights return only when impersonation ends (manually or at its time limit).

#### B4. Operator command
- [x] **`platform:create-operator`**, a console command that creates a platform operator outside every business.

#### B5. Audit
- [x] Every console action (reads across businesses included) is written to the audit log with the operator,
      the business and the action.

#### B6. Role templates (links Part A to the console)
- [x] A **Templates** screen under `/platform/roles`: view and edit the shared templates (manager, cashier,
      inventory-clerk, accountant, customer) and the owner role's permissions. This is the only place they can be
      changed.
- [x] Editing a template changes what **new** businesses get and what "Reset to default" applies; it doesn't
      silently rewrite existing copies. The screen says how many businesses hold a copy and offers an explicit,
      confirmed **"Apply to all copies"** action (audited) for platform-wide changes.
- [x] Per business (from B1): view its roles, and reset a copied role to default on the business's behalf
      (audited).

---

## Schema

- [x] `roles.is_active` boolean, default true.
- [x] `roles.template_id` nullable, foreign key to `roles` (`nullOnDelete`).
- [x] Five new permissions (data, through `PermissionSeeder`).
- [x] A suspension status on businesses.
- [x] An impersonation log.
- [x] Every migration additive or carrying data across; rehearsed on `import_testing` with counts before and
      after; snapshot before running on UAT.

---

## Acceptance criteria

### Part A: Roles per business

**Isolation**
- [x] Business A's owner gets a 404 for viewing, editing, switching, resetting or deleting business B's roles
      (web and API), and B's roles are unchanged.
- [x] No business can change a shared template or the owner role; after a business's attempt, every other
      business's roles and permission checks are unchanged. The platform operator can (through the console).
- [x] `roles.full-access` alone can't delete, rename or switch off the owner role or a template.

**Escalation**
- [x] A non-owner with `roles.update` can't add to a role a permission they don't hold, can't edit or switch off
      a role they hold, and can't give an inactive role to anyone.
- [x] An owner can edit any role of their business except `admin`.

**Templates**
- [x] A copied template can't be deleted or renamed (web and API refuse with a clear message, 403 or 422).
- [x] "Reset to default" sets the copy's permissions to the template's current permissions, only in that
      business, and is refused for custom roles.
- [x] A new business gets copies of manager, cashier, inventory clerk, accountant and customer, each linked to
      its template.

**Active and inactive**
- [x] A person whose only role is inactive has none of its permissions on the web, the flat API and `/api/v1`,
      and their sign-in and `/api/user` permissions leave them out; switching the role back on restores them with
      no change to the assignment.
- [x] The owner role can't be switched off. Switching a role is recorded in the audit log.
- [x] Switching a role off first shows a SweetAlert confirmation saying the people who hold it won't be able to
      do anything in the system with it, with how many hold it; cancelling leaves the role active, and a request
      without the confirmation is refused (422). Switching it back on needs no confirmation.
- [x] Every role screen shows a role's true active state.

**Permissions**
- [x] `attributes.view`, `attributes.create`, `attributes.update`, `attributes.delete` and `stock.view` exist;
      owners and managers (copies included) hold them after `db:seed --class=PermissionSeeder` and
      `--class=RoleSeeder`.
- [x] Every attribute action is refused (403) without its permission; the Attributes and Stock menu entries show
      for the roles that hold them.
- [x] No `@can()` in the views names a permission that doesn't exist (a test lists them).

**Names and screens**
- [x] Two businesses can each have a role with the same name; a business can't create `admin` or a second role
      with a template's name; create and edit apply the same name rule.
- [x] The owner shows as "Owner" and the templates by their display names; no business screen says
      "Super Admin" or shows a fictitious state.

### Part B: Platform console

- [x] Only platform operators reach `/platform`; a business owner gets a 404 (or 403) for every `/platform` route.
- [x] Impersonation is logged and shown (banner), and ends at its time limit.
- [x] While impersonating, the operator has exactly the impersonated user's permissions: anything that user
      can't do is refused, `Gate::before` doesn't pass, and `/platform` routes are unavailable until impersonation
      ends.
- [x] Suspension blocks web, both APIs and sync. *(Sync doesn't exist yet (Phase 7); it goes through the same
      tenant resolution, which never chooses a suspended business.)*
- [x] Manual payments are attributed to the operator who entered them.
- [x] The operator command creates a user outside every business.
- [x] Editing a template in the console changes new businesses' copies and "Reset to default", and leaves
      existing copies unchanged unless "Apply to all copies" is confirmed.
- [x] Every console action, including cross-business reads, appears in the audit log.

### Contract and checks
- [x] Role names and permission strings in API responses are unchanged (flat API and `/api/v1` contract tests
      pass).
- [x] New routes and actions (role screens and `/platform`) added to the isolation resource matrix; isolation
      suite green, with the console's cross-business reads audited and covered by tests.
- [ ] Pint, the full suite (SQLite and MySQL 8) and both API contract test sets pass.

---

## Risks

- **The console as a way round isolation:** every action is audited, impersonation is time-limited and
  visible, and operator rights don't carry into an impersonated session.
- **Locking people out:** switching off a role used by everyone in a business. Mitigation: the owner role can't
  be switched off, and the SweetAlert confirmation says how many people hold the role and that they won't be
  able to do anything with it.
- **Platform-wide template changes:** a template edit applied to all copies affects every business at once.
  Mitigation: not applied to existing copies by default; "Apply to all copies" is explicit, confirmed and audited.
- **Permission caching:** Spatie caches permissions; switching a role, resetting it, applying a template change
  or suspending a business must clear the cache, or the change shows late.
- **Flutter:** the app caches permissions client-side (`authorization_helper.dart`); a switched-off role takes
  effect in the app at its next sign-in or profile refresh. Since Phase 3 the owner's role is reported as
  `admin` (was `super-admin`); check whether the app looks for `super-admin` anywhere, since that name now means
  only the platform operator.

## Rollout (UAT)

1. Snapshot: `php artisan db:snapshot`.
2. `php artisan migrate` (`roles.is_active`, `roles.template_id`, business suspension status, impersonation log).
3. `php artisan db:seed --class=PermissionSeeder` then `--class=RoleSeeder` (the five new permissions, granted to
   owners and managers).
4. `php artisan platform:create-operator` for the first platform operator.

**Rehearsed on `import_testing` (22 October 2026):** snapshot `storage/app/snapshots/pos_saas_import_testing-20261022-154643.sql.gz`;
`migrate` ran the 8 pending migrations (the end of stage 3, Phase 4's billing tables and Phase 5's four); both seeders;
`platform:create-operator`. Roles 11 → 11, copies linked 5 of 5, roles active 11 of 11, grants 6 → 6, permissions
203 → 208, role permissions 669 → 696 (27 added, none removed: the 17 grants of the five new permissions, and 10
listed permissions the old templates never got, which the rollout rule grants to templates only), operators 0 → 1
(no membership), impersonations 0. Details in PROGRESS.md.

## Definition of done

- [ ] All acceptance criteria met, with tests.
- [x] Isolation suite green, including the new role routes and every `/platform` route.
- [ ] Pint, the full suite (SQLite and MySQL 8) and both API contract test sets pass.
- [ ] Rollout rehearsed on `import_testing`, then run on UAT after a snapshot.

## Points to confirm

None.
