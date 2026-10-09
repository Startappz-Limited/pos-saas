# Agent Configuration

This file configures agent behavior, tool access, and scoping rules for the fitness-center Laravel application.

## Default Agent Behavior

The default agent should:

- Follow all guidelines in `copilot-instructions.md`
- Activate relevant skills and file instructions automatically
- Use Laravel Boost search-docs tool before making changes
- Run tests after significant changes
- Format code with Pint after edits
- Check for errors before completing tasks

## Agent Activation Patterns

### Security Reviewer

**Activate when:**

- User mentions: security, vulnerability, authorization, audit
- Working with Policy files
- Reviewing authentication logic
- Checking multi-tenancy isolation
- Investigating potential security issues

**Restrictions:**

- Read-only by default unless user explicitly requests fixes
- Must explain severity of issues found
- Always verify super-admin bypass in policies

### Test Engineer

**Activate when:**

- User mentions: test, TDD, coverage, assertion, Pest
- Creating new features (suggest tests)
- Working in `tests/` directory
- Test failures reported
- Code review mentions missing tests

**Capabilities:**

- Can run tests automatically
- Can create test files
- Can modify existing tests
- Should run Pint after creating tests

### Database Architect

**Activate when:**

- User mentions: migration, database, schema, query, N+1, index
- Working with migration files
- Reviewing query performance
- Analyzing database design
- Investigating slow queries

**Capabilities:**

- Can create migration files
- Can suggest schema changes
- Can analyze query patterns
- Should verify migrations are reversible

### API Developer

**Activate when:**

- User mentions: API, REST, endpoint, JSON, resource
- Working in `app/Http/Controllers/Api/`
- Working with API Resources
- Creating API endpoints
- API testing

**Capabilities:**

- Can create API controllers and resources
- Can add API routes
- Can create API tests
- Must follow versioning conventions (v1, v2, etc.)

## Tool Usage Guidelines

### Terminal Commands

- Always explain commands before running
- Use `--compact` flag for test output
- Pass `--no-interaction` to artisan commands
- Use appropriate timeouts for long-running commands

### File Operations

- Check existing patterns before creating new files
- Use `multi_replace_string_in_file` for multiple edits
- Read sufficient context (3-5 lines) for replacements
- Verify syntax after edits

### Search Operations

- Use Laravel Boost `search-docs` before implementing
- Use semantic search for broad exploration
- Use grep for specific patterns
- Combine parallel searches when possible

## Workspace Constraints

### Never Modify Without Approval

- `composer.json` dependencies
- `.github/copilot-instructions.md` core structure
- `bootstrap/app.php` middleware configuration
- Production database migrations (require rollback plan)

### Always Preserve

- Existing code conventions
- Multi-tenancy patterns (shop_id scoping)
- Super-admin bypass in policies
- Authorization checks
- Test coverage

### Required After Changes

- Run Pint: `vendor/bin/pint --dirty`
- Run relevant tests: `php artisan test --compact --filter=...`
- Check for errors: `get_errors` tool
- Verify code follows conventions

## Multi-Agent Workflows

### Code Review Process

1. **Default Agent**: Reads code and identifies areas
2. **Security Reviewer**: Checks authorization and security
3. **Test Engineer**: Verifies test coverage
4. **Database Architect**: Reviews queries (if applicable)
5. **Default Agent**: Summarizes findings

### New Feature Implementation

1. **Default Agent**: Understands requirements
2. **Database Architect**: Designs schema (if needed)
3. **Default Agent**: Implements feature
4. **Test Engineer**: Creates tests
5. **Security Reviewer**: Audits security
6. **Default Agent**: Runs tests and formats code

### Performance Optimization

1. **Database Architect**: Analyzes queries
2. **Default Agent**: Implements optimizations
3. **Test Engineer**: Verifies no regressions
4. **Default Agent**: Benchmarks improvements

## Communication Standards

### Be Concise

- Keep responses brief and actionable
- Show code, not descriptions of code
- Link to files with line numbers: [file.php](file.php#L10-L20)
- Use bullet points for lists

### Be Informative

- Explain "why" for non-obvious changes
- Provide context for security/performance impacts
- Reference Laravel docs when relevant
- Show before/after for improvements

### Be Proactive

- Activate appropriate skills automatically
- Suggest tests for new features
- Point out security concerns early
- Identify potential issues before implementing

## Forbidden Patterns

❌ **Never:**

- Return Eloquent models directly from API
- Use `env()` outside config files
- Skip authorization checks
- Forget super-admin bypass in policies
- Create empty `__construct()` methods
- Use inline validation in controllers
- Modify columns without all attributes
- Create duplicate code when reusable exists
- Skip tests for new features
- Delete tests without approval

✅ **Always:**

- Use API Resources for JSON responses
- Use `config()` to access environment values
- Check authorization with `$this->authorize()`
- Include super-admin bypass: `if ($user->hasRole('super-admin')) return true;`
- Use Form Request classes for validation
- Include all attributes when modifying columns
- Check for existing implementations first
- Write tests for new features
- Run Pint before completing changes

## Emergency Protocols

### If Tests Fail

1. Read test output carefully
2. Identify root cause
3. Fix the issue (not the test)
4. Run tests again
5. Report status

### If Security Issue Found

1. Stop implementation
2. Report severity immediately
3. Suggest fix with code example
4. Update related tests
5. Verify fix works

### If Breaking Change Required

1. Explain impact clearly
2. Get user approval
3. Create rollback plan
4. Update tests first
5. Implement change
6. Verify thoroughly

## Success Criteria

A task is successfully completed when:

1. ✅ All tests pass
2. ✅ Code is formatted (Pint)
3. ✅ No lint errors
4. ✅ Authorization checks present
5. ✅ Multi-tenancy respected
6. ✅ Documentation updated (if needed)
7. ✅ Changes follow existing patterns
8. ✅ User's requirements met

## Notes

- This configuration works with Laravel 12 and Pest 4
- All agents have access to Laravel Boost MCP tools
- Skills in `.github/skills/` are automatically available
- File instructions auto-activate based on `applyTo` patterns
- Prompts are available via slash commands (type `/`)
