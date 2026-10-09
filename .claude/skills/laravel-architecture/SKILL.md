---
name: laravel-architecture
description: Code organization and clean-code standards for this POS codebase — where a class belongs (Actions vs Services vs Jobs vs Observers vs Events vs Policies vs FormRequests vs Enums), thin controllers, method length and single responsibility, naming conventions, comments that explain WHY, dependency direction between layers, and enum-instead-of-magic-strings. Use when creating any new class, deciding where logic should live, splitting a fat controller or service, or reviewing structure, naming, and readability.
---

# Architecture & Coding Standards (POS)

Sources: [0.1 folder_structure_guide.md](../../../.ai/general/0.1%20folder_structure_guide.md),
[0.9 coding_standards_guide.md](../../../.ai/general/0.9%20coding_standards_guide.md).
Both are 🔴 mandatory for new features and refactors
([ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md)).

Query-efficiency guidance from §3 of the coding-standards guide lives in
[laravel-performance](../laravel-performance/SKILL.md).

## The real layout — put files where the siblings are

⚠️ [0.1](../../../.ai/general/0.1%20folder_structure_guide.md) describes deep per-domain folderization
(`Models/Payment/`, `Services/User/`, `Controllers/Api/V1/`). **This codebase is mostly flat with
selective sub-namespacing.** Match what exists:

```
app/
├── Actions/          88 single-purpose actions; flat + domain subfolders
│                     (Category/, Product/, Shop/, Supplier/, PurchaseOrder/,
│                      StockIntake/, PurchaseReturn/, PricingRule/, Campaign/,
│                      Social/, Baileys/)
├── Console/Commands/ artisan commands
├── Enums/            all constrained values (AdjustmentType, AlertStatus, AiProvider, …)
├── Http/
│   ├── Controllers/  FLAT for web + subdirs: Api/, Auth/, Baileys/
│   ├── Requests/     FormRequests (flat)
│   ├── Resources/    API resources
│   └── Middleware/
├── Jobs/             flat (sync, webhooks, campaign publishing)
├── Models/           FLAT — 67 models, no domain subfolders
├── Notifications/    + custom WhatsApp channel/messages
├── Observers/        model lifecycle hooks
├── Policies/         31 policies
├── Providers/
├── Services/         flat per domain + sub-namespaces:
│                     Ads/, Ai/, Integration/ (Shopify, WooCommerce), Social/
├── Support/          low-level helpers
└── View/Components/  Blade components
```

Views mirror resources in kebab-case folders (`resources/views/cash-registers/index.blade.php`), with
`layouts/`, `partials/`, `components/`, and `pdf/`.

Rules that follow from this:
- **Do not** create `app/Models/Sale/Sale.php`-style domain folders — models are flat.
- **Do not** create `Controllers/Api/V1/` — the API is unversioned (see [laravel-api](../laravel-api/SKILL.md)).
- A new service goes in `app/Services/` flat, unless it belongs to an existing sub-namespace
  (`Services/Integration/`, `Services/Ai/`, `Services/Ads/`, `Services/Social/`).
- A new action goes in `app/Actions/` — use a domain subfolder if one already exists for that domain.
- ⚠️ `app/Http/Startappz/Services/SMSservice.php` is a stray legacy path; don't add to it or copy it.

## Which construct do I reach for?

| Need | Use | Lives in |
|---|---|---|
| One well-defined operation, reusable | **Action** (`execute()`/`handle()`) | `app/Actions/` |
| Orchestrating several models/operations for a use case | **Service** | `app/Services/` |
| Slow, remote, or bulk work | **Job** (queued) | `app/Jobs/` |
| Model lifecycle side effect (stamping, cache bust, dispatch) | **Observer** | `app/Observers/` |
| Decoupled side effects after a domain fact | **Event + Listener** | `app/Events/`, `app/Listeners/` |
| Validation + request authorization | **FormRequest** | `app/Http/Requests/` |
| Can this user do this to this record | **Policy** | `app/Policies/` |
| Response shaping for the API | **API Resource** | `app/Http/Resources/` |
| A constrained set of values | **Enum** | `app/Enums/` |
| Reusable validation rule | **Rule** | `app/Rules/` |

Existing examples worth copying: `RecordStockMovement`, `ProcessPayment`, `ApproveReturn` (actions);
`ReportService`, `ReturnService`, `CreditAccountService`, `PricingService` (services); `SyncProductsJob`,
`ProcessOrderWebhookJob` (jobs).

🔴 **Check the class has a body before copying it.** Nine services are empty stubs — including
`SaleService`, `PaymentService`, `CustomerService`, `ExpenseService`, `NotificationService`,
`DashboardService`, `CostingService`, `AuditService` — along with ~20 models and 4 controllers. Sale and
payment logic actually lives inline in the controllers. Full list and the real call paths are in
[pos-domain-rules](../pos-domain-rules/SKILL.md).

## Controllers stay thin

Controllers handle HTTP: resolve input via FormRequest, authorize, delegate, return a response.

```php
class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
    ) {}

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        $sale = $this->saleService->create($request->validated());

        return redirect()->route('sales.show', $sale->uuid)
            ->with('success', __('Sale created.'));
    }
}
```

Reality check: some existing controllers are large and hold inline `DB::transaction` logic —
`SaleController` is 878 lines. That's the pattern you'll find, not the pattern to extend. When you add
to such a file, put new logic in the service/action and keep the new controller method thin; **don't
refactor unrelated methods** in the same file unless asked.

## Clean-code rules (🔴 mandatory)

- **Methods under 20 lines** of executable code, single responsibility. Extract private helpers —
  `ReportService` does this well (`applySaleFilters`, `applyWholesaleCondition`).
- Explicit types on every parameter and return; constructor property promotion for dependencies.
- PHPDoc on public methods (and on services/complex methods per the docs guide); comments explain
  **WHY**, never WHAT.
- Naming: classes PascalCase with suffix (`…Controller`, `…Service`, `…Job`, `…Request`, `…Policy`);
  methods and variables camelCase and descriptive; constants UPPER_SNAKE_CASE; enum cases TitleCase.
- **No magic strings** for statuses/types — use an enum from `app/Enums/`.
- No `dd()`/`dump()`/`var_dump()`, no commented-out code, no hardcoded values (use `config()`), DRY.
- Curly braces always; PSR-12 via `vendor/bin/pint`.

## Dependency direction

Allowed: Controller → Service/Action, Controller → Job (dispatch), Service → Model/Action/Job/Mail,
Service → Service (via DI), Job → Service/Model, Observer → Job (dispatch).

Not allowed: Model → Service (no business logic in models — only relationships, scopes, accessors),
Service → Request/Response (no HTTP knowledge in services), logic in Blade views, domain logic in
global helpers (`app/helpers.php` is for genuinely cross-cutting helpers only).

## Project-specific placement rules

- Stock changes go through `InventoryService`/`StockMovement`; cost layers through `CostingService`.
  Never mutate `stock_quantity` directly.
- AI calls go through `AiManager` (`app/Services/Ai/`), respecting the per-shop provider override —
  never a hardcoded provider.
- E-commerce and WhatsApp work goes through its service + a queued job, never inline HTTP in a
  controller or observer.
- Shop scoping belongs in the query, every time (`auth()->user()->shop_id ?? Shop::first()?->id`, or a
  `visibleTo($user)` scope where one exists).

## Forbidden

- ❌ Fat controllers / validation or business rules in controllers
- ❌ Business logic in models or Blade views
- ❌ Methods over 20 executable lines or doing several things
- ❌ New per-domain `Models/` subfolders or `Api/V1/` controller folders
- ❌ Magic status strings instead of enums
- ❌ Debug code, commented-out code, hardcoded config values
- ❌ Cross-domain reach-around (call a service, dispatch a job, or fire an event instead)
- ❌ Missing type hints or PHPDoc on public API
