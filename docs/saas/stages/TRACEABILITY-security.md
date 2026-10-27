# Traceability: Sign-in Security and Audit

Findings from the review of 27 October 2026 (sixth round), checked against `main` after Phase 7 (`832f684`).

| # | Source | Finding | Where it lives | Status |
|---|---|---|---|---|
| 1 | Module 18, "Authentication events logged" | Web sign-in, sign-out, failures not audited | Stage 16, step 1 | planned |
| 2 | `LoginRequest` | `Lockout` fired, nobody listens | Stage 16, steps 1 and 2 | planned |
| 3 | `POST /api/login`, `/api/v1` sign-in | Throttled, not recorded | Stage 16, step 1 | planned |
| 4 | Lockouts | Not visible to owners; only the till PIN can be unlocked by a manager | "Sign-in security" page and `users.unlock`: stage 16, step 2 | planned |
| 5 | Stage 5 follow-up | No console screen for the platform's own audit rows | Stage 16, step 3 | planned |
| 6 | `config/ratelimit.php` | Lockout thresholds in config only | Platform settings: stage 16, step 4 | planned |
| 7 | Module 18, `AuditSession`, `AuditExport` | Not built | Audit export exists (`audit-logs.export`) | no work: built differently |
