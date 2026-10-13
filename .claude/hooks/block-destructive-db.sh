#!/usr/bin/env bash
#
# PreToolUse(Bash) guard: refuse database commands that destroy data.
#
# Development works as though the system is live: every database except the
# test database is treated as holding live data (CLAUDE.md, "Live-data rule"),
# so the hard rules in .claude/skills/laravel-database/SKILL.md are enforced
# mechanically rather than left to prose.
#
# A command that explicitly targets a test database (a name ending in
# "_testing") and names no live database is allowed through.
#
# Reads the hook payload on stdin; emits a PreToolUse deny decision on a match.
# Exits 0 either way — a non-match must never interrupt the turn.

set -uo pipefail

cmd="$(jq -r '.tool_input.command // ""' 2>/dev/null)" || exit 0
[ -z "$cmd" ] && exit 0

# Collapse whitespace and lowercase so "migrate:fresh", "migrate:fresh" and
# "MIGRATE:FRESH" all match the same patterns.
norm="$(printf '%s' "$cmd" | tr -s '[:space:]' ' ' | tr '[:upper:]' '[:lower:]')"

deny() {
  jq -n --arg reason "$1" '{
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "deny",
      permissionDecisionReason: $reason
    }
  }'
  exit 0
}

# The test database may be reset freely; only when the command names it and
# no live database (the legacy and development databases) alongside it.
if printf '%s' "$norm" | grep -Eq '[a-z0-9_]+_testing\b' \
  && ! printf '%s' "$norm" | grep -Eq 'fitness_center|pos_saas_dev|ecohub_pos'; then
  exit 0
fi

case "$norm" in
*migrate:fresh* | *"migrate --fresh"* | *migrate:refresh* | *migrate:reset* | *migrate:rollback* | *db:wipe*)
  deny "BLOCKED by .claude/hooks/block-destructive-db.sh — this drops or rolls back tables, and every database except the test database is treated as live (CLAUDE.md, Live-data rule). Write an additive migration instead, and see .claude/skills/laravel-database/SKILL.md. If the user has explicitly authorised a reset, they must run it themselves."
  ;;
esac

case "$norm" in
*"migrate --force"*)
  deny "BLOCKED — 'migrate --force' applies migrations unprompted. Take a snapshot (php artisan db:snapshot), run 'php artisan migrate:status' and confirm with the user before applying anything."
  ;;
esac

# db:seed is permitted only for one explicitly named seeder class.
case "$norm" in
*db:seed*)
  case "$norm" in
  *--class=*) : ;;
  *)
    deny "BLOCKED — bare 'db:seed' runs the full DatabaseSeeder against a live database. Re-run with an explicit class (e.g. 'php artisan db:seed --class=PermissionSeeder') and only when the user asked for it."
    ;;
  esac
  ;;
esac

# Raw SQL equivalents, for when a client is invoked directly.
case "$norm" in
*"drop database"* | *"drop table"* | *"drop schema"*)
  deny "BLOCKED — raw destructive SQL against a live database. If this is genuinely required, the user must run it themselves after taking a snapshot."
  ;;
esac

# TRUNCATE, with or without the TABLE keyword.
if printf '%s' "$norm" | grep -Eq '(^|[^a-z_])truncate( table)? [`"a-z_]'; then
  deny "BLOCKED — TRUNCATE empties a table in a live database. If this is genuinely required, the user must run it themselves after taking a snapshot."
fi

# DELETE FROM with no WHERE clause empties the table just like TRUNCATE.
if printf '%s' "$norm" | grep -Eq '(^|[^a-z_])delete from [`"a-z_.]+' \
  && ! printf '%s' "$norm" | grep -Eq '(^|[^a-z_])delete from [^;]* where '; then
  deny "BLOCKED — DELETE FROM without a WHERE clause empties a table in a live database. Add a WHERE clause, or ask the user to run it after taking a snapshot."
fi

exit 0
