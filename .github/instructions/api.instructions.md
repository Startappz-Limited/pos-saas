---
description: API development with resources, versioning, and authentication. Use when creating API endpoints, API resources, API controllers, or when user mentions API, REST, JSON response, or API resource."
applyTo: "**/Http/Controllers/Api/**,**/Http/Resources/**"
---

# API Development Guidelines

## API Resource Structure

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (float) $this->price,
            'quantity' => $this->quantity,
            'is_active' => (bool) $this->is_active,
            'shop' => new ShopResource($this->whenLoaded('shop')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

## API Controller Pattern

```php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->where('shop_id', auth()->user()->shop_id)
            ->with('category')
            ->paginate();

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create([
            ...$request->validated(),
            'shop_id' => auth()->user()->shop_id,
        ]);

        return ProductResource::make($product)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        return ProductResource::make($product->load('category'));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return ProductResource::make($product->fresh());
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }
}
```

## API Versioning

Routes in `routes/api.php`:

```php
// V1 API
Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('products', ProductController::class);
    });
});
```

## Response Formats

**Success (200)**:

```json
{
    "data": {
        "id": 1,
        "name": "Product Name"
    }
}
```

**Collection (200)**:

```json
{
    "data": [...],
    "links": {...},
    "meta": {...}
}
```

**Created (201)**:

```json
{
    "data": {...},
    "message": "Product created successfully"
}
```

**Validation Error (422)**:

```json
{
    "message": "The given data was invalid",
    "errors": {
        "name": ["The name field is required"]
    }
}
```

**Authorization Error (403)**:

```json
{
    "message": "This action is unauthorized"
}
```

## Best Practices

1. **Always use API Resources** - Never return models directly
2. **Use Form Requests** - Validate with dedicated request classes
3. **Eager Load Relations** - Prevent N+1 queries with `->with()`
4. **Authorize First** - Check policies before any action
5. **Scope by Shop** - Filter data by authenticated user's shop_id
6. **Version APIs** - Use `/api/v1/` prefix for all routes
7. **Use Sanctum** - Authenticate with `auth:sanctum` middleware

## Testing APIs

```php
it('returns paginated products', function () {
    $user = User::factory()->create();
    Product::factory()->forShop($user->shop)->count(3)->create();

    actingAs($user, 'sanctum')
        ->getJson(route('api.v1.products.index'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'price']
            ],
            'links',
            'meta'
        ]);
});

it('prevents access to other shop products', function () {
    $user = User::factory()->create();
    $otherProduct = Product::factory()->create(); // Different shop

    actingAs($user, 'sanctum')
        ->getJson(route('api.v1.products.show', $otherProduct))
        ->assertForbidden();
});
```
