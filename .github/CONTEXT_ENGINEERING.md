# GitHub Copilot Context Engineering

This project uses advanced GitHub Copilot context-engineering techniques to improve AI accuracy and productivity.

## 📁 Structure Overview

```
.github/
├── copilot-instructions.md    # Core Laravel Boost guidelines (always active)
├── AGENTS.md                   # Agent behavior and scoping rules
├── instructions/               # File-specific auto-activated instructions
│   ├── actions.instructions.md
│   ├── api.instructions.md
│   ├── migrations.instructions.md
│   ├── policies.instructions.md
│   └── tests.instructions.md
├── prompts/                    # Reusable slash commands
│   ├── api-endpoint.prompt.md
│   ├── code-review.prompt.md
│   ├── crud-resource.prompt.md
│   ├── database-optimization.prompt.md
│   ├── generate-tests.prompt.md
│   └── security-audit.prompt.md
├── agents/                     # Specialized AI personas
│   ├── api-developer.agent.md
│   ├── database-architect.agent.md
│   ├── security-reviewer.agent.md
│   └── test-engineer.agent.md
└── skills/                     # Domain-specific workflows
    ├── bootstrap-development/
    └── pest-testing/
```

## 🎯 Technique 1: Custom Instructions

**What**: File-specific instructions that auto-activate based on the file you're working on.

**When they activate**: Automatically when you open or edit matching files.

### Available Instructions

| Instruction                  | Activates For                 | Purpose                                           |
| ---------------------------- | ----------------------------- | ------------------------------------------------- |
| `policies.instructions.md`   | `app/Policies/**`             | Ensures super-admin bypass, proper authorization  |
| `actions.instructions.md`    | `app/Actions/**`              | Business logic patterns, transactions, validation |
| `migrations.instructions.md` | `database/migrations/**`      | Schema design, column attributes, indexing        |
| `tests.instructions.md`      | `tests/**`                    | Pest 4 patterns, assertions, test structure       |
| `api.instructions.md`        | `app/Http/Controllers/Api/**` | API resources, versioning, authentication         |

### Example

When you open [app/Policies/ProductPolicy.php](app/Policies/ProductPolicy.php), Copilot automatically loads policy guidelines and reminds you to include super-admin bypass.

## ⚡ Technique 2: Reusable Prompts

**What**: Parameterized templates for common tasks, triggered with slash commands.

**How to use**: Type `/` in the chat and select a prompt.

### Available Prompts

| Command                  | Purpose                                   | Parameters                        |
| ------------------------ | ----------------------------------------- | --------------------------------- |
| `/crud-resource`         | Generate complete CRUD resource           | Model name, columns, multi-tenant |
| `/code-review`           | Comprehensive code review                 | File path or code to review       |
| `/generate-tests`        | Create Pest tests                         | Class/feature to test             |
| `/api-endpoint`          | Build API endpoint                        | Resource name, version            |
| `/database-optimization` | Analyze queries and suggest optimizations | Model or controller               |
| `/security-audit`        | Security review                           | File or feature to audit          |

### Example Usage

```
You: /crud-resource

Copilot prompts for:
- Model Name: Membership
- Table Name: memberships (auto)
- Multi-tenant: yes (default)
- Columns: member_id:foreignId, start_date:date, end_date:date, type:string

Creates:
✅ Model with relationships
✅ Migration with indexes
✅ Factory with states
✅ Controller with authorization
✅ Form Requests
✅ Policy with super-admin bypass
✅ Feature tests
✅ Routes
```

## 🤖 Technique 3: Custom Agents

**What**: Specialized AI personas with specific expertise and behaviors.

**How to use**: Ask Copilot to use a specific agent or it will auto-activate based on context.

### Available Agents

#### Security Reviewer

```
You: "Review this policy for security issues"
Copilot: [Activates security-reviewer agent]
```

**Expertise:**

- Authorization patterns
- Multi-tenancy isolation
- OWASP vulnerabilities
- Spatie Permission patterns

**When to use:**

- Security audits
- Policy reviews
- Authorization checks
- Multi-tenancy validation

---

#### Test Engineer

```
You: "Create tests for the Sale model"
Copilot: [Activates test-engineer agent]
```

**Expertise:**

- Pest 4 framework
- Laravel testing patterns
- Authorization testing
- Multi-tenancy test isolation

**When to use:**

- Writing new tests
- Debugging test failures
- Improving coverage
- TDD workflows

---

#### Database Architect

```
You: "This query is slow, help optimize it"
Copilot: [Activates database-architect agent]
```

**Expertise:**

- Schema design
- Query optimization
- N+1 prevention
- Indexing strategies

**When to use:**

- Creating migrations
- Optimizing queries
- Adding indexes
- Database design

---

#### API Developer

```
You: "Create an API endpoint for products"
Copilot: [Activates api-developer agent]
```

**Expertise:**

- REST API design
- Laravel Resources
- API versioning
- Sanctum authentication

**When to use:**

- Building APIs
- Creating API resources
- API testing
- API documentation

## 🎮 Technique 4: Agent Control (AGENTS.md)

**What**: Workspace-level configuration that controls agent behavior, tool access, and workflows.

**Key Features:**

### Auto-Activation Rules

Agents activate automatically based on keywords:

- "security" → Security Reviewer
- "test" → Test Engineer
- "migration" → Database Architect
- "API" → API Developer

### Tool Usage Guidelines

- Always explain terminal commands
- Use `--compact` for test output
- Run Pint after changes
- Verify changes pass tests

### Forbidden Patterns

❌ Never return models directly from API
❌ Never skip super-admin bypass in policies
❌ Never modify columns without all attributes

### Required Workflows

✅ Run tests after changes
✅ Format code with Pint
✅ Check authorization
✅ Preserve multi-tenancy

## 📚 Quick Start Examples

### Create a New Feature

```
You: "Create a new Equipment model for tracking gym equipment with name,
     type, purchase_date, and maintenance_schedule fields"

Copilot will:
1. Ask if you want to use /crud-resource prompt
2. Generate all files (model, migration, controller, etc.)
3. Activate test-engineer to create tests
4. Activate security-reviewer to verify authorization
5. Run Pint and tests
6. Show summary
```

### Review Code Security

```
You: "Review ProductPolicy for security issues"

Copilot will:
1. Activate security-reviewer agent
2. Check for super-admin bypass
3. Verify shop scoping
4. Check authorization logic
5. Report findings with severity levels
```

### Optimize Database Performance

```
You: "This sale query is loading too many records"

Copilot will:
1. Activate database-architect agent
2. Analyze the query
3. Identify N+1 problems
4. Suggest eager loading
5. Recommend indexes
6. Provide migration code
```

### Create API Endpoint

```
You: /api-endpoint

Copilot prompts for:
- Resource: Member
- Version: v1 (default)
- Auth: yes (default)

Creates:
✅ MemberResource
✅ MemberController with REST methods
✅ API routes in routes/api.php
✅ API tests with auth scenarios
✅ Documentation examples
```

## 🔄 Multi-Agent Workflows

### Code Review Process

```
You: "Review this new feature"

1. Default Agent: Reads code
2. Security Reviewer: Checks security
3. Test Engineer: Verifies tests
4. Database Architect: Reviews queries (if DB changes)
5. Default Agent: Summarizes findings
```

### New Feature Implementation

```
You: "Add membership expiration tracking"

1. Default Agent: Understands requirements
2. Database Architect: Designs schema
3. Default Agent: Implements feature
4. Test Engineer: Creates tests
5. Security Reviewer: Audits security
6. Default Agent: Runs tests and formats
```

## 💡 Pro Tips

### 1. Let Skills Activate Automatically

You don't need to mention skills - they activate based on keywords:

- "test" → activates pest-testing skill
- "bootstrap" → activates bootstrap-development skill

### 2. Use Slash Commands for Speed

Type `/` and browse available prompts instead of writing long instructions.

### 3. Chain Prompts

```
You: /crud-resource    # Creates resource
Then: /generate-tests  # Adds comprehensive tests
Then: /security-audit  # Reviews security
```

### 4. Request Specific Agents

```
You: "@security-reviewer check this policy"
You: "@test-engineer create tests for this"
You: "@database-architect optimize these queries"
```

### 5. Use File Instructions

Open a file to get context-specific guidance automatically.

## 🛡️ Security-First Development

Every agent and instruction enforces:

1. **Super-admin bypass** in all policies
2. **Shop scoping** for multi-tenancy
3. **Authorization checks** before actions
4. **Input validation** with Form Requests
5. **Test coverage** for security scenarios

## 📊 Measuring Success

Your implementation is successful when:

- ✅ Copilot automatically loads relevant instructions
- ✅ Slash commands work (type `/` to test)
- ✅ Agents activate based on keywords
- ✅ Code follows Laravel Boost guidelines
- ✅ Security checks are automatic
- ✅ Tests are generated automatically

## 🚀 Next Steps

1. **Try a prompt**: Type `/code-review` and review this file
2. **Create a feature**: Use `/crud-resource` to scaffold something
3. **Test an agent**: Ask for a security review
4. **Explore skills**: Type `/` to see all available commands

## 📖 Learn More

- [Agent Customization Skill](./skills/agent-customization/SKILL.md)
- [Laravel Boost Guidelines](./copilot-instructions.md)
- [Project Documentation](../docs/)

## 🤝 Contributing

When adding new prompts, instructions, or agents:

1. Follow existing naming conventions
2. Include clear descriptions in frontmatter
3. Provide code examples
4. Test activation triggers
5. Update this README

---

**Questions?** Ask Copilot: "Explain how [feature] works in this project"
