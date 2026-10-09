---
description: Action class development for business logic encapsulation. Use when creating Action classes, implementing business operations, handling complex workflows, or when user mentions action, business logic, operation, or workflow."
applyTo: "**/Actions/**"
---

# Action Class Guidelines

## Structure

Actions encapsulate single business operations with clear responsibilities:

```php
namespace App\Actions;

use App\Models\Sale;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CompleteSale
{
    public function execute(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale) {
            // 1. Validate state
            if ($sale->status !== 'pending') {
                throw new \InvalidArgumentException('Sale must be pending');
            }

            // 2. Update inventory
            foreach ($sale->items as $item) {
                $this->updateInventory($item);
            }

            // 3. Update sale status
            $sale->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // 4. Create audit log
            CreateUserAction::execute($sale->user, 'sale.completed', $sale);

            return $sale->fresh();
        });
    }

    protected function updateInventory(SaleItem $item): void
    {
        $item->product->decrement('quantity', $item->quantity);
    }
}
```

## Best Practices

1. **Single Responsibility**: One action = one business operation
2. **Use Transactions**: Wrap multi-step operations in DB::transaction()
3. **Type Hints**: Always use strict types for parameters and return values
4. **Validation First**: Check preconditions before making changes
5. **Return Results**: Return the affected model or meaningful result
6. **Audit Trail**: Create user action logs for important operations

## Method Naming

- Primary method: `execute()` or `handle()`
- Static convenience: `static::execute()` pattern is acceptable
- Action name describes the outcome: `CompleteSale`, not `SaleCompleter`

## Error Handling

```php
throw new \InvalidArgumentException('Clear error message');
throw new \DomainException('Business rule violation');
throw new \RuntimeException('System error during operation');
```

## Testing Actions

1. Test happy path with valid inputs
2. Test precondition failures
3. Test transaction rollback on errors
4. Verify side effects (inventory, logs, notifications)
5. Use factories to create test data
