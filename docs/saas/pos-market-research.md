# POS SaaS Market Research

> Research for converting this POS into a multi-tenant SaaS for the Kenyan / East African market.
> Prices are third-party figures unless stated otherwise, and several sources disagree. Treat them as
> indicative and check the vendor's page before relying on one. Researched 12 October 2026.
> The gap analysis describes the `main` branch.

## 1. Summary

**What the app does today (`main`)**

- A Laravel 12 retail POS with a Bootstrap admin UI and a Sanctum JSON API used by a separate Flutter
  till app.
- **Modules:** POS sales, receipts and PDF invoices; products and variations; inventory (intakes,
  adjustments, movements, low-stock alerts, snapshots); suppliers, purchase orders and purchase
  returns; customers and credit accounts; returns and refunds; expenses with approvals; cash
  registers; reports, a VAT engine and a VAT report; gapless invoice numbers; an audit log.
- **Integrations:** WooCommerce/Shopify sync, WhatsApp (Cloud API and Baileys), social posting and ads,
  and AI content.
- **It is a single-company system, not yet multi-tenant:**
  - There is no tenant (business/account) entity, and no `business_id` on any table.
  - Isolation is only by shop assignment (`shop_user`). A user with **no** shop assignments sees
    **every** shop.
  - Roles are global, and `super-admin` is effectively the shop owner.
  - Registration gives the new user no role.
  - Natural keys such as shop codes are unique across the whole system.
  - The only account removal is a self-delete with no data export.
- **Missing compared to the market:** subscription billing, eTIMS, M-Pesa collection at the till (M-Pesa
  is only *recorded* as a payment method), stock transfers between shops, CSV product import, device or
  register pairing, idempotent (retry-safe) sale creation for offline sync, loyalty and gift cards, and
  hardware integration beyond a browser-printable receipt.
- **Tenant leak:** the M-Pesa till number in the e-commerce order WhatsApp message is hard-coded
  (`NotifyCustomerOfEcommerceOrder`).

**Key takeaways**

- **The hierarchy is the same everywhere:** account → locations → devices/registers → staff with
  role-based PINs. Billing is per location, sometimes with a register or user add-on. `main` has shops
  and staff but **no account/business layer above shops** and no devices/registers as billable,
  pairable units.
- **Pricing works the same way:** a free or cheap entry tier for one location, then per-location tiers
  that unlock multi-location, advanced inventory (POs, transfers), staff permissions and integrations.
  Global players also earn on **card processing**, which isn't available to us. In Kenya the market
  prices **per till at about KES 1,500–5,000 a month**.
- **Offline selling is expected but deliberately limited.** Leaders allow offline *sale capture* and
  queue the sales for upload. Card payments, back-office work and some payment types are blocked
  offline, and losing unsynced sales is a known risk. For a Flutter till in Kenya, offline sale capture
  is close to an MVP requirement.
- **In Kenya, eTIMS and M-Pesa are table stakes.** eTIMS e-invoicing has applied to *all* businesses
  since 2023, and expenses without an eTIMS invoice are not tax-deductible. KRA checks returns against
  eTIMS data from 2026. Local competitors advertise "eTIMS ready", plus M-Pesa STK Push, Till and
  Paybill. Integration requires **KRA certification** (OSCU or VSCU), or a partnership with a certified
  integrator.
- **The app is already deep in back-office and marketing,** beyond most local competitors (WhatsApp
  invoices, e-commerce sync, AI, ads). The gaps are **tenancy itself**, **till-side basics** (offline,
  M-Pesa collection, hardware, onboarding) and **SaaS plumbing** (billing, plan limits, platform
  console).

## 2. Product Reviews

### Square (Square for Retail)

- **Hierarchy:**
  - Account → locations → devices (each paired to one location with a single-use *device code*, for
    per-device reporting) → team members.
  - Team members have a personal passcode and a *permission group*. Device codes sign a till in without
    sharing the owner's password, and can't open the dashboard or settings.
- **Pricing:** Free, Plus at $49 and Premium at $149 per location per month (US, after an October 2025
  change). Paid tiers also *lower card rates*. Premium reportedly adds cross-location returns, transfer
  orders and 24/7 phone support. Regional pages show older prices.
- **Feature gating:** location count, transfers, cross-location features and advanced permissions.
  Different permissions per employee historically needed a paid team add-on.
- **Onboarding:** sign up → identity verification (required before card payments) → link a bank
  account → order or connect hardware → a guided setup on device or dashboard → taxes and discounts →
  first sale.
- **Offline:** offline card payments are supported. Which tier includes them is unclear.
- **Payments:** Square card processing only; that's the main revenue source.
- **Hardware:**
  - Receipt printers, cash drawers, USB barcode scanners and keyboards.
  - USB plug-and-play on Stand/Register; printer profiles assign printers to tills.
  - The Retail app is iOS only, and Bluetooth scanners aren't supported on Register.
- **Tax/fiscal:** sales taxes are configured per item and location. No Kenyan fiscal integration.
- **Takeaways:** device codes and per-location billing; identity checks before payments go live.

### Shopify POS

- **Hierarchy:** store (account) → locations → POS devices → staff. Staff have POS roles (role-based
  only, no per-person permissions), with a default "Associate" role.
- **Pricing:**
  - POS Lite is free with every Shopify plan.
  - POS Pro is about $89 per location per month, or about $79 billed annually.
  - Shopify Plus includes 20 Pro locations.
  - Card rates fall on higher plans.
- **Feature gating:** **staff roles and permissions only on Pro** (Lite limits staff), plus advanced
  inventory, omnichannel, and reporting per staff member.
- **Onboarding:** built on the online store. Products, inventory and customers are already shared, so
  POS is "add a location and a device".
- **Offline:** limited offline payments. They must be **enabled in settings and granted to staff by
  role**, and swiped or manually typed cards are blocked. A third party reports a per-device offline
  switch recorded in an activity log.
- **Hardware:** being moved to Verifone terminals (announced January 2026); POS Go is reported as
  retired.
- **Takeaways:**
  - Gating permissions behind the paid tier.
  - Treating offline selling as a *permission* with an audit trail.
  - Sharing one catalogue across online and in-store, which this app's WooCommerce/Shopify sync partly
    does.

### Lightspeed Retail

- **Hierarchy:** account → locations (shops) → registers → staff.
- **Pricing:**
  - Basic $89, Core $149, Plus $289 per month; Enterprise custom.
  - Extra registers about $59 per month, extra locations $69–269.
  - Annual billing saves 15–20% (third-party, some possibly dated).
- **Feature gating:**
  - Basic: 1 register, inventory, customers, staff, reports.
  - Core and above: **multi-location, e-commerce and advanced reporting**.
- **Offline (X-Series), described as a "backup, not a complete solution":**
  - Switches on after 2 minutes without a connection.
  - Needs a completed first sync and a signed-in user.
  - Sales upload on reconnect, with a manual "Sync Offline Sales" option.
  - **Clearing the browser cache loses unsynced sales.**
  - Offline terminal payments aren't linked to sales and don't update inventory; declined offline
    payments become losses.
- **Takeaways:**
  - Price by register *and* location.
  - Offline needs a durable local store (not browser cache), an explicit sync status, and server-side
    de-duplication.
  - Payments taken offline must be reconciled to sales.

### Loyverse

- **Hierarchy:** account → stores → POS devices → employees with access levels.
- **Pricing (freemium):** the core POS is free. Paid add-ons:
  - Employee management about $5 per employee per month.
  - Advanced inventory about $25 per store per month (POs, valuation reports).
  - Unlimited sales history about $5–9 per store per month.
  - Integrations.

  Each has a 14-day trial with no card. Sources disagree on exact prices.
- **Feature gating:** **à-la-carte add-ons, priced per store or per employee,** not tiers.
- **Onboarding:**
  - Create an account online and install the app on phone, tablet or PC, in minutes.
  - Then set up products, taxes and the store in the web back office (1–2 hours).
  - Then staff, hardware pairing, stock import and barcodes.
  - Then test sales.
- **Offline:** **included free**. Sales are stored and synced on reconnect; offline covers sale
  capture, not back-office work.
- **Hardware:** Bluetooth, USB, Ethernet and Wi-Fi receipt printers using **ESC/POS
  (Epson-compatible)** commands; kitchen printers; Sunmi built-in printers; printer-driven cash
  drawers; barcode scanners.
- **Takeaways:**
  - Free core plus paid add-ons, with no-card trials.
  - ESC/POS from a mobile app.
  - Offline in the free tier. This is the closest model to small Kenyan shops, and Loyverse is used
    locally.

### Odoo Point of Sale

- **Hierarchy:** database (tenant) → companies → POS configurations (one per shop or till) → sessions →
  employees signing in by **PIN or badge** (multiple employees per session).
- **Pricing:**
  - POS alone is free with unlimited users.
  - With Inventory or Accounting, Standard is about $31 and Custom about $47 per user per month, billed
    annually.
  - Hardware and the **IoT Box** are extra.
- **Offline:** designed to keep selling offline and sync on reconnect. Printing works offline only on
  the local network.
- **Hardware:** the IoT Box (Raspberry Pi based) connects printers, scanners, drawers and displays. One
  box can serve several tills.
- **Cash control:** opening and closing sessions with cash counts. Gaps such as blind counting,
  per-cashier shifts and manager sign-off for voids are filled by third-party modules.
- **Tax/fiscal:** has a Kenyan fiscal localisation in its documentation; I didn't check the details of
  its eTIMS support.
- **Takeaways:**
  - Session-based cash control.
  - Several employees per till session using PINs.
  - Per-user pricing pushes customers to share logins, a pitfall for audit.

### Kenyan / East African products

| Product | Price (indicative) | Positioning | M-Pesa | eTIMS | Offline |
|---|---|---|---|---|---|
| **SokoniPOS** | KES 1,500 (1 outlet, 3 users) / 3,500 (3 outlets, 10 users) / 7,500 (enterprise); setup fee | Small and medium retail | STK Push, Till and Paybill on all plans | "eTIMS ready" (certification path not stated) | Claimed |
| **Uzapoint** | About KES 1,500–4,000 per till | Hospitality, small retail; mobile and web | Direct Daraja | Not confirmed | Not stated |
| **Veira** | Free to KES 2,000–5,000 per till (sources conflict) | Restaurants and retail; Android app | Direct Daraja | Built in: submitted in the background, QR on receipt, **queued offline and sent in order** | eTIMS queueing |
| **Zed Payments** | About KES 3,000–8,000 per till | Supermarket chains, multiple outlets | Yes | Not confirmed | Not stated |
| **Pesapal POS / RACK** | About KES 5,000 per month, plus hardware KES 20–40k | Payments-led, bundled with fees | Yes, plus cards | Not stated | Not stated |
| **Loyverse** | Free core | Very common with micro-retail | Recorded only | No | Yes |

Most of this comes from vendor pages or one comparison guide, and the SokoniPOS page is on an
unofficial-looking host. **Kenyan buyers expect M-Pesa STK Push, eTIMS invoices with a QR code,
per-till monthly pricing and Android hardware.** Setup fees and hardware bundles are common.

### Kenya fiscal compliance: KRA eTIMS

- **Who:** all businesses, VAT-registered or not, since 1 September 2023 according to KRA's notice.
  - Expenses without an eTIMS invoice haven't been deductible since 1 January 2024.
  - KRA checks income and expense returns against eTIMS data from 1 January 2026.
  - Buyers lose the deduction if the seller doesn't capture the buyer's PIN.
  - Sources conflict on whether the KES 5 million turnover exemption still applies.
- **How:** system-to-system through **OSCU** (online invoicing systems) or **VSCU** (bulk or
  not-always-online systems, software-based on the POS).
  - Self-integrators and third-party vendors must be **certified by KRA**: sandbox testing, a bio-data
    form, business and tax documents, proof of at least three technical staff, and an architecture
    document.
  - KRA publishes a list of approved integrators; the latest one I found is dated 26 January 2026.
  - Small traders can use free KRA tools (eTIMS Lite web, app, USSD \*222#).
- **Implication:** a SaaS POS selling to Kenyan businesses with corporate customers *must* produce
  eTIMS invoices. The options are to get certified, partner with a certified integrator, or tell
  customers to use KRA's free tools alongside the POS (a weak position).

## 3. Open-Source References

These were used for patterns only; no code was copied.

- **NexoPOS** (Laravel, Vue, Tailwind; GPL-3.0):
  - The core covers procurement, stock adjustments, roles and permissions, taxes, customer groups,
    loyalty, coupons and reports.
  - **Multi-store, SMS and staff are paid modules.** This is open core with a marketplace, the same
    split as SaaS tier gating.
  - It has a module system for extensions.
  - It's the closest stack match, and it confirms the market treats *multi-store* as a premium feature.
- **Open Source Point of Sale (OSPOS)** (PHP, CodeIgniter 4, MySQL; MIT plus a visible footer
  requirement):
  - VAT/GST and multi-tier tax; quotations and invoices.
  - Suspended sales; gift cards and rewards.
  - Item kits and attributes; barcode generation.
  - Receivings and purchase orders; expenses.
  - Daily cash-up; CSV import and export.
  - Permissions per module; GDPR-ready.
  - It sets the baseline of features a mature open-source POS has. **Suspended sales, gift cards, kits,
    CSV import and barcode labels** are all in it and missing here.
- **Tenancy packages for Laravel:**
  - **stancl/tenancy** has many features: automatic connection, cache and filesystem switching; single
    or multiple databases.
  - **spatie/laravel-multitenancy** is minimal: tenant identification, switching, and tenant-aware jobs
    and commands.
  - Both support **single-database (tenant id column)** and **database-per-tenant** setups.
  - `main` has no tenant layer yet, so this is the first architecture decision (see section 7).

## 4. Common Patterns

- **Tenant hierarchy:** account/business → locations → registers/devices → staff. Staff hold *roles*
  (not per-person permissions), sign in to a till with a **PIN** on a device paired to a location, and
  use full credentials only for the back office.
- **Pricing unit:** global players price **per location** with optional register add-ons. Kenyan vendors
  price **per till per month**. Odoo prices per user, and Loyverse per employee for staff features.
  Tiers almost always gate:
  1. The number of locations and registers.
  2. Advanced inventory (POs, transfers, valuation).
  3. Staff roles and permissions, and time tracking.
  4. E-commerce and integrations.
  5. Advanced reporting and support level.
- **Monetisation:** Square and Shopify subsidise software with card fees. Loyverse is free with paid
  add-ons. Kenyan vendors charge subscriptions, setup fees and hardware.
- **Trials:** 14 days with no card is typical (Loyverse add-ons, and common elsewhere).
- **Onboarding:** sign up → create the business and first location → payments setup and identity
  check → catalogue (manual, import or from the online store) → taxes → staff → hardware pairing → test
  sale. Leaders provide a **guided setup checklist**.
- **Offline:** sale capture continues; payments that need the network are limited; catalogue and staff
  must have been synced before going offline. On reconnect, sales upload in order with retry and an
  errors view. Back-office work needs a connection.
- **Hardware:** ESC/POS thermal printers over USB, Bluetooth or network; printer-driven or USB cash
  drawers; barcode scanners acting as keyboards; customer displays. Mobile-first vendors print from the
  device, and Odoo uses a local hub (IoT Box).

## 5. Patterns to Adopt / Pitfalls to Avoid

**Adopt**

1. **An explicit tenant (business/account) above shops,** with the owner as a business-level role
   distinct from the platform operator. Every product reviewed separates "the merchant account" from
   "the platform".
2. **Registers/devices as a real entity:** pair a till with a single-use **device code** tied to a shop.
   It's the billing unit (per till), gives per-device reporting, and avoids shared passwords.
3. **Staff PIN sign-in at the till,** with full login for the back office. Several cashiers per register
   session, each sale attributed to the cashier.
4. **Per-till or per-location pricing in KES with tiered feature gates,** plus a 14-day trial without a
   card. It matches local expectations, and the plan table can be data, not code.
5. **Offline sale capture in the Flutter till:**
   - A durable local queue, not a browser cache.
   - A **client-generated UUID per sale** so retries are idempotent.
   - Ordered upload and a visible sync status.
   - An errors view and server-side conflict rules (stock can go negative, prices are taken as of the
     sale).
6. **Offline as a permission:** an owner setting and a role permission, every offline session logged
   (Shopify).
7. **eTIMS as a core feature, not a bolt-on:**
   - Submit in the background after a sale, print the QR code and control unit details on the receipt.
   - Queue and send in order when offline (Veira's approach).
   - Track a branch ID per shop.
8. **M-Pesa STK Push at checkout,** plus Till/Paybill confirmation callbacks matched to the sale.
   Per-shop shortcode settings (fixes the hard-coded till).
9. **A guided onboarding checklist:** business → shop → tax settings → products (CSV import) → invite
   staff → pair a till → test sale.
10. **Session-based cash control:** opening float, blind closing count, variance, and manager approval
    above a threshold. Cash registers exist here; extend them.
11. **Data export before account or business deletion,** with a grace period. Expected under data
    protection law, and a common feature request when merchants leave.

**Avoid**

1. **"No restriction means everything."** On `main`, a user with no shop assignments sees every shop.
   In a multi-tenant system the default must be *no access*, never *all access*.
2. **Per-user pricing.** It pushes staff to share logins, which breaks the audit trail and per-cashier
   reports (a known Odoo pain point).
3. **Offline storage that a cache clear can wipe** (Lightspeed's warning), and offline payments not
   linked to a sale or inventory.
4. **Building on card processing for revenue.** Stripe doesn't serve Kenya, and M-Pesa margins don't
   fund free software. Price the software.
5. **Claiming "eTIMS ready" without certification or a certified partner.** KRA certifies integrators,
   and buyers lose tax deductions if invoices aren't valid.
6. **Locking basic multi-user access behind an add-on.** Even micro-shops have 2–3 staff; SokoniPOS's
   entry tier includes 3 users.
7. **Hard-coded per-tenant values** (payment numbers, sender IDs, branding) and **system-wide unique
   keys** on values tenants type themselves (shop codes, category names). Both are cross-tenant leaks
   or collisions.
8. **Overloading the entry tier with costly features** (WhatsApp sessions, AI). Gate features by what
   they cost to run.

## 6. Preliminary Feature Gap Analysis (MVP vs post-launch)

Describes the `main` branch.

| Area | `main` today | Market expectation | Priority |
|---|---|---|---|
| Tenant (business/account) layer: `business_id` on owned data, isolation for every query, per-tenant unique keys | None; isolation only by shop assignment; system-wide unique keys | Fundamental to SaaS | **MVP** (foundation) |
| Default-deny shop access (no assignment = no access) | No assignment = **all** shops | Standard | **MVP** (security) |
| Owner vs platform-operator roles; roles per tenant | Global roles; `super-admin` is the shop owner | Standard | **MVP** |
| Self-signup creating a tenant with an owner role | Registration assigns no role and creates nothing | Standard | **MVP** |
| Account deletion and data export (export, grace period, owner-only deletion of staff) | Breeze self-delete only; no export | Expected (data protection law) | **MVP** |
| Subscription billing (plans, trial, M-Pesa or card collection, invoices, dunning) | None | Standard | **MVP** |
| Plan limits and feature gates | None | Standard | **MVP** |
| Platform console (tenants, plans, manual payments, suspension) | None | Standard for SaaS | **MVP** |
| Onboarding checklist and CSV product import | None | Standard | **MVP** |
| Per-tenant payment settings (till/Paybill) | Hard-coded in one message | Standard | **MVP** (bug) |
| M-Pesa STK Push at checkout plus C2B confirmation | Recorded as a payment method only | Table stakes in Kenya | **MVP** |
| KRA eTIMS invoicing | None | Table stakes for business customers | **MVP**, at least via a certified partner; own certification can follow |
| Offline sale capture plus idempotent sync API | Not supported server-side (no client sale UUID) | Expected | **MVP** (server-side idempotency); full offline UX can be staged |
| Thermal receipt printing (ESC/POS) and cash drawer | Browser-printable receipt | Expected | **MVP** in the Flutter app |
| Device/register pairing and staff PINs | Cash registers per user per shop; no devices | Common | Post-launch (needed for per-till billing, so early) |
| Stock transfers between shops | None | Standard multi-store | Post-launch (high) |
| Barcode label printing | Barcode field and lookup only | Common | Post-launch |
| Suspended/parked sales, layaway | Not found | Common | Post-launch |
| Loyalty, gift cards, coupons | Pricing rules only | Common, often a paid tier | Post-launch |
| Blind cash count, variance approval, per-cashier shifts | Partial (cash registers) | Common | Post-launch |
| Restaurant features (tables, kitchen printers) | None | Separate vertical | Out of scope unless targeted |
| Inventory depth (POs, suppliers, adjustments, credit, expenses, VAT report, audit) | **Strong** | Often paid tiers | Already a differentiator |
| WhatsApp invoices, e-commerce sync, AI and ads | **Strong** | Rare locally | Differentiator; gate to higher tiers |

## 7. Recommendations for Architecture

1. **Add a tenant layer: single database, tenant id column, global scopes.**
   - Introduce a `Business` (account) that owns shops, staff and the shared lists (suppliers,
     categories, attributes, pricing rules, expense categories and so on).
   - Add `business_id` where a table isn't already reached through `shop_id`.
   - Apply global scopes to every shop- and business-owned model.
   - Change shop access to default-deny.
   - Scope unique keys per business.
   - This is the cheapest model for many small tenants. stancl/tenancy or spatie/laravel-multitenancy
     can be adopted, or a small in-house scope layer. Revisit database-per-tenant only for an enterprise
     tier that needs it.
   - Make queued jobs, webhooks and console commands carry the tenant explicitly, since they run with no
     signed-in user.
2. **Split roles:** a platform operator (super-admin, outside every tenant) versus a business owner and
   staff roles **per business** (for example Spatie "teams" keyed on the business). Self-signup creates
   the business and its owner.
3. **Extend the hierarchy to Business → Shop → Register (device) → Staff:**
   - Registers are paired by a single-use code and carry eTIMS device details and printer settings.
   - Cash sessions belong to a register, not just a user.
   - The register is the per-till billing unit.
4. **Billing as its own module:**
   - `plans` as data (limits plus features), `subscriptions` per business (storing the price at signup),
     and platform invoices and payments kept separately from tenant data so they survive a tenant's
     deletion (5-year retention).
   - M-Pesa STK and Paybill first, through Daraja or an aggregator; cards and M-Pesa Ratiba
     auto-renewal later.
   - One entitlement check (`hasFeature`, `limit`) used by middleware, policies, the UI and background
     jobs.
   - API error codes the Flutter app can show (`plan_upgrade_required`, `subscription_inactive`).
5. **Make sales offline-ready on the server before building the offline UI:**
   - Client-generated sale UUID with retry-safe create.
   - Accept the sale's original timestamp, the register and the cashier.
   - Set rules for stock going negative and for price differences.
   - Write a stock movement for every sale (currently missing), so offline sales reconcile.
6. **eTIMS behind an interface (`FiscalProvider`)** with a null provider for non-Kenyan or exempt
   tenants:
   - Start with a certified-integrator adapter.
   - Per-shop branch ID and per-register device details.
   - An asynchronous submit queue that sends in order, with retries.
   - Store the signature and QR on the sale, and show the fiscal status in the UI.
   - Decide early whether to pursue certification as a vendor; it takes time and staff.
7. **Payments at the till behind an interface (`PaymentCollector`):**
   - M-Pesa STK, Till/Paybill confirmation and cash first; cards later.
   - Per-shop credentials encrypted at rest (the pattern used for social tokens).
   - Callbacks matched to a pending sale payment.
8. **Hardware lives in the Flutter app:**
   - ESC/POS over Bluetooth, USB or network; printer-driven cash drawers; scanners as keyboard input.
   - The server supplies a structured receipt (JSON) that the app renders, rather than HTML.
9. **Onboarding as a state machine on the business** (setup steps completed), driving a dashboard
   checklist and trial nudges.
10. **Tenant lifecycle and compliance:**
    - Data export, owner-only deletion of staff, a grace period before a business is deleted, and
      erasure of personal data while business records are kept.
    - Check registration as a data controller/processor with Kenya's Office of the Data Protection
      Commissioner (not verified in this research).
    - Document data residency and backups.

## 8. Sources

**Square**
- [Square for Retail pricing (NerdWallet, 2026)](https://www.nerdwallet.com/business/software/learn/square-fees)
- [Square for Retail (ERP Research)](https://erpresearch.com/erp-add-ons/pos/square-for-retail)
- [Square for Retail pricing (ERP Research)](https://erpresearch.com/erp-add-ons/pos/square-for-retail/pricing)
- [Square POS pricing (Costbench)](https://www.costbench.com/software/retail-pos/square-pos/)
- [Square for Retail plans (AU)](https://squareup.com/help/au/article/6921)
- [Square for Retail plans (CA)](https://squareup.com/help/ca/article/6921)
- [Manage devices by location](https://square.com/help/ca/en/article/5586-manage-devices-by-location)
- [Set up device codes](https://squareup.com/help/au/en/article/8339-set-up-device-codes)
- [Square get-started guide](https://square.com/help/gb/en/article/5123-square-get-started-guide)
- [Device code and admin rights (community)](https://community.squareup.com/t5/Troubleshooting/Device-Code-and-Admins-rights/m-p/135166)
- [Set up hardware with Square for Retail](https://squareup.com/help/article/6385)
- [Get started with Square for Retail](https://square.com/help/us/en/article/5776-get-started-with-square-for-retail)
- [Square hardware compatibility guide (Spartan POS)](https://spartanpos.com/pages/square-pos-hardware-compatibility-guide)

**Shopify POS**
- [Shopify POS pricing guide 2026 (Style Factory)](https://www.stylefactoryproductions.com/blog/shopify-pos-pricing)
- [Shopify POS pricing (Qualimero)](https://qualimero.com/en/blog/shopify-pos-pricing)
- [Shopify POS fees (TrueProfit)](https://trueprofit.io/blog/shopify-pos-fees)
- [Shopify POS plans (Costbench)](https://costbench.com/software/retail-pos/shopify-pos/)
- [Shopify POS staff management overview](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/staff-management/point-of-sale-staff-management-overview?ld=1)
- [Shopify POS offline payments](https://help.shopify.com/en/manual/sell-in-person/shopify-pos/selling-offline/offline-payments?r_done=1)
- [Shopify per-device offline checkout (PowerCommerce)](https://powercommerce.com/blogs/shopify-updates/how-shopify-pos-s-per-device-offline-checkout-keeps-sales-flowing-during-outages)
- [Shopify POS hardware 2026 (First Pier)](https://www.firstpier.com/resources/shopify-pos-compatible-hardware)

**Lightspeed**
- [Lightspeed Retail (Costbench)](https://costbench.com/software/retail-pos/lightspeed-retail)
- [Lightspeed hidden costs (Costbench)](https://www.costbench.com/software/retail-pos/lightspeed-retail/hidden-costs/)
- [Lightspeed Retail pricing (ERP Research)](https://erpresearch.com/erp-add-ons/pos/lightspeed-retail/pricing)
- [Lightspeed review 2026 (Business Expert)](https://www.businessexpert.co.uk/payment-processing/lightspeed-review/)
- [Lightspeed (Merchant Maverick)](https://merchantmaverick.com/?p=11896)
- [Syncing offline sales, X-Series](https://x-series-support.lightspeedhq.com/hc/en-us/articles/25534043015195-Syncing-Offline-Sales-in-Retail-POS-X-Series)
- [X-Series offline mode](https://x-series-support.lightspeedhq.com/hc/articles/25533690950427)
- [Offline payments, R-Series](https://golf-support.lightspeedhq.com/hc/en-us/articles/29102071881115-Processing-offline-payments-through-Lightspeed-Retail-R-Series)
- [O-Series offline](https://o-series-support.lightspeedhq.com/hc/en-us/articles/31329361292571)
- [Saved sales sync](https://shopkeep-support.lightspeedhq.com/support/advanced/sync-backup)

**Loyverse**
- [Loyverse](https://Loyverse.com)
- [NexoPOS vs Loyverse pricing (InstaPods)](https://instapods.com/apps/nexopos/vs/loyverse/)
- [Loyverse (xpay)](https://www.xpay.sh/agent-ready-index/loyverse-pos/)
- [Loyverse review (MobileTransaction)](https://www.mobiletransaction.org/loyverse-review/)
- [Loyverse (Software Advice)](https://www.softwareadvice.com/retail/loyverse-profile)
- [Loyverse analysis (Zoftware)](https://zoftwarehub.com/en-sa/products/loyverse/zoftware-analysis)
- [Loyverse Bluetooth receipt printer](https://help.loyverse.com/help/bluetooth-receipt-printer)
- [Loyverse other printers on Android](https://help.loyverse.com/help/how-set-other-printers-loyverse-pos)
- [Loyverse hardware bundle (Spartan POS)](https://spartanpos.com/pages/loyverse-pos-hardware-bundle)

**Odoo**
- [Odoo POS (Gappsy)](https://gappsy.com/tools/odoo-point-of-sale/)
- [Odoo POS (ITQlick)](https://www.itqlick.com/odoo-point-of-sale)
- [Odoo POS hardware](https://www.odoo.com/ca_ES/app/point-of-sale-hardware)
- [Multi-employee per session (Cybrosys)](https://www.cybrosys.com/blog/how-the-multi-employee-per-session-feature-works-in-odoo-16-pos-app)
- [Odoo 18 POS (Cybrosys book)](https://cybrosys.com/odoo/odoo-books/v18-ce/point-of-sale-and-restaurant)
- [POS manager close session module](https://apps.odoo.com/apps/modules/19.0/pos_manager_close_session)
- [POS cashier shift control (Ecosire)](https://ecosire.com/es/apps/odoo/pos-cashier-shift-control)
- [POS retail multi session module](https://apps.odoo.com/apps/modules/14.0/pos_retail_multi_session)
- [Odoo Kenya fiscal localisation](https://www.odoo.com/documentation/20.0/applications/finance/fiscal_localizations/kenya.html)

**Kenya market**
- [M-Pesa POS integration Kenya 2026 (Paybill KE)](https://paybillke.com/guides/mpesa-pos-integration-kenya-2026)
- [SokoniPOS](https://shocpcms.vps.webdock.cloud/)
- [RACK handheld POS (Pesapal)](https://share.pesapal.com/shop/?p=381)
- [POS software for small businesses in Kenya (IntaSend)](https://intasend.com/payments/point-of-sale-software-for-small-businesses-in-kenya)
- [Veira (PeerPush)](https://peerpush.com/p/veira)
- [eTIMS Kenya guide 2026 (Veira, DEV Community)](https://dev.to/veira_59608bdb2de0135bea1/etims-kenya-the-complete-guide-for-business-owners-in-2026-and-why-your-pos-system-is-the-key-84p)
- [RoyalPOS Kenya](https://royalpos.in/pos-software-in-kenya)
- [Restaurant POS and mobile money Kenya 2026 (Kolonell)](https://kolonell.com/en/blog/restaurant-pos-mobile-money-kenya-2026)

**KRA eTIMS and tax**
- [eTIMS learn more (KRA)](https://etims.kra.go.ke/main/signup/indexLearnMore)
- [eTIMS taxpayer guidelines 2024 (KRA)](https://www.kra.go.ke/images/publications/eTIMS-Taxpayer-Guidelines_2024.pdf)
- [eTIMS confirmation document (KRA)](https://www.kra.go.ke/images/publications/eTIMS-confirmation-document.pdf)
- [Approved eTIMS third-party integrators, 26 January 2026 (KRA)](https://www.kra.go.ke/images/publications/List-of-Approved-eTIMS-3rd-party-integrators-as-at-26th-January-2026.pdf)
- [Onboarding of non-VAT taxpayers on eTIMS (KRA notice)](https://www.kra.go.ke/news-center/public-notices/2071-on-boarding-of-non-vat-taxpayers-on-etims)
- [Kenya eTIMS API integration guide](https://invoicedataextraction.com/blog/kenya-etims-api-integration-guide)
- [eTIMS (KPMG Kenya, 2026)](https://assets.kpmg.com/content/dam/kpmgsites/ke/pdf/thought_leaderships/tax/2026/eTIMS.pdf.coredownload.inline.pdf)
- [BDO East Africa eTIMS newsletter](https://www.bdo-ea.com/en-gb/insights/bdo-east-africa-kra-etims-solutions-and-compliance-newsletter)
- [Demystifying eTIMS (CM Advocates)](https://cmadvocates.com/blog/demystifying-etims-why-every-business-in-kenya-should-pay-attention/)
- [KRA to validate income and expenses (EY, December 2025)](https://taxnews.ey.com/news/2025-2471-kenya-revenue-authority-to-validate-income-and-expenses-in-income-tax-returns)
- [How 2026 tax changes will affect businesses (Business Daily)](https://www.businessdailyafrica.com/bd/opinion-analysis/letters/how-2026-tax-changes-will-affect-businesses-5297674)
- [KRA eTIMS income and expense validation guide (Beancount)](https://beancount.io/blog/2026/07/22/kenya-kra-etims-income-expense-validation-guide)
- [KRA eTIMS: no invoice, no deduction (Faidi HR)](https://www.faidihr.com/blog/kra-etims-the-no-invoice-no-deduction-era)

**M-Pesa Ratiba**
- [M-Pesa Ratiba API docs (mirror)](https://glama.ai/mcp/servers/@JacksCodeVault/mpesa-daraja-mcp/blob/e875f3b350a0119c928c623c4e0cdd8999a5d329/daraja_docs_v3/docs/MpesaRatiba.md)
- [Safaricom standing orders (Eastleigh Voice)](https://eastleighvoice.co.ke/m-pesa%20transactions/79169/safaricom-rolls-out-standing-order-feature-for-m-pesa-users)

**Open source and tenancy**
- [NexoPOS (Laravel News)](https://laravel-news.com/nexopos-point-of-sale-for-laravel)
- [NexoPOS (LinuxLinks)](https://www.linuxlinks.com/nexopos-web-based-point-sale-system/)
- [OSPOS feature datasheet](https://github.com/opensourcepos/opensourcepos/wiki/Complete-feature-datasheet)
- [OSPOS on GitHub](https://www.Github.com/opensourcepos)
- [Tenancy for Laravel: package comparison](https://www.tenancyforlaravel.com/docs/v3/package-comparison)
- [Tenancy for Laravel](https://tenancyforlaravel.com/)
- [spatie/laravel-multitenancy docs](https://docsearch.algolia.com/mcp/docs/repo/spatie/laravel-multitenancy)
