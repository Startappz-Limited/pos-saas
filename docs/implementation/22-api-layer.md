# Module 22: API Layer
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
RESTful API layer using Laravel Sanctum for token-based authentication. Provides complete API access to all system entities including products, categories, sales, customers, suppliers, shops, and inventory. Designed for mobile apps, third-party integrations, and SPA frontends.

**Priority:** P3 (Optional)  
**Dependencies:** All previous modules  
**Estimated Time:** 2 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | API returns UUID for all entities |
| **0.8 Routing** | UUID-only route binding in API |
| **1.0 Security** | Sanctum token authentication, Rate limiting |
| **API Standards** | RESTful conventions, JSON responses |

---

## 2. Authentication Setup

### Laravel Sanctum Installation

```bash
php artisan install:api
```

This command:
- ✅ Installs Laravel Sanctum package
- ✅ Publishes Sanctum configuration
- ✅ Creates `personal_access_tokens` migration
- ✅ Creates `routes/api.php` file

### User Model Update

**File:** `app/Models/User.php`

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;
    
    // ... rest of model
}
```

---

## 3. API Routes

**File:** `routes/api.php`

```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // API Resources
    Route::apiResource('shops', ShopController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('sales', SaleController::class);

    // Inventory routes
    Route::get('inventory', [InventoryController::class, 'index']);
    Route::get('inventory/shop/{shop}', [InventoryController::class, 'byShop']);
    Route::get('inventory/product/{product}', [InventoryController::class, 'byProduct']);
    Route::get('inventory/low-stock', [InventoryController::class, 'lowStock']);
});
```

---

## 4. API Controllers

### Authentication Controller

**File:** `app/Http/Controllers/Api/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
```

---

## 5. API Resources

### Product Resource Example

**File:** `app/Http/Resources/ProductResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'price' => [
                'amount' => $this->price,
                'formatted' => number_format($this->price, 2),
            ],
            'stock' => [
                'quantity' => $this->stock_quantity,
                'reorder_level' => $this->reorder_level,
                'in_stock' => $this->stock_quantity > 0,
                'low_stock' => $this->stock_quantity <= $this->reorder_level,
            ],
            'status' => $this->status,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

---

## 6. API Response Format

### Success Response
```json
{
    "data": {
        "id": "9d3e8f2a-1b4c-4d5e-8f9a-0b1c2d3e4f5a",
        "name": "Premium Protein Powder",
        "price": {
            "amount": 49.99,
            "formatted": "49.99"
        }
    }
}
```

### Error Response
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "The email field is required."
        ]
    }
}
```

### Collection Response
```json
{
    "data": [
        {
            "id": "uuid-1",
            "name": "Product 1"
        },
        {
            "id": "uuid-2",
            "name": "Product 2"
        }
    ],
    "links": {
        "first": "http://api.example.com/products?page=1",
        "last": "http://api.example.com/products?page=10",
        "prev": null,
        "next": "http://api.example.com/products?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 15,
        "to": 15,
        "total": 150
    }
}
```

---

## 7. API Usage Examples

### Login
```bash
curl -X POST http://api.example.com/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password"
  }'
```

### Get Products (Authenticated)
```bash
curl -X GET http://api.example.com/api/products \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Create Sale
```bash
curl -X POST http://api.example.com/api/sales \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "uuid",
    "shop_id": "uuid",
    "items": [
      {
        "product_id": "uuid",
        "quantity": 2,
        "unit_price": 49.99
      }
    ]
  }'
```

---

## 8. Rate Limiting

**File:** `app/Http/Kernel.php`

```php
'api' => [
    \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

Default: 60 requests per minute per user

---

## 9. Implementation Summary

### Created Files
- ✅ 8 API Controllers (Auth, Product, Category, Sale, Customer, Supplier, Shop, Inventory)
- ✅ 7 API Resources (Product, Category, Sale, Customer, Supplier, Shop, Inventory)
- ✅ 1 Routes file (routes/api.php)
- ✅ Sanctum authentication setup
- ✅ Personal access tokens migration

### API Endpoints Available
- `POST /api/login` - User login
- `POST /api/register` - User registration
- `POST /api/logout` - User logout
- `GET /api/user` - Get authenticated user
- `GET /api/shops` - List shops
- `GET /api/categories` - List categories
- `GET /api/products` - List products
- `GET /api/customers` - List customers
- `GET /api/suppliers` - List suppliers
- `GET /api/sales` - List sales
- `GET /api/inventory` - Get inventory
- Plus all standard REST endpoints (show, store, update, destroy)

### Key Features
- Token-based authentication via Sanctum
- RESTful API conventions
- JSON API resources for consistent formatting
- Rate limiting (60 req/min)
- UUID-based routing
- Pagination support
- Comprehensive error handling
- CORS support ready

---

## 10. Security Considerations

1. **Token Management**
   - Tokens should be stored securely on client
   - Implement token rotation for long-lived sessions
   - Revoke tokens on logout

2. **Rate Limiting**
   - Default: 60 requests/minute
   - Customize per endpoint if needed
   - Monitor for abuse

3. **CORS Configuration**
   - Configure allowed origins in `config/cors.php`
   - Restrict to known domains in production

4. **API Versioning** (Future)
   - Consider `/api/v1/` prefix for versioning
   - Maintain backward compatibility

---

## Status: ✅ COMPLETE
All infrastructure files created, Sanctum installed, and code formatted with Pint (430 files).
API layer ready for mobile apps and third-party integrations.
