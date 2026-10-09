# Enforcement Matrix

## Overview
This matrix defines which standards and guidelines must be enforced for different types of changes in this project. Use this to quickly determine which sections of the documentation apply to your work.

**Legend:**
- 🔴 **Mandatory** - Must be followed, PR will be blocked without compliance
- 🟡 **Recommended** - Strongly encouraged, deviations must be justified
- 🟢 **Optional** - Apply if relevant to the change
- ⚪ **Not Applicable** - Does not apply to this change type

---

## Enforcement by Change Type

| Standard | New Feature | Bug Fix | Refactor | Hotfix | Documentation | Config Change | Database Change | API Change |
|----------|-------------|---------|----------|--------|---------------|---------------|-----------------|------------|
| **Folder Structure** | 🔴 | 🟡 | 🔴 | 🟡 | ⚪ | ⚪ | 🟡 | 🔴 |
| **Coding Standards** | 🔴 | 🔴 | 🔴 | 🟡 | ⚪ | ⚪ | 🟡 | 🔴 |
| **Database Design** | 🔴 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | 🔴 | 🔴 |
| **Models & Eloquent** | 🔴 | 🟡 | 🔴 | 🟡 | ⚪ | ⚪ | 🔴 | 🔴 |
| **Roles & Permissions** | 🔴 | 🟡 | 🟡 | 🟡 | ⚪ | 🟡 | 🟡 | 🔴 |
| **Audit Logging** | 🔴 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | 🔴 | 🔴 |
| **API Standards** | 🔴 | 🔴 | 🔴 | 🔴 | ⚪ | 🟡 | ⚪ | 🔴 |
| **Routing Standards** | 🔴 | 🟡 | 🔴 | 🟡 | ⚪ | 🟡 | ⚪ | 🔴 |
| **Security Best Practices** | 🔴 | 🔴 | 🔴 | 🔴 | ⚪ | 🔴 | 🔴 | 🔴 |
| **UI/UX Guidelines** | 🔴 | 🟡 | 🟡 | 🟡 | ⚪ | ⚪ | ⚪ | ⚪ |
| **Testing** | 🔴 | 🔴 | 🔴 | 🟡 | ⚪ | 🟡 | 🔴 | 🔴 |
| **Documentation** | 🔴 | 🟡 | 🟡 | 🟡 | 🔴 | 🔴 | 🔴 | 🔴 |
| **Security Review** | 🔴 | 🔴 | 🟡 | 🔴 | ⚪ | 🔴 | 🔴 | 🔴 |
| **Code Quality** | 🔴 | 🔴 | 🔴 | 🔴 | 🟡 | 🟡 | 🔴 | 🔴 |
| **N+1 Prevention** | 🔴 | 🔴 | 🔴 | 🟡 | ⚪ | ⚪ | 🔴 | 🔴 |

---

## Enforcement by Component

| Standard | Controllers | Services | Jobs | Models | Views | API | Middleware | Events | Policies | Actions |
|----------|-------------|----------|------|--------|-------|-----|------------|--------|----------|----------|
| **Folder Structure** | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 |
| **Naming Conventions** | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 |
| **Type Hints** | 🔴 | 🔴 | 🔴 | 🔴 | ⚪ | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 |
| **UUID Usage** | 🔴 | 🔴 | 🔴 | 🔴 | 🟡 | 🔴 | 🟡 | 🟡 | 🔴 | 🔴 |
| **Authorization** | 🔴 | 🟡 | 🟡 | ⚪ | 🟡 | 🔴 | ⚪ | ⚪ | 🔴 | 🟡 |
| **Audit Logging** | 🟡 | 🔴 | 🟡 | ⚪ | ⚪ | 🔴 | ⚪ | 🔴 | ⚪ | 🔴 |
| **Error Handling** | 🔴 | 🔴 | 🔴 | 🟡 | 🟡 | 🔴 | 🔴 | 🟡 | 🟡 | 🔴 |
| **PHPDoc** | 🔴 | 🔴 | 🔴 | 🔴 | ⚪ | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 |
| **Testing** | 🔴 | 🔴 | 🔴 | 🔴 | 🟡 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 |
| **N+1 Prevention** | 🔴 | 🔴 | 🔴 | 🔴 | ⚪ | 🔴 | ⚪ | ⚪ | ⚪ | 🔴 |
| **Single Responsibility** | 🔴 | 🔴 | 🔴 | 🟡 | 🟡 | 🔴 | 🔴 | 🔴 | 🔴 | 🔴 |

---

## Security Standards Enforcement

All security-related standards are **mandatory** regardless of change type:

| Security Standard | Enforcement | Applies To |
|-------------------|-------------|------------|
| Authentication Check | 🔴 Mandatory | All protected routes and API endpoints |
| Authorization (Policies/Permissions) | 🔴 Mandatory | All resource access (create, read, update, delete) |
| UUID in URLs (not integer IDs) | 🔴 Mandatory | All public-facing routes and APIs |
| Mass Assignment Protection | 🔴 Mandatory | All models with user input |
| SQL Injection Prevention | 🔴 Mandatory | All database queries |
| XSS Prevention | 🔴 Mandatory | All Blade templates with user content |
| CSRF Protection | 🔴 Mandatory | All state-changing web forms |
| **Honeypot Protection** | 🔴 Mandatory | **ALL forms (no exceptions)** |
| Input Validation (Form Requests) | 🔴 Mandatory | All user input handling |
| Use `$request->validated()` | 🔴 Mandatory | All controller store/update methods |
| Sensitive Data Sanitization | 🔴 Mandatory | All logging and audit trails |
| Rate Limiting | 🔴 Mandatory | All API endpoints and login/signup forms |
| Secure Password Storage | 🔴 Mandatory | All password handling |
| API Token Security | 🔴 Mandatory | All Sanctum token generation and validation |
| Environment Variable Usage | 🔴 Mandatory | All credentials and secrets |
| Data Encryption | 🔴 Mandatory | All sensitive data storage |
| Queue Payload Encryption | 🟡 Recommended | Jobs with sensitive data |
| Security Headers | 🔴 Mandatory | All HTTP responses |
| Debug Mode Disabled | 🔴 Mandatory | Production environment |

---

## Data Protection Standards

| Data Standard | Enforcement | Applies To |
|---------------|-------------|------------|
| UUID Primary Keys | 🔴 Mandatory | All new models and tables |
| Foreign Key UUIDs | 🔴 Mandatory | All relationship definitions |
| Enum Casting | 🟡 Recommended | Status and type fields |
| Timestamp Fields | 🔴 Mandatory | All tables (created_at, updated_at) |
| Soft Deletes | 🟡 Recommended | User-facing data tables |
| Database Indexes | 🔴 Mandatory | Foreign keys and frequently queried columns |
| Migration Rollback | 🔴 Mandatory | All migrations must have down() method |
| Data Validation | 🔴 Mandatory | All user input before database insertion |
| Audit Trail | 🔴 Mandatory | Sensitive operations (user management, payments, policy changes) |
| Separate Audit DB | 🔴 Mandatory | All audit log entries |

---

## API Standards Enforcement

| API Standard | Enforcement | Public API | Internal API | Webhook |
|--------------|-------------|------------|--------------|---------|
| Versioning (/api/v1/) | 🔴 Mandatory | 🔴 | 🟡 | 🟡 |
| RESTful Conventions | 🔴 Mandatory | 🔴 | 🔴 | 🟡 |
| Standard Response Format | 🔴 Mandatory | 🔴 | 🔴 | 🔴 |
| HTTP Status Codes | 🔴 Mandatory | 🔴 | 🔴 | 🔴 |
| Authentication (Sanctum) | 🔴 Mandatory | 🔴 | 🔴 | 🟡 |
| Rate Limiting | 🔴 Mandatory | 🔴 | 🟡 | 🟡 |
| Pagination | 🔴 Mandatory | 🔴 | 🟡 | ⚪ |
| Field Filtering | 🟡 Recommended | 🟡 | 🟢 | ⚪ |
| API Documentation | 🔴 Mandatory | 🔴 | 🟡 | 🔴 |
| Idempotency Keys | 🟡 Recommended | 🟡 | 🟢 | 🟡 |
| Error Messages | 🔴 Mandatory | 🔴 | 🔴 | 🔴 |
| Request Validation | 🔴 Mandatory | 🔴 | 🔴 | 🔴 |

---

## Testing Requirements by Change Type

| Test Type | New Feature | Bug Fix | Refactor | Hotfix | API Change | Database Change |
|-----------|-------------|---------|----------|--------|------------|-----------------|
| **Unit Tests** | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory | 🟡 Recommended |
| **Feature Tests** | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory | 🟡 Recommended |
| **Integration Tests** | 🟡 Recommended | 🟢 Optional | 🟡 Recommended | ⚪ N/A | 🔴 Mandatory | 🟡 Recommended |
| **Edge Case Tests** | 🔴 Mandatory | 🔴 Mandatory | 🟡 Recommended | 🟡 Recommended | 🔴 Mandatory | 🟡 Recommended |
| **Negative Tests** | 🔴 Mandatory | 🟡 Recommended | 🟡 Recommended | 🟢 Optional | 🔴 Mandatory | 🟡 Recommended |
| **Performance Tests** | 🟡 Recommended | 🟢 Optional | 🟡 Recommended | ⚪ N/A | 🟡 Recommended | 🟡 Recommended |
| **Security Tests** | 🔴 Mandatory | 🟡 Recommended | 🟡 Recommended | 🔴 Mandatory | 🔴 Mandatory | 🟡 Recommended |

---

## Documentation Requirements

| Documentation Type | New Feature | Bug Fix | Refactor | Config Change | API Change | Breaking Change |
|--------------------|-------------|---------|----------|---------------|------------|-----------------|
| **Code Comments** | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory | 🔴 Mandatory |
| **PHPDoc Blocks** | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory | 🔴 Mandatory |
| **README Updates** | 🔴 Mandatory | 🟢 Optional | 🟡 Recommended | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory |
| **API Documentation** | 🔴 Mandatory | 🟡 Recommended | ⚪ N/A | ⚪ N/A | 🔴 Mandatory | 🔴 Mandatory |
| **Migration Guide** | 🟡 Recommended | ⚪ N/A | 🟡 Recommended | 🟡 Recommended | 🟡 Recommended | 🔴 Mandatory |
| **Changelog Entry** | 🔴 Mandatory | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory |
| **.env.example** | 🔴 Mandatory | 🟢 Optional | ⚪ N/A | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory |

---

## Code Quality Standards (Always Mandatory)

These standards apply to **ALL** code changes:

| Standard | Enforcement | Automated Check | Manual Review |
|----------|-------------|-----------------|---------------|
| PSR-12 Coding Standards | 🔴 Mandatory | ✅ PHP_CodeSniffer | ✅ Required |
| No Debug Code (dd, dump) | 🔴 Mandatory | ✅ CI Pipeline | ✅ Required |
| Type Hints (Parameters & Return) | 🔴 Mandatory | ✅ PHPStan/Larastan | ✅ Required |
| No Commented Code | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |
| Meaningful Variable Names | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |
| Single Responsibility | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |
| DRY Principle | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |
| Error Handling | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |
| No Hardcoded Values | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |
| Proper Exception Usage | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |
| **Methods < 20 Lines** | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |
| **Comments Explain WHY** | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |
| **N+1 Query Prevention** | 🔴 Mandatory | ⚪ Manual Only | ✅ Required |

---

## Performance Standards

| Standard | New Feature | Refactor | Database Change | API Endpoint |
|----------|-------------|----------|-----------------|--------------|
| Eager Loading (N+1 Prevention) | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory |
| Database Indexes | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory | 🔴 Mandatory |
| Query Optimization | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory |
| Pagination for Lists | 🔴 Mandatory | 🟡 Recommended | ⚪ N/A | 🔴 Mandatory |
| Caching Strategy | 🟡 Recommended | 🟡 Recommended | 🟢 Optional | 🟡 Recommended |
| Queue Heavy Operations | 🔴 Mandatory | 🟡 Recommended | 🟡 Recommended | 🔴 Mandatory |
| Response Time < 200ms | 🟡 Recommended | 🟡 Recommended | ⚪ N/A | 🔴 Mandatory |
| Memory Optimization | 🟡 Recommended | 🔴 Mandatory | 🟡 Recommended | 🔴 Mandatory |

---

## Deployment Checklist Enforcement

| Check | Feature Branch | Staging | Production | Hotfix |
|-------|----------------|---------|------------|--------|
| All Tests Pass | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory |
| Code Review Approved | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory | 🟡 Recommended |
| Documentation Updated | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory | 🟡 Recommended |
| .env.example Updated | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory | 🟡 Recommended |
| Migrations Tested | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory |
| Rollback Plan | 🟡 Recommended | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory |
| Security Scan | 🟡 Recommended | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory |
| Performance Testing | 🟢 Optional | 🟡 Recommended | 🔴 Mandatory | 🟢 Optional |
| Load Testing | ⚪ N/A | 🟢 Optional | 🟡 Recommended | ⚪ N/A |
| Database Backup | ⚪ N/A | 🔴 Mandatory | 🔴 Mandatory | 🔴 Mandatory |

---

## Exception Process

If you need to deviate from a 🔴 Mandatory standard:

1. **Document the Reason**: Explain why the standard cannot be followed
2. **Propose Alternative**: Suggest an alternative approach that achieves the same goal
3. **Security Review**: If security-related, get explicit approval from security lead
4. **Add Technical Debt**: Create a ticket to address the deviation later
5. **Get Approval**: Obtain approval from tech lead or architect
6. **Document in Code**: Add comments explaining the exception

### Exception Request Template

```markdown
## Standard Exception Request

**Standard:** [Name of standard being deviated from]
**Section:** [Documentation section reference]
**Reason:** [Detailed explanation of why standard cannot be followed]
**Alternative Approach:** [What will be done instead]
**Impact Assessment:** [Potential risks and mitigation]
**Technical Debt Ticket:** [Link to issue tracking the deviation]
**Approver:** [Name and role]
**Date:** [Approval date]
```

---

## Automated Enforcement Tools

| Tool | Purpose | Run On | Blocks PR |
|------|---------|--------|-----------|
| **PHP_CodeSniffer** | PSR-12 compliance | Every commit | ✅ Yes |
| **PHPStan/Larastan** | Static analysis, type checking | Every commit | ✅ Yes |
| **PHPUnit** | Unit & feature tests | Every commit | ✅ Yes |
| **Laravel Pint** | Code formatting | Pre-commit hook | 🟡 Warning |
| **PHPMD** | Code complexity | Every commit | 🟡 Warning |
| **Security Scanner** | Dependency vulnerabilities | Daily + PR | ✅ Yes |
| **Coverage Report** | Test coverage metrics | Every PR | 🟡 Warning |

---

## Review Priority Matrix

| Change Type | Review Depth | Required Reviewers | Max Review Time |
|-------------|--------------|-------------------|-----------------|
| **Hotfix** | 🔴 High | 1 Senior Dev | 2 hours |
| **Bug Fix** | 🟡 Medium | 1 Developer | 24 hours |
| **New Feature** | 🔴 High | 2 Developers | 48 hours |
| **Refactor** | 🔴 High | 1 Senior Dev | 48 hours |
| **Documentation** | 🟢 Light | 1 Developer | 24 hours |
| **Config Change** | 🔴 High | 1 Senior Dev + DevOps | 24 hours |
| **Database Change** | 🔴 High | 1 Senior Dev + DBA | 48 hours |
| **Security Update** | 🔴 Critical | 2 Senior Devs | 4 hours |

---

## Quick Reference

### For Developers

**Before starting work:**
1. Check this matrix for applicable standards
2. Review relevant documentation sections
3. Ensure local environment is configured

**Before submitting PR:**
1. Run automated checks locally
2. Complete applicable PR checklist items
3. Ensure all mandatory standards are met

**If standards unclear:**
1. Consult documentation guides
2. Ask in team chat
3. Request clarification before proceeding

### For Reviewers

**Priority checks:**
1. Security standards (all mandatory, including honeypot on forms)
2. N+1 query prevention (eager loading)
3. Data protection (all mandatory)
4. Testing coverage (based on change type)
5. Documentation completeness

**Can approve if:**
- All 🔴 mandatory items completed
- All 🟡 recommended items completed or justified
- Code quality meets standards
- Tests pass and cover edge cases

---

## Related Resources

- [Pull Request Checklist](./PR_CHECKLIST.md) - Detailed checklist for submitting PRs
- [Documentation Index](./README.md) - Links to all documentation guides
- [Folder Structure Guide](./folder_structure_guide.md) - Code organization standards
- [Coding Standards Guide](./0.9%20coding_standards_guide.md) - Clean code, architecture, N+1 prevention
- [Security Best Practices Guide](./1.0%20security_best_practices_guide.md) - Security standards, honeypot, encryption
- [Security Guide](../../SECURITY.md) - Security policies and reporting

---

**Last Updated:** January 14, 2026  
**Version:** 1.1.0  
**Maintainer:** Development Team
