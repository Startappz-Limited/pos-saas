# Quick Reference Card

## 🎯 File Instructions (Auto-Activate)

| Working in...                 | Get guidance for...               |
| ----------------------------- | --------------------------------- |
| `app/Policies/**`             | Super-admin bypass, authorization |
| `app/Actions/**`              | Business logic, transactions      |
| `database/migrations/**`      | Schema design, indexes            |
| `tests/**`                    | Pest 4 patterns, assertions       |
| `app/Http/Controllers/Api/**` | API resources, versioning         |

## ⚡ Slash Commands (Type `/`)

```bash
/crud-resource           # Generate complete CRUD
/code-review            # Comprehensive review
/generate-tests         # Create Pest tests
/api-endpoint          # Build API endpoint
/database-optimization # Query optimization
/security-audit        # Security review
```

## 🤖 Agents (Auto-activate or mention)

```bash
@security-reviewer     # Security audits
@test-engineer        # Test creation
@database-architect   # DB optimization
@api-developer        # API development
```

## ✅ Required After Changes

```bash
vendor/bin/pint --dirty                    # Format code
php artisan test --compact --filter=Name   # Run tests
```

## 🛡️ Security Checklist

- [ ] Super-admin bypass in policies
- [ ] Authorization checks present
- [ ] Shop scoping for multi-tenancy
- [ ] Form Request validation
- [ ] Tests for security scenarios

## 📝 Common Patterns

### Super-Admin Bypass (Required in ALL policies)

```php
if ($user->hasRole('super-admin')) {
    return true;
}
```

### Shop Scoping (Required for multi-tenant data)

```php
Product::where('shop_id', auth()->user()->shop_id)->get()
```

### API Resource (Required for API responses)

```php
return ProductResource::collection($products);
```

### Form Request (Required for validation)

```php
public function store(StoreProductRequest $request)
```

### Eager Loading (Prevent N+1)

```php
$sales = Sale::with(['items', 'customer'])->get();
```

## 🚫 Never Do

- ❌ Return models directly from API
- ❌ Skip super-admin bypass in policies
- ❌ Use `env()` outside config files
- ❌ Inline validation in controllers
- ❌ Modify columns without all attributes
- ❌ Skip authorization checks

## 🎮 Quick Actions

### Create New Feature

```
You: /crud-resource
Name: Equipment
Columns: name:string, type:string, purchase_date:date
```

### Review Security

```
You: @security-reviewer check ProductPolicy
```

### Optimize Query

```
You: @database-architect this query is slow: [paste query]
```

### Generate Tests

```
You: /generate-tests for ProductController
```

### Build API

```
You: /api-endpoint for Member resource v1
```

## 📚 More Help

Type in chat:

- "How do I..." → Get specific guidance
- "Show me examples of..." → See code patterns
- "Review..." → Comprehensive analysis
- "@agent-name..." → Use specific agent

Full docs: `.github/CONTEXT_ENGINEERING.md`
