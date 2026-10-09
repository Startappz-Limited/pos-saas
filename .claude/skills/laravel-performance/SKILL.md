---
name: laravel-performance
description: Performance standards for this POS codebase — N+1 prevention and eager loading, index-usable (sargable) queries, pagination and chunking for large sets, SQL-side aggregation for reports, caching strategy, and moving heavy work to queued jobs. Use when writing or reviewing any list/index endpoint, report or dashboard query, export/backfill command, observer, migration that adds indexes, or when something is reported as slow, timing out, or memory-exhausting.
---

# Performance Standards (POS)

Performance is a **🔴 mandatory** standard for new features, refactors, database changes, and API
endpoints — see the Performance Standards table in
[ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md). N+1 prevention is mandatory even
for bug fixes.

The canonical query-efficiency guidance is section 3 of
[0.9 coding_standards_guide.md](../../../.ai/general/0.9%20coding_standards_guide.md). This skill adds
the parts that guide doesn't cover (indexes, caching, queues, sargability) and pins them to how this
codebase is actually built.

## Know the runtime you are optimizing for

Verify against `.env`/`config/` before assuming, but as configured today:

- **`CACHE_STORE=database`** and **`QUEUE_CONNECTION=database`** — cache reads/writes and every queue
  push are **SQL round-trips against the same database**. A cache is not free here: cache one
  expensive aggregate, not a hundred tiny values inside a loop.
- **The dev `.env` points `DB_HOST` at a remote server**, not localhost. Every query pays network
  latency, so query *count* matters more than it would locally, and a slow report is slower for you
  than for production. Treat that database as shared and real (see the hard safety rules in
  [laravel-standards](../laravel-standards/SKILL.md)).
- Profiling tools available: **laravel-debugbar** (query count/timings per request, dev only),
  `php artisan pail` for logs, and Boost's `database-query` MCP tool for `EXPLAIN`.

## 1. N+1 queries — mandatory prevention

Follow [0.9 coding_standards_guide.md](../../../.ai/general/0.9%20coding_standards_guide.md) §3. The
short version:

- Eager-load every relationship a loop or Blade view touches: `with('customer')`,
  nested `with('items.product')`.
- Aggregates instead of loading rows: `withCount()`, `withExists()`, `withSum()`, `withAvg()`.
- Never query inside `foreach` — hoist to one `whereIn()` and key the result
  (`->get()->keyBy('id')`).
- Select only what you need, including on the relation:
  `with(['shop:id,name', 'customer:id,name,customer_type'])` — this is the pattern
  `ReportService::getDashboardData()` already uses; copy it.
- Blade views are where N+1 hides. If a view renders `$sale->customer->name` inside a loop, the
  eager load belongs in the controller/service that built `$sales`.

**Catching it:** enable strict mode in `AppServiceProvider::boot()` so lazy loads throw in dev
instead of silently costing 200 queries (not currently enabled — adding it is a safe, contained win):

```php
Model::preventLazyLoading(! app()->isProduction());
```

## 2. Make queries index-usable (sargable)

Wrapping an indexed column in a function defeats the index. This matters here because the hot tables
already carry the right composite indexes — `sales` has `['shop_id', 'created_at']` and
`['status', 'completed_at']`; `stock_movements` has `['product_id', 'variation_id', 'created_at']`
and `['shop_id', 'created_at']`.

```php
// ❌ DATE(created_at) cannot use the ['shop_id','created_at'] index — full scan
$query->whereDate('created_at', '>=', $start->toDateString())
      ->whereDate('created_at', '<=', $end->toDateString());

// ✅ range on the raw column — index range scan
$query->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()]);
```

`whereDate`/`whereMonth`/`whereYear` on an indexed column is the single most common performance
defect in this repo — [ReportService.php:205](../../../app/Services/ReportService.php#L205) and
[Api/SaleController.php:45](../../../app/Http/Controllers/Api/SaleController.php#L45) both do it.
When you touch such a query, convert it to a range; don't rewrite unrelated ones.

Also:
- Leading wildcards (`LIKE '%term%'`) can't use an index. Anchor when you can (`'term%'`), and keep
  product/customer search inputs bounded by shop scope first.
- Filter on the indexed column, not a computed expression, and order composite filters to match the
  index (`shop_id` then `created_at`).
- `JSON_EXTRACT` in a `WHERE` is always a scan — see §6.

## 3. Index every new query path

Per [0.3 database_and_data_design_guide.md](../../../.ai/general/0.3%20database_and_data_design_guide.md)
§3.6 — indexes are mandatory on foreign keys and frequently-filtered columns:

- Any column a new list/filter/report actually filters or sorts on gets an index in the **same**
  migration that introduces the query path.
- Composite index column order must match query order (`['shop_id', 'created_at']`, not the reverse) —
  a composite index serves prefixes of itself, so `shop_id` alone is covered too.
- Add the index in a new additive migration (never edit a shipped one), with a working `down()`.
- Don't index low-cardinality columns alone (a bare `status` index rarely helps); combine with
  `shop_id`, as `sales` does.

## 4. Bound every result set

- **Lists**: `paginate()` — mandatory for API list endpoints and admin index views. Never `->get()`
  a whole table for a screen.
- **Large processing** (exports, PDF reports, backfills, sync commands, artisan commands): stream
  instead of materializing. There is currently **no `chunk()`/`chunkById()`/`lazy()`/`cursor()`
  anywhere in `app/`** — anything you add that walks a full table must use one:

```php
Sale::query()
    ->where('shop_id', $shopId)
    ->with('items:id,sale_id,product_id,quantity')
    ->chunkById(500, function ($sales) { /* ... */ });
```

Use `chunkById()` rather than `chunk()` when the loop writes to the rows it is walking — `chunk()`
paginates with OFFSET and will skip records as the result set shifts.

## 5. Aggregate in SQL, not in PHP

Reporting here correctly pushes work into the database — `ReportService` uses `selectRaw` with
`COUNT`/`SUM`/`COALESCE` and `clone $query` to derive several summaries from one filtered base query.
Preserve that:

- Compute totals with `selectRaw('COALESCE(SUM(total_amount), 0) as total_revenue')`, not by loading
  models and summing a collection.
- Reuse a filtered base query with `clone` for each variant rather than rebuilding filters.
- **Always bind parameters in raw fragments** — `selectRaw('... = ?', ['approved'])`. Never
  interpolate request input into raw SQL (security rule, not just style).
- `groupBy` + a single query beats one query per group.

## 6. Keep the request path off expensive work

Observers, model events, and controllers run inside the user's request. Anything slow or remote
belongs in a queued job (`app/Jobs/`), which this project already does for e-commerce sync and
webhooks.

Concrete case: `ProductObserver::autoSyncProduct()` runs
`Shop::whereRaw("JSON_EXTRACT(settings, '$.woocommerce.enabled') = true OR ...")->get()` on **every**
product create and update. That's an unindexable JSON scan of `shops` in the write path, before any
work is even dispatched. When you touch that path, either cache the enabled-shop list or resolve it
inside the queued job — not per save.

Rules:
- No HTTP calls to external services (WooCommerce, Shopify, Meta, WhatsApp, AI providers) inline in a
  controller or observer — dispatch a job.
- Heavy operations are 🔴 mandatory to queue for new features and API endpoints.
- Observers should do cheap, local work (stamping a field, invalidating a cache key) and dispatch the
  rest.

## 7. Caching — new pattern, use it deliberately

There is essentially **no `Cache::` usage in `app/`** today, so you are establishing the pattern.
Caching is 🟡 recommended (not mandatory) — reach for it only after eager loading, indexing, and
pagination, and only for genuinely expensive reads.

- Cache computed/aggregated reads (dashboard tiles, report summaries, low-stock counts), not raw
  models you could eager-load.
- **Key by shop** — this is a multi-shop system and a cross-shop cache leak is a data-isolation bug,
  not just a stale value: `Cache::remember("dashboard:{$shopId}:{$date}", 300, fn () => ...)`.
- Every cache write needs a defined invalidation: TTL, or explicit `Cache::forget()` from the owning
  service/observer when the underlying data changes.
- Remember the store is the database — a cache hit is still a query. Cache things that cost many
  queries, not things that cost one.
- Never cache a value derived from `auth()` without the user/shop in the key.

## 8. Before declaring a performance change done

- Count queries for the affected page/endpoint with debugbar before and after; state the numbers.
- `EXPLAIN` any new report query or new index (Boost `database-query`) and confirm it isn't a full
  scan.
- Confirm the result set is bounded (paginated or chunked) and the shop scope is still applied.
- Run `vendor/bin/pint` and the relevant tests (see [laravel-testing](../laravel-testing/SKILL.md) —
  do not run the full suite blindly).

## Forbidden performance anti-patterns

- ❌ Queries inside loops (including inside Blade loops)
- ❌ Unpaginated list endpoints / `->get()` on an unbounded table for a screen
- ❌ Loading full models to `count()` or `sum()` them in PHP
- ❌ `whereDate`/`whereMonth`/`whereYear` on an indexed column in a new query
- ❌ New filter/sort paths with no supporting index
- ❌ External HTTP calls or heavy loops in controllers, observers, or model events
- ❌ Caching without a shop-scoped key or a defined invalidation path
- ❌ Interpolating input into `DB::raw`/`selectRaw` (use bindings)
