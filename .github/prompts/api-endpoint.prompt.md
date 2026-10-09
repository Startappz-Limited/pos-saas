---
title: Create API Endpoint
description: Generates a complete API endpoint with controller, resource, tests, and documentation
---

Create API endpoint for:

Resource: {{resourceName}}
Version: {{version|default:v1}}
Authentication Required: {{auth|default:yes}}

## Generate

1. **API Resource** (`app/Http/Resources/{{resourceName}}Resource.php`)
    - Transform model attributes
    - Include related resources with `whenLoaded()`
    - Format dates as ISO 8601
    - Cast numeric types appropriately

2. **API Controller** (`app/Http/Controllers/Api/{{version|upper}}/{{resourceName}}Controller.php`)
    - RESTful methods: index, store, show, update, destroy
    - Paginate collections
    - Eager load relationships
    - Authorize actions
    - Scope by shop_id if multi-tenant
    - Return appropriate status codes

3. **Form Requests** (reuse existing or create new)
    - Validation rules
    - Custom error messages

4. **API Route** (add to `routes/api.php`)

    ```php
    Route::prefix('{{version}}')->group(function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::apiResource('{{resourceName|lower|plural}}', {{resourceName}}Controller::class);
        });
    });
    ```

5. **API Tests** (`tests/Feature/Api/{{version|upper}}/{{resourceName}}ApiTest.php`)
    ```php
    it('returns paginated {{resourceName|lower|plural}}', function () { ... });
    it('creates {{resourceName|lower}}', function () { ... });
    it('updates {{resourceName|lower}}', function () { ... });
    it('deletes {{resourceName|lower}}', function () { ... });
    it('prevents access to other shop data', function () { ... });
    it('validates required fields', function () { ... });
    it('requires authentication', function () { ... });
    ```

## Response Examples

**GET /api/{{version}}/{{resourceName|lower|plural}}** (200 OK)

```json
{
    "data": [
        {
            "id": 1,
            "attribute": "value",
            "created_at": "2024-01-01T00:00:00.000000Z"
        }
    ],
    "links": { ... },
    "meta": { ... }
}
```

**POST /api/{{version}}/{{resourceName|lower|plural}}** (201 Created)

```json
{
    "data": {
        "id": 1,
        "attribute": "value"
    },
    "message": "{{resourceName}} created successfully"
}
```

**Validation Error** (422 Unprocessable Entity)

```json
{
    "message": "The given data was invalid",
    "errors": {
        "field": ["The field is required"]
    }
}
```

## Authentication

Sanctum tokens via Bearer authentication:

```
Authorization: Bearer {token}
```

## After Generation

1. Run Pint: `vendor/bin/pint --dirty`
2. Run tests: `php artisan test --compact --filter={{resourceName}}Api`
3. Test with API client (Postman/Insomnia)
4. Update API documentation
