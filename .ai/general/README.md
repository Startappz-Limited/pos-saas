# Development Documentation

## 📚 Overview

Welcome to the comprehensive development documentation for **this system**, a Laravel 11+ application built with modern best practices, security-first architecture, and scalable folderization patterns.

This documentation provides complete guidelines for developers, covering everything from code organization to API design, security standards, and deployment practices.

---

## 🚀 Quick Start

### For New Developers

1. **Read the [Folder Structure Guide](./folder_structure_guide.md)** first to understand how the project is organized
2. **Review [Coding Standards](./0.9%20coding_standards_guide.md)** - architecture patterns and clean code principles
3. **Review [Security Best Practices](./1.0%20security_best_practices_guide.md)** - these are non-negotiable
4. **Check the [PR Checklist](./PR_CHECKLIST.md)** before starting any work
5. **Reference the [Enforcement Matrix](./ENFORCEMENT_MATRIX.md)** to understand what standards apply to your changes

### For Code Reviews

1. Use the [Pull Request Checklist](./PR_CHECKLIST.md) as your review guide
2. Consult the [Enforcement Matrix](./ENFORCEMENT_MATRIX.md) for mandatory vs. recommended standards
3. Verify security standards are met (see [Security Section](#security-first-approach))

### For API Development

1. Start with [API Guide](./0.7%20api_guide.md) for standards and patterns
2. Review [Routing Guide](./0.8%20routing_guide.md) for endpoint organization
3. Implement [Roles & Permissions](./roles_and_permissions_guide.md) for authorization
4. Add [Audit Logging](./audit_log_guide.md) for sensitive operations

---

## 📖 Complete Documentation Index

### Section 0: Project Foundation (This Document)

**Purpose:** Project overview, quick start, and documentation navigation  
**Audience:** All developers, new team members  
**When to use:** First day on the project, general reference

---

### Section 1: [Folder Structure Guide](./folder_structure_guide.md) 🏗️

**Purpose:** Code organization and project structure standards  
**Key Concepts:**

- Folderization architecture (vs. modulization)
- Controllers, Services, Jobs, Models, Mail, Views organization
- Naming conventions and file placement
- Examples for each folder type

**When to use:**

- Creating any new file
- Organizing features
- Refactoring code structure

**Critical Standards:**

- ✅ Controllers: Organized by area (`Controllers/Admin/`, `Controllers/Api/`, `Controllers/Web/`)
- ✅ Services: Domain-driven folders (`Services/Payment/`, `Services/Policy/`)
- ✅ Jobs: Purpose-based organization (`Jobs/Email/`, `Jobs/Report/`)
- ✅ Models: Flat structure in `Models/` directory
- ✅ Mail: Domain-based folders (`Mail/User/`, `Mail/Policy/`)
- ✅ Views: Resource-based nesting (`views/admin/users/index.blade.php`)

---

### Section 2: [UI Design Guide](./0.2%20ui_design_guide.md) 🎨

**Purpose:** Frontend design system and component standards  
**Key Concepts:**

- Raw HTML designs location: `./designs/theme/`
- Brand color palette (example tokens — replace per project)
- Brand typography (configurable primary/secondary fonts)
- Brand assets and logo usage
- Component library
- Responsive design patterns
- Accessibility requirements

**When to use:**

- Building any user interface
- Creating Blade templates
- Styling components
- Implementing designs

**Critical Standards:**

- ✅ Copy raw HTML from `./designs/theme/` directory
- ✅ Use the defined brand colors (no hard-coded hex)
- ✅ Follow the brand typography system (primary font for headings, secondary for supporting text)
- ✅ Implement responsive breakpoints
- ✅ Add accessibility attributes (ARIA, alt text)

---

### Section 3: [Database & Data Design Guide](./database_and_data_design_guide.md) 🗄️

**Purpose:** Database architecture, migrations, and model standards  
**Key Concepts:**

- Anonymous migrations (Laravel 11+)
- UUID/ULID primary keys
- Modern `casts()` method
- Enum implementation and casting
- Model traits and observers
- Query optimization

**When to use:**

- Creating migrations
- Defining models
- Implementing relationships
- Optimizing queries

**Critical Standards:**

- ✅ Use anonymous migration classes
- ✅ UUIDs for all primary and foreign keys
- ✅ `HasUuids` or `HasUlids` trait on models
- ✅ Modern `casts()` method (not `$casts` property)
- ✅ Enum casting for status/type fields
- ✅ Indexes on foreign keys and queried columns
- ✅ Both `up()` and `down()` migration methods

**Example:**

```php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {
        Schema::create('policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index('user_id');
        });
    }
};
```

---

### Section 4: [Roles & Permissions Guide](./roles_and_permissions_guide.md) 🔐

**Purpose:** Authorization system using Spatie Laravel Permission  
**Key Concepts:**

- Spatie Laravel Permission v6.0 integration
- `HasRoles` trait implementation
- Permission naming conventions
- Policy-based authorization
- Middleware configuration
- Blade directives

**When to use:**

- Implementing access control
- Creating new roles or permissions
- Protecting routes or actions
- Building admin features

**Critical Standards:**

- ✅ Use `HasRoles` trait on User model
- ✅ Permission naming: `{resource}.{action}` (e.g., `policy.create`)
- ✅ Middleware: `permission:`, `role:`, or `role_or_permission:`
- ✅ Policies for complex authorization
- ✅ Blade directives: `@can`, `@role`, `@hasrole`
- ✅ UUID for permission and role IDs

**Example:**

```php
// Controller
public function store(Request $request) {
    $this->authorize('create', Policy::class);
    // ... create policy
}

// Route
Route::middleware(['permission:policy.create'])->group(function () {
    Route::post('/policies', [PolicyController::class, 'store']);
});

// Blade
@can('policy.create')
    <button>Create Policy</button>
@endcan
```

---

### Section 5: [Audit Log Guide](./audit_log_guide.md) 📝

**Purpose:** Comprehensive audit trail system with separate database  
**Key Concepts:**

- Event-driven audit logging
- Separate audit database connection
- `AuditableEvent` and `AuditLogger`
- Model observers for automatic logging
- Data sanitization
- Immutable audit records

**When to use:**

- Sensitive data operations (create, update, delete)
- User authentication events
- Payment/policy operations
- Administrative actions

**Critical Standards:**

- ✅ Use separate audit database (`config/database.php`)
- ✅ Dispatch `AuditableEvent` for sensitive operations
- ✅ Include user context (ID, IP, user agent)
- ✅ Sanitize sensitive data before logging
- ✅ UUID primary keys for audit records
- ✅ No updates/deletes on audit logs (immutable)

**Example:**

```php
use App\Events\AuditableEvent;

AuditableEvent::dispatch(
    action: 'policy.created',
    model: Policy::class,
    modelId: $policy->uuid,
    changes: ['status' => 'active'],
    userId: auth()->id()
);
```

---

### Section 6: [Documentation Guide](./0.6%20documentation_guide.md) 📄

**Purpose:** Documentation standards and best practices  
**Key Concepts:**

- PHPDoc standards
- Markdown formatting
- Code documentation patterns
- API documentation
- README templates
- Diagram creation (Mermaid)

**When to use:**

- Writing code comments
- Creating README files
- Documenting APIs
- Adding inline documentation

**Critical Standards:**

- ✅ PHPDoc blocks for all public methods
- ✅ Markdown formatting for documentation files
- ✅ Code examples in documentation
- ✅ API endpoint documentation
- ✅ Environment variables in `.env.example`

---

### Section 7: [API Guide](./0.7%20api_guide.md) 🌐

**Purpose:** RESTful API design patterns and standards  
**Key Concepts:**

- API versioning (`/api/v1/`)
- Sanctum authentication
- Standard response format
- HTTP status codes
- Rate limiting
- Pagination patterns
- Idempotency
- Error handling

**When to use:**

- Building API endpoints
- Implementing authentication
- Creating API responses
- Handling errors

**Critical Standards:**

- ✅ Versioned routes: `/api/v1/resource`
- ✅ Sanctum token authentication
- ✅ Standard JSON response structure
- ✅ Proper HTTP status codes
- ✅ Rate limiting per endpoint type
- ✅ Pagination for list endpoints
- ✅ UUID in URLs (not integer IDs)
- ✅ FormRequest validation

**Response Format:**

```json
{
    "success": true,
    "message": "Resource retrieved successfully",
    "data": { ... },
    "meta": {
        "current_page": 1,
        "total": 100
    }
}
```

---

### Section 8: [Routing Guide](./0.8%20routing_guide.md) 🛣️

**Purpose:** Route organization and Laravel 11+ routing patterns  
**Key Concepts:**

- `bootstrap/app.php` route registration
- Route grouping strategies
- Naming conventions
- UUID route binding
- Middleware stacks
- API vs. Web routes

**When to use:**

- Creating new routes
- Organizing route files
- Configuring middleware
- Implementing versioning

**Critical Standards:**

- ✅ Register routes in `bootstrap/app.php`
- ✅ Route naming: dot notation (`admin.users.index`)
- ✅ UUID binding for models
- ✅ Group routes by domain/area
- ✅ Explicit middleware stacks
- ✅ Separate API and web routes

**Example:**

```php
// bootstrap/app.php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        Route::middleware(['web', 'auth', 'verified'])
            ->prefix('admin')
            ->name('admin.')
            ->group(base_path('routes/admin/users.php'));
    }
)
```

---

### Section 9: [Coding Standards Guide](./0.9%20coding_standards_guide.md) 🧹

**Purpose:** Clean code principles, Laravel architecture patterns, and N+1 prevention  
**Key Concepts:**

- Clean code with meaningful comments
- Laravel architecture (Actions, Services, Jobs, Events, Listeners, Observers)
- N+1 query prevention techniques
- Eager loading and query optimization

**When to use:**

- Writing any new code
- Refactoring existing code
- Code reviews
- Deciding where to place business logic

**Critical Standards:**

- ✅ Short, meaningful comments explaining WHY (not WHAT)
- ✅ Methods under 20 lines, single responsibility
- ✅ Use Actions for single-purpose operations
- ✅ Use Services for complex business logic orchestration
- ✅ Use Jobs for queued/async operations
- ✅ Use Events/Listeners for decoupled side effects
- ✅ Use Observers for model lifecycle hooks
- ✅ Always eager load with `with()` to prevent N+1
- ✅ Use `withCount()`, `withExists()` for aggregates
- ✅ Chunk or lazy load large datasets

**Example:**

```php
// Action - single purpose
app(CreatePolicyAction::class)->execute($data);

// Service - orchestrates multiple operations
app(PolicyService::class)->activatePolicy($user, $data);

// Prevent N+1
$policies = Policy::with(['user', 'beneficiaries'])->get();
```

---

### Section 10: [Security Best Practices Guide](./1.0%20security_best_practices_guide.md) 🛡️

**Purpose:** Comprehensive security standards for Laravel applications  
**Key Concepts:**

- HTTPS enforcement
- Authentication & 2FA
- Input validation and sanitization
- XSS, CSRF, and SQL injection prevention
- Honeypot protection for forms
- Data encryption
- Secure logging practices
- Queue payload encryption

**When to use:**

- Building any feature handling user data
- Creating forms
- Implementing authentication
- Handling sensitive information
- Code security reviews

**Critical Standards:**

- ✅ Keep Laravel and dependencies updated
- ✅ Enforce HTTPS in production
- ✅ Strong password requirements with `Password::defaults()`
- ✅ Rate limit login attempts
- ✅ Use Form Requests for all validation
- ✅ Never use `$request->all()` - use `$request->validated()`
- ✅ Honeypot protection on ALL forms (`@honeypot`)
- ✅ Blade escaping `{{ }}` (avoid `{!! !!}`)
- ✅ Use Eloquent/Query Builder (avoid raw SQL)
- ✅ Encrypt sensitive data with `Crypt` facade
- ✅ `APP_DEBUG=false` in production
- ✅ Never log sensitive data (passwords, tokens)
- ✅ Security headers configured

**Example:**

```php
// Form with honeypot
<form method="POST" action="{{ route('submit') }}">
    @csrf
    @honeypot
    <!-- form fields -->
</form>

// Validated data only
$user = User::create($request->validated());

// Encrypt sensitive data
$encrypted = Crypt::encryptString($sensitiveData);
```

---

## 🔒 Security First Approach

Security is **non-negotiable** in this project. All security standards are mandatory.

### Critical Security Standards

| Standard              | Implementation               | Documentation                                                                                 |
| --------------------- | ---------------------------- | --------------------------------------------------------------------------------------------- |
| **Authentication**    | Laravel Sanctum              | [API Guide](./0.7%20api_guide.md#authentication)                                              |
| **Authorization**     | Spatie Permission + Policies | [Roles & Permissions](./roles_and_permissions_guide.md)                                       |
| **UUID in URLs**      | Route binding                | [Routing Guide](./0.8%20routing_guide.md#uuid-binding)                                        |
| **Mass Assignment**   | `$fillable`/`$guarded`       | [Security Guide](./1.0%20security_best_practices_guide.md#11-never-use-request-all)           |
| **SQL Injection**     | Eloquent + Parameter binding | [Security Guide](./1.0%20security_best_practices_guide.md#8-prevent-sql-injection)            |
| **XSS Prevention**    | Blade `{{ }}` escaping       | [Security Guide](./1.0%20security_best_practices_guide.md#5-prevent-cross-site-scripting-xss) |
| **CSRF Protection**   | `@csrf` directive            | [Security Guide](./1.0%20security_best_practices_guide.md#6-protect-against-csrf-attacks)     |
| **Honeypot**          | `@honeypot` on all forms     | [Security Guide](./1.0%20security_best_practices_guide.md#62-honeypot-protection)             |
| **Rate Limiting**     | RateLimiter facade           | [Security Guide](./1.0%20security_best_practices_guide.md#32-rate-limiting-login-attempts)    |
| **Audit Logging**     | Separate database            | [Audit Log Guide](./audit_log_guide.md)                                                       |
| **Data Sanitization** | Before logging               | [Security Guide](./1.0%20security_best_practices_guide.md#12-logging-best-practices)          |
| **N+1 Prevention**    | Eager loading                | [Coding Standards](./0.9%20coding_standards_guide.md#3-avoiding-n1-queries)                   |

### Security Checklist (Every PR)

- [ ] Authentication verified on protected routes
- [ ] Authorization checked (policies/permissions)
- [ ] UUID used in URLs (not integer IDs)
- [ ] User input validated via Form Requests
- [ ] Using `$request->validated()` (not `$request->all()`)
- [ ] SQL injection prevented (no raw queries)
- [ ] XSS prevented (proper Blade escaping)
- [ ] CSRF tokens included in forms (`@csrf`)
- [ ] Honeypot added to all forms (`@honeypot`)
- [ ] Rate limiting configured
- [ ] Sensitive operations audited
- [ ] Secrets in environment variables
- [ ] N+1 queries prevented with eager loading
- [ ] `APP_DEBUG=false` verified for production

---

## 🧪 Testing Standards

### Test Coverage Requirements

| Component         | Unit Tests | Feature Tests | Integration Tests |
| ----------------- | ---------- | ------------- | ----------------- |
| **Services**      | Required   | Recommended   | Optional          |
| **Controllers**   | Optional   | Required      | Recommended       |
| **Models**        | Required   | Optional      | Optional          |
| **Jobs**          | Required   | Recommended   | Optional          |
| **API Endpoints** | Optional   | Required      | Required          |
| **Policies**      | Required   | Optional      | Optional          |

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test
php artisan test --filter UserServiceTest

# Run tests for specific path
php artisan test tests/Feature/Api/
```

---

## 📋 Development Workflow

### Starting a New Feature

1. **Create feature branch** from `main`/`develop`

    ```bash
    git checkout -b feature/payment-integration
    ```

2. **Review applicable documentation**

    - Check [Folder Structure](./folder_structure_guide.md) for file placement
    - Review [Enforcement Matrix](./ENFORCEMENT_MATRIX.md) for mandatory standards
    - Read relevant guides (API, Database, etc.)

3. **Implement with standards**

    - Follow folderization patterns
    - Use UUID primary keys
    - Add authorization checks
    - Implement audit logging for sensitive operations

4. **Write tests**

    - Unit tests for business logic
    - Feature tests for endpoints
    - Edge cases and error scenarios

5. **Document your code**

    - PHPDoc blocks
    - Inline comments for complex logic
    - Update README if needed
    - API documentation

6. **Run quality checks**

    ```bash
    ./vendor/bin/phpstan analyze
    ./vendor/bin/pint
    php artisan test
    ```

7. **Submit PR with checklist**
    - Complete [PR Checklist](./PR_CHECKLIST.md)
    - Link related issues
    - Add screenshots for UI changes
    - Request reviews

### Code Review Process

1. **Automated Checks** (must pass)

    - PHPStan static analysis
    - PHP_CodeSniffer (PSR-12)
    - PHPUnit tests
    - Security scanner

2. **Manual Review**

    - Code quality and standards
    - Security implications
    - Performance considerations
    - Documentation completeness

3. **Approval & Merge**
    - Required approvals based on change type (see [Enforcement Matrix](./ENFORCEMENT_MATRIX.md#review-priority-matrix))
    - Squash or rebase as per project policy
    - Delete feature branch after merge

---

## 🛠️ Tools & Configuration

### Required Development Tools

| Tool                 | Purpose           | Configuration  |
| -------------------- | ----------------- | -------------- |
| **PHPStan/Larastan** | Static analysis   | `phpstan.neon` |
| **Laravel Pint**     | Code formatting   | `pint.json`    |
| **PHP_CodeSniffer**  | PSR-12 compliance | `phpcs.xml`    |
| **PHPUnit**          | Testing           | `phpunit.xml`  |

### IDE Configuration

**VS Code Extensions:**

- Laravel Extension Pack
- PHP Intelephense
- Laravel Blade Snippets
- Laravel goto view
- PHPDoc Comment Generator

**PHPStorm Plugins:**

- Laravel Plugin
- Laravel Idea
- PHP Inspections
- Blade Plugin

---

## 📦 Key Technologies

| Technology            | Version    | Purpose              |
| --------------------- | ---------- | -------------------- |
| **Laravel**           | 11.x       | PHP framework        |
| **PHP**               | 8.1+       | Programming language |
| **MySQL/MariaDB**     | 8.0+/10.3+ | Primary database     |
| **Spatie Permission** | 6.0+       | Roles & permissions  |
| **Laravel Sanctum**   | 4.x        | API authentication   |
| **Tailwind CSS**      | 3.x        | Styling framework    |
| **Alpine.js**         | 3.x        | JavaScript framework |

---

## 🚦 Project Status

| Section                                                             | Status      | Last Updated |
| ------------------------------------------------------------------- | ----------- | ------------ |
| [Folder Structure](./folder_structure_guide.md)                     | ✅ Complete | Dec 27, 2025 |
| [UI Design](./ui_design_guide.md)                                   | ✅ Complete | Dec 27, 2025 |
| [Database & Data](./database_and_data_design_guide.md)              | ✅ Complete | Dec 27, 2025 |
| [Roles & Permissions](./roles_and_permissions_guide.md)             | ✅ Complete | Dec 27, 2025 |
| [Audit Logging](./audit_log_guide.md)                               | ✅ Complete | Dec 27, 2025 |
| [Documentation](./0.6%20documentation_guide.md)                     | ✅ Complete | Dec 27, 2025 |
| [API Standards](./0.7%20api_guide.md)                               | ✅ Complete | Dec 27, 2025 |
| [Routing](./0.8%20routing_guide.md)                                 | ✅ Complete | Dec 27, 2025 |
| [Coding Standards](./0.9%20coding_standards_guide.md)               | ✅ Complete | Jan 14, 2026 |
| [Security Best Practices](./1.0%20security_best_practices_guide.md) | ✅ Complete | Jan 14, 2026 |
| [PR Checklist](./PR_CHECKLIST.md)                                   | ✅ Complete | Jan 14, 2026 |
| [Enforcement Matrix](./ENFORCEMENT_MATRIX.md)                       | ✅ Complete | Jan 14, 2026 |

---

## 🤝 Contributing

### Before Contributing

1. Read this README completely
2. Review the [PR Checklist](./PR_CHECKLIST.md)
3. Check the [Enforcement Matrix](./ENFORCEMENT_MATRIX.md)
4. Understand security requirements

### Getting Help

- **Documentation unclear?** Open an issue or ask in team chat
- **Standards conflict?** Escalate to tech lead
- **Security question?** Contact security lead immediately
- **Need code review?** Tag appropriate reviewers based on [Enforcement Matrix](./ENFORCEMENT_MATRIX.md)

### Reporting Issues

- **Security vulnerabilities:** See [SECURITY.md](../../SECURITY.md)
- **Bugs:** Use bug report template
- **Features:** Use feature request template
- **Documentation:** Open issue with "docs" label

---

## 📚 Additional Resources

### Internal Documentation

- [Client Edit Audit System](../../CLIENT_EDIT_AUDIT_SYSTEM.md)
- [Security Policy](../../SECURITY.md)
- [API Documentation](../api/)
- [Agent Documentation](../agent/)

### External Resources

- [Laravel 11 Documentation](https://laravel.com/docs/11.x)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6/)
- [Laravel Sanctum](https://laravel.com/docs/11.x/sanctum)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)

---

## 🔄 Version History

| Version | Date         | Changes                                                        |
| ------- | ------------ | -------------------------------------------------------------- |
| 1.1.0   | Jan 14, 2026 | Added Coding Standards Guide and Security Best Practices Guide |
| 1.0.0   | Dec 27, 2025 | Initial comprehensive documentation release                    |

---

## 📞 Support & Contact

**Development Team Lead:** [TBD]  
**Security Lead:** [TBD]  
**Documentation Maintainer:** Development Team

---

## 📝 License

This documentation is proprietary to this project.

---

**Last Updated:** January 14, 2026  
**Documentation Version:** 1.1.0  
**Project:** [Your Project]  
**Framework:** Laravel 11+

---

<div align="center">
<strong>Build with quality. Code with standards. Ship with confidence.</strong>
</div>
