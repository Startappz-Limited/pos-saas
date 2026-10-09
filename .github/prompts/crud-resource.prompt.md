---
title: Generate CRUD Resource
description: Scaffolds a complete Laravel CRUD resource with model, controller, form requests, policy, factory, tests, and routes
---

Create a complete CRUD resource for a Laravel model with the following specifications:

Model Name: {{modelName}}
Database Table: {{tableName|default:auto-generate from model name}}
Multi-tenant: {{multiTenant|default:yes}}

## Requirements

Generate the following files:

1. **Model** (`app/Models/{{modelName}}.php`)
    - Use proper casts() method
    - Define fillable attributes
    - Add relationships
    - Add shop_id foreign key if multi-tenant

2. **Migration** (`database/migrations/create_{{tableName}}_table.php`)
    - Include all necessary columns
    - Add proper indexes
    - Add foreign keys with cascade delete
    - Include shop_id if multi-tenant

3. **Factory** (`database/factories/{{modelName}}Factory.php`)
    - Define realistic fake data
    - Add custom states if applicable
    - Include shop relationship if multi-tenant

4. **Controller** (`app/Http/Controllers/{{modelName}}Controller.php`)
    - RESTful methods: index, create, store, show, edit, update, destroy
    - Scope queries by shop_id if multi-tenant
    - Use authorize() for policy checks
    - Flash success messages

5. **Form Requests**
    - `app/Http/Requests/Store{{modelName}}Request.php`
    - `app/Http/Requests/Update{{modelName}}Request.php`
    - Include validation rules and custom error messages

6. **Policy** (`app/Policies/{{modelName}}Policy.php`)
    - viewAny, view, create, update, delete methods
    - Include super-admin bypass in EVERY method
    - Shop scoping where applicable

7. **Feature Tests** (`tests/Feature/{{modelName}}Test.php`)
    - Test CRUD operations
    - Test authorization
    - Test super-admin bypass
    - Test multi-tenancy isolation if applicable

8. **Routes** (add to `routes/web.php`)
    - Resource route with auth middleware

## Column Specifications

{{columns|prompt:Specify columns with types (e.g., name:string, price:decimal, quantity:integer)}}

## After Generation

1. Run migration: `php artisan migrate`
2. Run Pint: `vendor/bin/pint --dirty`
3. Run tests: `php artisan test --compact --filter={{modelName}}`
4. Show summary of created files
