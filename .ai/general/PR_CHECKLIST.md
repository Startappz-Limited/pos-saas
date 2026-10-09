# Pull Request Checklist

## Overview
This checklist ensures all code contributions meet the  project standards as defined in the comprehensive documentation guides. Review all applicable sections before submitting your PR.

---

## 1. Code Organization & Structure

### Folderization Compliance
- [ ] New files are organized by purpose (not modules)
- [ ] Controllers placed in appropriate subfolder: `Controllers/{Admin|Api|Web}/`
- [ ] Services placed in: `Services/{Domain}/`
- [ ] Jobs placed in: `Jobs/{Purpose}/`
- [ ] Models placed in: `Models/`
- [ ] Mail classes placed in: `Mail/{Domain}/`
- [ ] Views follow folder structure: `views/{domain}/{action}.blade.php`
- [ ] Naming conventions followed (PascalCase for classes, kebab-case for views)

**Reference:** [Section 1: Folder Structure Guide](./folder_structure_guide.md)

---

## 2. Coding Standards & Architecture

### Clean Code
- [ ] Comments explain WHY, not WHAT
- [ ] Methods are under 20 lines with single responsibility
- [ ] Meaningful variable and method names
- [ ] No commented-out code (remove or justify)
- [ ] No debug code (`dd()`, `dump()`, `var_dump()`)

### Laravel Architecture
- [ ] Actions used for single-purpose reusable operations
- [ ] Services used for complex business logic orchestration
- [ ] Jobs used for queued/async operations
- [ ] Events & Listeners used for decoupled side effects
- [ ] Observers used for model lifecycle hooks (creating, updated, deleted)
- [ ] Form Requests used for validation
- [ ] Policies used for authorization logic
- [ ] API Resources used for response formatting
- [ ] Custom Rules in `Rules/` folder for reusable validation

### N+1 Prevention (Mandatory)
- [ ] Relationships eager loaded with `with()`
- [ ] Nested relationships loaded: `with('relation.nested')`
- [ ] `withCount()` used instead of `->count()` in loops
- [ ] `withExists()` used instead of existence checks in loops
- [ ] `withSum()`, `withAvg()` used for aggregates
- [ ] Large datasets chunked or lazy loaded
- [ ] Only needed columns selected with `select()`

**Reference:** [Section 9: Coding Standards Guide](./0.9%20coding_standards_guide.md)

---

## 3. Database & Data Changes

### Migrations
- [ ] Anonymous migration class syntax used (Laravel 11+)
- [ ] Migration file named descriptively with timestamp
- [ ] Both `up()` and `down()` methods implemented
- [ ] Foreign keys use `uuid` type (not `id`)
- [ ] Indexes added for foreign keys and frequently queried columns
- [ ] Default values specified where appropriate

### Models
- [ ] UUID/ULID trait added: `use HasUuids;` or `use HasUlids;`
- [ ] `$fillable` or `$guarded` property defined
- [ ] Timestamps configured (`$timestamps`)
- [ ] Modern `casts()` method used (not `$casts` property)
- [ ] Enum casting implemented for status fields
- [ ] Relationships defined with proper type hints
- [ ] Query scopes added for common filters
- [ ] Model events handled via observers when needed

### Data Validation
- [ ] FormRequest classes created for complex validation
- [ ] Validation rules are specific and secure
- [ ] Custom validation rules in `Rules/` folder
- [ ] Database queries use parameter binding (no raw SQL injection risks)

**Reference:** [Section 3: Database & Data Design Guide](./database_and_data_design_guide.md)

---

## 4. Security Best Practices

### Environment & Configuration
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production
- [ ] Strong `APP_KEY` generated
- [ ] HTTPS enforced in production
- [ ] `.env` excluded from version control
- [ ] Secrets stored in environment variables (not code)

### Authentication & Session
- [ ] Strong password requirements using `Password::defaults()`
- [ ] Login rate limiting configured (`throttle:5,1`)
- [ ] 2FA implemented for sensitive accounts (if applicable)
- [ ] Secure session configuration (HTTPS only, HttpOnly)
- [ ] Sanctum tokens used for API authentication

### Input & Output
- [ ] All input validated via Form Requests
- [ ] Using `$request->validated()` (NEVER `$request->all()`)
- [ ] Blade escaping `{{ }}` used (avoid `{!! !!}`)
- [ ] HTML purified for rich text content
- [ ] File uploads validated (MIME type, size, dimensions)

### Form Protection
- [ ] CSRF token included: `@csrf`
- [ ] Honeypot added to ALL forms: `@honeypot`
- [ ] Rate limiting on form submissions

### Database Security
- [ ] Using Eloquent/Query Builder (no raw SQL)
- [ ] Parameter binding for any raw queries
- [ ] Column names whitelisted for dynamic sorting/filtering
- [ ] Mass assignment protection (`$fillable`/`$guarded`)

### Data Protection
- [ ] Sensitive data encrypted with `Crypt` facade
- [ ] Passwords hashed with `Hash::make()`
- [ ] Sensitive data NOT logged (passwords, tokens, PII)
- [ ] Log data sanitized before storage
- [ ] Queue job payloads encrypted (`ShouldBeEncrypted`)

### Headers & Security
- [ ] Security headers configured (X-Frame-Options, CSP, etc.)
- [ ] CORS properly configured for APIs

**Reference:** [Section 10: Security Best Practices Guide](./1.0%20security_best_practices_guide.md)

---

## 5. Authorization & Permissions

### Authentication
- [ ] Sanctum tokens used for API authentication
- [ ] Token abilities defined for different access levels
- [ ] Session authentication used for web routes
- [ ] Authentication middleware applied to protected routes

### Permissions & Roles
- [ ] Spatie Laravel Permission package patterns followed
- [ ] `HasRoles` trait added to User model if new
- [ ] Permissions checked in controllers using `authorize()`
- [ ] Policy classes created for complex authorization
- [ ] Middleware applied: `permission:`, `role:`, or `role_or_permission:`
- [ ] Blade directives used: `@can`, `@role`, `@hasrole`
- [ ] New permissions seeded in database
- [ ] Permission names follow convention: `{resource}.{action}`

### Data Protection
- [ ] Sensitive data not logged or exposed in responses
- [ ] UUID used instead of integer IDs in URLs and APIs
- [ ] Mass assignment protection configured
- [ ] CSRF protection enabled for state-changing operations

**Reference:** [Section 4: Roles & Permissions Guide](./roles_and_permissions_guide.md)

---

## 6. Audit Logging

### Auditable Events
- [ ] Audit logging added for sensitive operations (create/update/delete)
- [ ] `AuditableEvent` dispatched with proper context
- [ ] User context included (`user_id`, `user_type`)
- [ ] IP address and user agent captured
- [ ] Sensitive data sanitized before logging
- [ ] Audit logs written to separate database connection

### Implementation
- [ ] Model observers used for automatic audit logging
- [ ] Manual audit logs for non-model operations
- [ ] Audit model uses `uuid` primary key
- [ ] Immutable audit records (no updates allowed)

**Reference:** [Section 5: Audit Log Guide](./audit_log_guide.md)

---

## 7. API Standards (if applicable)

### Endpoint Design
- [ ] RESTful conventions followed
- [ ] Versioned routes: `/api/v1/`
- [ ] Resource naming is plural: `/users`, `/policies`
- [ ] UUID used in URLs: `/api/v1/users/{uuid}`
- [ ] Nested resources limited to 2 levels

### Request Handling
- [ ] FormRequest classes for validation
- [ ] Bulk operations supported where appropriate
- [ ] Request headers validated (`Accept`, `Content-Type`)
- [ ] Idempotency keys supported for critical operations

### Response Format
- [ ] Standardized JSON response structure used
- [ ] HTTP status codes correct (200, 201, 204, 400, 401, 403, 404, 422, 500)
- [ ] Error responses include `message`, `errors` array
- [ ] Success responses include `data`, `message`, `meta`
- [ ] Pagination implemented with meta information
- [ ] Timestamps in ISO 8601 format

### Security & Performance
- [ ] Rate limiting configured per endpoint type
- [ ] Sanctum authentication middleware applied
- [ ] Token abilities checked for specific actions
- [ ] Eager loading used to prevent N+1 queries
- [ ] Response caching implemented where appropriate

**Reference:** [Section 7: API Guide](./0.7 api_guide.md)

---

## 8. Routing Standards

### Route Definition
- [ ] Routes registered in `bootstrap/app.php` or appropriate route file
- [ ] Route groups used for common middleware/prefixes
- [ ] Route names assigned using dot notation: `admin.users.index`
- [ ] UUID binding configured for models
- [ ] Middleware stack defined explicitly

### Route Organization
- [ ] Admin routes in `routes/admin/`
- [ ] API routes in `routes/api/`
- [ ] Web routes in `routes/web.php`
- [ ] Route files grouped by domain
- [ ] Versioned API routes separated: `routes/api/v1/`, `routes/api/v2/`

### Best Practices
- [ ] Resource routes used where applicable
- [ ] Unused resource actions excluded
- [ ] Route model binding utilized
- [ ] Authorization checked in route definitions or controllers
- [ ] Deprecated routes marked with middleware/comments

**Reference:** [Section 8: Routing Guide](./0.8 routing_guide.md)

---

## 9. UI & Frontend (if applicable)

### Design Compliance
- [ ] Raw HTML design copied from `./designs/theme/`
- [ ] Brand colors used from style guide
- [ ] Typography standards followed
- [ ] Responsive design implemented
- [ ] Accessibility attributes added (ARIA labels, alt text)

### Blade Templates
- [ ] Components created for reusable elements
- [ ] Layouts used for consistent structure
- [ ] XSS protection via `{{ }}` (not `{!! !!}` unless necessary)
- [ ] Form CSRF tokens included: `@csrf`
- [ ] Authorization directives used: `@can`, `@role`

**Reference:** [Section 2: UI Design Guide](./ui_design_guide.md)

---

## 10. Testing

### Test Coverage
- [ ] Unit tests for business logic (Services, Jobs)
- [ ] Feature tests for HTTP endpoints
- [ ] Database factories updated/created for new models
- [ ] Seeders created for test data
- [ ] Edge cases covered
- [ ] Negative test cases included

### Test Quality
- [ ] Tests are isolated and repeatable
- [ ] Database transactions used for test isolation
- [ ] Mock external services (email, SMS, payment gateways)
- [ ] Assertions are specific and meaningful
- [ ] Test names describe what is being tested

### Running Tests
- [ ] All tests pass: `php artisan test`
- [ ] No warnings or deprecations
- [ ] Code coverage maintained or improved

---

## 11. Documentation

### Code Documentation
- [ ] PHPDoc blocks for all public methods
- [ ] Complex logic explained with inline comments
- [ ] README created/updated for new features
- [ ] API endpoints documented
- [ ] Environment variables documented in `.env.example`

### Documentation Standards
- [ ] Markdown formatting standards followed
- [ ] Code examples provided where helpful
- [ ] Migration guides for breaking changes
- [ ] Screenshots/diagrams for UI changes

**Reference:** [Section 6: Documentation Guide](./0.6 documentation_guide.md)

---

## 12. Code Quality

### Code Standards
- [ ] PSR-12 coding standards followed
- [ ] Laravel best practices implemented
- [ ] No commented-out code (remove or explain why it's kept)
- [ ] No debug code (`dd()`, `dump()`, `var_dump()`)
- [ ] Environment-specific code uses config values

### Performance
- [ ] N+1 queries prevented with eager loading
- [ ] Database indexes added for performance
- [ ] Heavy operations queued (Jobs)
- [ ] Cache used appropriately
- [ ] Large datasets paginated

### Error Handling
- [ ] Try-catch blocks for external service calls
- [ ] Meaningful error messages
- [ ] Failed jobs logged and monitorable
- [ ] Graceful degradation for non-critical failures

---

## 13. Git & Version Control

### Commit Quality
- [ ] Commits are atomic and focused
- [ ] Commit messages are clear and descriptive
- [ ] No merge commits (rebase workflow)
- [ ] No unrelated changes included

### Branch Management
- [ ] Feature branch named descriptively
- [ ] Branch is up to date with `main`/`develop`
- [ ] Conflicts resolved correctly
- [ ] No unintended files committed (`.env`, IDE configs)

---

## 14. Deployment Readiness

### Environment Configuration
- [ ] New config values added to `.env.example`
- [ ] Config values documented
- [ ] Feature flags configured if needed
- [ ] No hardcoded credentials or secrets

### Migration Safety
- [ ] Migrations tested on staging environment
- [ ] Rollback strategy documented
- [ ] Data migration scripts tested
- [ ] No data loss risk

### Dependencies
- [ ] New packages added to `composer.json`/`package.json`
- [ ] Lock files updated
- [ ] Dependencies are stable versions (not dev branches)
- [ ] License compatibility verified

---

## Review Checklist (For Reviewers)

- [ ] Code follows project standards
- [ ] Security vulnerabilities checked
- [ ] Performance implications considered
- [ ] Tests are comprehensive
- [ ] Documentation is clear
- [ ] Breaking changes identified and documented
- [ ] Deployment risks assessed

---

## Pre-Merge Final Checks

- [ ] All CI/CD checks passing
- [ ] Required approvals obtained
- [ ] No unresolved comments
- [ ] Merge conflicts resolved
- [ ] Target branch is correct (`main`, `develop`, etc.)

---

## Quick Reference

| Section | Documentation | Key Standards |
|---------|---------------|---------------|
| 0 | [Project Overview](./README.md) | Project structure, getting started |
| 1 | [Folder Structure](./folder_structure_guide.md) | Folderization, naming conventions |
| 2 | [UI Design](./ui_design_guide.md) | Design system, components, accessibility |
| 3 | [Database & Data](./database_and_data_design_guide.md) | Migrations, models, UUIDs, enums |
| 4 | [Roles & Permissions](./roles_and_permissions_guide.md) | Spatie package, policies, middleware |
| 5 | [Audit Logging](./audit_log_guide.md) | Event-driven audits, separate DB |
| 6 | [Documentation](./0.6 documentation_guide.md) | PHPDoc, markdown, examples |
| 7 | [API Standards](./0.7 api_guide.md) | REST, versioning, responses, auth |
| 8 | [Routing](./0.8 routing_guide.md) | Route organization, naming, middleware |
| 9 | [Coding Standards](./0.9 coding_standards_guide.md) | Clean code, architecture, N+1 prevention |
| 10 | [Security Best Practices](./1.0 security_best_practices_guide.md) | Security, honeypot, encryption, logging |

---

## Notes

- This checklist is comprehensive. Only complete sections relevant to your changes.
- When in doubt, refer to the linked documentation guides.
- Ask for clarification before submitting if requirements are unclear.
- Security, honeypot protection, and N+1 prevention items are **mandatory** for all PRs.

---

**Last Updated:** January 14, 2026  
**Version:** 1.1.0  
**Maintainer:** Development Team
