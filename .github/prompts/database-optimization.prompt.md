---
title: Database Optimization Review
description: Analyzes queries, suggests indexes, identifies N+1 problems, and recommends performance improvements
---

Analyze database queries and suggest optimizations for:

{{target|prompt:Specify model, controller, or code section to analyze}}

## Analysis Areas

### 1. N+1 Query Detection

Identify and fix N+1 query problems:

```php
// ❌ N+1 Problem
$sales = Sale::all();
foreach ($sales as $sale) {
    echo $sale->customer->name; // Queries customer each time
}

// ✅ Fixed with Eager Loading
$sales = Sale::with('customer')->get();
foreach ($sales as $sale) {
    echo $sale->customer->name; // No additional queries
}
```

### 2. Missing Indexes

Identify columns that should be indexed:

- Foreign keys (usually auto-indexed)
- Columns in WHERE clauses
- Columns in ORDER BY
- Columns in JOIN conditions
- Columns with high cardinality

**Suggest migration:**

```php
Schema::table('table_name', function (Blueprint $table) {
    $table->index('column_name');
    $table->index(['column1', 'column2']); // Composite
});
```

### 3. Query Optimization

**Unnecessary Selects:**

```php
// ❌ Selecting all columns
$users = User::all();

// ✅ Select only needed
$users = User::select('id', 'name', 'email')->get();
```

**Inefficient Counting:**

```php
// ❌ Loads all records
$count = Model::all()->count();

// ✅ Database count
$count = Model::count();
```

**Chunk Large Datasets:**

```php
// For large datasets
Model::chunk(1000, function ($records) {
    foreach ($records as $record) {
        // Process
    }
});
```

### 4. Relationship Loading

**Conditional Loading:**

```php
$products = Product::query()
    ->with(['category', 'images'])
    ->when($request->includePricing, fn($q) => $q->with('priceHistory'))
    ->get();
```

**Nested Relations:**

```php
$products = Product::with('category.parent')->get();
```

**Counting Relations:**

```php
// ❌ Loads all items
$sales = Sale::with('items')->get();
$count = $sales->first()->items->count();

// ✅ Count in query
$sales = Sale::withCount('items')->get();
$count = $sales->first()->items_count;
```

### 5. Query Analysis Tools

**Log Queries:**

```php
DB::listen(function ($query) {
    Log::info($query->sql, $query->bindings);
});
```

**Laravel Debugbar:**

- Check queries count
- Identify duplicate queries
- Monitor query execution time

## Output Format

Provide analysis in this structure:

**🔴 Critical Issues**

- N+1 queries found
- Missing critical indexes
- Full table scans

**🟡 Optimization Opportunities**

- Unnecessary eager loading
- Excessive column selection
- Suboptimal query patterns

**✅ Current Good Practices**

- Proper eager loading
- Existing indexes
- Efficient queries

**📊 Recommendations**

- Index suggestions with migration code
- Query refactoring examples
- Performance benchmarks (if measurable)
