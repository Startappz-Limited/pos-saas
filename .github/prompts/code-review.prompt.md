---
title: Code Review
description: Performs comprehensive code review checking security, performance, testing, and Laravel best practices
---

Perform a comprehensive code review on the following code:

{{codeContext|prompt:Specify file path, line range, or paste code to review}}

## Review Checklist

### Security

- [ ] Super-admin bypass in policies
- [ ] Authorization checks before actions
- [ ] Shop-scoped queries for multi-tenancy
- [ ] SQL injection prevention (using query builder/Eloquent)
- [ ] XSS prevention (proper escaping in views)
- [ ] CSRF protection on forms
- [ ] Mass assignment protection ($fillable/$guarded)

### Laravel Best Practices

- [ ] Following existing code conventions
- [ ] Using Form Requests for validation
- [ ] Proper type declarations (params & return types)
- [ ] Using Eloquent relationships instead of joins
- [ ] Eager loading to prevent N+1 queries
- [ ] Using named routes
- [ ] Proper use of transactions for multi-step operations
- [ ] Following PSR-12 coding standards

### Performance

- [ ] N+1 query prevention
- [ ] Proper indexing considerations
- [ ] Efficient query patterns
- [ ] Avoiding unnecessary database calls
- [ ] Using chunking for large datasets

### Testing

- [ ] Tests exist for new functionality
- [ ] Authorization scenarios covered
- [ ] Edge cases handled
- [ ] Factories used for test data
- [ ] Tests are properly isolated

### Code Quality

- [ ] Descriptive variable/method names
- [ ] Single Responsibility Principle
- [ ] DRY (Don't Repeat Yourself)
- [ ] Proper error handling
- [ ] Clear and concise PHPDoc blocks when needed
- [ ] No commented-out code

## Output Format

Provide findings in this structure:

**🔴 Critical Issues** (Must fix)

- Issue description
- File location
- Suggested fix

**🟡 Warnings** (Should fix)

- Issue description
- Suggested improvement

**✅ Positive Feedback**

- What's done well

**💡 Suggestions**

- Optional improvements
- Performance optimizations
- Alternative approaches
